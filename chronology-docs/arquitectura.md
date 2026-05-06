# Arquitectura del sistema

## Capas

```
┌──────────────────────────────────────────────────────────┐
│  HTTP Layer  (app/Http/)                                 │
│  Controllers · FormRequests · Resources · Middleware     │
├──────────────────────────────────────────────────────────┤
│  Application Layer  (app/Actions/, app/Jobs/)            │
│  Actions (use-cases) · Jobs (async processing)           │
├──────────────────────────────────────────────────────────┤
│  Domain Layer  (app/Domain/)                             │
│  Attendance/ · Import/  — lógica de negocio pura         │
├──────────────────────────────────────────────────────────┤
│  Infrastructure  (app/Services/, app/Models/)            │
│  LicenseService · ImportService · Eloquent models        │
└──────────────────────────────────────────────────────────┘
```

### HTTP Layer (`app/Http/`)

- **Controllers** — delegan en Actions, Jobs o Domain; no contienen lógica de negocio.
- **FormRequests** — toda validación de entrada vive aquí, nunca en el controlador.
- **Resources** — transforman modelos Eloquent en JSON de respuesta.
- **Middleware** — `RoleMiddleware` para autorización por rol.

### Application Layer

- **Actions** (`app/Actions/`) — casos de uso de una sola responsabilidad que orquestan múltiples servicios o jobs. Ejemplo: `ImportCsvAction` coordina hash-check, almacenamiento, creación de batch e `ImportService`.
- **Jobs** (`app/Jobs/`) — trabajo asíncrono en cola Redis. Ver [flujo de jobs](#flujo-de-jobs).

### Domain Layer (`app/Domain/`)

Clases PHP puras sin dependencias de framework. Ver [calculo-asistencia.md](calculo-asistencia.md) para el detalle.

- `Attendance/` — motor completo de cálculo de asistencia.
- `Import/` — parser, validador y normalizador de CSV.

### Infrastructure

- **`LicenseService`** — llama a una API externa para autorizar acciones y reportar uso.
- **`ImportService`** — orquesta el procesamiento del CSV hasta el almacenamiento de `RawLog`.
- **`SystemSetting`** — configuración runtime en base de datos (`key → value`).

---

## Flujo de jobs

### Importación CSV

```
POST /api/import
  └─ ImportCsvAction.execute()
       ├─ ImportService.process()  → crea RawLogs
       └─ ProcessImportBatchJob (queue)
            ├─ AssignWeeklyShiftsJob.dispatchSync()   ← asignación automática de turno
            └─ ProcessAttendanceDayJob × N (queue)    ← uno por cada empleado+fecha
                 └─ AttendanceEngine.process()
                      └─ AttendanceDay.updateOrCreate()
                           └─ LicenseService.reportUsage() [cuando batch completa]
```

### Generación de reportes

```
POST /api/reports
  └─ GenerateReportJob (queue)
       ├─ [tipo individual] → SendReportEmailJob (queue, 10 reintentos)
       └─ [tipo general]    → DispatchBatchReportEmailsJob (queue)
                               └─ SendReportEmailJob × N (rate-limited: 15/hora)
```

---

## Convenciones importantes

- **No usar `env()` fuera de config** — siempre `config('grupo.clave')`.
- **Eloquent en lugar de `DB::`** — usar `Model::query()`.
- **Eager loading** para evitar N+1 (`with('relation')`).
- **Casts en método `casts()`** — no en propiedad `$casts`.
- **Constructor property promotion** en todas las clases.
- **Return types explícitos** en todos los métodos.
- Correr `vendor/bin/pint --dirty` antes de finalizar cualquier cambio.
