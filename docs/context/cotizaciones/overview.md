# Cotizaciones (NexProv) — Overview

Dominio de **cotización** hacia/desde una empresa: solicitud (pública u opcional/interna), gestión en NexProv, envío al cliente y archivo en galería.

> No confundir con la entidad legacy `cotizaciones` (Construcc / marketplace) ni con el adjunto `ruta_archivo_cotizacion` de Solicitudes de pago.

## Flujo de negocio

1. **Solicitud** (opcional / variable):
   - Cliente externo vía cotizador público, **o**
   - Alta interna en NexProv (`nexprov_interna`), **o**
   - Solicitud en nombre de otra empresa (`empresa_tercero` + `solicitante_empresa`).
2. Empresa recibe **notificación** en NexProv (solo app `nexprov`) cuando el origen es público.
3. Empresa **revisa y elabora**: productos, precios, sugerencias, **vigencia** (`vigencia_hasta`) y **políticas**.
4. **Envío** de la cotización por email y/o WhatsApp + PDF → estatus `respondida`. El PDF queda en **galería**.
5. El cliente recibe la cotización. Puede **no usarla** (barrera fuera del sistema); permanece `respondida`.
6. Si el cliente **sí la usa**, la empresa marca **procesada** → se genera/archiva PDF definitivo en la **galería de archivos**.

## Qué es (técnico)

- Catálogo público abierto + cotizador.
- Inbox NexProv + alta interna.
- PDF Blade (secciones, QR, vigencia/políticas).
- Galería: `solicitud_cotizacion_archivos` (envíos + procesadas).

## Qué no es

| No mezclar con | Motivo |
|----------------|--------|
| Presupuestos | Otro documento / PDF / cartera |
| Solicitudes de pago | El archivo `ruta_archivo_cotizacion` es adjunto de SP |
| Pedidos / requisiciones | Fuera de alcance |
| CRUD legacy `cotizaciones` | Construcc / schema antiguo |

## Actores

| Actor | Identidad |
|-------|-----------|
| Cliente externo | Form (nombre, email, teléfono/WhatsApp, notas) |
| Empresa solicitante tercera | Campo `solicitante_empresa` (origen `empresa_tercero`) |
| Empresa cotizante | Usuarios del proveedor en NexProv |

## Estados

`recibida` → `en_revision` → `respondida` → `procesada`  
también: `cerrada` | `rechazada`

## Orígenes

| Valor | Uso |
|-------|-----|
| `publico_cotizador` | Alta desde `/public/cotizador/...` |
| `nexprov_interna` | Alta manual en NexProv |
| `empresa_tercero` | Solicitud a nombre de otra empresa |

## Lectura

- API: [api.md](./api.md)
- BD: [database.md](./database.md)
- Front: [front.md](./front.md)
- Contrato Codex: [nexprov-api-dev.md](./nexprov-api-dev.md)
- Puentes: [../cross-domain.md](../cross-domain.md)
