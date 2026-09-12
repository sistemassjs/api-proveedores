# Plantilla genérica de importación de catálogo (NEXPROV)

**Versión plantilla:** 1.1 (propuesta de estandarización)  
**Base documentada:** Lineamiento GestiónPlus / NEXPROV — 28 ago 2026  
**API actual:** `CatalogoImportPlantilla` v1.0 (`2026-09-09`) + `CSVImportProductValidator::getExpectedHeaders()`  
**Muestras analizadas:** `app-proveedores/.data/productos_*.csv`

Ejemplo CSV: [plantilla-importacion-ejemplo-v1.1.csv](./plantilla-importacion-ejemplo-v1.1.csv)

---

## 1. Qué hay hoy en `.data`

Todos los CSV de prueba usan el mismo encabezado mínimo (10 columnas):

```text
codigo,producto,descripcion,marca,categoria,subcategoria,unidad_medida,precio,precio_mayoreo,precio_menudeo
```

Eso cubre solo identificación comercial básica + 3 precios. **No** incluye Familia, Tipo, presentación/conversiones, disponibilidad, tags, propiedades dinámicas ni control (`activo`).

La API **ya acepta** muchas más columnas opcionales; las muestras de `.data` simplemente no las usan.

---

## 2. Dos taxonomías (no confundir)

| En plantilla CSV | Significado | Destino |
|------------------|-------------|---------|
| `categoria` / `subcategoria` | Clasificación **local del proveedor** (Grupo/Categoría del lineamiento) | `categorias` del proveedor |
| `familia` / `subfamilia` | Taxonomía **OPUS global** (Familia del lineamiento) | `catalogo_familias` / `catalogo_subfamilias` (FK opcionales; homologación no bloqueante) |

Jerarquía del lineamiento → NEXPROV:

| Lineamiento | Columna / origen |
|-------------|------------------|
| Empresa / Proveedor | Automático (`proveedor_id` de la ruta); no va en el CSV |
| Familia | `familia` (+ `subfamilia` OPUS) |
| Grupo / Categoría | `categoria` |
| Subcategoría | `subcategoria` |
| Producto | `producto` |

---

## 3. Formato genérico recomendado (orden oficial)

Encabezados **exactos** (minúsculas, sin acentos en el nombre de columna).  
Campos vacíos permitidos si no aplican.  
No eliminar columnas opcionales de la plantilla solo porque un proveedor no las use.

### 3.1 Obligatorias (importación sencilla)

| # | Columna CSV | Lineamiento | Notas |
|---|-------------|-------------|--------|
| 1 | `codigo` | Código / SKU | Obligatorio API |
| 2 | `producto` | Producto | Nombre comercial |
| 3 | `marca` | Marca | Obligatorio API hoy |
| 4 | `categoria` | Grupo / Categoría | Local proveedor |
| 5 | `unidad_medida` | Unidad de venta | Catálogo global de unidades |
| 6 | `precio` | Precio | → `precio_base` |

### 3.2 Recomendadas / opcionales ya soportadas por la API (v1.0)

| Columna CSV | Lineamiento | Estado API |
|-------------|-------------|------------|
| `descripcion` | Descripción | OK |
| `subcategoria` | Subcategoría | OK |
| `precio_mayoreo` | Precio mayoreo | OK |
| `precio_menudeo` | Precio menudeo | OK |
| `familia` | Familia (OPUS) | OK (homologación) |
| `subfamilia` | Subfamilia OPUS | OK |
| `tipo` | Tipo (Producto / Servicio / Renta) | OK |
| `modelo` | Modelo | OK |
| `codigo_fabricante` | Código fabricante | OK |
| `codigo_barras` | Código de barras | OK |
| `presentacion` | Presentación | OK |
| `cantidad_contenida` | Cantidad contenida | OK |
| `unidad_contenido` | Unidad de contenido | OK |
| `unidad_base` | Unidad base de cálculo | OK |
| `factor_conversion` | Factor de conversión | OK |
| `disponibilidad` | Disponibilidad | OK |
| `tiempo_entrega` | Tiempo de entrega | OK |
| `url_producto` | URL del producto | OK |
| `tags` | Palabras clave / Tags | OK |
| `PropiedadN_Clave` / `PropiedadN_Valor` | Propiedades dinámicas | OK (N = 1…∞ en validación; Excel puede traer 5–10) |

### 3.3 Columnas a **agregar** a la plantilla (gap vs lineamiento)

