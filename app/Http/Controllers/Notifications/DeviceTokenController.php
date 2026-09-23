<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use App\Support\ClientApp;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Controller para manejar tokens de dispositivos FCM
 * Permite registrar, actualizar y gestionar tokens de push notifications
 * Segmentados por app_key (gestion | nexprov).
 */
class DeviceTokenController extends Controller
{
    /**
     * Registrar o actualizar un token de dispositivo
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $allowedApps = ClientApp::keys();

            $validator = Validator::make($request->all(), [
                'token' => 'required|string|max:255',
                'platform' => 'required|in:ios,android,web',
                'device_id' => 'nullable|string|max:255',
                'device_name' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
                'app_key' => ['nullable', 'string', Rule::in($allowedApps)],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $validated = $validator->validated();
            $appKey = ClientApp::normalize($validated['app_key'] ?? ClientApp::key());

            $metadata = array_merge($validated['metadata'] ?? [], [
                'app_key' => $appKey,
            ]);

            // Buscar token existente por token exacto (independiente del usuario)
            $existingToken = UserDeviceToken::where('token', $validated['token'])->first();

            // Si no existe, buscar por device_id + app_key del mismo usuario
            if (! $existingToken && isset($validated['device_id'])) {
                $existingToken = UserDeviceToken::where('user_id', $user->id)
                    ->where('device_id', $validated['device_id'])
                    ->where('app_key', $appKey)
                    ->first();
            }

            if ($existingToken) {
                $existingToken->update([
                    'user_id' => $user->id,
                    'app_key' => $appKey,
                    'token' => $validated['token'],
                    'platform' => $validated['platform'],
                    'device_id' => $validated['device_id'] ?? $existingToken->device_id,
                    'device_name' => $validated['device_name'] ?? $existingToken->device_name,
                    'metadata' => array_merge($existingToken->metadata ?? [], $metadata),
                    'last_used_at' => now(),
                    'is_active' => true,
                ]);

                $this->deactivateStaleSiblingTokens((int) $user->id, $existingToken);

                return response()->json([
                    'success' => true,
                    'message' => 'Token actualizado correctamente',
                    'data' => [
                        'id' => $existingToken->id,
                        'token' => $existingToken->token,
                        'platform' => $existingToken->platform,
                        'app_key' => $existingToken->app_key,
                        'device_info' => $existingToken->device_info,
                        'updated' => true,
                    ],
                ]);
            }

            $deviceToken = UserDeviceToken::create([
                'user_id' => $user->id,
                'app_key' => $appKey,
                'token' => $validated['token'],
                'platform' => $validated['platform'],
                'device_id' => $validated['device_id'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'metadata' => $metadata,
                'last_used_at' => now(),
                'is_active' => true,
            ]);

            $this->deactivateStaleSiblingTokens((int) $user->id, $deviceToken);

            return response()->json([
                'success' => true,
                'message' => 'Token registrado correctamente',
                'data' => [
                    'id' => $deviceToken->id,
                    'token' => $deviceToken->token,
                    'platform' => $deviceToken->platform,
                    'app_key' => $deviceToken->app_key,
                    'device_info' => $deviceToken->device_info,
                    'created' => true,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todos los tokens del usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $query = $user->deviceTokens();

            if ($request->has('platform')) {
                $query->byPlatform($request->platform);
            }

            if ($request->filled('app_key')) {
                $query->byAppKey($request->string('app_key')->toString());
            }

            if ($request->has('active')) {
                if ($request->boolean('active')) {
                    $query->active();
                } else {
                    $query->where('is_active', false);
                }
            }

            $tokens = $query->orderBy('last_used_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tokens->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'app_key' => $token->app_key,
                        'platform' => $token->platform,
                        'device_info' => $token->device_info,
                        'is_active' => $token->is_active,
                        'created_at' => $token->created_at->toISOString(),
                        'last_used_at' => $token->last_used_at?->toISOString(),
                    ];
                }),
                'meta' => [
                    'total' => $tokens->count(),
                    'active' => $tokens->where('is_active', true)->count(),
                    'platforms' => $tokens->groupBy('platform')->keys()->toArray(),
                    'apps' => $tokens->groupBy('app_key')->keys()->toArray(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo tokens',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desactiva el token FCM del dispositivo actual (cierre de sesión).
     */
    public function deactivateCurrent(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                ], 401);
            }

            $token = $request->input('token');

            if (! is_string($token) || trim($token) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de dispositivo requerido',
                ], 422);
            }

            $appKey = ClientApp::normalize($request->input('app_key', ClientApp::key()));

            $updated = UserDeviceToken::query()
                ->where('user_id', $user->id)
                ->where('token', $token)
                ->where('app_key', $appKey)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Token de dispositivo desactivado',
                'data' => [
                    'deactivated' => $updated > 0,
                    'app_key' => $appKey,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error desactivando token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desactivar un token específico
     */
    public function deactivate(Request $request, int $tokenId): JsonResponse
    {
        try {
            $user = Auth::user();

            $token = UserDeviceToken::where('user_id', $user->id)
                ->where('id', $tokenId)
                ->first();

            if (! $token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no encontrado',
                ], 404);
            }

            $token->deactivate();

            return response()->json([
                'success' => true,
                'message' => 'Token desactivado correctamente',
                'data' => [
                    'id' => $token->id,
                    'is_active' => $token->is_active,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error desactivando token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar tokens expirados (comando de limpieza)
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $days = $request->get('days', 60);

            $expiredTokens = $user->deviceTokens()
                ->where(function ($query) use ($days) {
                    $query->where('last_used_at', '<', now()->subDays($days))
                        ->orWhere(function ($q) use ($days) {
                            $q->whereNull('last_used_at')
                                ->where('created_at', '<', now()->subDays($days));
                        });
                });

            if ($request->filled('app_key')) {
                $expiredTokens->byAppKey($request->string('app_key')->toString());
            }

            $count = $expiredTokens->count();
            $expiredTokens->delete();

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$count} tokens expirados",
                'data' => [
                    'deleted_count' => $count,
                    'criteria_days' => $days,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en limpieza de tokens',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Probar envío de notificación de prueba (solo desarrollo)
     */
    public function testNotification(Request $request): JsonResponse
    {
        if (! app()->environment('local', 'development')) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint solo disponible en desarrollo',
            ], 403);
        }

        try {
            $user = Auth::user();
            $appKey = ClientApp::normalize($request->input('app_key', ClientApp::key()));
            $tokens = $user->fcmTokensForApps([$appKey]);

            if (empty($tokens)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay tokens activos para este usuario/app',
                    'data' => ['app_key' => $appKey],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificación de prueba programada',
                'data' => [
                    'app_key' => $appKey,
                    'tokens_count' => count($tokens),
                    'test_payload' => [
                        'title' => 'Notificación de prueba',
                        'body' => 'Esta es una notificación de prueba del sistema',
                        'data' => [
                            'type' => 'general',
                            'action' => 'view',
                            'app_key' => $appKey,
                            'timestamp' => now()->toISOString(),
                        ],
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error enviando notificación de prueba',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desactiva hermanos obsoletos del mismo usuario/app/plataforma.
     * No toca tokens de otra app_key (GestionPlus vs NexProv).
     */
    private function deactivateStaleSiblingTokens(int $userId, UserDeviceToken $current): void
    {
        $staleBefore = now()->subDays(30);
        $appKey = $current->app_key ?: ClientApp::key();

        if (! empty($current->device_id)) {
            UserDeviceToken::query()
                ->where('user_id', $userId)
                ->where('app_key', $appKey)
                ->where('id', '!=', $current->id)
                ->where('device_id', $current->device_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        UserDeviceToken::query()
            ->where('user_id', $userId)
            ->where('app_key', $appKey)
            ->where('id', '!=', $current->id)
            ->where('platform', $current->platform)
            ->where('is_active', true)
            ->where(function ($q) use ($staleBefore) {
                $q->where(function ($inner) use ($staleBefore) {
                    $inner->whereNotNull('last_used_at')
                        ->where('last_used_at', '<', $staleBefore);
                })->orWhere(function ($inner) use ($staleBefore) {
                    $inner->whereNull('last_used_at')
                        ->where('updated_at', '<', $staleBefore);
                });
            })
            ->update(['is_active' => false]);
    }
}
