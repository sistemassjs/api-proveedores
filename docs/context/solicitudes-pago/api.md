# Solicitudes de pago — API

Repo: `api-proveedores`.

## Lado proveedor (`routes/segmented/gerente.php`)

| Prefijo | Controller | Rol |
|---------|------------|-----|
| `{proveedor}/solicitudes-pago` | `ProveedorSolicitudPagoController` | CRUD, métricas, facturas, comprobantes, confirmar |
| `{proveedor}/pagos-spp/{pago}/descargar-comprobante` | idem | Comprobante pago parcial |
| `{proveedor}/ordenes-compra/consultar…` | `ProveedorOrdenCompraController` | Solo consulta OC (proxy Construcciones) |
| `{proveedor}/ordenes-compra-sp` | `OrdenCompraSolicitudPagoController` | Convertir OC→SP, validate, preview, unlink |
| `{proveedor}/empresas-constructoras` | `EmpresaConstruccController` | CRUD / search empresas |
| `{proveedor}/cuentas-bancarias` | `ProveedorCuentaBancariaController` | Cuentas del proveedor (insumo SP) |

Endpoints SP frecuentes: `GET/POST /`, `POST /sin-factura`, `GET /historico`, `/conteo-por-estado`, `/dashboard/metricas`, `POST /{id}/subir-factura*`, `subir-comprobante`, `confirmar-pago`, descargas.

## Lado Construcc (`routes/segmented/construcc.php`)

Middleware ApiKey. Controllers: `ConstruccSolicitudPagoController`, `ConstruccPagosSPPController`, `ConstruccProveedorSolicitudPagoController`, etc.

> El mismo archivo también tiene rutas de **productos** (`construcc/proveedores/…/productos`). Esas pertenecen al dominio **catálogo**, no a SP.

## Webhooks (`routes/segmented/notifications.php`)

- `POST /notifications/solicitud-pago/pagada`
- `POST /notifications/solicitud-pago/rechazada`

## Notificaciones Laravel

`app/Notifications/SolicitudPago/`: Pagada, Abonada, Rechazada, FacturaSubida, FacturaPendiente, SinFactura, ComprobanteActualizado, etc.

## Servicios

`ConstruccionesApiService`, `OrdenCompraConversionService`, entre otros.
