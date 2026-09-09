# SPP por Proveedor

## Listar Proveedores
**GET** `/construcc/pagos-spp/proveedor`

## Listar SPP por Proveedor
**GET** `/construcc/pagos-spp/proveedor/{proveedorId}/spp`

## Detalle SPP por Proveedor
**GET** `/construcc/pagos-spp/proveedor/{proveedorId}/spp/{sppId}`

## Listar Pagos de una SPP
**GET** `/construcc/pagos-spp/proveedor/{proveedorId}/spp/{sppId}/pagos`

## Listar SPP de Pago
**GET** `/construcc/pagos-spp/proveedor/{proveedorId}/pagos/{pagoId}/spp`

## Listar Cuentas del Proveedor
**GET** `/construcc/pagos-spp/proveedor/{proveedorId}/cuentas_bancarias`

## Registrar Pago para SPPs del Proveedor
**POST** `/construcc/pagos-spp/proveedor/{proveedorId}/pagos`
