# Solicitudes de pago — Overview

Dominio **aislado**: cobranza del proveedor hacia empresas constructoras (SP / SPP), con facturas, comprobantes, pagos parciales y vínculo opcional a Órdenes de compra.

## Propósito

Crear y dar seguimiento a solicitudes de pago; subir facturas PDF/XML; recibir comprobantes; pagos parciales/completos; **pagos directos** (sin SPP ni aprobación); convertir OC a SP; gestionar empresas constructoras vinculadas.

**Canales de pagador (XOR):**

| Canal | Campo | Quién paga |
|-------|--------|------------|
| Construcc (legado) | `empresa_construcc_id` | Empresa constructora vía API Construcc |
| GestionPlus | `empresa_gestionplus_id` → `proveedores.id` | Otra empresa de la misma plataforma |

El canal Construcc no cambia de contrato. El canal GestionPlus usa rutas `{proveedor}/solicitudes-pago-recibidas` (autorizar / rechazar / pagar) sin roles DG/DT ni InterAPI.

## Límites (qué entra)

- Solicitud de pago y estados / verificación / autorizaciones por rol
- Facturas, cotización adjunta, comprobantes (en **pago** + espejo en SP; ver [workflows.md](./workflows.md#comprobante-de-pago-importante))
- Pagos SPP (parciales) y pivot
- **Pagos directos** (`origen=directo`): sin SPP; N facturas + complementos de pago en el propio pago
- Empresas constructoras + vínculo proveedor
- Cuentas bancarias usadas en el flujo SP
- Consulta OC y conversión OC a SP
- API Construcc (consumidor) + webhooks de pago/rechazo
- Notificaciones de dominio SP

## Qué NO es este dominio

| No mezclar con | Motivo |
|----------------|--------|
| Catálogo de productos | SP no usa productos ni líneas de catálogo |
| Presupuestos | No hay FK ni conversión presupuesto a SP |
| Sistema de compras completo | La OC "viva" vive en API Construcciones; aquí es consulta/conversión |

## Dependencia externa

**API Construcciones** — consulta de OC, webhooks, y app Construcc como consumidor (ApiKey). Es parte de este dominio, no de catálogo ni presupuestos.

## Estado

Maduro; rutas front activas (`sp`, `dashboard-oc-sp`, empresas). Pagos directos: API Construcc lista; UI pendiente.

**Admin:** listado/consulta de documentos SPP en panel (`/pages/panel-admin/spp`) + endpoints `/admin/solicitudes-pago` (lectura y descargas).

## Docs del dominio

- [api.md](./api.md)
- [database.md](./database.md)
- [front.md](./front.md)
- [workflows.md](./workflows.md)

Ver también: [../cross-domain.md](../cross-domain.md)