Estas del lineamiento **aún no** están como columnas de import CSV (aunque parte exista en BD o en satélites):

| Columna CSV propuesta | Lineamiento | Prioridad | Notas de implementación |
|----------------------|-------------|-----------|-------------------------|
| `moneda` | Moneda (MXN, USD…) — **Sí** | Alta | Hoy no hay columna en `productos` ni en el validador. Definir default `MXN` o campo nuevo. |
| `activo` | Activo / Inactivo — **Sí** | Alta | Ya existe en BD (`activo`); **falta** aceptarlo en CSV/job. Valores: `1/0`, `true/false`, `si/no`. |
| `imagen_principal` | Imagen principal | Media | Existe en BD; **falta** en headers de import (URL o path). |
| `ficha_tecnica` | Ficha técnica (PDF/URL) | Media | Existe `producto_documentos`; mapear URL/path en import a documento tipo `ficha_tecnica`. |
| `cantidad_minima` | Cantidad mínima / umbral mayoreo | Media | No existe en modelo; nueva columna o propiedad comercial. |
| `vigencia_precio` | Vigencia del precio | Media | No existe; fecha ISO o texto. Requiere columna o tabla de precios. |
| `logo` | Logo empresa/marca | Baja en fila | Suele ser del **proveedor**, no por producto; omitir del CSV de productos (automático). |

**No agregar como columna fija por producto:** número ilimitado de “Propiedad11…”. Seguir con pares `PropiedadN_Clave` / `PropiedadN_Valor` en Excel y EAV en BD.

---

## 4. Encabezado completo propuesto

### 4.1 Usable ya (sin migraciones) — plantilla intermedia v1.1

```text
codigo,producto,descripcion,tipo,familia,subfamilia,categoria,subcategoria,marca,modelo,codigo_fabricante,codigo_barras,unidad_medida,presentacion,cantidad_contenida,unidad_contenido,unidad_base,factor_conversion,precio,precio_mayoreo,precio_menudeo,disponibilidad,tiempo_entrega,url_producto,tags,Propiedad1_Clave,Propiedad1_Valor,Propiedad2_Clave,Propiedad2_Valor,Propiedad3_Clave,Propiedad3_Valor,Propiedad4_Clave,Propiedad4_Valor,Propiedad5_Clave,Propiedad5_Valor
```

### 4.2 Mínimo viable (compatible con `.data` actual)

```text
codigo,producto,descripcion,marca,categoria,subcategoria,unidad_medida,precio,precio_mayoreo,precio_menudeo
```

### 4.3 Objetivo lineamiento completo (requiere gaps §3.3)

```text
codigo,producto,descripcion,tipo,familia,subfamilia,categoria,subcategoria,marca,modelo,codigo_fabricante,codigo_barras,unidad_medida,presentacion,cantidad_contenida,unidad_contenido,unidad_base,factor_conversion,precio,precio_mayoreo,precio_menudeo,moneda,cantidad_minima,vigencia_precio,disponibilidad,tiempo_entrega,imagen_principal,url_producto,ficha_tecnica,tags,activo,Propiedad1_Clave,Propiedad1_Valor,Propiedad2_Clave,Propiedad2_Valor,Propiedad3_Clave,Propiedad3_Valor,Propiedad4_Clave,Propiedad4_Valor,Propiedad5_Clave,Propiedad5_Valor,Propiedad6_Clave,Propiedad6_Valor,Propiedad7_Clave,Propiedad7_Valor,Propiedad8_Clave,Propiedad8_Valor,Propiedad9_Clave,Propiedad9_Valor,Propiedad10_Clave,Propiedad10_Valor
```

---

## 5. Matriz lineamiento ↔ NEXPROV

