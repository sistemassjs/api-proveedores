# Catálogo de productos — API

Repo: `api-proveedores`. Núcleo en `routes/segmented/gerente.php` bajo `proveedores/{proveedor}/…`.

## Prefijos gerente

| Prefijo | Controller |
|---------|------------|
| `{proveedor}/productos` | `ProveedorProductoController` (+ logo) |
| `{proveedor}/productos/bulk` | `POST` importación editable ≤1000 (front `import-productos` + localStorage). **No** usar para ~50k |
| `{proveedor}/productos/{producto}/documentos` | `ProveedorProductoDocumentoController` |
| `{proveedor}/categorias` | `ProveedorCategoriaController` (+ subcategorías, logo, counts) |
| `{proveedor}/marcas` | `ProveedorMarcaController` (+ logo) |
| `{proveedor}/unidades` | `ProveedorUnidadMedidaController` — **catálogo global** (rutas bajo proveedor por compatibilidad; ya no filtra por `proveedor_id`) |
| `{proveedor}/sucursales/{sucursal}/productos` | `SucursalProductoController` (asignar / desasignar / stock) |
| `{proveedor}/csv-import` | `CsvImportController` — importación **masiva servidor** (~50k; temp tables + `CSVImportJob`) |

### Dos caminos de importación (no mezclar)

| Camino | Front | API | Límite práctico |
|--------|-------|-----|-----------------|
| Editable / pequeño | `import-productos` + localStorage | `POST …/productos/bulk` | ≤ ~1000 filas |
| Masivo servidor | `csv-import` | `upload` → `confirm` → `status`/`results` | ~50 MB / ~50k filas |

Flujo masivo:

1. `POST …/csv-import/upload` — max 50 MB; respuesta incluye `camino: csv-import-servidor`, `audit_id`, `preview_token`, plantilla.
2. `POST …/csv-import/confirm` — solo si `estado=preview` (409 si ya confirmado); despacha `CSVImportJob` en cola `imports` con `afterResponse()`.
3. Poll `GET …/csv-import/status/{audit}` / `results/{audit}`.

**Ops (local):** `QUEUE_CONNECTION=database`, `QUEUE_RETRY_AFTER=1900`, worker `queue:work --queue=imports,… --timeout=1800` (`composer dev`). Con `sync`, el job corre en el request HTTP y puede timeout. El `confirm` despacha a la cola de inmediato (sin `afterResponse`) para que `artisan serve` no retrase el JSON y el front no se quede en “Confirmando…”. En imports grandes el progreso del job se escribe con menos frecuencia; el log live se quitó de `composer dev` (usar `storage/logs/laravel.log`) para no congelar la consola.

Controllers tipan `Proveedor $proveedor` (route model binding).

Middleware de recurso: `proveedor.producto`, `proveedor.categoria`, `proveedor.marca`, `proveedor.unidad` (solo resuelve la unidad; no valida ownership), `proveedor.sucursal` (según ruta).

### Producto — campos / specs

- Store/update aceptan campos universales opcionales (`tipo`, `familia_id`, `subfamilia_id`, presentación/conversión, `tags`, etc.).
- Array `especificaciones[]` (`atributo`|`clave`, `valor`, `unidad`, `orden`) se sincroniza en create/update.
- Precios siguen en columnas: `precio_base`, `precio_mayoreo`, `precio_menudeo`.

### Import CSV (plantilla v1.0)

- `plantilla_version` / `plantilla_fecha` en `ImportAudit` (`CatalogoImportPlantilla`).
- Columnas opcionales: `familia`, `subfamilia`, más campos universales; `PropiedadN_Clave` / `PropiedadN_Valor` → EAV.
- Homologación OPUS no bloqueante: match por `familia`/`subfamilia` o, si faltan, por `categoria`/`subcategoria` local (`CatalogoOpusHomologacionService`).
- Columna `precio` → `precio_base`.

## Catálogos globales OPUS

| Prefijo | Rol |
|---------|-----|
| `admin/catalogo-familias` | CRUD admin (+ subfamilias anidadas) |
| `catalogo-familias` (shared) | Lectura autenticada |
| `tienda/catalogo-familias` | Lectura (filtros tienda) |

## Otros consumidores (mismo dominio de datos)

| Archivo | Uso |
|---------|-----|
| `admin.php` | CRUD admin global / catálogos / OPUS / catálogo público |
| `shared.php` | Búsqueda / tienda / lectura OPUS |
| `public.php` | Indexes read-only |
| `construcc.php` | Búsqueda productos para Construcc (**no** es lógica SP) |

## Servicios

- `app/Services/CSVImport/` — processor, validators, export
- `app/Services/Catalogo/CatalogoOpusHomologacionService` — match import → FKs OPUS
- `CatalogoPublicoImportService` — feed público (no mezclar con CSV gerente)

## Catálogo público (feed admin)

| Prefijo | Controller |
|---------|------------|
| `admin/catalogo-publico` | `AdminCatalogoPublicoController` |
| `catalogo-publico` (shared, auth) | `CatalogoPublicoItemController` |

Import: upsert por `(empresa, codigo)`.

## Postman

| Archivo | Contenido |
|---------|-----------|
| `postman/Catalogo.postman_collection.json` | Colección v2.1: gerente, shared/tienda, admin OPUS y catálogo público |
| `postman/catalogo-json-schemas.json` | JSON Schema (bodies + resources + ApiResponse) |

Variables útiles: `base_url`, `sanctum_token`, `proveedor_id` (demo Ferreteria LM: `19`).
