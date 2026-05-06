# API Endpoints

Base URL: `/api`  
Autenticación: Bearer Token (Laravel Sanctum) — header `Authorization: Bearer {token}`

---

## Convenciones

- Campos marcados con `*` son **requeridos**.
- Campos sin `*` son opcionales (`nullable` o `sometimes`).
- Las respuestas de listas siguen el formato de paginación estándar de Laravel (`data`, `links`, `meta`).
- Todas las fechas en formato `YYYY-MM-DD`, los timestamps en `YYYY-MM-DD HH:MM:SS`.

---

## Autenticación

> Sin autenticación (excepto `/logout` y `/me`)

| Método | Ruta | Descripción | Rate limit |
|--------|------|-------------|------------|
| POST | `/register` | Registrar cuenta manager | 5/min |
| POST | `/login` | Obtener token Sanctum | 5/min |
| POST | `/logout` | Revocar todos los tokens del usuario | — |
| GET | `/me` | Info del usuario autenticado | — |

### POST `/register`

**Request:**
```json
{
  "name": "Juan Pérez",          // * string, max 255
  "email": "user@example.com",   // * email único
  "password": "SecurePass1",     // * mín 10 chars, letras mayúsculas/minúsculas y números
  "password_confirmation": "SecurePass1"  // * debe coincidir con password
}
```
**Response `201`:**
```json
{
  "user": { "id": 1, "name": "Juan Pérez", "email": "user@example.com", "role": "manager" },
  "message": "Usuario registrado exitosamente. Use /api/login para obtener un token."
}
```

---

### POST `/login`

**Request:**
```json
{
  "email": "user@example.com",  // *
  "password": "SecurePass1"     // *
}
```
**Response `200`:**
```json
{
  "user": { "id": 1, "name": "Juan Pérez", "email": "user@example.com", "role": "manager" },
  "token": "1|abc123..."
}
```
**Response `401`:** credenciales inválidas.  
**Response `409`:** el usuario ya tiene una sesión activa.

---

### POST `/logout`
No requiere body. Revoca todos los tokens del usuario autenticado.

**Response `200`:**
```json
{ "message": "Sesión cerrada correctamente." }
```

---

### GET `/me`
No requiere body.

**Response `200`:**
```json
{ "id": 1, "name": "Juan Pérez", "email": "user@example.com", "role": "manager" }
```

---

## Empleados

