# Producto — validaciones create/update (Angular)

Fuente de verdad API: `ProductoStoreRequest`, `ProductoUpdateRequest`, trait `MapsProductoCatalogoUniversales`.

Endpoints (gerente):

| Acción | Método | Path |
|--------|--------|------|
| Create | `POST` | `/proveedores/{proveedor}/productos` |
| Update | `PUT`/`PATCH` | `/proveedores/{proveedor}/productos/{producto}` |

## Respuesta de error de validación (422)

```json
{
  "success": false,
  "message": "Datos de entrada inválidos",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "nombre": ["El nombre es obligatorio."],
    "familia_id": ["La familia es obligatoria cuando se indica subfamilia."]
  }
}
```

- `errors` es un mapa **campo → string[]** (Laravel `MessageBag`).
- Mostrar cada mensaje bajo el control del form (`errors.nombre[0]`, etc.).
- Si hay varios campos, listar todos (toast/modal resumen + errores por campo).
- Claves anidadas de specs: `especificaciones.0.atributo`, `especificaciones.0.valor`, `tags.0`.

## Comportamientos transversales

| Tema | Comportamiento |
|------|----------------|
| Taxonomía local | `categoria_id` / `subcategoria_id` — árbol del proveedor |
| Taxonomía OPUS | `familia_id` / `subfamilia_id` — **campos aparte**; en CRUD **no** se auto-rellenan desde categoría |
| Subcategoría | Si se envía valor, **debe** enviarse también `categoria_id` coherente (padre) |
| Subfamilia | Si se envía valor, **debe** enviarse también `familia_id` coherente |
| Limpiar FK/opcional | Enviar `null` (no omitir la key si se quiere borrar en update) |
| Precios vacíos | `""` / `"null"` / espacios → API normaliza a `null` |
| PPTOs | Si falta unidad/precio en el ítem, el usuario los completa en el modal de línea |

---

# 1. Create producto

**Body:** objeto completo (campos omitidos = ausentes/null según reglas).  
**Required absolutos:** `nombre`, `codigo_interno`.

## 1.1 Campos y reglas

### Identidad (obligatorios)

| Campo | Tipo | Validators Angular sugeridos | Restricciones API |
|-------|------|------------------------------|-------------------|
| `nombre` | string | `Validators.required`, `maxLength(100)` | required, string, max:100 |
| `codigo_interno` | string | `Validators.required`, `maxLength(50)` | required, string, max:50 |

### Opcionales comerciales / taxonomía local

| Campo | Tipo | Validators Angular | Restricciones API |
|-------|------|--------------------|-------------------|
| `descripcion` | string \| null | `maxLength(255)` | nullable, string, max:255 |
| `marca_id` | number \| null | — | nullable, integer, exists:marcas; **debe ser del proveedor** |
| `unidad_medida_id` | number \| null | — | nullable, integer, exists:unidad_medidas |
| `categoria_id` | number \| null | required si hay `subcategoria_id` | nullable, integer, exists; del proveedor; **nivel 0** |
| `subcategoria_id` | number \| null | — | nullable, integer, exists; del proveedor; con `parent_id`; hija de `categoria_id` si ambos vienen |
| `precio_base` | number \| null | `min(0)` | nullable, numeric, min:0 |
| `precio_mayoreo` | number \| null | `min(0)` | nullable, numeric, min:0 |
| `precio_menudeo` | number \| null | `min(0)` | nullable, numeric, min:0 |

### OPUS (opcionales, independientes de categoría)

| Campo | Tipo | Validators Angular | Restricciones API |
|-------|------|--------------------|-------------------|
| `familia_id` | number \| null | required si hay `subfamilia_id` | nullable, integer, exists:catalogo_familias |
| `subfamilia_id` | number \| null | — | nullable, integer, exists; debe pertenecer a `familia_id` si ambos vienen |

### Universales opcionales

| Campo | Restricciones |
|-------|----------------|
| `tipo` | nullable; `producto` \| `servicio` \| `renta` |
| `modelo` | nullable, string, max:60 |
| `codigo_fabricante` | nullable, string, max:100 |
| `codigo_barras` | nullable, string, max:100 |
| `presentacion` | nullable, string, max:255 |
| `cantidad_contenida` | nullable, numeric, min:0 |
| `unidad_contenido_id` | nullable, integer, exists:unidad_medidas |
| `unidad_base_id` | nullable, integer, exists:unidad_medidas |
| `factor_conversion` | nullable, numeric, min:0 |
| `disponibilidad` | nullable, string, max:50 |
| `tiempo_entrega` | nullable, string, max:100 |
| `url_producto` | nullable, string, max:500 |
| `tags` | nullable, array; cada ítem string max:100 |
| `mostrar_en_catalogo_publico` | nullable, boolean (default BD `false`) |
| `especificaciones` | nullable, array (ver abajo) |
| `proveedor_id` | sometimes; lo fija la ruta — no enviar desde el form |

