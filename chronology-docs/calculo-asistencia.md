# Cálculo de asistencia

El núcleo del sistema. Todo el cálculo vive en `app/Domain/Attendance/` y es independiente del framework. El punto de entrada es `AttendanceEngine::process()`, llamado desde `ProcessAttendanceDayJob`.

---

## Pipeline completo

```
AttendanceEngine::process(rawLogs, employeeId, date, settings)
  │
  ├─ 1. ScheduleResolver::resolve(employeeId, date)
  │       → ScheduleResult { isWorkingDay, shift, source }
  │       Si isWorkingDay = false → return AttendanceResult::rest()
  │
  ├─ 2. LogReducer::reduce(rawLogs, noiseWindowMinutes)
  │       → Collection<RawLog>  (logs filtrados)
  │
  ├─ 3. ShiftResolver::resolve(employeeId, date)
  │       → Shift|null  (si ScheduleResult no trajo turno)
  │
  └─ 4. AttendanceCalculator::calculate(reducedLogs, shift, date, settings)
          │
          ├─ 4a. LunchAnalyzer::analyze()     → lunchMinutes
          ├─ 4b. WorkTimeCalculator::calculate() → AttendanceResult parcial
          ├─ 4c. LateCalculator::calculate()   → lateMinutes, earlyDepartureMinutes
          └─ 4d. OvertimeCalculator::calculate() → overtimeMinutes (diurnal/nocturnal)
```

---

## 1. ScheduleResolver

**Archivo:** `app/Domain/Attendance/ScheduleResolver.php`

Determina si una fecha es día laboral y qué turno aplica para el empleado.

**Prioridad (de mayor a menor):**

1. `EmployeeScheduleException` para ese día exacto → retorna lo que indique la excepción (`is_working_day`, `shift_id`).
2. `EmployeeShiftAssignment` activo → revisa si el `dayOfWeek` está en `work_days`. Si no está → día de descanso.
3. Sin asignaciones → asume día laboral sin turno definido (`source: 'none'`).

**Retorna** `ScheduleResult`:
```php
new ScheduleResult(
    isWorkingDay: bool,
    shift: ?Shift,    // con breaks cargados
    source: 'exception' | 'assignment' | 'none'
)
```

---

## 2. LogReducer

**Archivo:** `app/Domain/Attendance/LogReducer.php`

Elimina ruido de marcaciones repetidas. Los lectores biométricos a veces registran varias marcaciones en pocos minutos para la misma acción.

**Algoritmo:**
- Ordena por `check_time`.
- Agrupa marcaciones consecutivas cuya diferencia desde el **primer** punch del grupo sea menor a `noise_window_minutes` (default: 60 min).
- De cada grupo retiene solo el **primero** y el **último**.

**Ejemplo:** 4 marcaciones en 5 minutos → 2 marcaciones (primera y última del grupo).

---

## 3. WorkTimeCalculator

**Archivo:** `app/Domain/Attendance/WorkTimeCalculator.php`

Calcula `worked_minutes`, `first_check_in`, `last_check_out` y `status`.

| Caso | Status | Cálculo |
|------|--------|---------|
| Sin logs | `absent` | — |
| 1 solo log | `incomplete` | Se registra el check, `worked_minutes = 0` |
| 2+ logs | `present` | `(effectiveStart → lastCheck) - lunchMinutes` |

**Llegada temprana:** si el empleado llega antes del inicio del turno, `effectiveStart = shift.start_time` (no se contabilizan los minutos antes del turno).

---

## 4. LateCalculator

**Archivo:** `app/Domain/Attendance/LateCalculator.php`

Solo aplica si `status = 'present'` y hay turno asignado.

- **Tardanza:** `first_check_in > shift_start + tolerance_minutes`  
  → `late_minutes = diff(shift_start, first_check_in)`

- **Salida temprana:** `last_check_out < shift_end`  
  → `early_departure_minutes = diff(last_check_out, shift_end)`

Los turnos `crosses_midnight = true` suman un día a `shift_end`.

---

## 5. OvertimeCalculator

**Archivo:** `app/Domain/Attendance/OvertimeCalculator.php`

Solo aplica si `shift.overtime_enabled = true` y `status = 'present'`.

**Cálculo:**
1. `extraMinutes = last_check_out - shift_end`
2. `completedBlocks = floor(extraMinutes / overtime_min_block_minutes)`  
   → solo se cuentan bloques completos (ej: si el bloque es 60 min y trabajó 75 extra, solo cuenta 60).
3. `overtimeMinutes = completedBlocks × overtime_min_block_minutes`
4. Si `max_daily_overtime_minutes > 0`: `overtimeMinutes = min(overtimeMinutes, max)`

**Split diurnal/nocturnal:**  
Recorre minuto a minuto el período de horas extras y clasifica cada tramo según si está dentro de `diurnal_start_time` (default 06:00) y `nocturnal_start_time` (default 20:00).

---

## 6. LunchAnalyzer

**Archivo:** `app/Domain/Attendance/LunchAnalyzer.php`

Intenta detectar si el empleado salió y regresó para el almuerzo.

- Si hay 4+ marcaciones: busca punches dentro de la ventana `shift_break ± lunch_margin_minutes`.
  - Si los detecta → usa la **duración real** (salida real a regreso real).
  - Si no → usa `ShiftBreak.duration_minutes` configurado.
- Si hay menos de 4 marcaciones → usa la duración configurada directamente.

El resultado (`lunchMinutes`) se descuenta de `worked_minutes` en `WorkTimeCalculator`.

---

## AttendanceResult DTO

```php
class AttendanceResult {
    public ?Carbon $firstCheck,
    public ?Carbon $lastCheck,
    public int $workedMinutes = 0,
    public int $lateMinutes = 0,
    public int $earlyDepartureMinutes = 0,
    public int $overtimeMinutes = 0,
    public int $overtimeDiurnalMinutes = 0,
    public int $overtimeNocturnalMinutes = 0,
    public string $status,    // present|absent|incomplete|rest
    public ?Shift $shift,
}
```

`AttendanceResult::rest()` → factory para días de descanso (status = 'rest', todo en 0).

---

## Parámetros configurables

Todos se leen desde `SystemSetting` en `ProcessAttendanceDayJob`:

| Setting | Default | Descripción |
|---------|---------|-------------|
| `noise_window_minutes` | 60 | Ventana de deduplicación de punches |
| `diurnal_start_time` | `06:00` | Inicio del período diurno |
| `nocturnal_start_time` | `20:00` | Inicio del período nocturno |
| `lunch_margin_minutes` | 15 | Margen para detectar punches de almuerzo |
