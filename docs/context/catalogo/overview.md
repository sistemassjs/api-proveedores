# Catálogo de productos — Overview

Dominio **aislado**: inventario comercial del proveedor (productos y taxonomía local) + stock por sucursal + importación CSV + catálogos globales de apoyo (OPUS, unidades).

## Propósito

Administrar productos, categorías/subcategorías **por empresa**, marcas; asignar productos a sucursales con stock; importar por CSV. Homologar opcionalmente contra **familias/subfamilias OPUS globales** y usar **unidades de medida globales**.

**Importación:** dos caminos aislados — (1) `productos/bulk` + localStorage (editable, ≤~1000); (2) `csv-import` + job en cola (masivo ~50k, tablas temporales). No mezclar ni romper el camino 1 al endurecer el 2.

**Publicación para presupuestos:** bandera `mostrar_en_catalogo_publico` en `productos`. Solo proveedores `is_proveedor_catalogo` con productos publicados aparecen en el picker de PPTOs (`GET /catalogo/empresas`). Mecanismo: **lectura + snapshot** (sin FK). Detalle en [../cross-domain.md](../cross-domain.md).

## Límites (qué entra)

- Producto (campos universales + precios + specs/docs/imágenes + `mostrar_en_catalogo_publico`)
- Categoria (árbol por proveedor)
- CatalogoFamilia / CatalogoSubfamilia (global OPUS, FKs opcionales en producto)
- Marca (por proveedor)
- UnidadMedida (**global**)
- Pivot sucursal-producto (stock/precio local)
- Import CSV + auditorías de import
- Lectura autenticada para picker PPTOs (`/catalogo/empresas`)
- Consumo read-only desde shared/construcc/tienda (consumidores)

## Qué NO es este dominio

| No mezclar con | Motivo |
|----------------|--------|
| Solicitudes de pago | Sin `producto_id` en SP |
| Catálogo de conceptos PPTOs | Tabla `presupuesto_catalogo_conceptos` — dominio presupuestos |
| "Catálogo" de clientes en presupuestos | Otro significado |
| Feed admin `catalogo_publico_items` | **Deprecado / plan de apagado**; sustituido por productos publicados |

## Estado / gap front

- **API:** CRUD gerente + `/catalogo/empresas` (picker) + `/admin/catalogo-empresas` (gestión admin).
- **Front productos (gerente):** módulos existen; `proveedor-routing` puede no montar lazy routes — ver [front.md](./front.md).
- **Admin catálogo empresas:** UI `/pages/panel-admin/catalogo-empresas` (import CSV + multiselect publicar). Reemplaza el menú del feed `catalogo-publico`.
- **Picker PPTOs:** usa `/catalogo/empresas`.
- **Pendiente:** bandera en form producto gerente; apagado físico del módulo legacy `catalogo-publico`; UI admin familias OPUS.

## Docs del dominio

- [api.md](./api.md)
- [database.md](./database.md)
- [front.md](./front.md)
- [plantilla-importacion.md](./plantilla-importacion.md)
- Ejemplo CSV: [plantilla-importacion-ejemplo-v1.1.csv](./plantilla-importacion-ejemplo-v1.1.csv)

Ver también: [../cross-domain.md](../cross-domain.md)
