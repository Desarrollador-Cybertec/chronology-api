# 1️⃣ Principios de arquitectura de Chronology

Chronology tiene un **dominio claro**:

```
Importación de marcaciones
↓
Normalización
↓
Procesamiento de asistencia
↓
Generación de reportes
```

Por eso la arquitectura debe separar:

| Capa | Responsabilidad |
| --- | --- |
| Controllers | Entrada HTTP |
| Actions / Services | Casos de uso |
| Domain | Reglas laborales |
| Infrastructure | CSV, DB, jobs |
| Models | Persistencia |

El **Attendance Engine** no debe depender de Laravel.

Debe ser una librería interna del dominio.

---

# 2️⃣ Estructura de carpetas recomendada

Dentro de `app/`

```
app
│
├── Domain                         ← lógica pura del dominio (no Laravel)
│   ├── Attendance
│   │   ├── AttendanceEngine.php
│   │   ├── AttendanceCalculator.php
│   │   ├── AttendanceDayBuilder.php
│   │   ├── LogReducer.php
│   │   ├── OvertimeCalculator.php
│   │   ├── LateCalculator.php
│   │   └── ShiftResolver.php
│   │
│   └── Import
│       ├── CsvParser.php
│       ├── ImportValidator.php
│       └── RawLogNormalizer.php
│
├── Services                       ← orquestan casos de uso
│   ├── ImportService.php
│   ├── AttendanceProcessingService.php
│   └── EmployeeResolverService.php
│
├── Actions                        ← acciones específicas ejecutadas por controllers
│   ├── Import
│   │   └── ImportCsvAction.php
│   │
│   └── Attendance
│       └── ReprocessBatchAction.php
│
├── Jobs                           ← procesos en cola
│   ├── ProcessImportBatchJob.php
│   └── ProcessAttendanceDayJob.php
│
├── Models
│   ├── Employee.php
│   ├── Shift.php
│   ├── RawLog.php
│   ├── ImportBatch.php
│   ├── AttendanceDay.php
│   └── AttendanceEdit.php
│
├── Http
│   ├── Controllers
│   │   ├── ImportController.php
│   │   ├── AttendanceController.php
│   │   ├── ShiftController.php
│   │   └── EmployeeController.php
│   │
│   ├── Requests                   ← validaciones
│   │   ├── Import
│   │   │   └── ImportCsvRequest.php
│   │   │
│   │   ├── Employee
│   │   │   ├── StoreEmployeeRequest.php
│   │   │   └── UpdateEmployeeRequest.php
│   │   │
│   │   └── Shift
│   │       ├── StoreShiftRequest.php
│   │       └── UpdateShiftRequest.php
│   │
│   └── Resources                  ← transformers API
│       ├── EmployeeResource.php
│       ├── AttendanceDayResource.php
│       └── ShiftResource.php
│
├── Helpers
│   ├── ServiceResponse.php
│   └── QueryHelper.php
│
└── Providers
    └── AppServiceProvider.php
```

Esto evita:

❌ lógica en controllers

❌ lógica en models

Y centraliza todo en **Domain + Services**.

---

# 3️⃣ Pipeline de importación CSV

Cuando se sube un CSV, ocurre esto:

```
Upload CSV
↓
ImportCsvAction
↓
ImportService
↓
CsvParser
↓
ImportValidator
↓
RawLogNormalizer
↓
Guardar raw_logs
↓
Dispatch ProcessImportBatchJob
```

---

## ImportCsvAction

Responsable de:

- recibir archivo
- crear import_batch
- delegar al ImportService

---

## ImportService

Responsable de:

```
parseCsv()
validateCsv()
normalizeRows()
storeRawLogs()
dispatchProcessingJob()
```

---

# 4️⃣ Diseño del Attendance Engine

Este es **el corazón de Chronology**.

Debe ser completamente independiente de Laravel.

---

## AttendanceEngine

```
class AttendanceEngine
{
    public function process(Employee $employee, Carbon $date): AttendanceResult
}
```

Responsabilidad:

Coordinar el cálculo completo.

---

## Pipeline interno del engine

```
raw_logs
↓
LogReducer
↓
ShiftResolver
↓
AttendanceDayBuilder
↓
LateCalculator
↓
OvertimeCalculator
↓
AttendanceResult
```

---

# 5️⃣ Componentes del Attendance Engine

---

# LogReducer

Elimina ruido de marcaciones.

Regla:

> si múltiples marcaciones < 60 minutos → tomar primera y última.
> 

Entrada:

```
Collection<RawLog>
```

Salida:

```
Collection<RawLog>
```

---

# ShiftResolver

Determina el turno activo del empleado.

Entrada:

```
employee_id
date
```

Salida:

```
Shift
```

---

# AttendanceDayBuilder

Construye:

```
first_check
last_check
worked_minutes
```

---

# LateCalculator

Calcula:

```
late_minutes
early_departure_minutes
```

---

# OvertimeCalculator

Calcula:

```
overtime_minutes
overtime_diurnal_minutes
overtime_nocturnal_minutes
```

Regla:

solo bloques ≥ 60 minutos.

---

# AttendanceResult

Objeto final:

```
class AttendanceResult
{
    public $firstCheck;
    public $lastCheck;
    public $workedMinutes;
    public $lateMinutes;
    public $overtimeMinutes;
    public $status;
}
```

---

# 6️⃣ Jobs de procesamiento

Para evitar bloquear el sistema.

---

## ProcessImportBatchJob

Procesa un CSV completo.

```
buscar empleados
↓
agrupar logs por empleado/día
↓
crear jobs ProcessAttendanceDayJob
```

---

## ProcessAttendanceDayJob

Responsable de:

```
$engine->process($employee, $date);
```

Y guardar en:

```
attendance_days
```

---

# 7️⃣ Flujo completo del sistema

```
Usuario sube CSV
↓
ImportCsvAction
↓
ImportService
↓
Guardar import_batch
↓
Guardar raw_logs
↓
ProcessImportBatchJob
↓
Agrupar logs por empleado/día
↓
ProcessAttendanceDayJob
↓
AttendanceEngine
↓
Guardar attendance_days
↓
Disponible para reportes
```