# Segmentación de push por app (GestionPlus / NexProv)

Misma API (`api-proveedores`), dos clientes: **GestionPlus** (`gestion`) y **NexProv** (`nexprov`).
Los tokens FCM se etiquetan con `app_key` y el envío push solo usa tokens de la(s) app(s) destino.

Relacionado: [notificaciones-reverb-firebase.md](./notificaciones-reverb-firebase.md), [plan-ataque-notificaciones.md](./plan-ataque-notificaciones.md).

---

## Problema

Sin `app_key`, un usuario con ambas apps registraba dos tokens y `notify()` enviaba FCM a **todos** → push cruzado.

## Solución

1. Columna `user_device_tokens.app_key` (`gestion` | `nexprov`, default `gestion`).
2. Unique `(user_id, device_id, app_key)`.
3. Registro desde cada front con `X-Client-App` + body `app_key`.
4. Envío FCM filtrado por `fcmTargetAppKeys()` (trait `TargetsClientApp`).
5. Payload incluye `app_key`; el front ignora si no coincide con `environment.clientApp`.

**Tokens legacy:** migración los deja en `gestion`. No hace falta borrarlos; al abrir cada app se re-registran.

---

## Backend

| Pieza | Rol |
|-------|-----|
| Migración `2026_09_22_120000_add_app_key_to_user_device_tokens_table` | Columna + backfill + unique |
| `UserDeviceToken` | `app_key`, scopes `byAppKey` / `byAppKeys` |
| `User::fcmTokensForApps()` / `hasActiveDeviceTokensForApps()` | Filtrado |
| `DeviceTokenController` | Persiste `app_key`; siblings solo misma app |
| `TargetsClientApp` | Destino + `sendFcmToNotifiable` |
| `FcmService::sendToNotifiableApps()` | Un lote por `app_key` |
| `FcmChannel` | Usa apps destino; ignora `toFcm()` void (ya envió) |

### Destino por notificación

Por defecto: `ClientApp::key()` (header `X-Client-App`, o `gestion`).

Override fluent:

```php
$user->notify((new SomeNotification(...))->forClientApps('nexprov'));
// o ambas:
$user->notify((new SomeNotification(...))->forClientApps('gestion', 'nexprov'));
```

Webhooks/cron sin header → default `gestion` (dominio GestionPlus).

### Auth / ProveedorEmpresa / Usuario

Suenan en la **app que disparó** (`X-Client-App`). Dominio catálogo (NexProv) y dominio GestionPlus (SP, OC, presupuesto, etc.) se fijan con `forClientApps` en el disparo.

**Verificación de email** (`updateUserData` → mail → `verifyUpdatedEmail`): el link `GET /api/auth/verificar-email-token` no envía `X-Client-App`. Por eso:

1. Al emitir el token se guarda `app_key` en cache junto a `user_id` / `email`.
2. Al verificar: `CuentaVerificadaNotification` → `forClientApps($appKey)` y redirect a `ClientApp::frontendUrlFor($appKey)`.
3. Tokens de cache antiguos sin `app_key` → fallback `gestion`.

### Deploy API

```bash
php artisan migrate
```

---

## GestionPlus (`app-proveedores`)

- `environment.clientApp = 'gestion'`
- `fcm.service.ts` / `push-notification.service.ts`: envían `X-Client-App`, `app_key`, `device_id` prefijado
- `notification.service` / initializer: omiten payload con `app_key` distinto

## NexProv (`nexprov`)

Igual con `environment.clientApp = 'nexprov'`.

---

## Checklist

1. Solo GestionPlus → push solo ahí.
2. Solo NexProv → push solo ahí (tras un login/init FCM).
3. Usuario con ambas → evento de Gestion no suena en NexProv (y al revés).
4. Logout en una app no desactiva el token de la otra.
5. Tokens previos siguen vivos como `gestion` hasta re-registro.
6. Cambio de email desde NexProv → push `CuentaVerificada` y redirect a NexProv (y lo mismo para GestionPlus).

---

## Fuera de alcance (fase 2)

- Canales Reverb separados por app (`User.{id}.{app_key}`). Hoy el front puede filtrar por `app_key` en el payload broadcast si viene informado.
