# Relaciones entre dominios

Los tres dominios son **aislados**. Este archivo lista solo lo que existe de verdad. Si no está aquí, **no inventes** un puente.

## Resumen

| De → A | ¿Relación de negocio? | Detalle |
|--------|------------------------|---------|
| Catálogo → SP | **No** | SP no usa `producto_id` ni líneas de catálogo |
| Catálogo productos → Presupuestos | **Lectura + snapshot** | El picker de PPTOs lista empresas `is_proveedor_catalogo` con productos `mostrar_en_catalogo_publico`. Al elegir se **copia** a `presupuesto_conceptos` (sin FK `producto_id`). Ver [catalogo/api.md](./catalogo/api.md#catálogo-empresas-picker-pptos) |
| Catálogo de conceptos (PPTOs) | **Propio de presupuestos** | Tabla `presupuesto_catalogo_conceptos`; no es el dominio Catálogo de productos |
| Feed admin `catalogo_publico_items` → PPTOs | **Deprecado** | Sustituido por productos publicados. Admin/import del feed: **plan de apagado** |
| Presupuestos → SP | **No** | No hay conversión presupuesto → SP. El cobro/finalización de presupuestos es **roadmap dentro de presupuestos** (cuentas + pasarelas), no el dominio SP |
| SP → Catálogo | **No** | Endpoints de productos bajo `construcc/…` son otro dominio, mismo archivo de rutas |
| Cualquiera → Plataforma | **Sí** | Auth, `proveedor_id`, roles/usuarios (core), storage, notificaciones infra |

## Puente Catálogo → Presupuestos (v1)

Mecanismo: **solo lectura + snapshot** (mismo comportamiento UX que tenía el feed admin).

1. Cards de empresas = proveedores `is_proveedor_catalogo` con ≥1 producto `mostrar_en_catalogo_publico`.
2. Dentro de empresa → listado de esos productos; filtros avanzados **solo ahí**, taxonomía **OPUS** (familia/subfamilia); **sin** filtro marca.
3. Snapshot a la línea: `nombre`→`descripcion`, unidad de medida, `precio_base`, `imagen_principal`; cantidad la pone el usuario.
4. Sin FK a `productos`. El documento PPTO queda autónomo.
5. API: `GET /catalogo/empresas…` (`shared.php`). Sugerencias PPTOs origen `catalogo` leen productos (no `catalogo_publico_items`).

## Acoplamientos suaves (no son puentes de negocio)

1. **Home / dashboard:** puede mostrar contadores o enlaces a más de un dominio. Solo navegación/UI.
2. **Métricas SP:** el dashboard de SP puede contar presupuestos como número suelto. No hay FK ni flujo.
3. **Palabra “catálogo” en presupuestos:** suele significar **cartera de clientes/receptores** o **catálogo de conceptos** Plus — no confundir con productos.
5. **Admin catálogo empresas:** gestiona productos reales (`/admin/catalogo-empresas` + import CSV). Reemplaza el feed `catalogo-publico` en el menú admin.

## Dependencia externa (solo SP)

- **API Construcciones:** órdenes de compra (consulta), webhooks de pago/rechazo, consumidor con ApiKey. Pertenece al dominio [solicitudes-pago](./solicitudes-pago/), no a plataforma genérica ni a los otros dos dominios.

## Futuro (apps independientes)

Si se dividen repos/apps, estas relaciones no deberían crecer. Evitar nuevas FKs o servicios compartidos entre catálogo, SP y presupuestos. El puente lectura+snapshot es el único permitido catálogo↔PPTOs.
