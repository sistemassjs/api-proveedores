# Solicitudes de pago — Base de datos

## Models / tablas

| Model | Tabla | Rol |
|-------|-------|-----|
| `SolicitudPago` | `solicitudes_pago` | Núcleo SP (montos, facturas, comprobante, roles, OC) |
| `PagoSPP` | `pagos_spp` | Pago a una o varias SP **o** pago directo (`origen`) |
| `PagoSolicitudPago` | `pago_solicitud_pago` | Pivot monto aplicado (solo `origen=spp`) |
| `PagoFactura` | `pago_facturas` | N facturas ligadas a un pago (flujo directo; PDF/XML opcionales) |
| `PagoComplemento` | `pago_complementos` | Complementos CFDI tipo P por factura (PPD) |
| `CuentaBancaria` | `cuentas_bancarias` | Cuentas del proveedor |
| `SolicitudPagoCuentaBancaria` | pivot | SP ↔ cuentas |
| `EmpresaConstrucc` | `empresa_construcc` | Empresa constructora (+ consecutivos) |
| `OcConstrucc` | `oc_construcc` | Tracking ligero OC externa |
| `OrdenCompra` | local | Soporte conversión OC→SP |

## `pagos_spp.origen`

| Valor | Significado |
|-------|-------------|
| `spp` (default) | Pago aplicado a SPP vía pivot; requiere autorización previa de la SPP |
| `directo` | Sin SPP ni aprobación; documentos en `pago_facturas` / `pago_complementos` |

Folio: `folio_pago_spp_consecutivo` (misma serie por empresa si `config('pagos.pagos_directos_usan_misma_serie_folio')`).

## Documentos faltantes

Por factura del pago: `factura_pdf`, `factura_xml`; si `metodo_pago=PPD` y `config('pagos.marcar_complemento_faltante_si_ppd')`: `complemento_pago_pdf` / `complemento_pago_xml`.

Storage disco `private`: `comprobantes/`, `facturas/pdf|xml/`, `complementos_pago/pdf|xml/`.

## Enums de estado

| Enum | Uso |
|------|-----|
| `EstadoSP` | `pendiente` → `autorizada` → `pagado` \| `rechazada` |
| `EstadoSolicitud` | Flags por rol (dg, dt, pc, si, da, ro) |
| `EstadoCuentaBancaria` | activa / inactiva / validada / … |
| `EstadoOrdenCompra` | pendiente / aprobada / … |

Flags ortogonales frecuentes: `tiene_factura`, `verificada`. Campos OC: `referencia_oc`, `origen_oc`.

## Cuenta bancaria (`cuentas_bancarias`)

| Campo | Regla de negocio |
|-------|------------------|
| `cuenta` | Solo dígitos. **Longitud libre** en validación. Columna BD: `string(255)`. |
| `clabe` | Exactamente 18 dígitos. |
| `tarjeta` | Exactamente 16 dígitos. |

Al menos uno de `cuenta` / `clabe` / `tarjeta` debe venir informado. Si hay `clabe`, `cuenta` es obligatoria.
