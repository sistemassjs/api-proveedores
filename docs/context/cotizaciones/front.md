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
- Alta interna: `/pages/proveedor/cotizaciones/solicitudes/nueva`
- Galería: `/pages/proveedor/cotizaciones/galeria`

API: `/api/proveedores/{proveedor}/solicitudes-cotizacion…`

#### Inbox

Chips de estatus (`estatus_counts` en meta), filtro por `origen`.

#### Detalle / elaboración

- Editar líneas, agregar producto, marcar sugerencia.
- Campos **vigencia_hasta** y **políticas** (textarea multilínea).
- `solicitante_empresa` si origen `empresa_tercero`.
- Preview PDF.
- Responder email/WhatsApp/ambos (`whatsapp_link` → abrir/copiar).
- Acción **Marcar procesada** (cuando el cliente usó la cotización).

#### Galería

Listado de PDFs (`GET …/galeria`) con filtro `tipo=envio,procesada` y descarga `…/galeria/{id}/pdf`.

## Notificaciones

Tipo broadcast: `solicitud-cotizacion-recibida`.  
Solo tokens/app `nexprov`. Deep-link al detalle.  
No se dispara en alta interna.

## No usar

- Módulo legacy de cotizaciones Construcc en menú NexProv para este flujo.
- Endpoints auth `/catalogo/empresas` para la experiencia pública (usar `/public/…`).
