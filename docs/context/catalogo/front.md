# Catálogo de productos — Frontend

Repo: `app-proveedores`. Carpetas bajo `src/app/pages/proveedor/`:

| Carpeta | Rol |
|---------|-----|
| `producto-proveedor/` | Listado / form / detail |
| `categorias-proveedor/` | Categorías |
| `marcas-proveedor/` | Marcas |
| `unidades-proveedor/` | Unidades |
| `sucursales-proveedor/` | Sucursales (+ stock) |
| `import-productos/` | Flujo import + historial |
| `csv-import/` | Alternativa CSV (upload → confirm → results) |

Modelos compartidos: `shared/models/producto.model.ts`.

## Header del shell (obligatorio)

Las pages de productos / marcas / categorías / importación siguen `app-proveedores/.cursor/rules/front-header-appstate.mdc` (`setHeader` init+WillEnter; `clearHeader` en WillLeave).

## Gap de routing

En `proveedor-routing.module.ts` **actual** están montados perfil, dashboard, sp, presupuestos, empresas, usuarios — **no** productos/categorías/marcas/import.

Los módulos y el menú (`PROVEEDOR_CATALOG` / tipo catálogo) siguen existiendo. URLs esperadas históricamente: `/pages/proveedor/productos`, `categorias`, `import-productos`, `csv-import`, etc.

Al trabajar el front de catálogo: verificar si hay que **volver a registrar** lazy routes, no asumir que ya navegan.

## Catálogo público

Admin: `src/app/pages/panel-administrativo/pages/catalogo-publico/` — import Excel/CSV + listado seccionado por empresas (cards cuadradas/largas, hueco de imagen, edición de empresa y producto, filtros laterales marca/empresa/categoría, barra sticky de búsqueda+filtro, página de resultado de importación). Ruta UI: `/pages/panel-admin/catalogo-publico` (+ `/import-resultado`).

Picker presupuestos (`concepto-catalogo-manual-modal`): al añadir concepto abre en **Catálogo**. Filtros: Todos (acordeones por empresa + mis conceptos), Mis conceptos, Catálogo empresas (cards → productos).

