# Matriz de costos y aproximación a Opus

> Contexto de dominio **presupuestos**. Complementa [overview.md](./overview.md), [api.md](./api.md), [database.md](./database.md), [front.md](./front.md).  
> Decisiones del hito octubre–noviembre 2026: [hito-indirectos-matriz-anidada.md](../../planning/hito-indirectos-matriz-anidada.md).

## Premisa de producto

Se desea que GestionPlus **imite el comportamiento de Opus** al generar un presupuesto:

1. **Raíz del PPTO** — listado de partidas (hoy: líneas concepto/párrafo; roadmap: capítulos jerárquicos).
2. **Matriz de costo del concepto** — el **precio unitario** de una partida se calcula desde insumos (cantidad × costo, o % sobre mano de obra del mismo nivel) más el pie de indirectos; opcionalmente se muestra el desglose al cliente (preview / PDF / enlace público) y/o una **hoja de análisis** por compuesto.

Referencia Opus (comportamiento, no clone 1:1):

- Pantalla raíz: capítulos + conceptos con unidad, cantidad, P.U., total.
- Desglose del concepto: insumos tipados (materiales, mano de obra, herramienta, equipo, auxiliares, flete, trabajo) que suman el costo directo; sobrecostos → P.U.

## Estado actual

| Capacidad | Estado |
|-----------|--------|
| Catálogo compuestos (`es_compuesto` + componentes) | **Hecho** |
| Línea PPTO `tiene_matriz` + `componentes` (API) | **Hecho** |
| Editor de matriz **en captura** del PPTO (modal Manual) | **Hecho** (Plus) |
| Página dedicada de compuesto + breadcrumb (anidados) | **Hecho** |
| Snapshot al elegir compuesto del catálogo | **Hecho** |
| Switch documento `config_mostrar_matriz_costos` | **Hecho** (default off) |
| Desglose en preview / enlace público / PDF | **Hecho** |
| Plantillas + duplicar con matriz | **Hecho** |
| **Tipos de insumo Opus** en componentes | **Hecho** (MVP) |
| Select tipo con buscador (`app-matriz-insumo-tipo-select`) | **Hecho** |
| Filtro listado `matriz_tipo` (segmento 1 fila) | **Hecho** |
| Doble clic en P.U. → convertir a matriz | **Hecho** |
| Clave de partida / `clave_snapshot` en matriz; vacía = número de renglón | **Hecho** |
| Matriz anidada (`componentes_anidados`) | **Hecho** |
| Indirectos documento + override por matriz (general / propio) | **Hecho** |
| Indirectos ampliados: financiamiento + otros | **Hecho** |
| Cálculo `porcentaje_mo` (% sobre MO del mismo nivel) | **Hecho** |
| Cuadrillas de obra (`presupuesto_cuadrillas`) en matriz | **Hecho** |
| Hoja de análisis en PDF (`mostrar_hoja_analisis`) | **Hecho** (PDF; preview Angular aún sin paridad) |
| Tour / `data-tour` en captura de compuesto | **Hecho** |
| Capítulos / niveles en la raíz | **Roadmap** Fase 2 |
| Explosión de insumos (lista de compras de la obra) | **Roadmap** Fase 3 |
| Catálogo **Básicos** usable en el picker | **Pendiente** (tab reservado) |
| Totales consolidados por tipo de insumo en PDF (obra completa) | **Roadmap** Fase 3 |

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
2. Captura PPTO — switch Precio fijo / Matriz; P.U. = costo directo (+ pie de indirectos si aplica).
3. Página / modal de compuesto — tabla agrupada por tipo, buscador Propio / Global (/ Básicos reservado), drill-down a anidados.
4. Doble clic en **P. UNITARIO** → siembra componente / abre editor.
5. Seleccionar compuesto del catálogo → snapshot de componentes tipados (y anidados).

### Visibilidad del desglose

| Flag | Efecto |
|------|--------|
| `config_mostrar_matriz_costos` (documento, default off) | Desglose de componentes bajo la descripción en preview / PDF / enlace público |
| `mostrar_hoja_analisis` (línea con matriz, default false) | Anexa hoja(s) de análisis al final del PDF (tras cuerpo + anexos). Grupos por tipo, CD, sobrecostos, P.U. |

