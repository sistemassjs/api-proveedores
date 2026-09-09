# Presupuestos — Overview

Dominio **aislado**: herramienta para generar presupuestos a distintos giros comerciales (mecánicos, herreros, constructores, vendedores, etc.) con una UX simple, al alcance de cualquier usuario.

Parte de un **esquema gratuito** (plan base) y funciones **Plus** (ver [front.md](./front.md) — badge / directiva). A futuro puede evolucionar a apps independientes; hoy cohabita en el monorepo sin mezclarse con catálogo de productos ni solicitudes de pago.

## Propósito

Facilitar la presupuestación comercial: crear, personalizar, enviar y cerrar (aceptar/rechazar) presupuestos; PDF con temas; cartera propia; acceso a empresas ya registradas; configuración de emisor (usuario ≠ solo datos de empresa).

Visión de producto (menú / secciones): el módulo no es solo el ciclo del documento. Bajo **Presupuestos** conviven Generar / Mis presupuestos y el grupo colapsable **Recursos** (Clientes, Catálogo de conceptos, Tarjetas Presentación). Detalle de menú y rutas en [front.md](./front.md).

## Capacidad (mapa Hecho / Pendiente / Roadmap)

| Capacidad | Estado | Notas |
|-----------|--------|-------|
| Cartera de clientes propia | **Hecho** | API `cartera-clientes` + sección menú **Clientes** (list/form/detail) |
| Acceso a empresas/proveedores registrados | **Hecho** | `proveedores-registrados` |
| Receptor manual (texto) | **Hecho** | Sin entidad previa |
| Conceptos libres (línea / párrafo) | **Hecho** | Snapshot; sin `producto_id` |
| Catálogo de conceptos reutilizable | **Hecho** | API + modal captura (Plus) + sección menú **Catálogo de conceptos** (list/form/detail) |
| Tarjetas Presentación (emisor / Atte.) | **Hecho** | API config + sección menú **Tarjetas** (list/form/detail); Perfil enlaza al listado |
| Menú producto (Generar / Mis presupuestos / …) | **Hecho** | Ver [front.md](./front.md) |
| Descuentos, IVA, términos y condiciones | **Hecho** | En formulario / PDF |
| Monedas | **Hecho** | Solo **MXN**, **USD**, **EUR** (`term_cond_moneda`; default MXN) |
| Anexos imagen | **Hecho** | Máx. **4** en captura (solo front); título sección `titulo_anexos` → PDF/preview |
| Anexos PDF | **Hecho** | Merge al final; título sección `titulo_anexos_pdf` → estampado; título por archivo opcional |
| Fecha de emisión editable | **Hecho** | Modal ajustes; front: no fecha futura |
| Layout PDF `ppto_config` | **Hecho** | JSON mm (8 keys) + modal Ajustes completo; gap logo default 7 |
| Histórico estados / timeline | **Hecho** | `presupuesto_estado_logs` + modal Historial; sin log en borrador |
| Totales opcionales | **Hecho** | `config_mostrar_totales` (oculta IVA/total/letra) |
| Folio `PRES-XXXX` | **Hecho** | Consecutivo por proveedor; bump histórico +200 (migración) |
| Numeración alineada concepto/párrafo | **Hecho** | Columna `#` centrada en PDF + preview |
| Temas PDF personalizables | **Hecho** | `pdf-themes` |
| Datos usuario emisor ≠ empresa emisora | **Hecho** | Config / tarjetas emisor-receptor |
| Envío app + correo + enlace público | **Hecho** | Aceptar / rechazar; notifs: ver workflows |
| Duplicar presupuesto | **Hecho** | API + icono en cards de Mis presupuestos; no en recibidos. Distinto de Plantillas |
| Plantillas de presupuesto | **Hecho** | Captura = page-modals sin cliente; `aplicar` + `desde-presupuesto`; anexos propios |
| Notificaciones ppto (copy bandeja) | **Hecho** | Título `FOLIO · EMPRESA · NOMBRE · ACCIÓN` + hechos en mensaje |
| QR pie PDF → enlace público | **Hecho** | `{frontend}/public/presupuesto/{token}`; con sesión → `enlace-publico/:token` |
| Cuentas bancarias (perfil empresa) | **Hecho** (soporte) | Perfil; no cobranza automática |
| Historial en menú | **Fuera de v1** | Ruta `historial` puede existir; no foco del rework de menú |
| Pasarelas PayPal / Stripe | **Roadmap** | UI en perfil (“Servicios digitales”); **sin implementar** |
| Pago → finalización del presupuesto | **Roadmap** | Tras aceptar; no confundir con dominio SP |

## Límites (qué entra)

- Documento presupuesto + conceptos + anexos (imagen / PDF)
- Cartera y config emisor/receptor (incl. gestión en secciones propias del módulo)
- PDF, correo, notificaciones de presupuesto, token público
- Monedas MXN | USD | EUR
- Roadmap de cobro (cuentas / pasarelas) **como parte de este dominio**, no vía Solicitudes de pago

## Qué NO es este dominio

| No mezclar con | Motivo |
|----------------|--------|
| Solicitudes de pago (SP/SPP) | No hay conversión presupuesto → SP |
| Catálogo de **productos** | Conceptos sin FK a producto |
| Cotizaciones / pedidos | Otros flujos |

## Advertencia de lenguaje

- **“Catálogo de conceptos”** (menú / Plus) = biblioteca reutilizable de líneas del dominio presupuestos — **no** el dominio Catálogo de productos.
- **“Clientes”** = cartera de presupuestos (`cartera-clientes`), no clientes/empresas de SP.
- **“Tarjetas Presentación”** = config emisor/receptor / cierre Atte. — no tarjetas bancarias ni pasarelas.
- **“Mis presupuestos”** = listados / ciclo del documento (no llamarlo “Documentos”).
- Funciones **Plus**: marcar en UI con `appPlanPlusBadge` / `<app-plan-plus-badge>` ([front.md](./front.md)).

## Estado general

Ciclo **borrador → enviar → aceptar/rechazar** maduro (API + front activos).

Ajustes de documento en captura (cerrados):

- `fecha_emision` — modal Información general (permite futuras en front)
- `ppto_config` — 8 keys mm (márgenes / gaps); modal Ajustes + PDF
- `titulo_anexos` — inline en card anexos imagen (default **Anexos**; PDF sección imágenes + preview)
- `titulo_anexos_pdf` — inline en card anexos PDF (default **Anexos PDF**; estampado al mergear PDFs)
- Anexos imagen: tope **4** solo en front (`PRESUPUESTO_ANEXOS_IMAGEN_MAX`)
- Historial estados: sheet en listado + final del preview; Solicitar aprobación solo emisor

**Pago → finalización**: roadmap.

**Gestión de recursos (v1 UI):** menú Generar / Mis presupuestos / **Recursos** (Clientes, Catálogo de conceptos, Tarjetas Presentación); CRUD list/form/detail por recurso (patrón SPP). Detalle en [front.md](./front.md).

## Docs del dominio

- [api.md](./api.md)
- [database.md](./database.md)
- [front.md](./front.md)
- [workflows.md](./workflows.md)

Ver también: [../cross-domain.md](../cross-domain.md)
