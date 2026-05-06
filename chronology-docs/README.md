# Chronology API — Documentación

Sistema de control de asistencia basado en registros biométricos (marcaciones). Procesa archivos CSV exportados desde lectores biométricos, calcula métricas de asistencia por empleado y día, y genera reportes en PDF.

## Índice

| Documento | Descripción |
|-----------|-------------|
| [arquitectura.md](arquitectura.md) | Visión general, capas y flujos del sistema |
| [api-endpoints.md](api-endpoints.md) | Referencia completa de todos los endpoints REST |
| [modelos.md](modelos.md) | Modelos Eloquent, campos y relaciones |
| [calculo-asistencia.md](calculo-asistencia.md) | Pipeline de cálculo de asistencia (Domain layer) |
| [importacion.md](importacion.md) | Flujo de importación de archivos CSV |
| [reportes.md](reportes.md) | Tipos de reportes, generación y envío por email |
| [turnos-y-horarios.md](turnos-y-horarios.md) | Turnos, asignaciones semanales y excepciones |
| [licencias.md](licencias.md) | Integración con el API de suscripciones externo |
| [configuracion.md](configuracion.md) | System settings y variables de entorno |

## Stack tecnológico

- **PHP 8.2+** / **Laravel 12** / **Laravel Sanctum v4**
- **Redis** (Predis) para colas de jobs
- **DomPDF** para generación de PDFs
- **PHPUnit 11** para tests

## Roles de usuario

El sistema tiene dos roles gestionados con `RoleMiddleware`:

- **`superadmin`** — acceso completo: escritura, edición manual, eliminación, configuración del sistema.
- **`manager`** — acceso de lectura/consulta y creación de reportes e importaciones.
