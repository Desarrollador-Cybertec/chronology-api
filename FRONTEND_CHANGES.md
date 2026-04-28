# Cambios de API — Emails de empleados + Reporte individual PDF

**Fecha:** 28/04/2026  
**Rama:** main

---

## 1. Campo `email` en empleados

El modelo `Employee` ahora incluye el campo `email` en todas las respuestas donde antes aparecía.

**Ejemplo de respuesta actualizada (`GET /api/employees/{id}`):**
```json
{
  "data": {
    "id": 1,
    "internal_id": "001",
    "first_name": "Juan",
    "last_name": "Pérez",
    "email": "juan.perez@empresa.com",
    "department": "Ventas",
    "position": "Vendedor",
    "is_active": true
  }
}
```

> El campo puede ser `null` si aún no se ha asignado correo al empleado.

---

## 2. Importar correos desde CSV

Permite cargar masivamente los correos de los empleados a partir de un archivo CSV. El sistema hace matching por nombre completo.

**Endpoint:** `POST /api/employees/import-emails`  
**Auth:** requerida (solo superadmin)  
**Content-Type:** `multipart/form-data`

**Parámetros:**

| Campo | Tipo   | Requerido | Descripción |
|-------|--------|-----------|-------------|
| file  | File   | Sí        | Archivo CSV con columnas `usuario` y `correo electronico` |

**Formato del CSV esperado:**
```
usuario,correo electronico
Juan Pérez,juan.perez@empresa.com
María García,maria.garcia@empresa.com
```

**Respuesta exitosa `200`:**
```json
{
  "message": "Se asignaron correos a 42 empleados.",
  "matched": 42,
  "unmatched": 3,
  "unmatched_names": [
    "Pedro Ramirez",
    "Ana Lopez",
    "Carlos Mendez"
  ]
}
```

> `unmatched_names` lista los nombres del CSV que no pudieron ser asociados a ningún empleado. Se puede mostrar como advertencia al usuario.

---

## 3. Descargar reporte individual como PDF

Descarga el reporte individual ya generado en formato PDF.

**Endpoint:** `GET /api/reports/{id}/download`  
**Auth:** requerida (superadmin o manager)

**Condiciones:**
- El reporte debe ser de tipo `individual`
- El reporte debe tener status `completed`

**Respuesta exitosa:** archivo PDF descargable (`application/pdf`)

**Respuesta de error `422`:**
```json
{
  "message": "El reporte debe ser de tipo individual y estar completado."
}
```

**Ejemplo de uso (fetch):**
```js
const response = await fetch(`/api/reports/${reportId}/download`, {
  headers: { Authorization: `Bearer ${token}` },
});

const blob = await response.blob();
const url = URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = `reporte_${employeeName}.pdf`;
a.click();
```

---

## 4. Enviar reporte por correo al empleado

Envía el reporte individual al correo del empleado como PDF adjunto. El envío es asíncrono (cola), la respuesta es inmediata.

**Endpoint:** `POST /api/reports/{id}/send-email`  
**Auth:** requerida (superadmin o manager)  
**Body:** ninguno

**Condiciones:**
- El reporte debe ser de tipo `individual`
- El reporte debe tener status `completed`
- El empleado debe tener un `email` registrado

**Respuesta exitosa `200`:**
```json
{
  "message": "El reporte será enviado al correo del empleado en breve."
}
```

**Posibles errores `422`:**
```json
{ "message": "El reporte debe ser de tipo individual y estar completado." }
{ "message": "El empleado no tiene correo electrónico registrado." }
```

---

## Flujo sugerido en el frontend

```
1. Subir CSV de correos
   POST /employees/import-emails
   └── mostrar resumen: X asignados, Y sin match

2. Generar reporte individual (ya existía)
   POST /reports  { type: "individual", employee_id, date_from, date_to }
   └── polling hasta status === "completed"

3. Descargar PDF
   GET /reports/{id}/download  →  descarga directa

4. Enviar por correo  (botón independiente)
   POST /reports/{id}/send-email
   └── mostrar confirmación: "Enviado a empleado@empresa.com"
```

---

## Notas

- El botón "Enviar por correo" debe deshabilitarse si `employee.email` es `null`. Se puede verificar desde la respuesta de `GET /reports/{id}` que incluye el objeto `employee` completo.
- El PDF replica la misma información que ya muestra el frontend en el reporte individual.
- No hay cambios en los endpoints existentes de reportes (`index`, `store`, `show`, `destroy`).
