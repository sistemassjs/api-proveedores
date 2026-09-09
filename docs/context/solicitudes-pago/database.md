# Solicitudes de pago — Base de datos

## Models / tablas

| Model | Tabla | Rol |
|-------|-------|-----|
| `SolicitudPago` | `solicitudes_pago` | Núcleo SP (montos, facturas, comprobante, roles, OC) |
| `PagoSPP` | `pagos_spp` | Pago a una o varias SP |
| `PagoSolicitudPago` | `pago_solicitud_pago` | Pivot monto aplicado |
| `CuentaBancaria` | `cuentas_bancarias` | Cuentas del proveedor |
| `SolicitudPagoCuentaBancaria` | pivot | SP ↔ cuentas |
| `EmpresaConstrucc` | `empresa_construcc` | Empresa constructora (+ consecutivos) |
| `OcConstrucc` | `oc_construcc` | Tracking ligero OC externa |
| `OrdenCompra` | local | Soporte conversión OC→SP |

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