# Solicitudes de pago — Workflows

## Ciclo SP (resumen)

1. **Alta** — con factura o `sin-factura` (estado pendiente).
2. **Autorización** — roles Construcc (flags por rol) → autorizada o rechazada.
3. **Factura** — si faltaba, subir PDF/XML.
4. **Pago** — completo o parcial (`PagoSPP` + pivot, `origen=spp`) → pagado.
5. **Comprobantes** — fuente de verdad en el **pago**; espejo en la SPP para listados/descarga por SP.

## Comprobante de pago (importante)

Al registrar pago desde Construcc (`POST .../pagos-spp/proveedor/{proveedor}/pagos`):

| Dónde | Campo | Rol |
|-------|--------|-----|
| `pagos_spp` | `comprobante_pago` | **Fuente de verdad** (disco `private`, carpeta `comprobantes/`) |
| `solicitudes_pago` | `ruta_archivo_comprobante_pago` | Campo legado / espejo opcional (`sincronizarComprobanteDesdePago`) |

**Dos caminos válidos** (API Resources de SPP):

1. Comprobante en la SPP → `url_comprobante_pago` = descarga por solicitud.
2. Sin ruta en SPP pero con pago asociado que tiene archivo → `url_comprobante_pago` = descarga por pago (`pagos-spp/.../descargar-comprobante`).

Helpers: `resolverPagoConComprobante()`, `resolverRutaComprobantePago()`, `resolverUrlComprobantePago()`.  
Expuestos en `SolicitudPagoResource`, `ConstruccSolicitudPagoResource` y **`ConstruccPagoSPPResource`** (listado GestionPlus `.../pagos-spp/proveedor/{id}/spp`).  
`ruta_archivo_comprobante_pago` en response = ruta efectiva (SPP o pago) para chips; la URL apunta al origen real.

- Un pago puede liquidar **varias** SPP: el archivo vive en el pago; las resources resuelven el enlace sin exigir espejo en cada SPP.
- Flujo legado DA `confirmarPago` escribe solo en la SPP; el flujo vigente de GestionPlus es pagos-spp.

## Pago directo (sin SPP)

1. Construcc registra pago con comprobante (`POST .../pagos-directos`, `origen=directo`).
2. Opcional: N facturas (PDF y/o XML; a veces solo PDF) + `metodo_pago` PUE/PPD.
3. Si PPD: se pueden subir complementos (PDF/XML) en el alta o después.
4. El registro **no se bloquea** por documentos incompletos; el reporte marca `documentos_faltantes`.
5. Sin notificaciones / InterAPI en v1.
6. Mismo reporte de contabilidad que pagos SPP.

## OC → SP

1. Consultar OC vía API Construcciones (`ordenes-compra/consultar`).
2. Validar / preview conversión.
3. Crear SP desde OC (`ordenes-compra-sp/convert`).
4. Tracking con `referencia_oc` / `origen_oc`.

## Notificaciones externas

Webhooks Construcciones: pagada / rechazada → notificaciones a usuarios del proveedor.
