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

Al crear: origen `publico_cotizador`, `vigencia_hasta` = hoy+15, snapshot de líneas, notificación NexProv.  
Controller: `Cotizacion\CotizadorPublicoController`.  
Service: `Cotizacion\SolicitudCotizacionService`.

## NexProv (Sanctum + roles proveedor + `proveedor.access`)

Prefijo: `/proveedores/{proveedor}/solicitudes-cotizacion`

| Método | Path | Rol |
|--------|------|-----|
| `GET` | `/` | Inbox paginado + `estatus_counts` |
| `POST` | `/` | Alta interna (`nexprov_interna` \| `empresa_tercero`) |
| `GET` | `/productos?q=` | Productos para agregar líneas |
| `GET` | `/galeria` | Galería de PDFs (`tipo`, `search`, `per_page`) |
| `GET` | `/galeria/{archivo}/pdf` | Descargar PDF de galería |
| `GET` | `/{solicitudCotizacion}` | Detalle (`recibida`→`en_revision`) |
| `PATCH` | `/{solicitudCotizacion}` | Cabecera (vigencia, políticas, …) + sync `detalles` |
| `GET` | `/{solicitudCotizacion}/pdf` | Preview PDF |
| `POST` | `/{solicitudCotizacion}/responder` | Enviar email/whatsapp/ambos |
| `POST` | `/{solicitudCotizacion}/procesar` | Marcar procesada + archivar PDF |
| `GET` | `/{solicitudCotizacion}/respuestas/{respuesta}/pdf` | PDF de un envío |

Filtros inbox: `estatus`, `origen`, `search`, `fecha_desde`, `fecha_hasta`, `sort_by`, `order`, `per_page`.

### Alta interna (`POST /`)

```json
{
  "origen": "nexprov_interna",
  "cliente_nombre": "…",
  "cliente_email": "…",
  "solicitante_empresa": "opcional si empresa_tercero",
  "vigencia_hasta": "2026-10-22",
  "politicas": "Línea 1\nLínea 2",
  "detalles": [
    { "producto_id": 1, "cantidad": 2, "precio_unitario": 100 }
  ]
}
```

Estatus inicial: `en_revision`. Sin notificación (la empresa la crea).

### PATCH — campos de elaboración

Además de cliente/detalles: `vigencia_hasta`, `politicas`, `solicitante_empresa`, `observaciones_internas`, `estatus`.

### Responder

1. Genera PDF (private disk).
2. Email si aplica (fallo no aborta).
3. WhatsApp: `wa.me`; API solo si `services.whatsapp.enabled`.
4. Persiste respuesta + **archivo galería** tipo `envio`.
5. Pasa a `respondida` si algún canal útil.

### Procesar

Body opcional: `{ "nota": "…", "generar_pdf": true }`.

1. Genera PDF (salvo `generar_pdf=false` y ya hay archivo).
2. Archiva en galería tipo `procesada`.
3. Estatus → `procesada`, set `procesada_at`.

Notification al crear público: `SolicitudCotizacionRecibidaNotification` con `forClientApps('nexprov')`.

## Legacy (no es este dominio)

- `/proveedores/{proveedor}/cotizaciones` → entidad `cotizaciones` (Construcc).
- `/construcc/cotizaciones` → CRUD Construcc.
- `…/solicitudes-pago/…/descargar-cotizacion` → archivo SP.
