# Guía de implementación: notificaciones (Laravel Reverb + Firebase FCM)

Documento de análisis extraído de **api-proveedores** (backend Laravel) y **app-proveedores** (frontend Angular/Ionic). Sirve para replicar el mismo esquema en otra aplicación.

No hay paquete `kreait/laravel-firebase`. FCM se envía con HTTP v1 y un service account. Reverb usa el protocolo compatible con Pusher.

---

## 1. Arquitectura (tres canales de entrega)

Un mismo evento de negocio dispara hasta tres entregas:

| Canal | Tecnología | Cuándo se usa | Quién lo consume |
|-------|------------|---------------|------------------|
| **broadcast** | Laravel Reverb (WebSocket, protocolo Pusher) | App abierta / pestaña activa | Laravel Echo |
| **database** | Tabla `notifications` de Laravel | Persistencia, campana, polling | REST `GET /api/notifications` |
| **fcm** | Firebase Cloud Messaging HTTP v1 | App en background, cerrada o móvil | SW / Capacitor / SO |

Opcional: **mail** si el usuario tiene email válido.

```
Evento de negocio (orden, pago, presupuesto…)
        │
        ▼
$user->notify(new XxxNotification(...))   // ShouldBroadcastNow
        │
        ├── broadcast  → Reverb → Echo.private('App.Models.User.{id}')
        ├── database   → INSERT notifications
        ├── mail       → (si email válido)
        └── fcm        → FcmService → FCM v1 → dispositivo
```

**Regla de UX en el front de este proyecto:** si Reverb está conectado, FCM en foreground se ignora (Reverb es la fuente in-app). FCM cubre background y el caso en que Reverb esté caído. Deduplicación con `notification_correlation_id`.

---

## 2. Dependencias

### Backend (Composer)

```json
"laravel/reverb": "^1.6",
"pusher/pusher-php-server": "^7.2",
"predis/predis": "^2.0"
```

No se usa SDK oficial de Firebase en PHP. El JWT hacia Google se firma con `openssl_*`.

Instalación típica:

```bash
composer require laravel/reverb pusher/pusher-php-server
php artisan install:broadcasting   # genera config/reverb.php, broadcasting, Echo
php artisan notifications:table
php artisan migrate
```

Proceso Reverb (obligatorio en runtime):

```bash
php artisan reverb:start
# o, en este proyecto: composer dev  (serve + queue + reverb)
```

### Frontend (npm)

```json
"laravel-echo": "^1.19.0",
"pusher-js": "^8.4.0",
"firebase": "^12.4.0",
"@capacitor/push-notifications": "^7.0.3"
```

En este proyecto el front es Angular 19 + Ionic 8 (no Vite). La config vive en `src/environments/environment*.ts`, no en `VITE_*`.

---

## 3. Configurar Reverb en el backend

### 3.1 Variables de entorno

Usar **`BROADCAST_CONNECTION`** (Laravel 11+). `BROADCAST_DRIVER` está obsoleto.

```env
BROADCAST_CONNECTION=reverb

# Credenciales de la “app” Reverb (key pública; secret solo backend)
REVERB_APP_ID=cambiar
REVERB_APP_KEY=cambiar
REVERB_APP_SECRET=cambiar

# Host/puerto que Laravel usa para PUBLICAR eventos hacia Reverb
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Host/puerto donde ESCUCHA el proceso reverb:start
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

PUSHER_APP_CLUSTER=mt1
```

Producción (TLS / proxy inverso):

```env
REVERB_HOST=api.ejemplo.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

Nginx (o equivalente) debe exponer el puerto de Reverb al cliente (`wss://api.ejemplo.com:443`) y el API Laravel aparte (`https://api.ejemplo.com/gestion/api`). **No** mezclar la ruta REST con el WebSocket.

Opcionales de `config/reverb.php`:

| Variable | Default | Uso |
|----------|---------|-----|
| `REVERB_SERVER` | `reverb` | Driver del servidor |
| `REVERB_SERVER_PATH` | `''` | Path del WS |
| `REVERB_MAX_REQUEST_SIZE` | `10000` | Tamaño máximo request |
| `REVERB_SCALING_ENABLED` | `false` | Multi-nodo vía Redis |
| `REVERB_APP_PING_INTERVAL` | `60` | Ping |
| `REVERB_APP_ACTIVITY_TIMEOUT` | `30` | Timeout actividad |
| `REVERB_APP_MAX_MESSAGE_SIZE` | `10000` | Tamaño mensaje |

