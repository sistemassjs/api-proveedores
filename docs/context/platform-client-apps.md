# Apps cliente — acceso por usuario (API)

Una sola API sirve **GestionPlus** y **NexProv**. Misma identidad `User` + `Proveedor`. El acceso a cada PWA es **por usuario** (opción B).

Amplía [platform-shared.md](./platform-shared.md). Google OAuth multi-app: ver [platform-auth-socialite.md](./platform-auth-socialite.md) (`?app=` + state).

## Regla

| Evento | Comportamiento |
|--------|----------------|
| Request API | Header `X-Client-App: gestion` o `nexprov`. Si falta o es inválido → **`gestion`**. |
| Registro empresa | Crea/asegura User + otorga `user_client_apps` para la app del request. Correo `/gen-pass` con nombre y URL de esa app. |
| Login / `/auth/me` / refresh | Credenciales OK **y** el user tiene esa `app_key`. Si no → 403 `sin_acceso_app`. |
| Google OAuth | `?app=` en redirect → state firmado → `grantClientApp(app)` → callback a `frontend_url` de esa app. |
| Forgot password | Solo si el user tiene acceso a la app del request; link de reset a esa URL. |
| Rutas auth | **Las mismas** (`/auth/...`). No hay rutas por app. |

No hay entitlement automático GestionPlus → NexProv.

## Fuente de verdad: tabla `users`

El flujo de **confirmar acceso** mira **solo** `users` (email y/o teléfono del formulario).

| Dato | ¿Decide acceso multi-app? |
|------|---------------------------|
| `users.email` / `users.telefono` | **Sí** |
| `proveedores.email` / `proveedores.telefono` | **No** (solo identidad comercial / reutilizar empresa en registro) |
| Email solo en empresa, sin fila en `users` | Alta / validación de empresa como hoy; **no** abre el modal de confirmar acceso |

Reutilizar empresa en registro: solo por **teléfono** o **razón social** (no por email de empresa).

## Confirmar acceso (modal con marca de la app origen)

Cuando el **usuario** ya existe en `users` pero **no tiene acceso a la app actual** (destino), login y registro responden `403` con payload que el front usa para un **único modal**.

### Payload `errors` (API decide marca y copy)

| Campo | Descripción |
|-------|-------------|
| `codigo` | `sin_acceso_app` |
| `puede_activar_app` | `true` → abrir modal (interceptor no muestra modal genérico) |
| `requiere_password` | Si la cuenta ya tiene contraseña |
| `app_origen` | App más antigua en `user_client_apps` distinta a la actual |
| `app_destino` | App del header `X-Client-App` |
| `email` / `telefono` | Solo de `users` (email inválido/numérico no se muestra como correo) |
| `forgot_password_url` | `{frontend_url de origen}/auth/recuperar-password` |
| `ui` | `title`, `subtitle`, `cta`, `cancel`, `forgot_password_label` |
| `brand` | `name`, `logo_url`, `header`, `cta`, `cta_text` (colores de `client_apps` / mail theme de **origen**) |

`message` de la respuesta = `ui.subtitle` (compatibilidad).

### Modal (front)

Componente `ConfirmarAccesoAppModal` (Gestion + NexProv):

| Elemento | Fuente |
|----------|--------|
| Logo / colores | Solo **logo** de app origen (UI genérica, sin colores de marca) |
| Título | `ui.title` (p. ej. “Cuenta en GestionPlus”) |
| Identidad | Solo email y/o teléfono del user |
| Contraseña | Si `requiere_password` |
| CTA | Generar acceso a la app **destino** / Enviar correo |
| ¿Olvidaste tu contraseña? | `forgot_password_url` (app **origen**); sin Google en este modal |

### Acciones

| Acción | Comportamiento |
|--------|----------------|
| Cancelar | Cierra; no otorga |
| Generar acceso (+ password) | `POST /auth/login` con identificador + password + `activar_app: true` + header de la app **destino** |
| Enviar correo (sin password) | Registro con `activar_app: true` → `/gen-pass` |

**Regla de seguridad:** no se otorga `user_client_apps` sin validar contraseña si el usuario ya la tiene (`401` `password_requerida` en registro si falta).

Login y registro usan el **mismo** modal.

## Persistencia

Tabla `user_client_apps`:

- `user_id` (FK users)
- `app_key` (`gestion` \| `nexprov` | futuras)
- unique `(user_id, app_key)`

Modelo: `App\Models\UserClientApp`.  
User: `clientApps()`, `hasClientApp()`, `grantClientApp()`.

Migración: backfill de `gestion` a todos los users existentes.

## Código API

| Pieza | Ubicación |
|-------|-----------|
| Config | `config/client_apps.php` |
| Resolver | `App\Support\ClientApp` (`nameFor`, `frontendPathFor`, `logoWebUrl`, `mailTheme`) |
| Middleware | `IdentifyClientApp` (grupo `api` en `bootstrap/app.php`) |
| Auth | `AuthController` → `payloadConfirmarAccesoApp()` |
| Mails | `CompletaRegistro*Mail`, `PasswordResetMail` (guardan `appKey`) |

## Variables `.env`

```env
APP_FRONTEND_URL=http://localhost:4300
NEXPROV_FRONTEND_URL=http://localhost:4400
```

Opcionales: `CLIENT_APP_GESTION_NAME`, `CLIENT_APP_NEXPROV_NAME`, `CLIENT_APP_GESTION_LOGO_URL`, `CLIENT_APP_NEXPROV_LOGO_URL`.

## Correos de auth (branding por app)

Solo estos mails usan la marca de la app del request (`appKey`):

- `CompletaRegistroProveedorMail`
- `CompletaRegistroUsuarioMail`
- `PasswordResetMail`

| App | Header | CTA |
|-----|--------|-----|
| gestion | azul `#2b6cb0` | amarillo `#FFC107` |
| nexprov | verde `#00a878` | verde `#00a878` (texto blanco) |

## Validadores de registro (`verificar-*`)

`existe` es **bloqueante solo para la app actual** (`X-Client-App`). El front **no** marca el campo si solo existe en otra app.

| Caso | `existe` | `puede_activar_app` |
|------|----------|---------------------|
| Dato libre | `false` | `false` |
| Ya tiene acceso a esta app | `true` | `false` |
| Existe en el sistema / otra app, sin esta app | `false` | `true` (no se marca repetido) |

- **Email / teléfono:** solo `users`.
- **Razón social (empresa):** mira si **algún** usuario de esa empresa tiene la app.

## Contrato front

- Enviar siempre `X-Client-App`.
- Login y registro: modal **ConfirmarAccesoApp** con payload `ui`/`brand` del API.
- Interceptor: no modal genérico si `puede_activar_app`.
- Login: botón **Continuar con {app hermana}** (mismo bloque que Google). En Gestion → NexProv; en NexProv → GestionPlus. Abre el modal con marca de la hermana (`editable_identity`) y hace `login` + `activar_app`. Config: `environment.siblingClientApp`.
- Google: `loginWithProvider` → `/auth/google/redirect?app={environment.clientApp}` (ver [platform-auth-socialite.md](./platform-auth-socialite.md)).

## Fuera de alcance (hoy)

- Google dentro del modal Confirmar acceso (el modal sigue sin Google a propósito)
- FCM filtrado por app
- Rutas auth diferenciadas por path
- Redirect a página de la app origen en el flujo password (opción B); hoy el modal vive en la PWA actual con marca que manda la API
