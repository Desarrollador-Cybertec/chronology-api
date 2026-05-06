# Modelos Eloquent

## Diagrama de relaciones

```
User
 ├── importBatches (hasMany ImportBatch)
 └── attendanceEdits (hasMany AttendanceEdit)

Employee
 ├── rawLogs (hasMany RawLog)
 ├── attendanceDays (hasMany AttendanceDay)
 ├── shiftAssignments (hasMany EmployeeShiftAssignment)
 └── scheduleExceptions (hasMany EmployeeScheduleException)

Shift
 ├── assignments (hasMany EmployeeShiftAssignment)
 └── breaks (hasMany ShiftBreak, ordenados por position)

AttendanceDay
 └── edits (hasMany AttendanceEdit)

ImportBatch
 └── rawLogs (hasMany RawLog)

Report
 ├── generatedBy (belongsTo User)
 └── employee (belongsTo Employee, nullable)
```

---

## User

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `name` | string | Nombre del usuario |
| `email` | string | Email único |
| `password` | string | Hash bcrypt |
| `role` | string | `superadmin` \| `manager` |

Métodos: `isSuperAdmin()`, `isManager()`

---

## Employee

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `internal_id` | string | ID del empleado en el sistema biométrico |
| `first_name` | string | Nombre |
| `last_name` | string | Apellido |
| `email` | string\|null | Para envío de reportes |
| `department` | string\|null | Departamento |
| `position` | string\|null | Cargo |
| `is_active` | boolean | Si aparece en importaciones |

Accessor: `full_name` → `"{first_name} {last_name}"`

---

## Shift

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `name` | string | Nombre del turno |
| `start_time` | string | Hora de inicio (`HH:MM:SS`) |
| `end_time` | string | Hora de fin (`HH:MM:SS`) |
| `crosses_midnight` | boolean | Si el turno cruza la medianoche |
| `tolerance_minutes` | int | Minutos de gracia para tardanza |
| `overtime_enabled` | boolean | Si calcula horas extras |
| `overtime_min_block_minutes` | int | Bloque mínimo de horas extras (ej: 60 min) |
| `max_daily_overtime_minutes` | int | Tope diario de horas extras (0 = sin tope) |
| `is_active` | boolean | Disponible para asignación |

Accessor: `total_break_minutes` → suma de `ShiftBreak.duration_minutes`

---

## ShiftBreak

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `shift_id` | int | FK → Shift |
| `type` | string | Tipo de descanso (ej: `lunch`) |
| `start_time` | string | Hora inicio del descanso |
| `end_time` | string | Hora fin del descanso |
| `duration_minutes` | int | Duración configurada |
| `position` | int | Orden para la relación `Shift::breaks()` |

---

## EmployeeShiftAssignment

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `employee_id` | int | FK → Employee |
| `shift_id` | int | FK → Shift |
| `effective_date` | date | Desde cuándo aplica |
| `end_date` | date\|null | Hasta cuándo aplica (null = indefinido) |
| `work_days` | array\<int\> | Días laborales: 0=Dom, 1=Lun, ..., 6=Sáb |

Un empleado puede tener múltiples asignaciones activas si los `work_days` no se solapan.

---

## EmployeeScheduleException

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `employee_id` | int | FK → Employee |
| `date` | date | Fecha de la excepción |
| `shift_id` | int\|null | Turno especial para ese día (null si no labora) |
| `is_working_day` | boolean | Si es día laborable |
| `reason` | string\|null | Motivo (ej: "Feriado") |

Tiene mayor prioridad que `EmployeeShiftAssignment` en el `ScheduleResolver`.

---

## RawLog

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `employee_id` | int | FK → Employee |
| `import_batch_id` | int | FK → ImportBatch |
| `check_time` | datetime | Timestamp exacto de la marcación |
| `date_reference` | date | Fecha del día laboral (puede diferir de `check_time` en turnos nocturnos) |
| `original_line` | string | Línea original del CSV para trazabilidad |

Índice compuesto en `(employee_id, check_time)`.

---

## ImportBatch

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `uploaded_by` | int | FK → User |
| `original_filename` | string | Nombre original del archivo |
| `stored_path` | string | Ruta en storage |
| `file_hash` | string | Hash para detectar duplicados |
| `status` | string | `pending` \| `processing` \| `completed` \| `failed` |
| `total_rows` | int | Total de grupos empleado+fecha |
| `processed_rows` | int | Grupos procesados |
| `failed_rows` | int | Filas fallidas en parsing |
| `errors` | array\|null | Mensajes de error del parsing |
| `processed_at` | datetime\|null | Cuándo completó |

---

## AttendanceDay

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `employee_id` | int | FK → Employee |
| `date_reference` | date | Fecha del día (unique con employee_id) |
| `first_check_in` | datetime\|null | Primera marcación efectiva |
| `last_check_out` | datetime\|null | Última marcación efectiva |
| `worked_minutes` | int | Minutos trabajados (descontando almuerzo) |
| `overtime_minutes` | int | Total horas extras en minutos |
| `overtime_diurnal_minutes` | int | Horas extras diurnas (06:00–20:00) |
| `overtime_nocturnal_minutes` | int | Horas extras nocturnas (20:00–06:00) |
| `late_minutes` | int | Minutos de tardanza |
| `early_departure_minutes` | int | Minutos de salida temprana |
| `status` | string | `present` \| `absent` \| `incomplete` \| `rest` |
| `is_manually_edited` | boolean | Fue modificado manualmente por un superadmin |

Constraint único: `(employee_id, date_reference)`.  
Los registros con `is_manually_edited = true` **no se sobreescriben** en un reprocesamiento.

---

## AttendanceEdit

Auditoría de cambios manuales en `AttendanceDay`.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `attendance_day_id` | int | FK → AttendanceDay |
| `edited_by` | int | FK → User (el superadmin que editó) |
| `field_changed` | string | Nombre del campo modificado |
| `old_value` | string | Valor anterior |
| `new_value` | string | Valor nuevo |
| `reason` | string | Motivo obligatorio de la edición |

---

## Report

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `generated_by` | int | FK → User |
| `employee_id` | int\|null | FK → Employee (solo tipo `individual`) |
| `type` | string | Ver tipos abajo |
| `date_from` | date | Inicio del período |
| `date_to` | date | Fin del período |
| `status` | string | `pending` \| `processing` \| `completed` \| `failed` |
| `summary` | array\|null | Totales del reporte |
| `rows` | array\|null | Filas de datos del reporte |
| `error_message` | string\|null | Error si falló |
| `completed_at` | datetime\|null | Cuándo completó |

Accessor: `name` → genera un nombre legible según el tipo y rango de fechas.

**Tipos:** `individual` · `general` · `tardanzas` · `incompletas` · `informe_total` · `horas_laborales`

---

## SystemSetting

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `key` | string | Clave única de configuración |
| `value` | string | Valor como texto |
| `group` | string | Agrupación lógica |

Método estático: `SystemSetting::getValue(string $key, string $default): string`
