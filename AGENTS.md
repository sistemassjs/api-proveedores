# Guia de agentes — api-proveedores

API Laravel del ecosistema proveedores. El **contexto operativo de negocio** vive solo en este repo.

## Contexto (fuente unica)

Empezar por: [`docs/context/README.md`](docs/context/README.md)

| Dominio | Ruta |
|---------|------|
| Catalogo de productos | `docs/context/catalogo/` |
| Solicitudes de pago | `docs/context/solicitudes-pago/` |
| Presupuestos | `docs/context/presupuestos/` |

- Plataforma (auth, proveedor_id, ApiResponse): `docs/context/platform-shared.md`
- Login social OAuth / Socialite: `docs/context/platform-auth-socialite.md`
- Usuarios / roles / matriz MVP (core): `docs/context/platform-users-roles.md`
- Relaciones entre dominios: `docs/context/cross-domain.md`

**Regla:** tres dominios aislados; no mezclar. Ver `.cursor/rules/domains-aislados.mdc`.

La UI (Angular) esta en `app-proveedores`; la documentacion de front por dominio esta en `docs/context/*/front.md` **aqui**, no en el repo front.

## Stack

Laravel · PHP 8 · MySQL · Sanctum · ApiResponse (`status`, `code`, `message`, `data`, `errors`)

## Reglas Cursor (`.cursor/rules/`)

| Archivo | Notas |
|---------|--------|
| `00-general.mdc` | Siempre |
| `domains-aislados.mdc` | Siempre — tres dominios |
| `api-controllers.mdc` | Controllers |
| `api-resources.mdc` | Resources |
| `api-models.mdc` | Models |
| `api-requests.mdc` | Form Requests |
| `api-database.mdc` | Migraciones; **checklist drop/rename** → alinear modelo (+ requests/resources) |
| `api-mail-notifications.mdc` | Mail / Notifications |
| `api-routes.mdc` | Rutas segmentadas |

## Rutas

Segmentadas por rol en `routes/segmented/`. Prefijo tipico: `proveedores/{proveedor}/recurso`. Validar `tieneAccesoAProveedor` / middleware `proveedor.access`.