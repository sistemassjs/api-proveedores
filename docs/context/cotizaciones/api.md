# Cotizaciones — API

Prefijo API: `/api`.

## Público (sin auth)

### Catálogo abierto — modo tienda

Throttle `60/min`.

| Método | Path | Rol |
|--------|------|-----|
| `GET` | `/public/catalogo/empresas` | Cards paginadas (`search`, `per_page`) |
| `GET` | `/public/catalogo/empresas/{proveedor}/productos` | Productos publicados (`search`, facets ids, `per_page`) |
| `GET` | `/public/catalogo/empresas/{proveedor}/productos/{producto}` | Detalle |
| `GET` | `/public/catalogo/empresas/{proveedor}/productos/facets` | Facets OPUS |

Fuente: `is_proveedor_catalogo` + `mostrar_en_catalogo_publico` + `activo`.  
Controller: `Catalogo\CatalogoPublicoController`.

### Cotizador

Throttle `30/min` (alta `10/min`).

| Método | Path | Rol |
|--------|------|-----|
| `GET` | `/public/cotizador/empresas` | Listado empresas para elegir destino (`search`) |
| `GET` | `/public/cotizador/empresas/{proveedor}/productos/sugerencias?q=` | Autocomplete (≥2 chars, `limit`≤30) |
| `POST` | `/public/cotizador/empresas/{proveedor}/solicitudes` | Crear solicitud |

Body create:

```json
{
  "cliente_nombre": "…",
  "cliente_email": "…",
  "cliente_telefono": "opcional",
  "cliente_whatsapp": "opcional",
  "cliente_notas": "opcional",
  "detalles": [
    { "producto_id": 1, "cantidad": 2, "precio_unitario": null, "observaciones_linea": null }
  ]
}
```

Productos deben ser públicos del mismo `proveedor`. Snapshot de nombre/unidad/precio/imagen al crear.  
Controller: `Cotizacion\CotizadorPublicoController`.  
Service: `Cotizacion\SolicitudCotizacionService`.

## NexProv (Sanctum + roles proveedor + `proveedor.access`)

Prefijo: `/proveedores/{proveedor}/solicitudes-cotizacion`

| Método | Path | Rol |
|--------|------|-----|
| `GET` | `/` | Inbox paginado + `estatus_counts` |
| `GET` | `/productos?q=` | Productos del proveedor (públicos y privados) para agregar líneas |
| `GET` | `/{solicitudCotizacion}` | Detalle (marca `en_revision` si estaba `recibida`) |
| `PATCH` | `/{solicitudCotizacion}` | Cabecera + sync de `detalles` |
| `GET` | `/{solicitudCotizacion}/pdf` | Preview PDF |
| `POST` | `/{solicitudCotizacion}/responder` | `canales`: `email`\|`whatsapp`\|`ambos` + `mensaje` |
| `GET` | `/{solicitudCotizacion}/respuestas/{respuesta}/pdf` | PDF guardado de un envío |

Filtros inbox: `estatus`, `search`, `fecha_desde`, `fecha_hasta`, `sort_by`, `order`, `per_page`.

### Responder

1. Genera PDF (private disk).
2. Email si aplica (fallo no aborta).
3. WhatsApp: siempre intenta `wa.me`; API solo si `services.whatsapp.enabled`.
4. Persiste `solicitud_cotizacion_respuestas` y pasa a `respondida` si algún canal útil.

Notification al crear: `SolicitudCotizacionRecibidaNotification` con `forClientApps('nexprov')`.

## Legacy (no es este dominio)

- `/proveedores/{proveedor}/cotizaciones` → entidad `cotizaciones` (Construcc).
- `/construcc/cotizaciones` → CRUD Construcc.
- `…/solicitudes-pago/…/descargar-cotizacion` → archivo SP.