### 3.2 `config/broadcasting.php`

Conexión `reverb` con **driver `pusher`** (protocolo Pusher, servidor Reverb):

```php
'default' => env('BROADCAST_CONNECTION', 'null'),

'connections' => [
    'reverb' => [
        'driver' => 'pusher',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'host' => env('REVERB_HOST', '127.0.0.1'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
            'useTLS' => false, // en prod alinear con REVERB_SCHEME === 'https'
        ],
    ],
],
```

Si `BROADCAST_CONNECTION` queda en `null` o `log`, Echo nunca recibe eventos.

### 3.3 Auth de canales privados

Hay que registrar `Broadcast::routes` con Sanctum y CORS.

En este proyecto:

- `BroadcastServiceProvider` llama `Broadcast::routes()`.
- `routes/segmented/notifications.php` vuelve a registrar:

```php
Broadcast::routes(['middleware' => ['auth:sanctum']]);
```

El front usa:

```
POST {API_URL}/broadcasting/auth
Authorization: Bearer {token Sanctum}
Accept: application/json
```

Ejemplo local: `http://localhost:8088/api/broadcasting/auth`.

CORS debe incluir el path:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],
```

Registrar el provider:

```php
// bootstrap/providers.php
App\Providers\BroadcastServiceProvider::class,
```

Y cargar `routes/channels.php` (en Laravel 11+ también vía `bootstrap/app.php` → `channels:`).

### 3.4 Definición de canales (`routes/channels.php`)

```php
// Público (cualquiera puede suscribirse)
Broadcast::channel('public-notifications', function () {
    return true;
});

// Privado por usuario — canal por defecto de las Notification de Laravel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Privado por recurso (aquí: proveedor)
Broadcast::channel('proveedor.{proveedorId}', function ($user, $proveedorId) {
    return $user->tieneAccesoAProveedor((int) $proveedorId);
});
```

**Canal productivo principal:** `private-App.Models.User.{id}`.  
Si `broadcastOn()` de la Notification retorna `[]`, Laravel usa ese canal automáticamente.

No hay canales presence.

---

## 4. Configurar Firebase (FCM) en el backend

### 4.1 Consola Firebase (una sola vez)

1. Crear proyecto en [Firebase Console](https://console.firebase.google.com/).
2. Activar **Cloud Messaging**.
3. Descargar **Service account** (JSON) → *Project settings → Service accounts → Generate new private key*.
4. Guardarlo **fuera del git**, por ejemplo:

   `storage/app/firebase/service-account.json`

5. Anotar:
   - `project_id` (campo del JSON)
   - `messagingSenderId` (Project settings → Cloud Messaging)
6. Para **Web Push**: generar certificado VAPID (*Cloud Messaging → Web Push certificates*). La clave pública va al front.

### 4.2 Variables de entorno backend

```env
# Ruta RELATIVA a storage_path()
FIREBASE_CREDENTIALS=app/firebase/service-account.json
FCM_PROJECT_ID=tu-proyecto-firebase
FCM_SENDER_ID=123456789012
```

`config/services.php`:

```php
'fcm' => [
    'credentials' => env('FIREBASE_CREDENTIALS', 'app/firebase/service-account.json'),
    'project_id' => env('FCM_PROJECT_ID'),
    'sender_id' => env('FCM_SENDER_ID'),
],
```

`FcmService` resuelve el archivo así:

```php
storage_path(config('services.fcm.credentials'));
// → storage/app/firebase/service-account.json
```

### 4.3 Cómo se envía (sin SDK)

`app/Services/FcmService.php`:

1. Lee el JSON del service account.
2. Firma un JWT RS256 con scope `https://www.googleapis.com/auth/firebase.messaging`.
3. POST `https://oauth2.googleapis.com/token` (grant `jwt-bearer`).
4. POST `https://fcm.googleapis.com/v1/projects/{projectId}/messages:send` con Bearer del access token.
5. Si FCM responde `404 UNREGISTERED`, borra el token de `user_device_tokens`.

Payload Android/iOS que usa este proyecto:

- Android: `priority: high`, `channel_id: app_proveedores_notifications`, sound default.
- APNS: sound, badge 1, `content-available`, `mutable-content`.
- `data`: **todos los valores casteados a string** (FCM lo exige).

La API v1 **no acepta arrays de tokens**; se itera token a token.

### 4.4 Canal Laravel `fcm`

