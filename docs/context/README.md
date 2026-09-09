# Contexto para agentes — App Proveedores

**Fuente unica:** este directorio en `api-proveedores`. No hay documentacion de dominios en `app-proveedores`.

Este ecosistema (`api-proveedores` + `app-proveedores`) tiene **tres dominios de negocio aislados**. Cohabitan en el mismo monorepo, pero **no deben mezclarse** al implementar o refactorizar.

> **Futuro:** hay intencion de dividirlos en apps independientes. Aun no esta definido. Mientras tanto, tratar cada dominio como caso aislado.

## Los tres dominios

| Dominio | Carpeta | Que es | Que no es |
|---------|---------|--------|-----------|
| **Catalogo** | [catalogo/](./catalogo/) | Productos, categorias, marcas, unidades, stock sucursal, import CSV | No es SP ni presupuestos |
| **Solicitudes de pago** | [solicitudes-pago/](./solicitudes-pago/) | SP/SPP, facturas, comprobantes, OC a SP, empresas constructoras | No es catalogo ni presupuestos |
| **Presupuestos** | [presupuestos/](./presupuestos/) | Presupuestos multi-giro, PDF, cartera, monedas MXN/USD/EUR; cobro roadmap (Plus / pasarelas) | No es SP ni catalogo de productos |

La UI Angular se describe en cada dominio en `front.md`, pero el codigo vive en `app-proveedores`.

## Regla anti-mezcla (obligatoria)

1. **Identifica el dominio** antes de tocar codigo.
2. **No importes** modelos, servicios ni flujos de otro dominio salvo lo listado en [cross-domain.md](./cross-domain.md).
3. Si una tarea parece cruzar dominios, **pregunta o documenta** la relacion; no asumas puente (p. ej. presupuesto a SP **no existe**).
4. Auth, `proveedor_id`, ApiResponse y **gestion de usuarios/roles** son **plataforma compartida** (core), no un dominio de negocio. Ver [platform-shared.md](./platform-shared.md) y [platform-users-roles.md](./platform-users-roles.md).

## Lectura rapida por tarea

| Si trabajas en… | Empieza por |
|-----------------|-------------|
| Productos / categorias / marcas / import | `@docs/context/catalogo/overview.md` |
| SP, facturas, pagos, OC, Construcciones | `@docs/context/solicitudes-pago/overview.md` |
| Presupuestos, PDF, cartera, enlace publico | `@docs/context/presupuestos/overview.md` |
| Usuarios, roles, matriz de acceso por rol (MVP) | `@docs/context/platform-users-roles.md` |
| Auth / shell / ApiResponse / métricas plataforma / **perfil público** | `@docs/context/platform-shared.md` |
| Login social (Google / Socialite) | `@docs/context/platform-auth-socialite.md` |
| Hay relacion entre dominios? | `@docs/context/cross-domain.md` |

## Repos

| Repo | Rol |
|------|-----|
| `api-proveedores` | API Laravel + **este contexto** |
| `app-proveedores` | Angular + Ionic PWA (sin docs de dominio) |

Patrones de codigo: `NORMAS_DESARROLLO.md` (raiz del ecosistema) y `.cursor/rules/` de cada repo.