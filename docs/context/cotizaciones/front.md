# Cotizaciones — Front (orientación)

Código UI en `app-proveedores` (aún por construir / conectar). Este doc orienta contratos.

## Superficies

### Público (sin login)

1. **Tienda / vitrina** → `GET /api/public/catalogo/empresas…`
2. **Cotizador**  
   - Paso 1: elegir empresa → `GET /api/public/cotizador/empresas`  
   - Paso 2: input búsqueda → `GET …/productos/sugerencias?q=`  
   - Paso 3: form cliente + POST solicitud  

Acuse post-envío: mensaje de éxito con folio (sin tracking autenticado).

### NexProv (app autenticada)

Rutas UI sugeridas:

- Listado: `/pages/proveedor/cotizaciones/solicitudes`
- Detalle: `/pages/proveedor/cotizaciones/solicitudes/:id`

API: `/api/proveedores/{proveedor}/solicitudes-cotizacion…`

Inbox con chips de estatus (`estatus_counts` en meta).  
En detalle: editar líneas, agregar producto (públicos/privados), marcar sugerencia, preview PDF, responder email/WhatsApp/ambos.  
Si WhatsApp devuelve `whatsapp_link`, ofrecer abrir/copiar aunque la API haya fallado.

## Notificaciones

Tipo broadcast: `solicitud-cotizacion-recibida`.  
Solo tokens/app `nexprov`. Deep-link al detalle.

## No usar

- Módulo legacy de cotizaciones Construcc en menú NexProv para este flujo.
- Endpoints auth `/catalogo/empresas` para la experiencia pública (usar `/public/…`).
