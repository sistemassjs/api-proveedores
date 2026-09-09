# Presupuestos — API

Repo: `api-proveedores`. Prefijo gerente: `proveedores/{proveedor}/…` + `proveedor.access`.

## Rutas principales (`routes/segmented/gerente.php`)

| Grupo | Path | Controller |
|-------|------|------------|
| Presupuestos | `{proveedor}/presupuestos` | `ProveedorPresupuestoController` |
| | `GET/POST /`, `GET/PUT/PATCH/DELETE /{presupuesto}` | CRUD |
| | `GET /next-folio`, `GET /pdf-themes` | Folio / temas |
| | `POST /generar-pdf` | PDF sin persistir |
| | `GET /{presupuesto}/pdf`, `PATCH …/pdf-theme` | PDF guardado / tema |
| | `POST …/duplicar`, `…/enviar`, `…/enviar-correo`, `…/notificar-receptor-app`, `…/reenviar` | Ciclo de vida (`enviar` también desde rechazo con observación) |
| | `GET /proveedores-registrados` | Receptores = proveedores del sistema |
| Cartera | `{proveedor}/presupuestos/cartera-clientes` | `ProveedorPresupuestoCarteraClientesController` |
| Catálogo conceptos | `{proveedor}/presupuestos/presupuesto-catalogo-conceptos` | `ProveedorPresupuestoCatalogoConceptosController` |
| | `GET …/sugerencias` | Interno + catálogo público (snapshot al elegir) |
| Plantillas | `{proveedor}/presupuestos/plantillas` | `ProveedorPresupuestoPlantillaController` |
| | `GET/POST /`, `GET/PUT/PATCH/DELETE /{plantilla}` | CRUD aislado del documento |
| | `POST …/plantillas/desde-presupuesto/{presupuesto}` | Snapshot PPTO → plantilla (sin receptor) |
| | `POST …/plantillas/{plantilla}/aplicar` | Crea PPTO borrador (sin receptor); servicio propio, no `duplicar` |
| | `POST …/plantillas/{plantilla}/aplicar-sobre/{presupuesto}` | Mezcla plantilla en PPTO existente (conceptos/anexos/tema/tarjeta/términos); conserva receptor, fecha, `concepto_general`, `nombre_presupuesto` |
| | `…/plantillas/{plantilla}/anexos` (+ `/bulk`) | Anexos imagen de plantilla |
| | `…/plantillas/{plantilla}/anexos-pdf` | Anexos PDF de plantilla |
| Anexos | `{proveedor}/presupuestos/{presupuesto}/anexos` (+ `/bulk`) | `ProveedorPresupuestoAnexoController` |
| Anexos PDF | `…/anexos-pdf` | `ProveedorPresupuestoAnexoPdfController` |
| Config | `{proveedor}/config-emisor-receptor-presupuestos` | `ProveedorPresupuestoConfigController` |

`show` carga `estadoLogs.user`. Payload incluye `estado_logs`, fechas derivadas, `ppto_config`.

## Público (`routes/segmented/public.php`)

| Método | Path | Acción |
|--------|------|--------|
| GET | `public/presupuestos/{token}` | Ver |
| GET | `public/presupuestos/{token}/pdf` | PDF |
| POST | `public/presupuestos/{token}/aceptar` | Aceptar |
| POST | `public/presupuestos/{token}/rechazar` | Rechazar (`motivo` → `rechazado_con_observacion` + `nota` en log) |

Controller: `PresupuestoPublicController`. Resource: `PresupuestoPublicResource`.

### Payload `GET public/presupuestos/{token}` (paridad de documento)

Además de cabecera / conceptos / totales / emisor-receptor, incluye:

| Campo | Notas |
|-------|--------|
| `anexos` | `PresupuestoAnexoResource` (URLs públicas; sin forzar base64) |
| `anexos_pdf` | `PresupuestoAnexoPdfResource` (metadatos + `archivo_url`) |
| `pdf_theme` | Key de tema (default `corporativo`; incluye `caterpillar`, etc.) |
| `pdf_theme_css` | Mapa de CSS vars (`--color-primary`, …) para preview sin auth |
| `ppto_config` | JSON mm de layout |
| `titulo_anexos` / `titulo_anexos_pdf` | Defaults **Anexos** / **Anexos PDF** |
| `nombre_presupuesto` | `nullable|string|max:120`; título corto del documento (PDF / listados / notificaciones) |
| `term_cond_*`, `term_cond_visibilidad`, `validacion_alcances` | Misma forma que `PresupuestoResource` |
| `term_cond_enunciados`, `validaciones_enunciados`, `observaciones_enunciados` | Desde `getEnunciadosClasificados()` |
| `configuracion_condiciones` | Flags legacy; `condiciones` se mantiene por compat front antiguo |

**No** expone: `estado_logs`, `user`, `token_publico`, `item_visto`, IDs internos de cartera/receptor.