| Campo lineamiento | En plantilla CSV | En BD / import hoy | Acción |
|-------------------|------------------|--------------------|--------|
| Empresa / Proveedor | — (ruta) | `proveedor_id` | Ninguna |
| Tipo | `tipo` | Sí | Documentar valores: `producto`, `servicio`, `renta` |
| Familia | `familia` | OPUS FK | Usar en plantilla |
| Grupo / Categoría | `categoria` | Local | Ya en `.data` |
| Subcategoría | `subcategoria` | Local | Ya en `.data` |
| Producto | `producto` | `nombre` | OK |
| Descripción | `descripcion` | Sí | OK |
| Código / SKU | `codigo` | `codigo_interno` | OK |
| Código fabricante | `codigo_fabricante` | Sí | Agregar a plantilla descargable |
| Código barras | `codigo_barras` | Sí | Agregar a plantilla |
| Marca | `marca` | Sí | OK |
| Modelo | `modelo` | Sí | Agregar a plantilla |
| Unidad de venta | `unidad_medida` | Global | OK |
| Presentación | `presentacion` | Sí | Agregar |
| Cantidad contenida | `cantidad_contenida` | Sí | Agregar |
| Unidad contenido | `unidad_contenido` | Sí | Agregar |
| Unidad base | `unidad_base` | Sí | Agregar |
| Factor conversión | `factor_conversion` | Sí | Agregar |
| Precio | `precio` | `precio_base` | OK |
| Precio mayoreo / menudeo | columnas | Sí | OK |
| Moneda | `moneda` | **No** | **Agregar** (campo + validación) |
| Cantidad mínima | `cantidad_minima` | **No** | **Agregar** o diferir a precios |
| Vigencia precio | `vigencia_precio` | **No** | **Agregar** o diferir |
| Disponibilidad | `disponibilidad` | Sí | Agregar a plantilla |
| Tiempo entrega | `tiempo_entrega` | Sí | Agregar |
| Logo | — | Proveedor | Fuera del CSV producto |
| Imagen principal | `imagen_principal` | BD sí / CSV no | **Agregar** a import |
| URL producto | `url_producto` | Sí | Agregar a plantilla |
| Ficha técnica | `ficha_tecnica` | docs sí / CSV no | **Agregar** mapeo |
| Tags | `tags` | Sí | Agregar a plantilla |
| Activo | `activo` | BD sí / CSV no | **Agregar** a import |
| Fecha última actualización | — | timestamps / audit | Automático |
| Propiedades N | `PropiedadN_*` | EAV | Incluir 5–10 en Excel |

---

## 6. Validaciones (alineadas al §7 del lineamiento)

Ya parcialmente cubiertas por `csv-import` (preview → confirm → audit):

- Encabezados vs plantilla versionada (`plantilla_version` / `plantilla_fecha`)
- Columnas desconocidas → warning (ignoradas)
- Duplicados / skip / update vía `import_options`
- Resumen antes de confirmar (preview)
- Auditoría en `ImportAudit`

Pendiente endurecer según lineamiento:

- Validar `moneda` cuando haya precio
- Advertir familia/categoría incompletas
- Diff explícito de campos que se actualizarán
- Valores canónicos de `tipo` y `disponibilidad`

---

## 7. Checklist vs documento maestro

| Punto de control | Criterio | Estado NEXPROV |
|------------------|----------|----------------|
| Clasificación | Empresa → Familia → Categoría → Subcategoría → Producto | Parcial (dual local + OPUS); documentar en UI |
| Campos universales | Plantilla con campos del lineamiento | Parcial: API lista en gran parte; plantilla `.data` y download aún mínimas |
| Propiedades | Dinámicas EAV; Excel no limita BD | Hecho (EAV + `PropiedadN_*`) |
| Precios y unidades | Distintos: precio, presentación, unidad venta/base, factor | Parcial: columnas OK; falta moneda/vigencia/mínimo |
| Importación | Validar headers, tipos, duplicados, preview | Hecho en camino masivo |
| Versionado | Versión + fecha plantilla + audit | Hecho (`1.0` / `2026-09-09`); subir a **1.1** al publicar nuevas columnas |
| Extensibilidad | Nuevos giros sin rediseño | Arquitectura OK; completar gaps de columnas |

---

## 8. Recomendación de rollout

1. **Ya (sin migración):** publicar plantilla CSV/Excel con columnas de §4.1 + propiedades; regenerar ejemplos en `.data` cuando convenga.
2. **Corto plazo:** aceptar en import `activo`, `imagen_principal`, `ficha_tecnica` (mapeo a existentes).
3. **Mediano:** decidir si `moneda`, `cantidad_minima`, `vigencia_precio` son columnas universales (migración) o quedan fuera hasta tabla de precios.
4. Al publicar: subir `CatalogoImportPlantilla::VERSION` a `1.1` y nueva `FECHA`.

---

## 9. Referencias código

- Validador headers: `app/Services/CSVImport/CSVImportProductValidator.php` → `getExpectedHeaders()`
- Constantes plantilla: `app/Support/Catalogo/CatalogoImportPlantilla.php`
- Specs EAV: `producto_especificaciones` + job de import
- Contexto API: [api.md](./api.md)
