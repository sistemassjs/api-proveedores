# Catálogo de productos — Overview

Dominio **aislado**: inventario comercial del proveedor (productos y taxonomía) + stock por sucursal + importación CSV.

Hay un **segundo recurso** en este dominio, independiente del anterior: el **catálogo público** (`catalogo_publico_items`). Es un feed plano importado por **admin** (Excel/CSV con columnas `empresa` y `logo`). No usa `proveedor_id`, no toca `productos` / NextPro (`is_proveedor_catalogo`) ni el import CSV del gerente.

## Propósito

Administrar productos, categorías (y subcategorías), marcas, unidades de medida; asignar productos a sucursales con stock; importar por CSV.

**Catálogo público:** fuente global actualizable para presupuestos (picker combinado, snapshot) y Construcc (lectura).

## Límites (qué entra)

- Producto, Categoria, Marca, UnidadMedida
- Pivot sucursal-producto (stock/precio local)
- Import CSV + auditorías de import
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

- **API:** implementada y activa en `gerente.php`.
- **Front:** módulos existen en disco, pero el `proveedor-routing` actual **no monta** las lazy routes de catálogo (sí hay menú/código). Tratar como *implementado / desconectado del shell de rutas actual*.
- **Catálogo público:** API + pantalla admin de import/listado; picker de presupuestos consume sugerencias combinadas.

## Docs del dominio

- [api.md](./api.md)
- [database.md](./database.md)
- [front.md](./front.md)

Ver también: [../cross-domain.md](../cross-domain.md)