Registrar en `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Notification;
use App\Channels\FcmChannel;

Notification::extend('fcm', function ($app) {
    return $app->make(FcmChannel::class);
});
```

`FcmChannel` espera que `toFcm()` **devuelva un array**:

```php
return [
    'notification' => ['title' => '...', 'body' => '...'],
    'data' => [ /* strings */ ],
];
```

Luego llama `FcmService::sendToTokens($notifiable->fcm_tokens, ...)`.

**Patrón a replicar (el correcto):** `toFcm(): array`.  
En este repo varias notificaciones de dominio hacen `toFcm(): void` y llaman a `FcmService` por dentro; el canal luego loguea “Payload inválido”. El push igual sale, pero no copies ese patrón.

### 4.5 Tabla y modelo de tokens

Migración `user_device_tokens`:

- `user_id` FK
- `token` unique (string 255)
- `platform` enum `ios|android|web`
- `device_id`, `device_name`
- `metadata` json
- `last_used_at`, `is_active`
- unique `(user_id, device_id)`

En `User`:

```php
public function deviceTokens(): HasMany { ... }
public function getFcmTokensAttribute(): array
{
    return $this->activeDeviceTokens()
        ->recentlyUsed(30) // solo usados en 30 días
        ->pluck('token')
        ->toArray();
}
```

`via()` típico:

```php
$via = ['broadcast', 'database'];
if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
    $via[] = 'mail';
}
if (method_exists($notifiable, 'deviceTokens')
    && $notifiable->deviceTokens()->where('is_active', true)->exists()) {
    $via[] = 'fcm';
}
return $via;
```

### 4.6 Endpoints de tokens (auth Sanctum)

| Método | Ruta | Uso |
|--------|------|-----|
| `POST` | `/api/device-tokens` | Registrar/actualizar |
| `GET` | `/api/device-tokens` | Listar (sin exponer el token crudo en listados) |
| `POST` | `/api/device-tokens/deactivate-current` | Logout |
| `DELETE` | `/api/device-tokens/{tokenId}` | Desactivar |
| `POST` | `/api/device-tokens/cleanup` | Expirados |
| `POST` | `/api/device-tokens/test` | Test (solo local/dev) |

Body de registro:

```json
{
  "token": "<fcm-token>",
  "platform": "web",
  "device_id": "web-<uuid>",
  "device_name": "Mozilla/5.0 ...",
  "metadata": { "user_agent": "...", "language": "es" }
}
```

Lógica de `store`: busca por token global o por `(user_id, device_id)`; si existe, actualiza (puede reasignar de usuario) y reactiva; si no, crea. Desactiva tokens hermanos obsoletos del mismo dispositivo.

---

## 5. Clase Notification (contrato a copiar)

