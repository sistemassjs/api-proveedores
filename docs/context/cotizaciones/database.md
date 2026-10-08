# Cotizaciones — Base de datos

## Tablas

### `solicitud_cotizaciones`

| Columna | Notas |
|---------|--------|
| `proveedor_id` | FK cascade |
| `folio` | Único por proveedor (`SC000001`…) |
| `origen` | `publico_cotizador` \| `nexprov_interna` \| `empresa_tercero` |
| `estatus` | `recibida`\|`en_revision`\|`respondida`\|`procesada`\|`cerrada`\|`rechazada` |
| `cliente_*` | nombre, email, telefono, whatsapp, notas |
| `solicitante_empresa` | Nombre de empresa tercera (opcional) |
| `observaciones_internas` | solo empresa |
| `vigencia_hasta` | date nullable |
| `politicas` | text (líneas separadas por `\n`) |
| `total` | suma subtotales |
| `respondida_at` / `cerrada_at` / `procesada_at` | |

Enum PHP: `App\Enums\EstadoSolicitudCotizacion`.

### `solicitud_cotizacion_detalles`

Snapshot editable: `producto_id` nullable, `nombre`, `descripcion`, `unidad`, `codigo`, `imagen`, `precio_referencia`, `precio_unitario`, `cantidad`, `subtotal`, `es_sugerencia_empresa`, `motivo_sugerencia`, `observaciones_linea`, `orden`.

### `solicitud_cotizacion_respuestas`

Historial de envíos: `canales`, `mensaje`, `pdf_path`, estados email/whatsapp, `whatsapp_link`, `payload_resumen`, `enviado_por_user_id`.

### `solicitud_cotizacion_archivos` (galería)

| Columna | Notas |
|---------|--------|
| `proveedor_id` | FK (consulta galería) |
| `solicitud_cotizacion_id` | FK |
| `respuesta_id` | nullable (si viene de un envío) |
| `tipo` | `envio` \| `procesada` \| `manual` |
| `nombre` | filename sugerido |
| `pdf_path` | disk `private` |
| `creado_por_user_id` | nullable |

Al migrar: backfill desde respuestas existentes con `pdf_path`.

## Migraciones

- `*_create_solicitud_cotizaciones_table`
- `*_create_solicitud_cotizacion_detalles_table`
- `*_create_solicitud_cotizacion_respuestas_table`
- `*_add_vigencia_politicas_procesada_to_solicitud_cotizaciones_table`
- `*_create_solicitud_cotizacion_archivos_table`

## Modelos

- `SolicitudCotizacion`
- `SolicitudCotizacionDetalle`
- `SolicitudCotizacionRespuesta`
- `SolicitudCotizacionArchivo`

## Fuera de este esquema

Tablas `cotizaciones` / `cotizacion_detalles` = legacy Construcc. No usar para el cotizador NexProv.
