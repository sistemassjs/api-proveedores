# Presupuestos — Base de datos

## Models / tablas

| Model | Tabla | Rol |
|-------|-------|-----|
| `Presupuesto` | `presupuestos` | Documento principal |
| `PresupuestoConcepto` | `presupuesto_conceptos` | Líneas (concepto/párrafo); snapshot sin FK a productos |
| `PresupuestoAnexo` | `presupuesto_anexos` | Anexos imagen |
| `PresupuestoAnexoPdf` | `presupuesto_anexo_pdf` | Anexos PDF (merge al final del documento) |
| `PresupuestoPlantilla` | `presupuesto_plantillas` | Receta reutilizable (aislada del documento) |
| `PresupuestoPlantillaConcepto` | `presupuesto_plantilla_conceptos` | Líneas de la plantilla |
| `PresupuestoPlantillaAnexo` | `presupuesto_plantilla_anexos` | Anexos imagen de plantilla |
| `PresupuestoPlantillaAnexoPdf` | `presupuesto_plantilla_anexo_pdf` | Anexos PDF de plantilla |
| `CarteraCliente` | `cartera_clientes` | Clientes del emisor (dominio presupuestos) |
| `PresupuestoCatalogoConcepto` | `presupuesto_catalogo_conceptos` | Biblioteca reutilizable de conceptos (Plus) |
| `PresupuestoEstadoLog` | `presupuesto_estado_logs` | Histórico de cambios de estado (timeline) |
| `ConfigEmisorReceptorPresupuesto` | `config_emisor_receptor_presupuestos` | Tarjetas emisor/receptor |

## Relaciones clave (`Presupuesto`)

- Emisor: `proveedor_id` → `Proveedor`
- Receptor: `empresa_receptora_id` → `CarteraCliente` **o** `proveedor_receptor_id` → `Proveedor` **o** solo campos texto
- Snapshot emisor: `config_emisor_presupuesto_id` + campos `empresa_emisora_*`
- Hijos: `conceptos`, `anexos`, `anexosPdf`, `estadoLogs`
- Público: `token_publico`
- Layout PDF: `ppto_config` (JSON plano key→mm; whitelist en `PresupuestoPdfDocumentConfig::PPTO_CONFIG_KEYS`)

## Estados

`borrador` | `enviado` | `aceptado` | `rechazado` | `rechazado_con_observacion` | `vencido`

- **Sin log** al crear `borrador`. Primer log al **enviar**.
- `rechazado_con_observacion` (o `rechazado` con `motivo_rechazo`) = solicitud de corrección: emisor puede editar y **reenviar** → `enviado`.
- En `enviado` el emisor **no** edita hasta acción del cliente.
- Fechas de envío/aceptación/rechazo: **derivadas** del historial (`fechaDeEstado`), no columnas sueltas.
- Columna `nota` en logs: en rechazo guarda el motivo.

Roadmap (aún no en BD de presupuesto): estados o flags de **pago** / **finalización**.

## Folio (`numero_presupuesto`)

- Formato: `PRES-XXXX` (cero-padded, mínimo 4 dígitos).
- Fuente del siguiente folio: `proveedores.consecutivo_presupuesto_siguiente` (no la PK del presupuesto).
- Migración one-shot `2026_07_23_095250_bump_presupuesto_folios_by_200`: suma **200** al consecutivo numérico de cada `PRES-*` existente y realinea `consecutivo_presupuesto_siguiente` al `max(folio)+1` por proveedor. Actualiza en **dos fases** (temporal `__TMP_BUMP_{id}` → folio final) para no chocar con el unique `(proveedor_id, numero_presupuesto)`. Folios que no matchean `PRES-\d+` se omiten.

## Moneda

Campo típico `term_cond_moneda`: valores admitidos **MXN** | **USD** | **EUR** (default MXN).

## Conceptos

Tipos: `concepto` | `parrafo`. Campos libres: descripción, cantidad, unidad, precios, imagen. **Sin `producto_id`.**

Catálogo de conceptos reutilizable: tabla `presupuesto_catalogo_conceptos` (`descripcion`, `categoria` producto|servicio, `unidad`, `precio_unitario` decimal(15,4) para Opus, `imagen_path` opcional, `activo` boolean default true — baja/reactivar sin hard delete). Al usarlo en un presupuesto se hace **snapshot** a la línea (sin FK). UI de precios: `environment.presupuestoPrecioDecimals` (storage 4 / display 2 por defecto). Sugerencias PPTO solo conceptos `activo=true`.

