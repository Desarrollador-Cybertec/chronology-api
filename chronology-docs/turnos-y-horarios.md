# Turnos y horarios

---

## Turno (Shift)

Un turno define el horario base de trabajo. Los empleados se asignan a turnos semanalmente.

**Campos clave:**
- `start_time` / `end_time` — hora de inicio y fin (`HH:MM:SS`).
- `crosses_midnight` — si el turno termina al día siguiente (ej: 22:00–06:00).
- `tolerance_minutes` — minutos de gracia antes de marcar tardanza.
- `overtime_enabled` + `overtime_min_block_minutes` + `max_daily_overtime_minutes` — configuración de horas extras.

Un turno puede tener múltiples **breaks** (`ShiftBreak`), ordenados por `position`. Cada break tiene `start_time`, `end_time` y `duration_minutes` configurada.

---

## Asignación semanal (EmployeeShiftAssignment)

Relaciona un empleado con un turno para un rango de fechas y días específicos.

```json
{
  "employee_id": 5,
  "shift_id": 2,
  "effective_date": "2024-01-01",
  "end_date": "2024-01-31",
  "work_days": [1, 2, 3, 4, 5]
}
```

- `work_days`: días laborales del empleado (0=Dom, 1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb).
- `end_date = null` significa vigencia indefinida.
- Un empleado puede tener **múltiples asignaciones activas** si cubren días distintos.

---

## Excepción de horario (EmployeeScheduleException)

Override de una fecha específica para un empleado. Tiene la mayor prioridad en el `ScheduleResolver`.

**Casos de uso:**
- Marcar un feriado: `is_working_day: false, shift_id: null`
- Asignar un turno especial ese día: `is_working_day: true, shift_id: 3`
- Registrar trabajo en día no laboral: `is_working_day: true`

Se pueden crear en lote con `POST /api/schedule-exceptions/batch`.

---

## Auto-asignación de turnos (AssignWeeklyShiftsJob)

Cuando se importa un CSV, el sistema intenta **detectar automáticamente** el turno de cada empleado analizando los patrones de marcación. Este comportamiento es configurable.

### Algoritmo

1. Por cada empleado y semana ISO del batch, obtiene el `first_check_in` de cada día.
2. Para cada turno activo, compara cada `first_check_in` contra el `shift.start_time ± auto_assign_tolerance_minutes`.
3. Calcula el porcentaje de días que coinciden con ese turno.
4. Si el mejor turno supera `auto_assign_regularity_percent` → crea un `EmployeeShiftAssignment` para esa semana.
5. Si ya existe una asignación que cubra esa semana → no la sobreescribe.

### Configuración

| Setting | Default | Descripción |
|---------|---------|-------------|
| `auto_assign_shift` | `true` | Activar/desactivar auto-asignación |
| `auto_assign_tolerance_minutes` | `30` | Margen de tiempo para comparar check_in con turno |
| `auto_assign_regularity_percent` | `70` | % mínimo de días que deben coincidir |

### Resultado

Se crea un `EmployeeShiftAssignment` por semana detectada:
- `effective_date` = lunes de la semana
- `end_date` = viernes de la semana
- `work_days` = días en que el empleado efectivamente marcó ese período

---

## Resolución de horario (ScheduleResolver)

Usado en cada `ProcessAttendanceDayJob` para determinar si el empleado trabajó y qué turno aplica.

**Prioridad:**

```
1. EmployeeScheduleException (para la fecha exacta)
         ↓ si no existe
2. EmployeeShiftAssignment activo
   → si el dayOfWeek NO está en work_days → día de descanso
   → si está en work_days → día laboral con ese turno
         ↓ si no hay asignaciones
3. Día laboral sin turno definido
   (se calculan worked_minutes pero sin métricas de tardanza/extras)
```

**`ScheduleResult`:**
```php
{
    isWorkingDay: bool,
    shift: Shift|null,
    source: 'exception' | 'assignment' | 'none'
}
```