Implementar `ShouldBroadcastNow` (síncrono; no depende de la cola de broadcast).

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class EjemploNotification extends Notification implements ShouldBroadcastNow
{
    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];
        if (/* tiene tokens activos */) {
            $via[] = 'fcm';
        }
        return $via;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastType(): string
    {
        return 'ejemplo'; // nombre del evento en Echo
    }

    public function broadcastOn(): array
    {
        return []; // Laravel → private-App.Models.User.{id}
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toFcm(object $notifiable): array
    {
        $p = $this->payload();
        return [
            'notification' => [
                'title' => $p['titulo'],
                'body' => $p['mensaje'],
            ],
            'data' => $p, // FcmService casteará a string
        ];
    }

    private function payload(): array
    {
        return [
            'tipo' => 'ejemplo',
            'subtipo' => 'creado',
            'titulo' => '...',
            'mensaje' => '...',
            'action_url' => '/recurso/123',
            'data' => [ /* ids de dominio */ ],
            'timestamp' => now()->toIso8601String(),
            'notification_correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            // estilos UI (opcional): color, icon, style_class
        ];
    }
}
```

Disparo:

```php
$usuario->notify(new EjemploNotification(...));
```

### Payload que espera el front

Campos que el Echo/campana leen:

- `titulo` (no `title` en el root; el front hace fallback a `title`)
- `mensaje`
- `tipo` / `subtipo`
- `action_url` (deep link)
- `timestamp`
- `notification_correlation_id`
- `color`, `icon`, `style_class` (trait `NotificationStyleTrait`)

`broadcastType()` en este proyecto: `push-notification`, `orden-compra`, `solicitud-pago`, `presupuesto`, `cotizacion-creada`, `asociacion-empresa`, `usuario-reasignado`, etc.

### Deduplicación Reverb + FCM

Trait `NotificationCorrelationId`: un UUID por instancia, inyectado en broadcast, database y FCM data. El cliente usa ese id (o una clave semántica `tipo|subtipo|action_url|…`) para no mostrar dos veces el mismo aviso.

---

## 6. API de bandeja (database)

Tabla estándar Laravel `notifications` (`uuid`, `type`, morphs `notifiable`, `data`, `read_at`).

| Método | Ruta | Uso |
|--------|------|-----|
| `GET` | `/api/notifications` | Lista (`read=unread\|read\|all`) |
| `GET` | `/api/notifications/poll` | Fallback si no hay WebSocket |
| `PATCH` | `/api/notifications/{id}/read` | Marcar leída |
| `PATCH` | `/api/notifications/mark-all-read` | Todas leídas |
| `DELETE` | `/api/notifications/{id}` | Eliminar |
| `POST` | `/api/notifications/test` | Prueba |
| `POST` | `/api/notifications/send` | A sí mismo |
| `POST` | `/api/notifications/send/{userId}` | A otro usuario |

Todo bajo `auth:sanctum`. El poll (~90 s en el front) cubre clientes sin Reverb.

Este proyecto **no** tiene preferencias de mute / opt-out por tipo.

---

## 7. Configurar Reverb + Echo en el frontend

### 7.1 Variables (este proyecto: `environment.ts`)

Si la otra app es Vite/Vue, equivalentes `VITE_*`.

| Clave | Local | Producción (ejemplo real) | Rol |
|-------|-------|---------------------------|-----|
| `WEBSOCKET_URL` | `http://localhost:8080` | `wss://api.rorisafe.com:443` | Host/puerto Echo |
| `WEBSOCKET_KEY` | = `REVERB_APP_KEY` | misma key pública | App key |
| `WEBSOCKET_AUTH_ENDPOINT` | `http://localhost:8088/api/broadcasting/auth` | `{API_URL}/broadcasting/auth` | Auth canales privados |
| `REVERB_SCHEME` | `http` | `wss` | `forceTLS` / `encrypted` |
| `PUSHER_APP_CLUSTER` | `mt1` | `mt1` | Cluster Pusher JS |
| `API_URL` | `http://localhost:8088/api` | URL pública del API | REST + auth |

`WEBSOCKET_KEY` **debe coincidir** con `REVERB_APP_KEY`.  
`WEBSOCKET_AUTH_ENDPOINT` **debe coincidir** con la base del API (Sanctum), no con el host del WebSocket.

### 7.2 Instancia Echo

```ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

(window as any).Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: environment.WEBSOCKET_KEY,
  cluster: environment.PUSHER_APP_CLUSTER || 'mt1',
  wsHost,   // parseado de WEBSOCKET_URL
  wsPort,
  wssPort: wsPort,
  forceTLS: scheme === 'https' || scheme === 'wss',
  encrypted: scheme === 'https' || scheme === 'wss',
  disableStats: true,
  auth: {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
  },
  authEndpoint: environment.WEBSOCKET_AUTH_ENDPOINT,
  enabledTransports: ['ws'],
});
```

Conectar **después del login** (cuando hay usuario + Bearer). Desconectar en logout.

### 7.3 Suscripciones

Canal privado (el importante):

```ts
const channel = echo.private(`App.Models.User.${userId}`);
channel.notification((notification) => {
  // Laravel Notification broadcast → payload en notification
});
```

`.notification()` es el helper de Echo para `Illuminate\Notifications`. No hace falta `.listen('orden-compra')` si usas este helper.

Canales extra en este proyecto (menos críticos para replicar):

- `echo.channel('proveedor.{id}').listen('NuevaOrdenCompra', …)` — legado; el flujo actual va por Notification al canal de usuario.
- `echo.channel('public-notifications').listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', …)`

### 7.4 UI mínima a replicar

1. Campana + badge (contador unread).
2. Popover/lista (unread / all / read) alimentada por `GET /notifications`.
3. Banner in-app al llegar evento Reverb (sonido + vibración opcionales).
4. Marcar leída al abrir; navegar con `action_url`.
5. Polling de respaldo si WebSocket cae.

Flujo Reverb → UI en este repo:

`WebSocketService.notification$` → `NotificationService.addNotification` → badge + lista → `InAppNotificationService` (banner).

---

## 8. Configurar Firebase en el frontend

### 8.1 Variables

