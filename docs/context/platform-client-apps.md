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

## Pregunta única: acceso existente

Cuando la cuenta/empresa **ya existe en el sistema** pero **no tiene acceso a la app actual**, login y registro responden `403` con:

- `codigo: sin_acceso_app`
- `puede_activar_app: true`
- `email` / `telefono` (si aplica)
- Mensaje unificado (ver abajo)

El front muestra **un solo** modal:

| | |
|--|--|
| Título | Acceso existente |
| Botones | **Continuar** · **Cancelar** |
| Continuar | Reenvía la petición con `activar_app: true` → otorga acceso + login (o correo `/gen-pass` si falta contraseña) |
| Cancelar | Cierra el modal; no activa |

### Mensaje (API / UI)

- Solo correo: *Detectamos una cuenta registrada con el correo {email}. ¿Deseas generar el acceso a {app} con esos datos e iniciar sesión?*
- Solo teléfono: *Detectamos una cuenta registrada con el teléfono {telefono}. ¿Deseas generar el acceso a {app} con esos datos e iniciar sesión?*
- Ambos: *Detectamos una cuenta registrada con el correo {email} y el teléfono {telefono}. ¿Deseas generar el acceso a {app} con esos datos e iniciar sesión?*

Tras confirmar no se vuelve a preguntar: entra a la app (o pide revisar correo si aún no hay contraseña).

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

Opcionales: `CLIENT_APP_GESTION_NAME`, `CLIENT_APP_NEXPROV_NAME`.

## Validadores de registro (`verificar-*`)

`existe` es **bloqueante solo para la app actual** (`X-Client-App`). El front **no** marca el campo si solo existe en otra app.

| Caso | `existe` | `puede_activar_app` |
|------|----------|---------------------|
| Dato libre | `false` | `false` |
| Ya tiene acceso a esta app | `true` | `false` |
| Existe en el sistema / otra app, sin esta app | `false` | `true` (no se marca repetido) |

- **Razón social (empresa):** mira si **algún** usuario de esa empresa tiene la app.
- **Email / teléfono de un usuario:** mira el acceso de ese usuario.
- **Email / teléfono solo en proveedor:** misma regla de empresa.

`ProveedorRegisterRequest` acepta `activar_app` (boolean). Sin ese flag, si la empresa/cuenta existe sin esta app → pregunta unificada; con `activar_app: true` → otorga y responde token o correo.

## Contrato front

- Enviar siempre `X-Client-App`.
- GestionPlus / NexProv: interceptor + modal genérico `sin_acceso_app` **excepto** cuando `puede_activar_app` (lo maneja login/registro).
- Login y registro: modal **Acceso existente** → Continuar / Cancelar.

## Fuera de alcance (hoy)

- Google OAuth con `?app=` / state
- FCM filtrado por app
- Rutas auth diferenciadas
