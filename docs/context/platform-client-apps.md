# Apps cliente — acceso por usuario (API)

Una sola API sirve **GestionPlus** y **NexProv**. Misma identidad `User` + `Proveedor`. El acceso a cada PWA es **por usuario** (opción B).

Amplía [platform-shared.md](./platform-shared.md). **Google OAuth multi-app: pendiente.**

## Regla

| Evento | Comportamiento |
|--------|----------------|
| Request API | Header `X-Client-App: gestion` o `nexprov`. Si falta o es inválido → **`gestion`**. |
| Registro empresa | Crea/asegura User + otorga `user_client_apps` para la app del request. Correo `/gen-pass` con nombre y URL de esa app. |
| Login / `/auth/me` / refresh | Credenciales OK **y** el user tiene esa `app_key`. Si no → 403 `sin_acceso_app`. |
| Forgot password | Solo si el user tiene acceso a la app del request; link de reset a esa URL. |
| Rutas auth | **Las mismas** (`/auth/...`). No hay rutas por app. |

No hay entitlement automático GestionPlus → NexProv.

## Fuente de verdad: tabla `users`

El flujo de **Acceso existente** / `puede_activar_app` mira **solo** `users` (email y/o teléfono del formulario).

| Dato | ¿Decide acceso multi-app? |
|------|---------------------------|
| `users.email` / `users.telefono` | **Sí** |
| `proveedores.email` / `proveedores.telefono` | **No** (solo identidad comercial / reutilizar empresa) |
| Email solo en empresa, sin fila en `users` | Alta nueva de usuario (no modal Acceso existente) |

Reutilizar empresa en registro: solo por **teléfono** o **razón social** (no por email de empresa).

El correo `/gen-pass` se envía al email del **usuario principal** si existe; si no, al email de la empresa.

## Pregunta única: acceso existente

Cuando el **usuario** ya existe en `users` pero **no tiene acceso a la app actual**, login y registro responden `403` con:

- `codigo: sin_acceso_app`
- `puede_activar_app: true`
- `requiere_password: true` si la cuenta **ya tiene contraseña** (hay que confirmarla para otorgar acceso)
- `email` / `telefono` (solo de `users`; si `users.email` no es un email válido —p. ej. teléfono histórico— no se muestra como correo)
- Mensaje unificado (ver abajo)

El front muestra **un solo** modal:

| | |
|--|--|
| Título | Acceso existente |
| Botones | **Continuar** · **Cancelar** |
| Continuar (con contraseña) | Modal **Acceso existente** con campo contraseña → login/`activar_app` validado → otorga acceso + sesión |
| Continuar (sin contraseña aún) | `activar_app: true` en registro → correo `/gen-pass` |
| Cancelar | Cierra el modal; no activa |

**Regla de seguridad:** no se otorga `user_client_apps` sin validar la contraseña si el usuario ya la tiene. En registro, `activar_app` sin password correcto → `401` `password_requerida`. En login, la contraseña ya se validó antes del grant.

### Mensaje (API / UI)

Con contraseña ya definida:

- *Detectamos una cuenta registrada con el correo {email}. Para generar el acceso a {app} confirma tu contraseña e inicia sesión.*
- (análogo con teléfono / ambos)

Sin contraseña aún (alta incompleta):

- Solo correo: *Detectamos una cuenta registrada con el correo {email}. ¿Deseas generar el acceso a {app} con esos datos e iniciar sesión?*
- Solo teléfono / ambos: mismo patrón.

Tras confirmar con contraseña válida: entra a la app. Si aún no hay contraseña: pide revisar correo `/gen-pass`.

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
| Resolver | `App\Support\ClientApp` |
| Middleware | `IdentifyClientApp` (grupo `api` en `bootstrap/app.php`) |
| Auth | `AuthController` (registro, login, me, refresh, forgot) |
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

Comportamiento:

- Asunto y cuerpo con nombre de la app (`ClientApp::name()`).
- Remitente (`From` name) = nombre de la app (no el `APP_NAME` global).
- Logo: primero archivo en `public/assets/logos/…`, si no → descarga desde URL web del front (`frontend_url` + `logo`, o `logo_url` explícito) y se embebe como data URI.
- **Colores por app** (mismas plantillas): header + CTA según `config/client_apps.php` → `mail.*` / `ClientApp::mailTheme()`.

| App | Header | CTA |
|-----|--------|-----|
| gestion | azul `#2b6cb0` | amarillo `#FFC107` |
| nexprov | verde `#00a878` | verde `#00a878` (texto blanco) |

| App | Logo local | URL web por defecto |
|-----|------------|---------------------|
| gestion | `public/assets/logos/logo-gestionplus.png` | `{APP_FRONTEND_URL}/assets/logos/logo-gestionplus.png` |
| nexprov | `public/assets/logos/logo-nexprov.png` | `{NEXPROV_FRONTEND_URL}/assets/logos/logo-nexprov.png` |

## Validadores de registro (`verificar-*`)

`existe` es **bloqueante solo para la app actual** (`X-Client-App`). El front **no** marca el campo si solo existe en otra app.

| Caso | `existe` | `puede_activar_app` |
|------|----------|---------------------|
| Dato libre | `false` | `false` |
| Ya tiene acceso a esta app | `true` | `false` |
| Existe en el sistema / otra app, sin esta app | `false` | `true` (no se marca repetido) |

- **Email / teléfono:** solo `users`. Si el dato está solo en `proveedores`, `existe_en_sistema` = `false` y no hay `puede_activar_app`.
- **Razón social (empresa):** mira si **algún** usuario de esa empresa tiene la app.

`ProveedorRegisterRequest` acepta `activar_app` (boolean) y opcional `password`. Sin `activar_app`, si el **usuario** existe sin esta app → pregunta unificada; con `activar_app: true` + password válida (si aplica) → otorga y responde token o correo.

## Contrato front

- Enviar siempre `X-Client-App`.
- GestionPlus / NexProv: interceptor + modal genérico `sin_acceso_app` **excepto** cuando `puede_activar_app` (lo maneja login/registro).
- Login y registro: modal **Acceso existente** → Continuar / Cancelar.

## Fuera de alcance (hoy)

- Google OAuth con `?app=` / state
- FCM filtrado por app
- Rutas auth diferenciadas
