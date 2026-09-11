# Catálogo de productos — Overview

Dominio **aislado**: inventario comercial del proveedor (productos y taxonomía local) + stock por sucursal + importación CSV + catálogos globales de apoyo (OPUS, unidades).

Hay un **segundo recurso** en este dominio, independiente del anterior: el **catálogo público** (`catalogo_publico_items`). Es un feed plano importado por **admin** (Excel/CSV con columnas `empresa` y `logo`). No usa `proveedor_id`, no toca `productos` / NextPro (`is_proveedor_catalogo`) ni el import CSV del gerente.

## Propósito

Administrar productos, categorías/subcategorías **por empresa**, marcas; asignar productos a sucursales con stock; importar por CSV. Homologar opcionalmente contra **familias/subfamilias OPUS globales** y usar **unidades de medida globales**.

**Importación:** dos caminos aislados — (1) `productos/bulk` + localStorage (editable, ≤~1000); (2) `csv-import` + job en cola (masivo ~50k, tablas temporales). No mezclar ni romper el camino 1 al endurecer el 2.

**Catálogo público:** fuente global actualizable para presupuestos (picker combinado, snapshot) y Construcc (lectura).

## Límites (qué entra)

- Producto (campos universales + precios en columnas + specs/docs/imágenes)
- Categoria (árbol por proveedor)
- CatalogoFamilia / CatalogoSubfamilia (global OPUS, FKs opcionales en producto)
- Marca (por proveedor)
- UnidadMedida (**global**)
- Pivot sucursal-producto (stock/precio local)
- Import CSV + auditorías de import (`plantilla_version` / `plantilla_fecha`)
- Consumo read-only desde admin/shared/construcc/tienda (consumidores, no dueños del diseño)
- **Catálogo público:** `CatalogoPublicoItem`, import admin, lectura autenticada

## Qué NO es este dominio

| No mezclar con | Motivo |
|----------------|--------|
| Solicitudes de pago | Sin `producto_id` en SP |
| Presupuestos | Conceptos sin FK a producto; catálogo de conceptos propio del dominio presupuestos. El picker **lee** el catálogo público y copia la línea (snapshot). |
| "Catálogo" de clientes en presupuestos | Otro significado |
| NextPro / tienda | Bandera `is_proveedor_catalogo`; no aplica al feed público |

## Estado / gap front

- **API:** implementada y activa en `gerente.php` (unidades bajo ruta proveedor pero datos globales).
- **Front:** módulos existen en disco, pero el `proveedor-routing` actual **no monta** las lazy routes de catálogo (sí hay menú/código). Tratar como *implementado / desconectado del shell de rutas actual*.
- **Catálogo público:** API + pantalla admin de import/listado; picker de presupuestos consume sugerencias combinadas.
- **Pendiente (fases posteriores / front):** UI admin familias OPUS, UI producto campos/specs/docs, plantilla Excel descargable.

## Docs del dominio

- [api.md](./api.md)
- [database.md](./database.md)
- [front.md](./front.md)

Ver también: [../cross-domain.md](../cross-domain.md)
