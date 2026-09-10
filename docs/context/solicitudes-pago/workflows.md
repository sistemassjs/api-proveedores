# Solicitudes de pago — Workflows

## Ciclo SP (resumen)

1. **Alta** — con factura o `sin-factura` (estado pendiente).
2. **Autorización** — roles Construcc (flags por rol) → autorizada o rechazada.
3. **Factura** — si faltaba, subir PDF/XML.
4. **Pago** — completo o parcial (`PagoSPP` + pivot, `origen=spp`) → pagado.
5. **Comprobantes** — en SP y/o en pago parcial.

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
