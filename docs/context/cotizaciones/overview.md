# Cotizaciones (NexProv) — Overview

Dominio de **solicitud pública de cotización** hacia una empresa de catálogo, gestión en NexProv y respuesta al cliente por email y/o WhatsApp.

> No confundir con la entidad legacy `cotizaciones` (Construcc / marketplace) ni con el adjunto `ruta_archivo_cotizacion` de Solicitudes de pago.

## Qué es

1. Cliente externo (sin cuenta) navega catálogo **público abierto**.
2. Elige **una empresa** y productos públicos → envía solicitud (datos del form).
3. La empresa recibe en **NexProv** (notificación solo `nexprov`).
4. Edita precios, cantidades, productos; puede sugerir alternativas (p. ej. stock 0).
5. Genera PDF (Blade + logo empresa + logos NexProv) y responde por **email y/o WhatsApp**.
6. WhatsApp API es opcional; si falla, el flujo sigue con deep-link `wa.me`.

## Qué no es

| No mezclar con | Motivo |
|----------------|--------|
| Presupuestos | Otro documento / PDF / cartera |
| Solicitudes de pago | El archivo `ruta_archivo_cotizacion` es adjunto de SP, no este flujo |
| Pedidos / requisiciones | Fuera de alcance |
| CRUD legacy `cotizaciones` | Construcc / schema antiguo |

## Actores

| Actor | Identidad |
|-------|-----------|
| Cliente externo | Solo form (nombre, email, teléfono/WhatsApp, notas) |
| Empresa | Usuarios del proveedor en NexProv |

## Estados

`recibida` → `en_revision` → `respondida` → `cerrada`  
también: `rechazada`

## Lectura

- API: [api.md](./api.md)
- BD: [database.md](./database.md)
- Front (orientación): [front.md](./front.md)
- Puentes: [../cross-domain.md](../cross-domain.md)
