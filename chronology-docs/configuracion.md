# Configuración

---

## Variables de entorno (`.env`)

| Variable | Descripción |
|----------|-------------|
| `APP_KEY` | Clave de cifrado de Laravel |
| `APP_URL` | URL base de la aplicación |
| `DB_*` | Credenciales de base de datos |
| `REDIS_HOST` / `REDIS_PORT` | Conexión Redis para colas |
| `QUEUE_CONNECTION` | Driver de colas (usar `redis` en producción) |
| `SUBSCRIPTION_API_URL` | URL base de la API de licencias |
| `SUBSCRIPTION_API_KEY` | Clave de autenticación de la API de licencias |
| `MAIL_*` | Configuración SMTP para envío de emails |

---

## System Settings (base de datos)

Configuración runtime modificable vía `PUT /api/settings` (solo `superadmin`).  
Se leen con `SystemSetting::getValue('clave', 'default')`.

### Procesamiento de asistencia

| Clave | Default | Descripción |
|-------|---------|-------------|
| `noise_window_minutes` | `60` | Minutos de ventana para deduplicar marcaciones consecutivas del mismo evento |
| `diurnal_start_time` | `06:00` | Hora de inicio del período diurno (para split de horas extras) |
| `nocturnal_start_time` | `20:00` | Hora de inicio del período nocturno |
| `lunch_margin_minutes` | `15` | Minutos de margen para detectar marcaciones de almuerzo |

### Auto-asignación de turnos

| Clave | Default | Descripción |
|-------|---------|-------------|
| `auto_assign_shift` | `true` | Activar detección automática de turno al importar |
| `auto_assign_tolerance_minutes` | `30` | Margen en minutos para comparar el primer check-in con el inicio del turno |
| `auto_assign_regularity_percent` | `70` | Porcentaje mínimo de días que deben coincidir para asignar un turno |

---

## Bootstrapping (Laravel 12)

- **Middleware** — registrado en `bootstrap/app.php` via `Application::configure()->withMiddleware()`.
- **Service Providers** — en `bootstrap/providers.php`.
- **Console commands** — en `app/Console/Commands/`, se registran automáticamente.
- **Routes** — `routes/api.php` (prefijo `/api`), `routes/web.php`, `routes/console.php`.
