# Matriz de costos y aproximación a Opus

> Contexto de dominio **presupuestos**. Complementa [overview.md](./overview.md), [api.md](./api.md), [database.md](./database.md), [front.md](./front.md).

## Premisa de producto

Se desea que GestionPlus **imite el comportamiento de Opus** al generar un presupuesto:

1. **Raíz del PPTO** — listado de partidas (hoy: líneas concepto/párrafo; roadmap: capítulos jerárquicos).
2. **Matriz de costo del concepto** — el **precio unitario** de una partida se calcula como Σ (cantidad × costo) de componentes/insumos; opcionalmente se muestra el desglose al cliente (preview / PDF / enlace público).

Referencia Opus (comportamiento, no clone 1:1):

- Pantalla raíz: capítulos + conceptos con unidad, cantidad, P.U., total.
- Desglose del concepto: insumos tipados (materiales, mano de obra, herramienta, equipo, auxiliares, flete, trabajo) que suman el P.U.

## Estado actual

| Capacidad | Estado |
|-----------|--------|
| Catálogo compuestos (`es_compuesto` + componentes) | **Hecho** |
| Línea PPTO `tiene_matriz` + `componentes` (API) | **Hecho** |
| Editor de matriz **en captura** del PPTO (modal Manual) | **Hecho** (Plus) |
| Snapshot al elegir compuesto del catálogo | **Hecho** |
| Switch documento `config_mostrar_matriz_costos` | **Hecho** (default off) |
| Desglose en preview / enlace público / PDF | **Hecho** |
| Plantillas + duplicar con matriz | **Hecho** |
| **Tipos de insumo Opus** en componentes | **Hecho** (MVP) |
| Select tipo con buscador (`app-matriz-insumo-tipo-select`) | **Hecho** |
| Filtro listado `matriz_tipo` (segmento 1 fila) | **Hecho** |
| Doble clic en P.U. → convertir a matriz | **Hecho** |
| Capítulos / niveles | **Roadmap** Fase 2 |
| Totales por tipo en PDF / % sobre MO / explosión | **Roadmap** Fase 3 |

### Categorías

| Ámbito | Valores |
|--------|---------|
| Concepto de catálogo (comercial) | `producto` \| `servicio` |
| **Componente de matriz** (Opus) | `material` \| `mano_obra` \| `herramienta` \| `equipo` \| `auxiliar` \| `flete` \| `trabajo` |

`producto` / `servicio` **no** son tipos de matriz. Legacy se coacciona: `producto`→`material`, `servicio`→`mano_obra`. Default al sembrar: `material`.

API: `categoriasValidas()` vs `categoriasInsumoValidas()` / `coerceCategoriaInsumo()`.  
Front: `MatrizInsumoTipo`, `MATRIZ_INSUMO_TIPOS`, `app-matriz-insumo-tipo-select`.

### Dónde se arma la matriz

1. Catálogo compuestos — cada componente con tipo Opus.
2. Captura PPTO — switch Precio fijo / Matriz; P.U. = Σ.
3. Doble clic en **P. UNITARIO** → siembra componente / abre editor.
4. Seleccionar compuesto del catálogo → snapshot de todos los componentes tipados.

### Visibilidad del desglose

Flag `config_mostrar_matriz_costos` (default off): preview / PDF / enlace público.

### Filtro de listado

- API: `matriz_tipo=<insumo>`.
- UI: un **segmento de una sola fila** (`matriz-tipo-filter`): solo el activo resaltado.

## Motor

`PresupuestoMatrizCalculoService` — importe = cant × P.U.; P.U. padre = Σ; coerce de categorías; sync línea/plantilla/catálogo; anti-ciclo.

## Tablas

`presupuesto_*_concepto_componentes.categoria` (string 20) — sin migración de columna.

## Roadmap

```
Fase 0–1 (hecha) → matriz + tipado Opus + filtro + dblclick P.U.
Fase 2 → capítulos
Fase 3 → totales por tipo en PDF, %, explosión
```

## Front de referencia

| Pieza | Ruta |
|-------|------|
| Tipos | `models/presupuesto-matriz-costos.model.ts` |
| Select tipado + búsqueda | `components/matriz-insumo-tipo-select/` |
| Editor | `components/presupuesto-matriz-costos-editor/` |
| Filtro listado | `components/presupuesto-filters/` |
| Doble clic P.U. | `presupuesto-page-modals.convertirConceptoAMatrizPorPrecio` |
