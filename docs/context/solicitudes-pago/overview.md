# Solicitudes de pago — Overview

Dominio **aislado**: cobranza del proveedor hacia empresas constructoras (SP / SPP), con facturas, comprobantes, pagos parciales y vÍnculo opcional a Órdenes de compra.

## PropÓsito

Crear y dar seguimiento a solicitudes de pago; subir facturas PDF/XML; recibir comprobantes; pagos parciales/completos; convertir OC a SP; gestionar empresas constructoras vinculadas.

## LÍmites (quÉ entra)

- Solicitud de pago y estados / verificaciÓn / autorizaciones por rol
- Facturas, cotizaciÓn adjunta, comprobantes
- Pagos SPP (parciales) y pivot
- Empresas constructoras + vÍnculo proveedor
- Cuentas bancarias usadas en el flujo SP
- Consulta OC y conversiÓn OC a SP
- API Construcc (consumidor) + webhooks de pago/rechazo
- Notificaciones de dominio SP

## QuÉ NO es este dominio

| No mezclar con | Motivo |
|----------------|--------|
| CatÁlogo de productos | SP no usa productos ni lÍneas de catÁlogo |
| Presupuestos | No hay FK ni conversiÓn presupuesto a SP |
| Sistema de compras completo | La OC "viva" vive en API Construcciones; aquÍ es consulta/conversiÓn |

## Dependencia externa

**API Construcciones** — consulta de OC, webhooks, y app Construcc como consumidor (ApiKey). Es parte de este dominio, no de catÁlogo ni presupuestos.

## Estado

Maduro; rutas front activas (`sp`, `dashboard-oc-sp`, empresas).

## Docs del dominio

- [api.md](./api.md)
- [database.md](./database.md)
- [front.md](./front.md)
- [workflows.md](./workflows.md)

Ver tambiÉn: [../cross-domain.md](../cross-domain.md)