```ts
FIREBASE_CONFIG: {
  apiKey: '...',
  authDomain: 'tu-proyecto.firebaseapp.com',
  projectId: 'tu-proyecto',
  storageBucket: 'tu-proyecto.firebasestorage.app',
  messagingSenderId: '...',   // = FCM_SENDER_ID
  appId: '...',
  measurementId: '...',       // opcional Analytics
},
FIREBASE_VAPID_KEY: 'BNxxxx...',  // Web Push certificate (pública)
ENABLE_FCM: true,
```

`projectId` y `messagingSenderId` deben coincidir con `FCM_PROJECT_ID` / `FCM_SENDER_ID` del backend.

### 8.2 Service Worker (web)

Este proyecto registra `/sw.js` desde `index.html` (no el `firebase-messaging-sw.js` legacy). El SW:

1. Inicializa Firebase Messaging.
2. `onBackgroundMessage` → `showNotification` (title/body, tag = `notification_correlation_id`).
3. `notificationclick` → focus de ventana + `postMessage({ type: 'NOTIFICATION_OPEN', data })` o `clients.openWindow(action_url)`.

Para otra app web hace falta:

- SW servido en la **raíz** del origen (`/sw.js` o `/firebase-messaging-sw.js`).
- HTTPS (o localhost).
- Mismo `FIREBASE_CONFIG` que el JS de página.

Canal Android nativo: crear notification channel `app_proveedores_notifications` (o el `channel_id` que pongas en `FcmService`).

### 8.3 Pedir permiso y registrar token

Orden en este proyecto (post-login):

1. `Notification.requestPermission()` si está en `default`.
2. `initializeApp(FIREBASE_CONFIG)` + `getMessaging()`.
3. `getToken(messaging, { vapidKey, serviceWorkerRegistration })`.
4. `POST /api/device-tokens` con Bearer.
5. `onMessage` en foreground.

Logout: `POST /api/device-tokens/deactivate-current` con `{ token }`. Conservar `device_id` web en storage para re-registro estable.

Móvil nativo (Capacitor): `@capacitor/push-notifications` → mismo `POST /device-tokens` con `platform: 'android' | 'ios'`.

### 8.4 Foreground vs background (regla a copiar)

| Contexto | Comportamiento |
|----------|----------------|
| App visible + Reverb OK | Solo UI in-app por Echo; FCM `onMessage` se descarta |
| App visible + Reverb caído | FCM foreground → misma UI in-app |
| App en background / cerrada | SW o SO muestra la push |
| Click en push | Deep link `action_url` o `tipo` + id de entidad |

---

## 9. Flujo de usuario (checklist runtime)

```
Boot app
  → inicializar orquestador de notificaciones
  → (móvil) PushNotifications.initialize()

Login (Sanctum token en storage)
  → Echo.connect() + Bearer a /broadcasting/auth
  → echo.private('App.Models.User.{id}')
  → requestPermission()
  → getToken() → POST /device-tokens
  → GET /notifications (bandeja)
  → poll periódico de respaldo

Evento de negocio en API
  → $user->notify(...)
  → Reverb inmediato si hay socket
  → FCM si hay tokens activos
  → fila en notifications

Logout
  → POST /device-tokens/deactivate-current
  → Echo.disconnect()
  → limpiar lista/badge
```

---

## 10. Checklist para otra app (mínimo viable)

### Backend

- [ ] `composer require laravel/reverb pusher/pusher-php-server`
- [ ] `BROADCAST_CONNECTION=reverb` + `REVERB_*` alineados
- [ ] Proceso `php artisan reverb:start` (supervisor/systemd en prod)
- [ ] `Broadcast::routes(['middleware' => ['auth:sanctum']])`
- [ ] CORS incluye `broadcasting/auth`
- [ ] `routes/channels.php` con `App.Models.User.{id}`
- [ ] Tabla `notifications` + tabla `user_device_tokens`
- [ ] Service account en `storage/app/firebase/` (no commitear)
- [ ] `FIREBASE_CREDENTIALS`, `FCM_PROJECT_ID`, `FCM_SENDER_ID`
- [ ] `FcmService` + `FcmChannel` registrados (`Notification::extend('fcm')`)
- [ ] CRUD `/api/device-tokens` y bandeja `/api/notifications*`
- [ ] Notification classes con `ShouldBroadcastNow` + `via` + `toBroadcast` + `toArray` + `toFcm(): array`

### Frontend

