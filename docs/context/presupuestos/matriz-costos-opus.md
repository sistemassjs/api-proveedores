# Matriz de costos y aproximación a Opus

> Contexto de dominio **presupuestos**. Complementa [overview.md](./overview.md), [api.md](./api.md), [database.md](./database.md), [front.md](./front.md).

## Premisa de producto

Se desea que GestionPlus **imite el comportamiento de Opus** al generar un presupuesto:

1. **Raíz del PPTO** — listado de partidas (hoy: líneas concepto/párrafo; roadmap: capítulos jerárquicos).
2. **Matriz de costo del concepto** — el **precio unitario** de una partida se calcula como Σ (cantidad × costo) de componentes/insumos; opcionalmente se muestra el desglose al cliente (preview / PDF / enlace público).

Referencia Opus (comportamiento, no clone 1:1):

- Pantalla raíz: capítulos + conceptos con unidad, cantidad, P.U., total.
- Desglose del concepto: insumos tipados (materiales, mano de obra, herramienta, equipo, auxiliares, etc.) que suman el P.U.

## Estado actual (v1 — Fase 0 cerrada)

| Capacidad | Estado |
|-----------|--------|
| Catálogo compuestos (`es_compuesto` + componentes) | **Hecho** |
| Línea PPTO `tiene_matriz` + `componentes` (API) | **Hecho** |
| Editor de matriz **en captura** del PPTO (modal Manual) | **Hecho** (Plus) |
| Snapshot al elegir compuesto del catálogo | **Hecho** |
| Switch documento `config_mostrar_matriz_costos` | **Hecho** (default off) |
| Desglose en preview / enlace público | **Hecho** |
| Desglose en PDF (Blade) | **Hecho** |
| Plantillas: persistir / aplicar matriz | **Hecho** |
| Duplicar PPTO con matriz | **Hecho** |
| Capítulos / niveles (raíz Opus) | **Roadmap** Fase 2 |
| Tipos de insumo Opus (MO, herramienta, % sobre MO…) | **Roadmap** Fase 1 |
| Taxonomía familias OPUS en form producto | Dominio **catálogo** (puente); ver `docs/context/catalogo/` |

### Categorías de componente (v1)

Solo **`producto` \| `servicio`**. No hay aún materiales / mano de obra / herramienta / equipo / auxiliares como en Opus.

### Dónde se arma la matriz

1. **Catálogo de conceptos** — compuestos (gestión Plus, CRUD list/form/detail).
2. **Captura PPTO** — switch pill **Precio fijo / Matriz** (mismo patrón que Producto/Servicio; `data-tour="ppto-matriz-toggle"` / `ppto-matriz-editor`). El P.U. queda calculado (readonly) = Σ importes.
3. Al elegir un compuesto del catálogo se hace **snapshot** a la línea (sin FK viva obligatoria).

### Visibilidad del desglose

- Flag de documento: `config_mostrar_matriz_costos` (UI: “Mostrar desglose de costos en PDF”).
- Si está activo y la línea tiene componentes, el desglose aparece bajo la descripción en preview, enlace público y PDF.

## Motor

`App\Services\Presupuesto\PresupuestoMatrizCalculoService`

- `importe = cantidad × precio_unitario` (4 decimales internos).
- P.U. padre = Σ importes (2 decimales en columna de línea/documento).
- `sincronizarComponentesLinea` — líneas de `presupuestos`.
- `sincronizarComponentesPlantillaLinea` — líneas de plantillas.
- Anti-ciclo en compuestos de catálogo.

## Tablas

| Tabla | Rol |
|-------|-----|
| `presupuesto_catalogo_concepto_componentes` | Matriz del compuesto en catálogo |
| `presupuesto_concepto_componentes` | Matriz snapshot en línea de PPTO |
| `presupuesto_plantilla_concepto_componentes` | Matriz en línea de plantilla |
| `presupuesto_conceptos.tiene_matriz` | Bool |
| `presupuesto_plantilla_conceptos.tiene_matriz` | Bool |
| `presupuestos.config_mostrar_matriz_costos` | Bool documento |

## Roadmap hacia Opus (acordado)

Orden recomendado:

```
Fase 0 (hecha) → editor en captura + PDF + plantillas
Fase 1 → tipos de insumo (materiales, MO, herramienta, equipo, auxiliares) + totales por tipo
Fase 2 → capítulos / clave jerárquica en la raíz del PPTO
Fase 3 → % sobre MO, indirectos Opus, explosión de insumos, vínculo fuerte con familias OPUS de catálogo
```

**No** convertir presupuesto → Solicitud de pago. Cobro/finalización es roadmap **dentro** de presupuestos (pasarelas), no dominio SP.

## Relación con “OPUS” del catálogo de productos

La taxonomía global `catalogo_familias` / `catalogo_subfamilias` (seed estilo Opus) y el picker de productos publicados son el **puente de materiales** hacia PPTOs. No sustituyen la matriz APU del concepto. Ver [../catalogo/overview.md](../catalogo/overview.md).

## Front de referencia

| Pieza | Ruta |
|-------|------|
| Editor matriz | `components/presupuesto-matriz-costos-editor/` |
| Modal captura | `concepto-catalogo-manual-modal` (toggle + editor) |
| Form catálogo compuesto | `pages/catalogo-conceptos/.../presupuesto-concepto-form` |
| Preview desglose | `presupuesto-proveedor-preview` |
| Tours | `presupuestos-tutorials.config.ts` |
