<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para manejar tokens de dispositivos FCM
 * Permite registrar, actualizar y gestionar tokens de push notifications
 */
class DeviceTokenController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/device-tokens",
     *     summary="Registrar token de dispositivo para notificaciones push",
     *     tags={"Notificaciones"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token", "platform"},
     *             @OA\Property(property="token", type="string", example="FCM_TOKEN_HERE"),
     *             @OA\Property(property="platform", type="string", enum={"ios", "android", "web"}),
     *             @OA\Property(property="device_id", type="string", nullable=true),
     *             @OA\Property(property="device_name", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Token registrado correctamente")
     * )
     * Registrar o actualizar un token de dispositivo
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string|max:255',
                'platform' => 'required|in:ios,android,web',
                'device_id' => 'nullable|string|max:255',
                'device_name' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
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

            // Buscar token existente por token exacto (independiente del usuario)
            // El token es único globalmente, puede que otro usuario lo tenga
            $existingToken = UserDeviceToken::where('token', $validated['token'])->first();

            // Si no existe, buscar por device_id del mismo usuario
            if (!$existingToken && isset($validated['device_id'])) {
                $existingToken = UserDeviceToken::where('user_id', $user->id)
                    ->where('device_id', $validated['device_id'])
                    ->first();
            }

            if ($existingToken) {
                // Actualizar token existente (puede cambiar de usuario)
                $existingToken->update([
                    'user_id' => $user->id, // Actualizar el usuario si cambió
                    'token' => $validated['token'],
                    'platform' => $validated['platform'],
                    'device_id' => $validated['device_id'] ?? $existingToken->device_id,
                    'device_name' => $validated['device_name'] ?? $existingToken->device_name,
                    'metadata' => array_merge($existingToken->metadata ?? [], $validated['metadata'] ?? []),
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
                        'device_info' => $existingToken->device_info,
                        'updated' => true,
                    ],
                ]);
            } else {
                // Crear nuevo token
                $deviceToken = UserDeviceToken::create([
                    'user_id' => $user->id,
                    'token' => $validated['token'],
                    'platform' => $validated['platform'],
                    'device_id' => $validated['device_id'],
                    'device_name' => $validated['device_name'],
                    'metadata' => $validated['metadata'] ?? [],
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
                        'device_info' => $deviceToken->device_info,
                        'created' => true,
                    ],
                ], 201);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/device-tokens",
     *     summary="Listar tokens de dispositivos del usuario",
     *     tags={"Notificaciones"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="platform", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Lista de tokens")
     * )
     * Obtener todos los tokens del usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $query = $user->deviceTokens();

            // Filtrar por plataforma si se especifica
            if ($request->has('platform')) {
                $query->byPlatform($request->platform);
            }

            // Filtrar por estado activo si se especifica
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
                        'platform' => $token->platform,
                        'device_info' => $token->device_info,
                        'is_active' => $token->is_active,
                        'created_at' => $token->created_at->toISOString(),
                        'last_used_at' => $token->last_used_at?->toISOString(),
                        // No incluir el token por seguridad
                    ];
                }),
                'meta' => [
                    'total' => $tokens->count(),
                    'active' => $tokens->where('is_active', true)->count(),
                    'platforms' => $tokens->groupBy('platform')->keys()->toArray(),
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

            $updated = UserDeviceToken::query()
                ->where('user_id', $user->id)
                ->where('token', $token)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Token de dispositivo desactivado',
                'data' => [
                    'deactivated' => $updated > 0,
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
            $days = $request->get('days', 60); // Por defecto 60 días

            $expiredTokens = $user->deviceTokens()
                ->where('last_used_at', '<', now()->subDays($days))
                ->orWhere(function ($query) use ($days) {
                    $query->whereNull('last_used_at')
                        ->where('created_at', '<', now()->subDays($days));
                });

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
            $tokens = $user->fcm_tokens;

            if (empty($tokens)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay tokens activos para este usuario',
                ]);
            }

            // Aquí iría la lógica de envío de FCM cuando esté implementada

            return response()->json([
                'success' => true,
                'message' => 'Notificación de prueba programada',
                'data' => [
                    'tokens_count' => count($tokens),
                    'test_payload' => [
                        'title' => 'Notificación de prueba',
                        'body' => 'Esta es una notificación de prueba del sistema',
                        'data' => [
                            'type' => 'general',
                            'action' => 'view',
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
     * Desactiva hermanos obsoletos del mismo usuario/plataforma sin adivinar dispositivos activos.
     *
     * Criterios (evidencia temporal + identidad de dispositivo):
     * 1) Mismo device_id distinto al token actual → token rotado en ese dispositivo.
     * 2) Misma plataforma, distinto device_id y sin uso reciente (≥ 30 días) → sesión abandonada.
     *
     * No toca tokens recientes de otros device_id (permite multi-dispositivo real).
     */
    private function deactivateStaleSiblingTokens(int $userId, UserDeviceToken $current): void
    {
        $staleBefore = now()->subDays(30);

        // 1) Rotación en el mismo device_id
        if (! empty($current->device_id)) {
            UserDeviceToken::query()
                ->where('user_id', $userId)
                ->where('id', '!=', $current->id)
                ->where('device_id', $current->device_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        // 2) Otras sesiones de la misma plataforma sin uso reciente
        UserDeviceToken::query()
            ->where('user_id', $userId)
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
