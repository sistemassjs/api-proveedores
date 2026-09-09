# CRUD Pagos

## Listar Pagos
**GET** `/construcc/pagos-spp`

Lista todos los pagos SPP con paginación y filtros.

## Ver Pago
**GET** `/construcc/pagos-spp/{pagoId}`

Obtiene el detalle de un pago específico con sus solicitudes asociadas.

## Descargar Comprobante
**GET** `/construcc/pagos-spp/{pagoId}/comprobante/download`

Descarga el archivo del comprobante de pago.
