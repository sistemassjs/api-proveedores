# Catálogo de productos — Frontend

Repo: `app-proveedores`. Carpetas bajo `src/app/pages/proveedor/`:

| Carpeta | Rol |
|---------|-----|
| `producto-proveedor/` | Listado / form / detail |
| `categorias-proveedor/` | Categorías |
| `marcas-proveedor/` | Marcas |
| `unidades-proveedor/` | Unidades (**catálogo global**; UI bajo proveedor por compatibilidad) |
| `sucursales-proveedor/` | Sucursales (+ stock) |
| `import-productos/` | Flujo editable + historial → `POST productos/bulk` (localStorage; ≤~1000). **No** tocar al endurecer masivo |
| `csv-import/` | Importación masiva servidor: upload → confirm → poll status/results (`camino: csv-import-servidor`) |

Modelos compartidos: `shared/models/producto.model.ts`.

## Header del shell (obligatorio)

Las pages de productos / marcas / categorías / importación siguen `app-proveedores/.cursor/rules/front-header-appstate.mdc` (`setHeader` init+WillEnter; `clearHeader` en WillLeave).

## Gap de routing

En `proveedor-routing.module.ts` **actual** están montados perfil, dashboard, sp, presupuestos, empresas, usuarios — **no** productos/categorías/marcas/import.

Los módulos y el menú (`PROVEEDOR_CATALOG` / tipo catálogo) siguen existiendo. URLs esperadas históricamente: `/pages/proveedor/productos`, `categorias`, `import-productos`, `csv-import`, etc.

Al trabajar el front de catálogo: verificar si hay que **volver a registrar** lazy routes, no asumir que ya navegan.

**Pendiente UI:** exponer `mostrar_en_catalogo_publico` en el form de producto para publicar al picker de PPTOs.

## Picker presupuestos (consumo del catálogo)

Modal `concepto-catalogo-manual-modal`:

- Cards empresas → `GET /catalogo/empresas` (proveedores `is_proveedor_catalogo` + productos publicados).
- Productos de empresa → `GET /catalogo/empresas/{proveedor}/productos`.
- Facets OPUS → `…/productos/facets` (árbol familia→subfamilias; sin marca).
- Picker PPTOs: barra de chips de familia; al elegir una, segunda barra anidada de subfamilias.
- Detalle → `…/productos/{producto}`.
- Snapshot: `nombre`→descripcion, unidad, `precio_base`, imagen; sin FK.

## Gestión admin (reemplazo de catalogo-publico)

Pantalla: `panel-administrativo/pages/catalogo-empresas/` — ruta UI `/pages/panel-admin/catalogo-empresas`.

- Cards de empresas catálogo → productos con **multiselección** (publicar / despublicar).
  - «Seleccionar visibles» marca la página cargada; si hay más resultados, banner **Seleccionar los N** aplica el filtro completo.
- Import CSV NEXPROV vía `admin/catalogos/proveedores/{id}/csv-import`.
- Nueva empresa: `proveedores/form?catalogo=1` (flag `is_proveedor_catalogo` + **logo** en el form; multipart al API).

Menú admin: **Catálogo de empresas** (ya no «Catálogo público»).

## Feed admin `catalogo-publico` (deprecado)

Pantalla legacy `catalogo-publico/` puede seguir en el repo/ruta pero **fuera del menú**. No usar para nuevas features.
