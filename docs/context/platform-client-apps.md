# Dos apps cliente (identidad) — plataforma

Una sola API (`api-proveedores`) sirve **GestionPlus** y **NexProv**. Comparten `User` + `Proveedor` (misma identidad). **No es un dominio de negocio** ni una licencia de producto.

Amplía [platform-shared.md](./platform-shared.md). Login social: [platform-auth-socialite.md](./platform-auth-socialite.md).

## Qué cubre esto (hoy)

| Pieza | Comportamiento |
|-------|----------------|
| Identificar la PWA | Header `X-Client-App: gestion` o `nexprov` |
| Default | Si el header no viene (o el valor es inválido) → **`gestion`**. GestionPlus no cambia. |
| Correos / Blade | Logo y nombre de la app actual |
| Links `/gen-pass` y reset | URL del front de esa app |
| Google OAuth | `?app=` al redirect + `state=app.{key}` en el callback |
| FCM | Token etiquetado con `app_key` (mismo proyecto Firebase) |

## Qué no cubre (aplazado)

- **Entitlement** GestionPlus → NexProv (registro en una no otorga acceso a la otra).
- `is_proveedor_catalogo` **no** es licencia de app: es publicación de catálogo para el picker de presupuestos. Ver [catalogo](./catalogo/).
- No mezclar catálogo / SP / presupuestos por ser “la otra app”. Los tres dominios siguen aislados.

## Claves y fronts

| Key (`X-Client-App`) | Nombre UI | Repo front | URL local |
|----------------------|-----------|------------|-----------|
| `gestion` (default) | GestionPlus | `app-proveedores` | `APP_FRONTEND_URL` (`http://localhost:4300`) |
| `nexprov` | NexProv | `nexprov` | `NEXPROV_FRONTEND_URL` (`http://localhost:4400`) |

## Resolución en la API

1. Middleware `IdentifyClientApp` (grupo `api` en `bootstrap/app.php`).
2. Orden: `state` OAuth `app.{key}` → si no, header `X-Client-App` → si no, query `?app=` → default `gestion`.
3. Helper `App\Support\ClientApp`: `key()`, `name()`, `frontendUrl()`, `frontendPath()`, `logoRelativePath()`, `oauthState()`.
4. Catálogo: `config/client_apps.php`.

Valores desconocidos se normalizan a `gestion`.

## Front

Cada environment declara `clientApp: 'gestion' | 'nexprov'` (además de `app_name`).

- Interceptor HTTP: header `X-Client-App` en todas las llamadas a la API (login, registro, FCM, autenticadas).
- Google: `GET {API_URL}/auth/{provider}/redirect?app={clientApp}` (navegación de ventana, no pasa por el interceptor).

Sin ese header, la API trata la petición como GestionPlus.

## Correos

View composer `emails.*` inyecta `clientAppName` y `logoAppDataUri` (`EmailLogoHelper::logoClientAppDataUri()`).

Los mailables de alta / reset **guardan `appKey`** en el constructor y llaman `ClientApp::setCurrent` en `build()`, para que un `afterResponse` / cola no caiga al default.

Logos en API: `public/assets/logos/logo-gestionplus.png` y `logo-nexprov.png`.

## FCM

Misma app Firebase en ambas PWAs. Cada registro en `user_device_tokens` lleva `app_key`.

- Unique vigente: `(user_id, app_key, device_id)`.
- El mismo dispositivo puede tener un token por app.
- Al registrar, se buscan siblings **solo de esa app**.
- El envío push **aún no filtra** por `app_key` (llega a todos los tokens activos del usuario). El tag existe para filtrar después.

Migración: `database/migrations/2026_09_17_170331_add_app_key_to_user_device_tokens_table.php`.

## Variables `.env` (API)

```env
APP_FRONTEND_URL=http://localhost:4300
NEXPROV_FRONTEND_URL=http://localhost:4400
```

Opcionales: `CLIENT_APP_GESTION_NAME`, `CLIENT_APP_NEXPROV_NAME`.

`OAUTH_FRONTEND_CALLBACK` / `services.oauth.frontend_callback` quedan como fallback histórico. El callback real usa `ClientApp::frontendUrl() . '/auth/callback'`.

El **redirect URI de Google** sigue siendo uno solo (el de la API): `{APP_URL}/api/auth/google/callback`.

## Google Cloud (orígenes extra)

Añadir orígenes JS de NexProv (sin path), p. ej. `http://localhost:4400` y la URL de producción cuando exista. El callback de Google **no** cambia: es el de Laravel.

Detalle del flujo: [platform-auth-socialite.md](./platform-auth-socialite.md).