## Enlace web / QR (pie del PDF y correos)

- URL canónica front: `{APP_FRONTEND_URL}/public/presupuesto/{token_publico}` (misma ruta que `public-routing`).
- Generación QR PDF: `PresupuestoPdf::generarQrCodeParaPresupuesto` (BaconQrCode → data URI).
- Correo / QR auxiliar en controller: `ProveedorPresupuestoController` usa la misma URL pública (no la preview autenticada).
- Requiere `asegurarTokenPublico()` antes de armar el enlace. Si `frontend_url` apunta a la API, el QR “no existe”.

## Piezas de soporte

| Pieza | Ubicación |
|-------|-----------|
| PDF DomPDF | `app/Support/PresupuestoPdf.php` (+ `PresupuestoPdfDocumentConfig`, layout/tema) |
| Merge anexos PDF | `app/Support/PresupuestoPdfAnexoMerger.php` |
| Estampado anexos PDF | `app/Support/PresupuestoPdfAnexoEstampado.php` (usa `titulo_anexos_pdf`) |
| Mail | `app/Mail/PresupuestoEnviadoMail.php` |
| Notifications | `app/Notifications/Presupuesto/*` (+ `PresupuestoNotificationContent`) |

Flujo de disparo, casos receptor registrado/no registrado y formato de título/mensaje: [workflows.md — Notificaciones](./workflows.md#notificaciones-presupuesto).

## Notas de contrato

- Moneda del documento: `term_cond_moneda` ∈ `MXN` \| `USD` \| `EUR`.
- Folio: `GET …/next-folio` / asignación al crear usan `PRES-` + `proveedores.consecutivo_presupuesto_siguiente` (no el `id` del presupuesto).
- `fecha_emision`: aceptada en store/update; el front permite fechas futuras.
- `nombre_presupuesto`: `nullable|string|max:120` en Store/Update. Resources lo exponen tal cual. PDF: si hay valor, se usa como título del bloque de descripción; `concepto_general` sigue siendo el cuerpo. Duplicar copia el campo. Al aplicar plantilla el PPTO nace con `concepto_general` = `Borrador` (sin copiar descripción de la plantilla).
- `titulo_anexos`: `nullable|string|max:80` en `StorePresupuestoRequest` / `UpdatePresupuestoRequest`. Resources y Blade (sección imágenes) normalizan vacío → **Anexos**.
- `titulo_anexos_pdf`: `nullable|string|max:80`. Resources normalizan vacío → **Anexos PDF**. En el PDF generado: título principal del **estampado** de cada hoja mergeada (`PresupuestoPdfAnexoEstampado`). Si el anexo PDF tiene `titulo` propio distinto, se muestra como subtítulo.
- Duplicar: body opcional (bool, default `true`): `mantener_cliente`, `mantener_anexos_imagen`, `mantener_anexos_pdf`, `mantener_tarjeta`. Si `false`, el borrador nuevo omite receptor, anexos imagen/PDF (copia de archivos propios) o tarjeta emisor según el flag. No copia columnas legacy droppeadas (`obs_traslados`, `obs_viaticos`, `term_cond_anticipo_porcentaje`). Copia `titulo_anexos` / `titulo_anexos_pdf`, términos vía `term_cond_*` / `term_cond_visibilidad`, y `pdf_theme` / `ppto_config`. Resetea `motivo_rechazo`, `item_visto`, folio y `fecha_emision`. Front: modal de confirmación en Mis presupuestos con switches.
- Plantillas: CRUD en `…/plantillas`. Contenido de la receta: **conceptos**, **anexos imagen/PDF**, **tema**, **tarjeta**. **Sin** descripción general del documento (`concepto_general` nullable/legacy; captura y aplicar/desde-presupuesto no lo usan). `POST …/aplicar` → borrador nuevo vía `PresupuestoPlantillaAplicarService::aplicar`. `POST …/aplicar-sobre/{presupuesto}` → `aplicarSobre` sobre el PPTO actual (reemplaza conceptos/anexos y copia layout; no toca receptor/fecha/descripción/nombre). `POST …/desde-presupuesto/{presupuesto}` → `PresupuestoPlantillaDesdePresupuestoService` (body opcional bool default `true`: `mantener_anexos_imagen`, `mantener_anexos_pdf`, `mantener_tarjeta`, `mantener_tema`; **sin** receptor ni descripción general). Anexos en tablas hijas + endpoints anidados.
- PDF tabla de conceptos: columna `#` centrada (`td:first-child`) en concepto y párrafo.
- Anexos imagen: **sin** límite de cantidad en API (el tope de 4 es solo front).
- No hay endpoints de cobro PayPal/Stripe ni de “finalizar por pago” en presupuestos (roadmap).
- Cuentas bancarias: rutas de `{proveedor}/cuentas-bancarias` (perfil / soporte); no son el flujo SP.
