# Catálogo de productos — API

Repo: `api-proveedores`. Núcleo en `routes/segmented/gerente.php` bajo `proveedores/{proveedor}/…`.

## Prefijos gerente

| Prefijo | Controller |
|---------|------------|
| `{proveedor}/productos` | `ProveedorProductoController` (+ logo) |
| `{proveedor}/categorias` | `ProveedorCategoriaController` (+ subcategorías, logo, counts) |
| `{proveedor}/marcas` | `ProveedorMarcaController` (+ logo) |
| `{proveedor}/unidades` | `ProveedorUnidadMedidaController` |
| `{proveedor}/sucursales/{sucursal}/productos` | `SucursalProductoController` (asignar / desasignar / stock) |
| `{proveedor}/csv-import` | `CsvImportController` (+ job `CSVImportJob`) |

Middleware de recurso: `proveedor.producto`, `proveedor.categoria`, `proveedor.marca`, `proveedor.unidad`, `proveedor.sucursal` (según ruta).

## Otros consumidores (mismo dominio de datos)

| Archivo | Uso |
|---------|-----|
| `admin.php` | CRUD admin global / catálogos |
| `shared.php` | Búsqueda / tienda |
| `public.php` | Indexes read-only |
| `construcc.php` | Búsqueda productos para Construcc (**no** es lógica SP) |

## Servicios import

`app/Services/CSVImport/` — processor, validators, export (catálogo comercial / NextPro). **No** usar para el catálogo público.

## Catálogo público (feed admin)

| Prefijo | Controller |
|---------|------------|
| `admin/catalogo-publico` | `AdminCatalogoPublicoController` (`POST /import`, GET, GET empresas, GET facets, PATCH ítem, PATCH empresas) |
| `catalogo-publico` (shared, auth) | `CatalogoPublicoItemController` (lectura: index, show, `GET empresas`) |
| `{proveedor}/presupuestos/presupuesto-catalogo-conceptos/sugerencias` | Picker combinado (lectura; dominio presupuestos). Query: `origen`, `search`, `empresa` |

Import: `CatalogoPublicoImportService`. Upsert por `(empresa, codigo)`.

Admin extra:

- `GET /admin/catalogo-publico/empresas` — agrupado por empresa (respeta `search`, `marca`, `categoria`, `empresa`).
- `GET /admin/catalogo-publico/facets` — valores distintos de empresa, marca y categoría.
- `PATCH /admin/catalogo-publico/empresas` — `{ empresa_actual, empresa?, logo? }` actualiza todas las filas de esa empresa (en transacción; no permite choque de `empresa+codigo`).

