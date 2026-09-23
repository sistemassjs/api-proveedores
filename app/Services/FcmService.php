<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FcmService
{
    private const FCM_URL_V1 = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    private string $projectId;
    private string $serviceAccountPath;
    private ?string $accessToken = null;
    private int $tokenExpiration = 0;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        $this->serviceAccountPath = storage_path(config('services.fcm.credentials'));

        if (empty($this->projectId)) {
            throw new Exception('FCM Project ID no configurado. Añade FCM_PROJECT_ID al .env');
        }

        if (!file_exists($this->serviceAccountPath)) {
            throw new Exception('Archivo de credenciales Firebase no encontrado: ' . $this->serviceAccountPath);
        }
    }

    /**
     * Enviar notificación a un token
     */
    public function sendToToken(string $token, array $notification, array $data = []): bool
    {
        $message = [
            "message" => [
                "token" => $token,
                "notification" => array_filter($notification),
                "data" => $this->processDataPayload($data),
                "android" => [
                    "priority" => "high",
                    "notification" => [
                        "sound" => "default",
                        "channel_id" => "app_proveedores_notifications",
                    ],
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "default",
                            "badge" => 1,
                            "content-available" => 1,
                            "mutable-content" => 1,
                        ],
                    ],
                ],
            ],
        ];

        return $this->sendRequest($message);
    }

    /**
     * Enviar notificación a un topic
     */
    public function sendToTopic(string $topic, array $notification, array $data = []): bool
    {
        $message = [
            "message" => [
                "topic" => $topic,
                "notification" => array_filter($notification),
                "data" => $this->processDataPayload($data),
                "android" => [
                    "priority" => "high",
                    "notification" => [
                        "sound" => "default",
                        "channel_id" => "app_proveedores_notifications",
                    ],
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "default",
                            "badge" => 1,
                            "content-available" => 1,
                            "mutable-content" => 1,
                        ],
                    ],
                ],
            ],
        ];

        return $this->sendRequest($message);
    }

    /**
     * Enviar notificación a múltiples tokens
     * (con un bucle, ya que la API v1 no admite arrays de tokens)
     */
    public function sendToTokens(array $tokens, array $notification, array $data = []): bool
    {
        if (empty($tokens)) {
            Log::warning('FCM: No hay tokens para enviar notificación');
            return false;
        }

        $success = true;

        foreach ($tokens as $token) {
            if (!$this->sendToToken($token, $notification, $data)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Envía push solo a tokens de las apps indicadas (gestion | nexprov).
     * Inyecta app_key en data por lote para que el front ignore cruces.
     *
     * @param  object  $notifiable  User (o notifiable con fcmTokensForApps)
     * @param  list<string>  $appKeys
     * @param  array{title?: string, body?: string}  $notification
     * @param  array<string, mixed>  $data
     */
    public function sendToNotifiableApps(
        object $notifiable,
        array $appKeys,
        array $notification,
        array $data = []
    ): bool {
        if (! method_exists($notifiable, 'fcmTokensForApps')) {
            Log::warning('FCM: notifiable sin fcmTokensForApps');

            return false;
        }

        if ($appKeys === []) {
            Log::warning('FCM: sin app_keys destino');

            return false;
        }

        $anySent = false;
        $success = true;

        foreach ($appKeys as $appKey) {
            $tokens = $notifiable->fcmTokensForApps([$appKey]);
            if ($tokens === []) {
                continue;
            }

            $anySent = true;
            $payloadData = array_merge($data, ['app_key' => $appKey]);

            if (! $this->sendToTokens($tokens, $notification, $payloadData)) {
                $success = false;
            }
        }

        if (! $anySent) {
            Log::info('FCM: sin tokens para apps destino', [
                'user_id' => $notifiable->id ?? null,
                'app_keys' => $appKeys,
            ]);

            return false;
        }

        return $success;
    }

    /**
     * Enviar solicitud HTTP a FCM v1
     */
    private function sendRequest(array $payload): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = sprintf(self::FCM_URL_V1, $this->projectId);

            Log::info('FCM: Enviando notificación', [
                'url' => $url,
                'payload' => $payload,
            ]);

            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ])->timeout(30);

            // Deshabilitar verificación SSL en desarrollo local (solo Windows)
            if (app()->environment('local') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $http = $http->withOptions(['verify' => false]);
            }

            $response = $http->post($url, $payload);

            if ($response->successful()) {
                Log::info('FCM: Notificación enviada correctamente', [
                    'response' => $response->json(),
                ]);
                return true;
            }

            // ⚠️ Manejo específico para tokens inválidos (UNREGISTERED)
            $status = $response->status();
            $body = $response->body();

            if ($status === 404 && str_contains($body, 'UNREGISTERED')) {
                $token = data_get($payload, 'message.token');

                // Log en archivo separado para dispositivos desinstalados
                Log::channel('fcm_unregistered')->info('Dispositivo desinstalado o token expirado - Token FCM eliminado automáticamente', [
                    'token' => $token,
                    'motivo' => 'El usuario desinstaló la aplicación, borró los datos de la app, o el token expiró',
                    'accion' => 'Token eliminado de la base de datos',
                    'fecha' => now()->toDateTimeString(),
                ]);

                // 🔥 Eliminar el token de la tabla user_device_tokens
                if ($token && class_exists(\App\Models\UserDeviceToken::class)) {
                    \App\Models\UserDeviceToken::where('token', $token)->delete();
                }

                // No consideramos esto un error fatal
                return true;
            }

            // Otros errores HTTP
            Log::error('FCM: Error en respuesta HTTP', [
                'status' => $status,
                'body' => $body,
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('FCM: Excepción al enviar notificación', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }


    /**
     * Procesar datos para que todos sean strings
     */
    private function processDataPayload(array $data): array
    {
        $processed = [];

        foreach ($data as $key => $value) {
            $processed[$key] = is_array($value) || is_object($value)
                ? json_encode($value)
                : (string) $value;
        }

        return $processed;
    }

    /**
     * Obtener token de acceso con Service Account (JWT)
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpiration) {
            return $this->accessToken;
        }

        try {
            $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);

            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $now = time();
            $payload = json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

            $data = $base64Header . '.' . $base64Payload;
            $signature = '';
            openssl_sign($data, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
            $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

            $jwt = $data . '.' . $base64Signature;

            $http = Http::asForm();

            // Deshabilitar verificación SSL en desarrollo local (solo Windows)
            if (app()->environment('local') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $http = $http->withOptions(['verify' => false]);
            }

            $response = $http->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $this->accessToken = $result['access_token'];
                $this->tokenExpiration = time() + ($result['expires_in'] - 300);
                return $this->accessToken;
            }

            throw new Exception('Error obteniendo access token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('FCM: Error obteniendo access token', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
