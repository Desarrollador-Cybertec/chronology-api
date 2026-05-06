# Reportes

Los reportes se generan de forma asíncrona. El endpoint `POST /api/reports` crea el registro y retorna `202 Accepted`; el procesamiento ocurre en background via `GenerateReportJob`.

---

## Tipos de reporte

### `individual`
Reporte detallado de un empleado. Requiere `employee_id`.

**Rows:** una fila por día con `date`, `first_check_in`, `last_check_out`, `worked_minutes`, `late_minutes`, `early_departure_minutes`, `overtime_minutes`, `overtime_diurnal_minutes`, `overtime_nocturnal_minutes`, `status`.

**Summary:**
```json
{
  "employee_name": "...",
  "employee_internal_id": "...",
  "total_days": 22,
  "days_present": 20,
  "days_absent": 1,
  "days_incomplete": 1,
  "times_late": 3,
  "total_late_minutes": 45,
  "total_worked_minutes": 9500,
  "total_overtime_minutes": 120,
  "total_overtime_diurnal_minutes": 120,
  "total_overtime_nocturnal_minutes": 0,
  "total_early_departure_minutes": 30
}
```

---

### `general`
Todos los empleados, todos los días del período.

**Rows:** igual que `individual` más `employee_code` y `employee_name`.

**Summary:** totales agregados de todos los empleados.

---

### `tardanzas`
Solo días con `status = 'present'` y `late_minutes > 0`.

**Rows:** `employee_code`, `employee_name`, `department`, `date`, `first_check_in`, `late_minutes`, `status`.

**Summary:** `total_employees_with_tardanzas`, `total_tardanzas`, `total_late_minutes`.

---

### `incompletas`
Solo días con `status = 'incomplete'`.

**Rows:** `employee_code`, `employee_name`, `department`, `date`, `first_check_in`, `last_check_out`, `worked_minutes`.

**Summary:** `total_employees_with_incompletas`, `total_incompletas`, `total_worked_minutes`.

---

### `informe_total`
Todas las novedades: tardanzas **o** salidas tempranas **o** incompletas.

**Filtro:** `late_minutes > 0 OR early_departure_minutes > 0 OR status = 'incomplete'`

**Rows:** `employee_code`, `employee_name`, `department`, `date`, `first_check_in`, `last_check_out`, `late_minutes`, `early_departure_minutes`, `worked_minutes`, `status`.

**Summary:** totales desglosados por tipo de novedad.

---

### `horas_laborales`
Resumen de horas trabajadas agrupado por empleado (no por día).

**Rows:** una fila por empleado con `employee_code`, `employee_name`, `department`, `days_worked`, `days_absent`, `days_incomplete`, `total_worked_minutes`.

**Summary:** `total_employees`, `total_worked_minutes`.

---

## Job pipeline de reportes

```
POST /api/reports
  ├─ LicenseService::authorize('run_report')
  ├─ Report::create(status: 'pending')
  └─ GenerateReportJob::dispatch(report)

GenerateReportJob
  ├─ report.status = 'processing'
  ├─ [ejecuta generateXxx() según type]
  ├─ report.update(status: 'completed', rows: [...], summary: {...})
  ├─ LicenseService::reportUsage('execution', 1, 'report_{id}')
  │
  ├─ [si type = 'individual' y employee.email existe]
  │    └─ SendReportEmailJob::dispatch(report)
  │
  └─ [si type = 'general']
       └─ DispatchBatchReportEmailsJob::dispatch(date_from, date_to, user_id)

SendReportEmailJob
  ├─ Reintentos: 10, backoff: 60s
  └─ Envía IndividualReportMail al email del empleado

DispatchBatchReportEmailsJob
  ├─ Rate limit: 15 emails/hora (240 segundos entre cada uno)
  ├─ Genera un reporte individual por cada empleado activo con email
  └─ Encola SendReportEmailJob con delay incremental
```

---

## Descarga de PDF

Solo para reportes de tipo `individual` con `status = 'completed'`.

`GET /api/reports/{id}/download`

- Genera el PDF en tiempo real usando DomPDF con la vista `resources/views/reports/individual.blade.php`.
- Nombre del archivo: `reporte_{nombre_empleado}_{fecha_desde}.pdf`.

---

## Envío manual de emails

| Endpoint | Descripción |
|----------|-------------|
| `POST /reports/{id}/send-email` | Envía el PDF individual al empleado (requiere `status = completed`) |
| `POST /reports/{id}/send-batch-emails` | Programa envío masivo desde un reporte `general` completado |

El batch email estima el tiempo de envío: `ceil(total_empleados / 15)` horas.

---

## Estados del reporte

| Estado | Significado |
|--------|-------------|
| `pending` | Creado, job en cola |
| `processing` | `GenerateReportJob` en ejecución |
| `completed` | `summary` y `rows` disponibles |
| `failed` | Error guardado en `error_message` |
