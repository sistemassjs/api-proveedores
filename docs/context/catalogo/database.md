# Catálogo de productos — Base de datos

## Models

| Model | Rol |
|-------|-----|
| `Producto` | Ítem del catálogo (scoped por `proveedor_id`) |
| `Categoria` | Árbol **por proveedor** (`parent_id` / children); categoría y subcategoría en producto |
| `CatalogoFamilia` / `CatalogoSubfamilia` | Taxonomía **global OPUS** (sin `proveedor_id`); FKs opcionales en producto |
| `Marca` | Marca del proveedor |
| `UnidadMedida` | Unidades **globales** (sin `proveedor_id`); unique `nombre` |
| `Sucursal` | Sucursales; pivot con producto |
| `ProductoImagen` / `ProductoEspecificacion` / `ProductoDocumento` | Satélites (EAV specs, galería, fichas/docs) |
| `ImportAudit` / `ImportValidationCache` | Import CSV (+ `plantilla_version` / `plantilla_fecha`); masivo usa tablas temporales + job cola `imports` |
| `CatalogoPublicoItem` | Feed plano global (`catalogo_publico_items`); unique `(empresa, codigo)` |

## Clasificación dual

```
[Global OPUS]
  catalogo_familias → catalogo_subfamilias
  (producto.familia_id / subfamilia_id, nullable; se puede homologar en import)

[Por proveedor]
  categorias (padre) → categorias hijas (subcategoría)
  (producto.categoria_id / subcategoria_id)
```

## Unidades

`unidad_medidas` es catálogo **global**. `producto.unidad_medida_id` = unidad de venta. Opcionales: `unidad_contenido_id`, `unidad_base_id`, `presentacion`, `cantidad_contenida`, `factor_conversion`.

## Precios

Se mantienen columnas en `productos`: `precio_base`, `precio_mayoreo`, `precio_menudeo` (sin tabla de precios).

## Campos universales adicionales en `productos`

`tipo`, `codigo_fabricante`, `codigo_barras`, `disponibilidad`, `tiempo_entrega`, `url_producto`, `tags` (json), más presentación/conversión y FKs OPUS anteriores.

## Catálogo público (feed)

Tabla plana, **sin** `proveedor_id`. `empresa` y `logo` vienen del Excel; `imagen` es la foto del producto. Columnas típicas de import: codigo, producto/nombre, descripcion, marca, categoria, subcategoria, unidad, modelo, empresa, logo, imagen, precio, precio_mayoreo, precio_menudeo. Extras → JSON `propiedades`.

## Relaciones

```
[Global]
  CatalogoFamilia → CatalogoSubfamilia
  UnidadMedida

Proveedor
  ├── Producto, Categoria, Marca, Sucursal
Sucursal ↔ Producto (pivot: stock_local, precio_local, activo)
Producto → Marca, UnidadMedida, Categoria, Subcategoria
Producto → Familia?, Subfamilia? (OPUS)
Producto → ProductoEspecificacion, ProductoImagen, ProductoDocumento
```

## Seeders

- `UnidadMedidaSeeder` — unidades globales
- `CatalogoOpusFamiliasSeeder` — listado OPUS 2025 completo (`database/data/catalogo_opus_familias.json`: 182 familias, 606 subfamilias)