### `especificaciones[]` (si se envía el array)

| Campo | Regla |
|-------|--------|
| `atributo` | required_with lista; string max:255 (también acepta `clave` en persistencia) |
| `clave` | nullable, string max:255 |
| `valor` | required_with lista; string |
| `unidad` | nullable, string max:50 |
| `orden` | nullable, integer, min:0 |

## 1.2 Mensajes de error por campo (create)

Usar estos textos en el front (espejo API) o mapear `errors[campo]`.

| Key Laravel | Mensaje |
|-------------|---------|
| `nombre.required` | El nombre es obligatorio. |
| `nombre.string` | El nombre debe ser texto. |
| `nombre.max` | El nombre no puede superar 100 caracteres. |
| `codigo_interno.required` | El código interno es obligatorio. |
| `codigo_interno.string` | El código interno debe ser texto. |
| `codigo_interno.max` | El código interno no puede superar 50 caracteres. |
| `descripcion.string` | La descripción debe ser texto. |
| `descripcion.max` | La descripción no puede superar 255 caracteres. |
| `unidad_medida_id.integer` | La unidad de medida debe ser un identificador numérico. |
| `unidad_medida_id.exists` | La unidad de medida seleccionada no es válida. |
| `categoria_id.required` | La categoría es obligatoria cuando se indica subcategoría. |
| `categoria_id.integer` | La categoría debe ser un identificador numérico. |
| `categoria_id.exists` | La categoría seleccionada no es válida. |
| *(closure)* `categoria_id` | La categoría no pertenece al proveedor. |
| *(closure)* `categoria_id` | La categoría debe estar en el nivel 0 (categoría padre). |
| `subcategoria_id.integer` | La subcategoría debe ser un identificador numérico. |
| `subcategoria_id.exists` | La subcategoría seleccionada no es válida. |
| *(closure)* `subcategoria_id` | La subcategoría no pertenece al proveedor. |
| *(closure)* `subcategoria_id` | La subcategoría seleccionada no es válida o no pertenece a una categoría padre. |
| *(closure)* `subcategoria_id` | La subcategoría no pertenece a la categoría indicada. |
| `marca_id.integer` | La marca debe ser un identificador numérico. |
| `marca_id.exists` | La marca seleccionada no es válida. |
| *(closure)* `marca_id` | La marca seleccionada no pertenece a este proveedor. |
| `precio_base.numeric` | El precio base debe ser un número. |
| `precio_base.min` | El precio base no puede ser negativo. |
| `precio_mayoreo.numeric` | El precio de mayoreo debe ser un número. |
| `precio_mayoreo.min` | El precio de mayoreo no puede ser negativo. |
| `precio_menudeo.numeric` | El precio de menudeo debe ser un número. |
| `precio_menudeo.min` | El precio de menudeo no puede ser negativo. |
| `tipo.in` | El tipo debe ser producto, servicio o renta. |
| `familia_id.required` | La familia es obligatoria cuando se indica subfamilia. |
| `familia_id.exists` | La familia OPUS seleccionada no es válida. |
| `familia_id.integer` | La familia OPUS debe ser un identificador numérico. |
| `subfamilia_id.exists` | La subfamilia OPUS seleccionada no es válida. |
| `subfamilia_id.integer` | La subfamilia OPUS debe ser un identificador numérico. |
| *(closure)* `subfamilia_id` | La subfamilia no pertenece a la familia indicada. |
| `modelo.max` | El modelo no puede superar 60 caracteres. |
| `codigo_fabricante.max` | El código de fabricante no puede superar 100 caracteres. |
| `codigo_barras.max` | El código de barras no puede superar 100 caracteres. |
| `presentacion.max` | La presentación no puede superar 255 caracteres. |
| `cantidad_contenida.numeric` / `.min` | La cantidad contenida debe ser un número. / … no puede ser negativa. |
| `unidad_contenido_id.exists` | La unidad de contenido seleccionada no es válida. |
| `unidad_base_id.exists` | La unidad base seleccionada no es válida. |
| `factor_conversion.numeric` / `.min` | El factor de conversión debe ser un número. / … no puede ser negativo. |
| `disponibilidad.max` | La disponibilidad no puede superar 50 caracteres. |
| `tiempo_entrega.max` | El tiempo de entrega no puede superar 100 caracteres. |
| `url_producto.max` | La URL del producto no puede superar 500 caracteres. |
| `tags.array` | Los tags deben enviarse como lista. |
| `tags.*.string` | Cada tag debe ser texto. |
| `tags.*.max` | Cada tag no puede superar 100 caracteres. |
| `especificaciones.array` | Las especificaciones deben enviarse como lista. |
| `especificaciones.*.atributo.required_with` | Cada especificación requiere un atributo. |
| `especificaciones.*.atributo.max` | El atributo de la especificación no puede superar 255 caracteres. |
| `especificaciones.*.valor.required_with` | Cada especificación requiere un valor. |
| `especificaciones.*.unidad.max` | La unidad de la especificación no puede superar 50 caracteres. |
| `especificaciones.*.orden.integer` / `.min` | El orden… debe ser un entero. / … no puede ser negativo. |
| `mostrar_en_catalogo_publico.boolean` | El indicador de catálogo público debe ser verdadero o falso. |

