# Relaciones entre dominios

Los tres dominios son **aislados**. Este archivo lista solo lo que existe de verdad. Si no está aquí, **no inventes** un puente.

## Resumen

| De → A | ¿Relación de negocio? | Detalle |
|--------|------------------------|---------|
| Catálogo → SP | **No** | SP no usa `producto_id` ni líneas de catálogo |
| Catálogo → Presupuestos | **No en datos de producto** | Conceptos son snapshot (texto). Catálogo de conceptos es propio del dominio presupuestos (`presupuesto_catalogo_conceptos`), no del dominio Catálogo de productos (`productos`) |
| Catálogo público → Presupuestos | **Lectura + snapshot** | El picker de líneas junta conceptos internos y `catalogo_publico_items`. Al elegir se **copia** a `presupuesto_conceptos` (sin FK) |
| Presupuestos → SP | **No** | No hay conversión presupuesto → SP. El cobro/finalización de presupuestos es **roadmap dentro de presupuestos** (cuentas + pasarelas), no el dominio SP |
| SP → Catálogo | **No** | Endpoints de productos bajo `construcc/…` son otro dominio, mismo archivo de rutas |
| Cualquiera → Plataforma | **Sí** | Auth, `proveedor_id`, roles/usuarios (core), storage, notificaciones infra |

## Acoplamientos suaves (no son puentes de negocio)

1. **Home / dashboard:** puede mostrar contadores o enlaces a más de un dominio. Solo navegación/UI.
2. **Métricas SP:** el dashboard de SP puede contar presupuestos como número suelto. No hay FK ni flujo.
3. **Palabra “catálogo” en presupuestos:** suele significar **cartera de clientes/receptores**, no productos. No confundir.
4. **Construcc (`construcc.php`):** mezcla HTTP de SP y de productos. Separar por controller: productos → dominio catálogo; SP/pagos → dominio SP.
5. **Catálogo público:** listado global importado por admin. Presupuestos y (a futuro) Construcc lo **leen**; no escriben.

## Dependencia externa (solo SP)

- **API Construcciones:** órdenes de compra (consulta), webhooks de pago/rechazo, consumidor con ApiKey. Pertenece al dominio [solicitudes-pago](./solicitudes-pago/), no a plataforma genérica ni a los otros dos dominios.

## Futuro (apps independientes)

Si se dividen repos/apps, estas relaciones no deberían crecer. Evitar nuevas FKs o servicios compartidos entre catálogo, SP y presupuestos.
