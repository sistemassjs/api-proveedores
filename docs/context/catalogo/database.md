# Catálogo de productos — Base de datos

## Models

| Model | Rol |
|-------|-----|
| `Producto` | Ítem del catálogo (scoped por `proveedor_id`) |
| `Categoria` | Árbol (`parent_id` / children); categoría y subcategoría en producto |
| `Marca` | Marca del proveedor |
| `UnidadMedida` | Unidades |
| `Sucursal` | Sucursales; pivot con producto |
| `ProductoImagen` / `ProductoEspecificacion` | Satélites |
| `ImportAudit` / `ImportValidationCache` | Import CSV |
| `CatalogoPublicoItem` | Feed plano global (`catalogo_publico_items`); unique `(empresa, codigo)` |

## Catálogo público (feed)

Tabla plana, **sin** `proveedor_id`. `empresa` y `logo` (logo de la empresa) vienen del Excel; `imagen` es la foto del producto. Columnas típicas de import: codigo, producto/nombre, descripcion, marca, categoria, subcategoria, unidad, modelo, empresa, logo, imagen, precio, precio_mayoreo, precio_menudeo. Extras → JSON `propiedades`.


## Relaciones

```
Proveedor
  ├── Producto, Categoria, Marca, UnidadMedida, Sucursal
Sucursal ↔ Producto (pivot: stock_local, precio_local, activo)
Producto → Marca, UnidadMedida, Categoria, Subcategoria
```

Campos típicos producto: sku, codigo_interno, precios, stock global, activo/estatus, imagen/logo.