- [ ] `laravel-echo` + `pusher-js` + `firebase`
- [ ] `WEBSOCKET_URL` / `KEY` / `AUTH_ENDPOINT` / `REVERB_SCHEME`
- [ ] Echo `broadcaster: 'reverb'` + Bearer
- [ ] Suscripción `private('App.Models.User.' + userId)` + `.notification()`
- [ ] `FIREBASE_CONFIG` + `FIREBASE_VAPID_KEY` + `ENABLE_FCM`
- [ ] Service worker FCM en la raíz
- [ ] Permiso → token → `POST /device-tokens`
- [ ] Deduplicar por `notification_correlation_id`
- [ ] Logout desactiva token
- [ ] Channel Android si hay app nativa

### Red / ops

- [ ] Firewall: cliente alcanza el puerto WSS de Reverb
- [ ] TLS: `REVERB_SCHEME` / `forceTLS` coherentes
- [ ] Key del cliente = `REVERB_APP_KEY`
- [ ] Auth endpoint en el **mismo origen/API** que Sanctum, no en el host WS
- [ ] `php artisan reverb:start` en supervisor (no solo `queue:work`)

Comandos de diagnóstico de este repo (útiles al portar): `php artisan` `test:reverb`, `DiagnoseNotifications`, `VerifyNotificationsSetup`.

---

## 11. Mapa de archivos de referencia

### api-proveedores

```
config/broadcasting.php
config/reverb.php
config/services.php                    # fcm
config/cors.php
routes/channels.php
routes/segmented/notifications.php     # Broadcast::routes + bandeja
routes/segmented/shared.php            # device-tokens
bootstrap/providers.php
app/Providers/BroadcastServiceProvider.php
app/Providers/AppServiceProvider.php   # Notification::extend('fcm')
app/Services/FcmService.php
app/Channels/FcmChannel.php
app/Models/UserDeviceToken.php
app/Models/User.php                    # deviceTokens, fcm_tokens
app/Notifications/**                   # ShouldBroadcastNow
app/Traits/NotificationCorrelationId.php
app/Traits/NotificationStyleTrait.php
app/Http/Controllers/Notifications/*
database/migrations/*notifications*
database/migrations/*user_device_tokens*
storage/app/firebase/service-account.json   # no versionar
```

### app-proveedores

```
src/environments/environment*.ts
src/index.html                         # register /sw.js
src/sw.js                              # FCM background + click
src/app/@notificaciones/services/websocket.service.ts
src/app/@notificaciones/services/fcm.service.ts
src/app/@notificaciones/services/notifications-initializer.service.ts
src/app/@notificaciones/services/notification.service.ts
src/app/@notificaciones/services/in-app-notification.service.ts
src/app/@notificaciones/components/notification-bell/
src/app/@core/services/push-notification.service.ts   # Capacitor
```

---

## 12. Decisiones y trampas al portar

1. **`BROADCAST_CONNECTION=reverb`**, no `BROADCAST_DRIVER`. Si queda `null`, no hay WS.
2. **Auth HTTP ≠ socket.** El handshake privado es `POST /api/broadcasting/auth` con Bearer; el WS apunta a `WEBSOCKET_URL`.
3. **`ShouldBroadcastNow` vs `ShouldBroadcast`.** Now evita depender de `queue:work` para el WS. FCM/database sí pueden ir en cola si lo configuras, pero aquí el notify es síncrono.
4. **`toFcm(): array`**, no void. Replica el de `PushNotification`.
5. **FCM `data` solo strings.** Arrays/objetos → `json_encode`.
6. **Tokens UNREGISTERED** hay que borrarlos (FcmService ya lo hace).
7. **Tokens de 30 días:** `fcm_tokens` ignora dispositivos inactivos; el registro debe refrescar `last_used_at`.
8. **No hay kreait.** Copiar `FcmService` + JSON de service account es suficiente.
9. **Sin preferencias de usuario** en este diseño; si la otra app las necesita, hay que añadirlas.
10. **Documentación interna** `NOTIFICATIONS_README.md` del front habla de Socket.IO / Laravel WebSockets: está desactualizada. El código real es Reverb + pusher-js.

---

## 13. Ejemplo mínimo de disparo de prueba

Backend (usuario autenticado):

```
POST /api/notifications/test
Authorization: Bearer …
```

Eso usa `PushNotification` (broadcast + database + fcm si hay token).

Verificar:

1. Fila en `notifications`.
2. Evento en Reverb (cliente Echo loguea payload).
3. Push en el dispositivo si el token está activo y hay permiso del navegador/SO.
)
