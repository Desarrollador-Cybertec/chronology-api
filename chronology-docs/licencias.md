# Integración de licencias

El sistema valida acciones contra una API externa de suscripciones antes de ejecutarlas. Esto controla los límites del plan contratado.

---

## Configuración

```env
SUBSCRIPTION_API_URL=https://...
SUBSCRIPTION_API_KEY=...
```

En `config/services.php`:
```php
'subscription' => [
    'api_url' => env('SUBSCRIPTION_API_URL'),
    'api_key' => env('SUBSCRIPTION_API_KEY'),
]
```

---

## LicenseService

**Archivo:** `app/Services/LicenseService.php`

### `authorize(string $action, int $quantity, bool $consume, ?string $referenceId): array`

Llama a `POST {api_url}/api/internal/authorize` antes de ejecutar una acción.

| Acción | Dónde se llama | `consume` |
|--------|---------------|-----------|
| `run_import` | `ImportController::store()` | `true` |
| `run_execution` | `ImportController::reprocess()` | `true` |
| `run_report` | `ReportController::store()` | `true` |

**Retorna:**
```json
{
  "allowed": true,
  "remaining": 45,
  "limit": 50,
  "current": 5,
  "status": "active"
}
```

**Lanza excepciones:**
- `LicenseException` — plan no permite la acción (límite alcanzado, plan inactivo, etc.)
- `LicenseSystemUnavailableException` — la API externa no responde (connection error o 5xx)

### `reportUsage(string $metric, int $value, string $referenceId): void`

Reporta uso consumado. Se llama en dos momentos:
1. Al completar un `ImportBatch` (en `ProcessAttendanceDayJob` cuando el último job termina).
2. Al completar un `Report` (en `GenerateReportJob`).

No lanza excepciones — solo loguea errores para no interrumpir el flujo.

---

## Manejo de errores

| Situación | Comportamiento |
|-----------|---------------|
| Plan activo, dentro del límite | Continúa normalmente |
| Límite alcanzado (`LicenseException`) | Retorna 422 con mensaje del plan |
| API caída (`LicenseSystemUnavailableException`) | Retorna 503 con mensaje genérico |

Los mensajes de error se mapean a texto en español en el handler de excepciones.

---

## `referenceId`

Identificador único para cada autorización, útil para idempotencia y trazabilidad en el sistema de suscripciones:
- Imports: `'import_' . Str::uuid()`
- Reprocess: `'reprocess_' . $importBatch->id`
- Reports: `'report_' . Str::uuid()`
- Usage report (import): `'import_batch_' . $batch->id`
- Usage report (report): `'report_' . $report->id`