> Lectura: `manager` y `superadmin` · Escritura: solo `superadmin`

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/employees` | Listar empleados (paginado) |
| GET | `/employees/all-ids` | Lista ligera de todos los empleados |
| GET | `/employees/{id}` | Ver empleado con resumen de asistencia |
| PATCH | `/employees/{id}` | Actualizar empleado *(superadmin)* |
| PATCH | `/employees/{id}/toggle-active` | Activar/desactivar *(superadmin)* |
| POST | `/employees/import-emails` | Importar emails desde CSV *(superadmin)* |

---

### GET `/employees`

**Query params:** `search` (nombre, apellido, ID interno o departamento), `per_page` (máx 100), `sort_by` (`internal_id|first_name|last_name|department|is_active`), `order` (`asc|desc`)

**Response `200`** (paginado):
```json
{
  "data": [
    {
      "id": 1,
      "internal_id": "EMP001",
      "first_name": "Juan",
      "last_name": "Pérez",
      "full_name": "Juan Pérez",
      "email": "juan@empresa.com",
      "department": "Operaciones",
      "position": "Técnico",
      "is_active": true,
      "shift_assignments": [ /* EmployeeShiftAssignment[] */ ],
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 80 }
}
```

---

### GET `/employees/all-ids`

**Query params:** `search`, `active_only` (bool)

**Response `200`:**
```json
{
  "data": [
    { "id": 1, "internal_id": "EMP001", "full_name": "Juan Pérez" }
  ],
  "total": 80
}
```

---

### GET `/employees/{id}`

**Response `200`:**
```json
{
  "id": 1,
  "internal_id": "EMP001",
  "first_name": "Juan",
  "last_name": "Pérez",
  "full_name": "Juan Pérez",
  "email": "juan@empresa.com",
  "department": "Operaciones",
  "position": "Técnico",
  "is_active": true,
  "shift_assignments": [ /* EmployeeShiftAssignment[] */ ],
  "attendance_summary": {
    "total_days_worked": 22,
    "total_days_absent": 1,
    "total_days_incomplete": 0,
    "total_worked_minutes": 10560,
    "total_overtime_minutes": 120,
    "total_overtime_diurnal_minutes": 90,
    "total_overtime_nocturnal_minutes": 30,
    "total_late_minutes": 15,
    "total_early_departure_minutes": 0
  },
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-15T10:00:00.000000Z"
}
```

---

### PATCH `/employees/{id}` *(superadmin)*

**Request:**
```json
{
  "first_name": "Juan",       // string, max 255
  "last_name": "Pérez",       // string, max 255
  "department": "Logística",  // string|null, max 255
  "position": "Supervisor"    // string|null, max 255
}
```
**Response `200`:** objeto `Employee` (igual al de `GET /employees/{id}` sin `attendance_summary`).

---

### PATCH `/employees/{id}/toggle-active` *(superadmin)*

No requiere body.

**Response `200`:**
```json
{ "message": "Empleado activado correctamente.", "is_active": true }
```

---

### POST `/employees/import-emails` *(superadmin)*

**Request:** `multipart/form-data` con campo `file` (`.csv` o `.txt`, máx 2 MB).  
El CSV debe tener columnas de nombre y correo para hacer el match por nombre.

**Response `200`:**
```json
{
  "message": "Se asignaron correos a 45 empleados.",
  "matched": 45,
  "unmatched": 3,
  "unmatched_names": ["Pedro López", "Ana Torres", "Carlos Ruiz"]
}
```

---

## Turnos (Shifts)

> Lectura: ambos roles · Escritura: solo `superadmin`

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/shifts` | Listar turnos |
| GET | `/shifts/{id}` | Ver turno con breaks |
| POST | `/shifts` | Crear turno *(superadmin)* |
| PATCH | `/shifts/{id}` | Actualizar turno *(superadmin)* |
| DELETE | `/shifts/{id}` | Eliminar turno *(superadmin)* |

**Objeto Shift (respuesta):**
```json
{
  "id": 1,
  "name": "Turno Diurno",
  "start_time": "08:00",
  "end_time": "17:00",
  "crosses_midnight": false,
  "tolerance_minutes": 10,
  "overtime_enabled": true,
  "overtime_min_block_minutes": 30,
  "max_daily_overtime_minutes": 120,
  "is_active": true,
  "breaks": [
    {
      "id": 1,
      "type": "lunch",
      "start_time": "12:00",
      "end_time": "13:00",
      "duration_minutes": 60,
      "position": 0,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ],
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

---

### POST `/shifts` *(superadmin)*

**Request:**
```json
{
  "name": "Turno Diurno",             // * string, max 255
  "start_time": "08:00",              // * formato HH:MM
  "end_time": "17:00",                // * formato HH:MM
  "crosses_midnight": false,          // boolean (default false)
  "tolerance_minutes": 10,            // integer 0–60
  "overtime_enabled": true,           // boolean
  "overtime_min_block_minutes": 30,   // integer >= 0
  "max_daily_overtime_minutes": 120,  // integer >= 0
  "is_active": true,                  // boolean
  "breaks": [
    {
      "type": "lunch",            // * string, max 50
      "start_time": "12:00",      // * formato HH:MM
      "end_time": "13:00",        // * formato HH:MM
      "duration_minutes": 60,     // * integer 1–120
      "position": 0               // integer >= 0
    }
  ]
}
```
**Response `201`:** objeto `Shift` completo.

---

### PATCH `/shifts/{id}` *(superadmin)*

**Request:** mismos campos que `POST /shifts`, todos opcionales (`sometimes`). Incluir `breaks` reemplaza todos los breaks existentes.

**Response `200`:** objeto `Shift` completo.

---

### DELETE `/shifts/{id}` *(superadmin)*

**Response `200`:**
```json
{ "message": "Turno eliminado correctamente." }
```

---

## Asignaciones de turno (Employee Shifts)

| Método | Ruta | Descripción | Rol |
|--------|------|-------------|-----|
| GET | `/employees/{id}/shifts` | Asignaciones del empleado | ambos |
| GET | `/employee-shifts/{id}` | Ver asignación | ambos |
| POST | `/employee-shifts` | Crear asignación | superadmin |
| PUT | `/employee-shifts/{id}` | Actualizar asignación | superadmin |
| DELETE | `/employee-shifts/{id}` | Eliminar asignación | superadmin |
| DELETE | `/employee-shifts` | Eliminar asignaciones en lote | superadmin |

**Objeto EmployeeShiftAssignment (respuesta):**
```json
{
  "id": 1,
  "employee_id": 5,
  "shift_id": 2,
  "effective_date": "2024-01-01",
  "end_date": null,
  "work_days": [1, 2, 3, 4, 5],
  "shift": { /* objeto Shift */ },
  "employee": { /* objeto Employee */ },
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

---

### POST `/employee-shifts` *(superadmin)*

**Request:**
```json
{
  "employee_id": 5,               // * integer, existe en employees
  "shift_id": 2,                  // * integer, existe en shifts
  "effective_date": "2024-01-01", // * date
  "end_date": null,               // date|null, >= effective_date
  "work_days": [1, 2, 3, 4, 5]   // array de enteros 0–6 (0=Dom…6=Sáb)
}
```
**Response `201`:** objeto `EmployeeShiftAssignment` completo.

---

### PUT `/employee-shifts/{id}` *(superadmin)*

**Request:**
```json
{
  "shift_id": 3,                  // integer, existe en shifts
  "effective_date": "2024-03-01", // date
  "end_date": "2024-06-30",       // date|null, >= effective_date
  "work_days": [1, 2, 3, 4, 5]   // array de enteros 0–6
}
```
**Response `200`:** objeto `EmployeeShiftAssignment` completo.

---

### DELETE `/employee-shifts/{id}` *(superadmin)*

**Response `200`:**
```json
{ "message": "Asignación eliminada correctamente." }
```

---

### DELETE `/employee-shifts` *(superadmin)*

Elimina asignaciones en lote. Sin body elimina **todas**.

**Request (opcional):**
```json
{
  "employee_ids": [1, 2, 3]  // array de IDs de empleados
}
```
**Response `200`:**
```json
{ "message": "Se eliminaron 15 asignaciones de turno.", "deleted_count": 15 }
```

---

## Excepciones de horario

| Método | Ruta | Descripción | Rol |
|--------|------|-------------|-----|
| GET | `/employees/{id}/schedule-exceptions` | Excepciones del empleado | ambos |
| GET | `/schedule-exceptions/{id}` | Ver excepción | ambos |
| POST | `/schedule-exceptions` | Crear/actualizar excepción | ambos |
| POST | `/schedule-exceptions/batch` | Crear/actualizar excepciones en lote | ambos |
| DELETE | `/schedule-exceptions/{id}` | Eliminar excepción | ambos |

**Objeto EmployeeScheduleException (respuesta):**
```json
{
  "id": 1,
  "employee_id": 5,
  "date": "2024-12-25",
  "shift_id": null,
  "is_working_day": false,
  "reason": "Feriado nacional",
  "shift": null,
  "employee": { /* objeto Employee */ },
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

---

### POST `/schedule-exceptions`

**Request:**
```json
{
  "employee_id": 5,              // * integer, existe en employees
  "date": "2024-12-25",          // * date
  "shift_id": null,              // integer|null, existe en shifts
  "is_working_day": false,       // boolean (default true)
  "reason": "Feriado nacional"   // string|null, max 500
}
```
**Response `201`:** objeto `EmployeeScheduleException` completo.

---

### POST `/schedule-exceptions/batch`

**Request:**
```json
{
  "exceptions": [
    {
      "employee_id": 5,             // *
      "date": "2024-12-25",         // *
      "shift_id": null,
      "is_working_day": false,
      "reason": "Feriado nacional"
    },
    {
      "employee_id": 6,
      "date": "2024-12-25",
      "is_working_day": false
    }
  ]
}
```
**Response `201`:** array de objetos `EmployeeScheduleException`.

---

## Asistencia

> Lectura: ambos roles · Edición manual: solo `superadmin`

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/attendance` | Listar días de asistencia (paginado, con filtros) |
| GET | `/attendance/date-range` | Rango de fechas procesadas |
| GET | `/attendance/day/{date}` | Días de asistencia de una fecha (`YYYY-MM-DD`) |
| GET | `/attendance/employee/{id}` | Días de asistencia de un empleado |
| GET | `/attendance/{id}` | Ver día con historial de ediciones |
| PUT | `/attendance/{id}` | Edición manual con auditoría *(superadmin)* |

**Objeto AttendanceDay (respuesta):**
```json
{
  "id": 1,
  "employee_id": 5,
  "employee": { /* objeto Employee */ },
  "date_reference": "2024-01-15",
  "first_check_in": "2024-01-15 08:05:00",
  "last_check_out": "2024-01-15 17:30:00",
  "worked_minutes": 445,
  "overtime_minutes": 30,
  "overtime_diurnal_minutes": 30,
  "overtime_nocturnal_minutes": 0,
  "late_minutes": 5,
  "early_departure_minutes": 0,
  "status": "present",
  "is_manually_edited": false,
  "edits": [
    {
      "id": 1,
      "attendance_day_id": 1,
      "edited_by": 2,
      "editor": { "id": 2, "name": "Admin" },
      "field_changed": "overtime_minutes",
      "old_value": "0",
      "new_value": "30",
      "reason": "Corrección de marcación",
      "created_at": "2024-01-16T09:00:00.000000Z"
    }
  ],
  "created_at": "2024-01-15T18:00:00.000000Z",
  "updated_at": "2024-01-15T18:00:00.000000Z"
}
```
> `edits` solo se incluye en `GET /attendance/{id}` y `PUT /attendance/{id}`.

---

### GET `/attendance`

**Query params:** `employee_id`, `date` (YYYY-MM-DD), `date_from`, `date_to`, `status` (`present|absent|incomplete|rest|holiday`), `has_overtime` (bool), `has_late` (bool), `sort_by` (`date_reference|first_check_in|last_check_out|worked_minutes|late_minutes|overtime_minutes|early_departure_minutes|status`), `order` (`asc|desc`), `per_page` (máx 100)

**Response `200`:** lista paginada de objetos `AttendanceDay`.

---

### GET `/attendance/date-range`

**Response `200`:**
```json
{
  "data": {
    "min_date": "2024-01-01",
    "max_date": "2024-01-31"
  }
}
```

---

### GET `/attendance/day/{date}`

**Query params:** `status`, `sort_by`, `order`, `per_page`

**Response `200`:** lista paginada de objetos `AttendanceDay` para esa fecha.

---

### GET `/attendance/employee/{id}`

**Query params:** `date_from`, `date_to`, `status`, `sort_by`, `order`, `per_page`

**Response `200`:** lista paginada de objetos `AttendanceDay` del empleado.

---

### PUT `/attendance/{id}` *(superadmin)*

Cada campo modificado genera un registro en `attendance_edits`. `reason` es obligatorio.

**Request:**
```json
{
  "first_check_in": "2024-01-15 08:05:00",       // date|null
  "last_check_out": "2024-01-15 17:00:00",        // date|null
  "worked_minutes": 475,                           // integer >= 0
  "overtime_minutes": 0,                           // integer >= 0
  "overtime_diurnal_minutes": 0,                   // integer >= 0
  "overtime_nocturnal_minutes": 0,                 // integer >= 0
  "late_minutes": 5,                               // integer >= 0
  "early_departure_minutes": 0,                    // integer >= 0
  "status": "present",                             // present|absent|incomplete|rest|holiday
  "reason": "Corrección de marcación"              // * string, max 500
}
```
**Response `200`:**
```json
{
  "data": { /* objeto AttendanceDay con edits */ },
  "edits_count": 2
}
```

---

## Importaciones

| Método | Ruta | Descripción | Rol |
|--------|------|-------------|-----|
| POST | `/import` | Subir CSV biométrico | ambos |
| GET | `/import` | Listar batches (paginado) | ambos |
| GET | `/import/{id}` | Ver batch y su estado | ambos |
| POST | `/import/{id}/reprocess` | Reprocesar batch existente | superadmin |

**Objeto ImportBatch (respuesta):**
```json
{
  "id": 1,
  "uploaded_by": 2,
  "uploader": { "id": 2, "name": "Admin" },
  "original_filename": "marcaciones_enero.csv",
  "file_hash": "abc123...",
  "status": "completed",
  "total_rows": 500,
  "processed_rows": 498,
  "failed_rows": 2,
  "errors": ["Fila 12: formato inválido"],
  "processed_at": "2024-01-15T10:05:00.000000Z",
  "created_at": "2024-01-15T10:00:00.000000Z",
  "updated_at": "2024-01-15T10:05:00.000000Z"
}
```
**Estados:** `pending` → `processing` → `completed` / `failed`

---

### POST `/import`

**Request:** `multipart/form-data`, campo `file` (`.csv` o `.txt`, máx 10 MB).

**Response `201`:**
```json
{
  "data": { /* objeto ImportBatch */ }
}
```
**Response `422`** (errores de parse):
```json
{
  "data": { /* objeto ImportBatch con status failed */ },
  "errors": ["Error de formato en fila 5"]
}
```

---

### POST `/import/{id}/reprocess` *(superadmin)*

No requiere body. Vuelve a procesar el batch con los datos originales.

**Response `200`:**
```json
{ "message": "El batch está siendo reprocesado." }
```

---

## Reportes

| Método | Ruta | Descripción | Rol |
|--------|------|-------------|-----|
| GET | `/reports` | Listar reportes (paginado) | ambos |
| POST | `/reports` | Generar reporte (async) | ambos |
| GET | `/reports/{id}` | Ver reporte con datos | ambos |
| GET | `/reports/{id}/download` | Descargar PDF (solo `individual` completado) | ambos |
| POST | `/reports/{id}/send-email` | Enviar PDF por email al empleado | ambos |
| POST | `/reports/{id}/send-batch-emails` | Enviar reportes a todos los empleados | ambos |
| DELETE | `/reports/{id}` | Eliminar reporte | ambos |

**Objeto Report (respuesta):**
```json
{
  "id": 1,
  "name": "Reporte Individual - Juan Pérez",
  "generated_by": 2,
  "employee_code": "EMP001",
  "type": "individual",
  "date_from": "2024-01-01",
  "date_to": "2024-01-31",
  "status": "completed",
  "summary": { /* objeto con totales del reporte, presente solo cuando status=completed */ },
  "rows": [ /* array de filas del reporte, presente solo cuando status=completed */ ],
  "error_message": null,
  "completed_at": "2024-01-15T10:05:00.000000Z",
  "created_at": "2024-01-15T10:00:00.000000Z",
  "updated_at": "2024-01-15T10:05:00.000000Z",
  "employee": { /* objeto Employee, si aplica */ },
  "generated_by_user": { "id": 2, "name": "Admin" }
}
```
> `rows` se omite si se pasa `?include_rows=false` en el query.

**Estados:** `pending` → `processing` → `completed` / `failed`  
**Tipos:** `individual` · `general` · `tardanzas` · `incompletas` · `informe_total` · `horas_laborales`

---

### GET `/reports`

**Query params:** `type`, `status`, `per_page` (máx 100)

**Response `200`:** lista paginada de objetos `Report` (sin `rows`).

---

### POST `/reports`

**Request:**
```json
{
  "type": "individual",      // * individual|general|tardanzas|incompletas|informe_total|horas_laborales
  "date_from": "2024-01-01", // * date
  "date_to": "2024-01-31",   // * date, >= date_from
  "employee_id": 5           // * si type=individual; null/omitir para los demás tipos
}
```
**Response `202`:** objeto `Report` con `status: "pending"`.

---

### GET `/reports/{id}`

**Query params:** `include_rows=false` para excluir el array `rows` de la respuesta.

**Response `200`:** objeto `Report` completo.

---

### GET `/reports/{id}/download`

Solo disponible para reportes de tipo `individual` con `status: "completed"`.

**Response `200`:** archivo PDF (`application/pdf`).

---

### POST `/reports/{id}/send-email`

Solo para reportes `individual` completados. El empleado debe tener email registrado.

**Response `200`:**
```json
{ "message": "El reporte será enviado al correo del empleado en breve." }
```

---

### POST `/reports/{id}/send-batch-emails`

Solo para reportes `general` completados. Envío en lote limitado a 15 emails/hora.

**Response `200`:**
```json
{
  "message": "Se programó el envío para 60 empleados a razón de 15 por hora.",
  "total_employees": 60,
  "estimated_hours": 4
}
```

---

### DELETE `/reports/{id}`

**Response `200`:**
```json
{ "message": "Reporte eliminado correctamente." }
```

---

## Configuración del sistema

> Solo `superadmin`

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/settings` | Ver todas las configuraciones |
| PUT | `/settings` | Actualizar configuraciones |

### GET `/settings`

**Response `200`:**
```json
{
  "data": [
    { "id": 1, "key": "noise_window_minutes", "value": "5", "group": "attendance" }
  ]
}
```

---

### PUT `/settings`

**Request:**
```json
{
  "settings": [
    { "key": "noise_window_minutes", "value": "5" },
    { "key": "lunch_margin_minutes", "value": "10" }
  ]
}
```
**Response `200`:**
```json
{
  "message": "Configuración actualizada correctamente.",
  "updated_keys": ["noise_window_minutes", "lunch_margin_minutes"]
}
```

Ver listado completo de claves en [configuracion.md](configuracion.md).

---

## Respuestas comunes

**Paginación:** todas las listas usan paginación estándar de Laravel con `per_page` (máx 100).

**Errores de licencia `402`:**
```json
{ "message": "Has alcanzado el límite de tu plan.", "error_code": "LIMIT_EXCEEDED" }
```

**Errores de validación `422`:**
```json
{ "message": "...", "errors": { "campo": ["El campo es requerido."] } }
```

**No autenticado `401`:**
```json
{ "message": "Unauthenticated." }
```

**Sin permisos `403`:**
```json
{ "message": "Forbidden." }
```
