# Importación de archivos CSV

El sistema procesa archivos CSV exportados desde lectores biométricos. El proceso es completamente asíncrono luego del upload.

---

## Flujo completo

```
POST /api/import (multipart/form-data, campo "file")
  │
  ├─ [HTTP] LicenseService::authorize('run_import')
  │
  ├─ [Action] ImportCsvAction::execute(file, user)
  │     ├─ Calcula hash del archivo → verifica que no sea duplicado
  │     ├─ Guarda el archivo en storage
  │     └─ Crea ImportBatch (status: pending)
  │
  ├─ [Service] ImportService::process(batch, csvContent)
  │     ├─ CsvParser::parse()            → headers + rows
  │     ├─ ImportValidator::validate()   → verifica columnas requeridas y formatos
  │     │     Si falla → batch.status = 'failed', retorna errores
  │     ├─ RawLogNormalizer::normalizeAll() → normaliza cada fila
  │     ├─ Fase 1: Resolver employee_id para cada fila
  │     ├─ Fase 2: Eliminar RawLogs previos de los mismos pares (employee_id, date)
  │     ├─ Fase 3: Insertar nuevos RawLogs (deduplica dentro del batch por check_time)
  │     └─ Despacha ProcessImportBatchJob (cola)
  │
  └─ Responde 201 con ImportBatchResource (o 422 si falló validación)

[Job] ProcessImportBatchJob
  ├─ Obtiene pares únicos (employee_id, date_reference) del batch
  ├─ AssignWeeklyShiftsJob::dispatchSync()  ← asigna turnos antes de procesar
  └─ ProcessAttendanceDayJob × N (uno por par)

[Job] ProcessAttendanceDayJob
  ├─ Carga RawLogs del par (employee_id, date), ordena por check_time
  ├─ Lee SystemSettings (noise_window, diurnal/nocturnal, lunch_margin)
  ├─ AttendanceEngine::process() → AttendanceResult
  ├─ AttendanceDay::updateOrCreate()
  └─ Incrementa batch.processed_rows
       Si processed_rows >= total_rows:
         batch.status = 'completed'
         LicenseService::reportUsage('execution', 1, 'import_batch_{id}')
```

---

## Formato del CSV

### Columnas requeridas

| Columna | Descripción |
|---------|-------------|
| `id de persona` | ID del empleado en el sistema biométrico (mapea con `Employee.internal_id`) |
| `hora` | Timestamp de la marcación |

### Formatos de fecha aceptados

- `Y-m-d H:i:s` → `2024-01-15 08:30:00`
- `Y-m-d H:i` → `2024-01-15 08:30`
- `d/m/Y H:i:s` → `15/01/2024 08:30:00`
- `d/m/Y H:i` → `15/01/2024 08:30`

### Columnas opcionales detectadas automáticamente

- Columna de dispositivo (`device`) — detectada por `RawLogNormalizer` si existe.
- Columna de departamento — usada opcionalmente para enriquecer datos.

### Encoding y separadores

`CsvParser` maneja:
- Múltiples tipos de salto de línea (`\r\n`, `\n`, `\r`).
- Headers normalizados a minúsculas.
- Agrega metadatos `_line_number` y `_original_line` a cada fila para trazabilidad de errores.

---

## Deduplicación

El sistema evita registros duplicados en dos niveles:

1. **Entre batches:** antes de insertar, elimina todos los `RawLog` existentes para los mismos pares `(employee_id, date_reference)`. Esto permite re-importar datos de un período y que los datos nuevos reemplacen a los anteriores.

2. **Dentro del batch:** si el mismo CSV tiene dos filas idénticas para el mismo empleado con el mismo `check_time`, solo se inserta una.

---

## Reprocesamiento

`POST /api/import/{id}/reprocess` (solo `superadmin`)

- Elimina los `AttendanceDay` generados por el batch, **excepto** los que tienen `is_manually_edited = true`.
- Resetea el batch a `status: processing`.
- Despacha de nuevo `ProcessImportBatchJob`.

Útil cuando se modifica la configuración (noise_window, turnos) y se quiere recalcular la asistencia.

---

## Estados del ImportBatch

| Estado | Significado |
|--------|-------------|
| `pending` | Archivo recibido, procesamiento CSV no iniciado |
| `processing` | Jobs de procesamiento en cola/ejecución |
| `completed` | Todos los `ProcessAttendanceDayJob` terminaron |
| `failed` | El CSV no pasó validación o hubo un error crítico |

`batch.errors` (array) contiene los mensajes de filas que fallaron durante el parsing. Estos errores no detienen el proceso; las filas válidas se procesan igual.
