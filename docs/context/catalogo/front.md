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
- Facets OPUS → `…/productos/facets` (familia; sin marca).
- Detalle → `…/productos/{producto}`.
- Snapshot: `nombre`→descripcion, unidad, `precio_base`, imagen; sin FK.
- Servicios: `PresupuestoCatalogoConceptosService` (`fetchEmpresasCatalogoPublico`, `fetchProductosCatalogoEmpresa`, …).

## Feed admin `catalogo-publico` (deprecado)

Pantalla admin `panel-administrativo/pages/catalogo-publico/` sigue en el repo pero **fuera del picker**. Plan de apagado completo (API + UI). No usar para nuevas features.
