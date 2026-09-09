# Solicitudes de pago — Workflows

## Ciclo SP (resumen)

1. **Alta** — con factura o `sin-factura` (estado pendiente).
2. **Autorización** — roles Construcc (flags por rol) → autorizada o rechazada.
3. **Factura** — si faltaba, subir PDF/XML.
4. **Pago** — completo o parcial (`PagoSPP` + pivot) → pagado.
5. **Comprobantes** — en SP y/o en pago parcial.

## OC → SP

1. Consultar OC vía API Construcciones (`ordenes-compra/consultar`).
2. Validar / preview conversión.
3. Crear SP desde OC (`ordenes-compra-sp/convert`).
4. Tracking con `referencia_oc` / `origen_oc`.

## Notificaciones externas

Webhooks Construcciones: pagada / rechazada → notificaciones a usuarios del proveedor.