`cartera_clientes`: mismo patrón `activo` (baja/reactivar). Tarjetas (`config_emisor_receptor_presupuestos`): `estado` inactivo = baja; listado de gestión con `incluir_inactivos=1`.

Traslados / viáticos: **no** hay columnas `obs_traslados` / `obs_viaticos` (drop fase 3). Fuente de verdad: `term_cond_visibilidad.incluye_traslados` / `incluye_viaticos`. La API puede exponer `obs_traslados` / `obs_viaticos` en Resources como **alias derivados** de esa visibilidad (compat front).

## Plantillas (`presupuesto_plantillas`)

Recurso **aislado** del documento `presupuestos` (no `es_plantilla`). Guarda lo reutilizable al crear un PPTO: **nombre** (identificador), **conceptos**, **anexos imagen/PDF**, **tema** (`pdf_theme` / `ppto_config`) y **tarjeta de presentación** (emisor). **No** incluye descripción general del presupuesto (`concepto_general`), receptor, folio, estado, token ni logs. Al **aplicar** se crea un PPTO borrador por snapshot (`PresupuestoPlantillaAplicarService`) con `concepto_general` = `Borrador` (el usuario lo completa en el documento). **Desde presupuesto** crea plantilla sin copiar `concepto_general`. Editar la plantilla no modifica PPTOs ya creados.

## Campos de documento (captura / PDF)

| Campo | Tabla | Notas |
|-------|-------|-------|
| `fecha_emision` | `presupuestos` | Fecha del documento; editable en UI (front: no futura) |
| `concepto_general` | `presupuestos` | Descripción general del documento (texto) |
| `nombre_presupuesto` | `presupuestos` | `varchar(120)` nullable; título corto del documento. Mig. `2026_08_29_101858_…`. No se rellena al aplicar plantilla (el nombre de plantilla es solo identificador de la receta) |
| `titulo_anexos` | `presupuestos` | `varchar(80)` nullable; mig. `2026_07_23_095249_…`. Vacío → **Anexos** (Resource, Blade sección imágenes, preview) |
| `titulo_anexos_pdf` | `presupuestos` | `varchar(80)` nullable; mig. `2026_07_23_103654_…`. Vacío → **Anexos PDF** (Resource + estampado FPDI de hojas mergeadas) |
| `config_mostrar_totales` | `presupuestos` | Si false, oculta subtotal/IVA/total/importe letra en preview y PDF |
| `ppto_config` | `presupuestos` | JSON nullable; 8 keys mm whitelist (`margen_*`, `gap_*`, `footer_height_mm`, `espacio_tras_titulo_atentamente_mm`). Modal Ajustes + merge en `PresupuestoPdfDocumentConfig` (gap logo default **7**) |

## Histórico de estados (`presupuesto_estado_logs`)

| Columna | Notas |
|---------|-------|
| `presupuesto_id`, `user_id` | FK; user nullable (cron/sistema) |
| `fecha` | Momento del cambio |
| `estado_anterior`, `estado` | Strings de estado |
| `nota` | Motivo de rechazo / nota del cambio |

API `show` carga `estadoLogs.user`. Resource expone `estado_logs` + `fecha_envio` / `fecha_aceptacion` / `fecha_rechazo` derivados. UI: sheet historial en **listado (cards)** y al **final del preview**.

## Anexos

| Tipo | Tabla | Persistencia de título de sección | Notas |
|------|-------|-----------------------------------|-------|
| Imagen | `presupuesto_anexos` | `presupuestos.titulo_anexos` | Front limita alta a **4** (`PRESUPUESTO_ANEXOS_IMAGEN_MAX`); API sin tope. PDF: 4 por página en Blade |
| PDF | `presupuesto_anexo_pdf` | `presupuestos.titulo_anexos_pdf` | Se concatenan tras el DomPDF (`PresupuestoPdfAnexoMerger`). Título de sección en estampado; cada fila puede tener `titulo` propio (subtítulo si distinto) |

## Cobro (relacionado, no SP)

- Cuentas bancarias del proveedor: dominio de soporte en perfil (tabla `cuentas_bancarias` / model `CuentaBancaria`).
- Pasarelas PayPal/Stripe: **sin persistencia de integración** todavía.
