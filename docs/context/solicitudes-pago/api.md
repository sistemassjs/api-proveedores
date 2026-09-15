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

Resources SPP (`SolicitudPagoResource`, `ConstruccSolicitudPagoResource`, `ConstruccPagoSPPResource`): `url_comprobante_pago` / `ruta_archivo_comprobante_pago` resuelven dos caminos — archivo en la SPP o en un `PagoSPP` asociado (`resolverUrlComprobantePago()`). El listado GestionPlus (`GET .../pagos-spp/proveedor/{id}/spp`) usa `ConstruccPagoSPPResource`.

## Lado administrador (`routes/segmented/admin.php`)

Rol `ADMINISTRADOR`. Consulta de SPP y documentos anexos (sin crear/editar/subir).

| Método | Ruta | Notas |
|--------|------|-------|
| `GET` | `/admin/solicitudes-pago` | Listado global paginado. Filtros Filterable: `proveedor_id`, `search` / `numero_folio_solicitud`, `fecha_registro_pendiente_desde\|hasta`, `empresa_construcc_id`, `estado_solicitud`, etc. Resource: `SolicitudPagoResource` |
| `GET` | `/admin/solicitudes-pago/{solicitudPago}` | Detalle |
| `GET` | `/admin/solicitudes-pago/{id}/descargar-factura-pdf\|xml` | Descarga factura |
| `GET` | `/admin/solicitudes-pago/{id}/descargar-cotizacion` | Descarga cotización |
| `GET` | `/admin/solicitudes-pago/{id}/descargar-comprobante` | Descarga comprobante (SP o fallback último pago) |

Controller: `AdminSolicitudPagoController`. Preview en ficha empresa: `GET /admin/catalogos/proveedores/{id}/resumen` → `ultimas_spp` (máx. 10).

## Lado Construcc (`routes/segmented/construcc.php`)

Middleware ApiKey. Controllers: `ConstruccSolicitudPagoController`, `ConstruccPagosSPPController`, `ConstruccProveedorSolicitudPagoController`, etc.

### Pagos SPP / directos (`/construcc/pagos-spp`)

| Método | Ruta | Notas |
|--------|------|-------|
| `POST` | `/proveedor/{proveedor}/pagos` | Pago con SPP (autorización previa). Comprobante → `pagos_spp` + espejo en cada SPP |
| `POST` | `/proveedor/{proveedor}/pagos-directos` | Pago sin SPP; comprobante required; facturas/complementos opcionales |
| `POST` | `/pagos/{pago}/facturas` | Agregar factura al pago |
| `POST` | `/pagos/{pago}/facturas/{factura}/complementos` | Agregar complemento |
| `GET` | `/facturas/{factura}/descargar-pdf\|xml` | Descarga |
| `GET` | `/complementos/{complemento}/descargar-pdf\|xml` | Descarga |
| `GET` | `/reportes/contabilidad` | Incluye `origen`, `facturas`, `documentos_faltantes` |

Config: `config/pagos.php` (`marcar_complemento_faltante_si_ppd`, `pagos_directos_usan_misma_serie_folio`).

> El mismo archivo también tiene rutas de **productos** (`construcc/proveedores/…/productos`). Esas pertenecen al dominio **catálogo**, no a SP.

## Webhooks (`routes/segmented/notifications.php`)

- `POST /notifications/solicitud-pago/pagada`
- `POST /notifications/solicitud-pago/rechazada`

## Notificaciones Laravel

`app/Notifications/SolicitudPago/`: Pagada, Abonada, Rechazada, FacturaSubida, FacturaPendiente, SinFactura, ComprobanteActualizado, etc.

Pagos directos: **sin notificaciones** en v1 (solo registro interno).

## Servicios

`ConstruccionesApiService`, `OrdenCompraConversionService`, entre otros.
