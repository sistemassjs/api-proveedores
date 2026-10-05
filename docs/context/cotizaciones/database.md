# Cotizaciones — Base de datos

## Tablas

### `solicitud_cotizaciones`

| Columna | Notas |
|---------|--------|
| `proveedor_id` | FK cascade |
| `folio` | Único por proveedor (`SC000001`…) |
| `origen` | default `publico_cotizador` |
| `estatus` | `recibida`\|`en_revision`\|`respondida`\|`cerrada`\|`rechazada` |
| `cliente_*` | nombre, email, telefono, whatsapp, notas |
| `observaciones_internas` | solo empresa |
| `total` | suma subtotales |
| `respondida_at` / `cerrada_at` | |

Enum PHP: `App\Enums\EstadoSolicitudCotizacion`.

### `solicitud_cotizacion_detalles`

Snapshot editable: `producto_id` nullable, `nombre`, `descripcion`, `unidad`, `codigo`, `imagen`, `precio_referencia`, `precio_unitario`, `cantidad`, `subtotal`, `es_sugerencia_empresa`, `motivo_sugerencia`, `observaciones_linea`, `orden`.

### `solicitud_cotizacion_respuestas`

Historial de envíos: `canales`, `mensaje`, `pdf_path`, estados email/whatsapp, `whatsapp_link`, `payload_resumen`, `enviado_por_user_id`.

## Migraciones

- `*_create_solicitud_cotizaciones_table`
- `*_create_solicitud_cotizacion_detalles_table`
- `*_create_solicitud_cotizacion_respuestas_table`

## Modelos

- `SolicitudCotizacion`
- `SolicitudCotizacionDetalle`
- `SolicitudCotizacionRespuesta`

## Fuera de este esquema

Tablas `cotizaciones` / `cotizacion_detalles` = legacy Construcc. No usar para el cotizador NexProv.