### Filtro de listado

- API: `matriz_tipo=<insumo>`.
- UI: un **segmento de una sola fila** (`matriz-tipo-filter`): solo el activo resaltado.

## Indirectos (pie de precio)

Porcentajes del documento (`presupuestos`):

- `indirecto_oficina_porcentaje`
- `indirecto_campo_porcentaje`
- `indirecto_financiamiento_porcentaje`
- `indirecto_utilidad_porcentaje`
- `indirecto_cargos_porcentaje`
- `indirecto_otros_porcentaje`

En la matriz (línea / catálogo / plantilla): modo **general** (usa los del documento) o **propio** (porcentajes de esa matriz; un cambio general no la toca).

Reglas:

- Costo directo = suma de importes de la matriz (redondeo por importe).
- Precio unitario de la línea = CD + pie de indirectos.
- IVA y descuento del documento se aplican **después**.
- Un renglón que apunta a otra matriz usa el **costo directo del hijo**, no su precio con indirectos (salvo matriz anidada ya editada con pie propio en el snapshot).

## Cálculo de renglón

| `calculo` | Importe |
|-----------|---------|
| `cantidad` (default) | `cantidad × precio_unitario` |
| `porcentaje_mo` | `%` sobre la suma de importes `mano_obra` del **mismo nivel** de la matriz |

Cuadrilla: componente con `presupuesto_cuadrilla_id` → cantidad × `costo_unitario` de la cuadrilla; categoría forzada a `mano_obra`. Si cambia el costo de la cuadrilla, el motor propaga el P.U. en las líneas que la usan.

## Motor

`PresupuestoMatrizCalculoService` — normaliza componentes (anidados, cuadrillas, % MO, indirectos); P.U. padre = Σ importes; coerce de categorías; sync línea / plantilla / catálogo; anti-ciclo; profundidad máx. anidada ~12.

## Tablas / columnas relevantes

| Pieza | Notas |
|-------|--------|
| `presupuesto_*_concepto_componentes.categoria` | string 20 — tipos Opus |
| `…componentes_anidados` | JSON árbol |
| `…calculo` / `…porcentaje` | `cantidad` \| `porcentaje_mo` |
| `…clave_snapshot` | clave del renglón de matriz |
| `…presupuesto_cuadrilla_id` | FK opcional a `presupuesto_cuadrillas` |
| `…indirectos` | JSON pie propio del renglón anidado (cuando aplica) |
| `presupuesto_cuadrillas` | clave, nombre, integrantes, unidad, costo_unitario |
| `mostrar_hoja_analisis` | en `presupuesto_conceptos` / plantilla conceptos |

Detalle de esquema: [database.md](./database.md).

## Roadmap

```
Fase 0–1 (hecha) → matriz + tipado Opus + filtro + dblclick P.U.
Hito anidados + indirectos (hecho) → página compuesto, breadcrumb, pie general/propio
Hito Opus 2.14 / API 1.6 (hecho) → clave, % MO, financiamiento/otros, cuadrillas, hoja análisis PDF
Fase 2 → capítulos en la raíz
Fase 3 → explosión de insumos; totales consolidados por tipo a nivel obra
Pendiente UX → catálogo Básicos; paridad hoja de análisis en preview Angular
```

## Front de referencia

| Pieza | Ruta (app-proveedores) |
|-------|------------------------|
| Tipos | `models/presupuesto-matriz-costos.model.ts` |
| Select tipado + búsqueda | `components/matriz-insumo-tipo-select/` |
| Editor | `components/presupuesto-matriz-costos-editor/` |
| Página compuesto | `pages/concepto-compuesto/` |
| Filtro listado | `components/presupuesto-filters/` |
| Doble clic P.U. | `presupuesto-page-modals.convertirConceptoAMatrizPorPrecio` |
| Cuadrillas / indirectos UI | `presupuesto-page-modals` (info general) + modal manual |
| Estilos pie Opus | `styles/presupuesto-indirectos-opus.scss` |