## 1.3 UX recomendada (create)

| Escenario | UI |
|-----------|-----|
| Falta nombre o código | Error **inline** en el campo; no abrir modal |
| Subcategoría sin categoría / subfamilia sin familia | Inline en el campo hijo + disable del select hijo hasta elegir padre |
| Marca/categoría de otro proveedor (exists/closure) | Inline o modal: “selección inválida, elige otra del catálogo” |
| Precio negativo | Inline |
| Specs incompletas | Inline por fila de la tabla de specs |
| Varios errores 422 | Toast/modal con resumen + destacar campos |

## 1.4 Ejemplo mínimo create

```json
{
  "nombre": "Cemento CPC 30",
  "codigo_interno": "CEM-001"
}
```

## 1.5 Ejemplo create con taxonomías

```json
{
  "nombre": "Cemento CPC 30",
  "codigo_interno": "CEM-001",
  "descripcion": "Saco 50 kg",
  "marca_id": 12,
  "unidad_medida_id": 3,
  "categoria_id": 40,
  "subcategoria_id": 41,
  "familia_id": 2,
  "subfamilia_id": 8,
  "precio_base": 185.5,
  "tipo": "producto",
  "mostrar_en_catalogo_publico": true,
  "tags": ["obra", "cemento"],
  "especificaciones": [
    { "atributo": "Peso", "valor": "50", "unidad": "kg", "orden": 0 }
  ]
}
```

---

# 2. Update producto

**Body parcial** (PATCH semántico vía Form Request `sometimes`): **solo se actualizan las keys enviadas**.

- No reenvíes el objeto completo si no cambió: evita pisar datos.
- Categoría y familia son **independientes**: cambiar una no modifica la otra.
- Para **borrar** un opcional (`marca_id`, `descripcion`, precios, etc.): envía la key con `null`.
- `especificaciones`: si la key viene, se **reemplaza** el set completo; si no viene, no se tocan.
- No enviar `proveedor_id` (se ignora/descarta).

## 2.1 Campos y reglas

Misma tipificación y mensajes que create, con estas diferencias:

| Campo | Regla update |
|-------|----------------|
| Todos | Prefijo `sometimes`: solo validan si la key está presente |
| `nombre` | Si viene → required (no vacío), max 100 |
| `codigo_interno` | Si viene → required, max 50 |
| `descripcion` | Si viene → nullable, max 255 |
| `marca_id`, `unidad_medida_id`, `categoria_id`, `subcategoria_id` | Si vienen → nullable + mismas reglas de exists/ownership |
| Precios | Si vienen → nullable, numeric, min:0 (`""` → null) |
| `familia_id` / `subfamilia_id` | Si `subfamilia_id` tiene valor → exige `familia_id` en el mismo body |
| `categoria_id` / `subcategoria_id` | Si `subcategoria_id` tiene valor → exige `categoria_id` en el mismo body |

**Implicación Angular:** al cambiar solo subcategoría/subfamilia, incluir también el ID del padre en el payload (aunque no haya “cambiado” en la UI).

## 2.2 Mensajes de error (update)

Los mismos strings de la tabla §1.2. Keys idénticas (`nombre.required` si mandas `nombre: ""`, etc.).

## 2.3 Ejemplos update

Solo precio:

```json
{ "precio_base": 200 }
```

Limpiar marca y descripción:

```json
{ "marca_id": null, "descripcion": null }
```

Cambiar OPUS (enviar familia + subfamilia juntos):

```json
{ "familia_id": 2, "subfamilia_id": 8 }
```

Cambiar categoría local (enviar categoría + subcategoría juntos si hay sub):

```json
{ "categoria_id": 40, "subcategoria_id": 41 }
```

Reemplazar specs:

```json
{
  "especificaciones": [
    { "atributo": "Color", "valor": "Gris", "orden": 0 }
  ]
}
```

## 2.4 UX recomendada (update)

| Escenario | UI |
|-----------|-----|
| Guardado parcial OK | Toast éxito; no recargar form completo si el resource devuelto actualiza el modelo |
| 422 en un campo | Inline en ese control |
| Intento de guardar subfamilia sin familia | Bloquear en cliente antes del POST |
| Conflicto ownership (marca/categoría de otro proveedor) | Modal + limpiar select |

---

## Referencias código

- `app/Http/Requests/Producto/ProductoStoreRequest.php`
- `app/Http/Requests/Producto/ProductoUpdateRequest.php`
- `app/Http/Requests/Producto/Concerns/MapsProductoCatalogoUniversales.php`
- `app/Http/Controllers/ProveedorProductoController.php`
- Contexto API: [api.md](./api.md)
