<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Enviar una notificación a un usuario específico
     */
    public function sendToUser(Request $request, $userId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|in:info,success,warning,error,danger',
            'data' => 'nullable|array',
        ]);

        $user = User::findOrFail($userId);

        try {
            $user->notify(new PushNotification(
                $request->input('title'),
                $request->input('message'),
                $request->input('type', 'info'),
                $request->input('data', [])
            ));

            return response()->json([
                'success' => true,
                'message' => 'Notificación enviada exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enviar una notificación al usuario autenticado
     */
    public function sendToCurrentUser(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|in:info,success,warning,error,danger',
            'data' => 'nullable|array',
        ]);

        $user = Auth::user();

        try {
            $user->notify(new PushNotification(
                $request->input('title'),
                $request->input('message'),
                $request->input('type', 'info'),
                $request->input('data', [])
            ));

            return response()->json([
                'success' => true,
                'message' => 'Notificación enviada exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enviar notificación de prueba
     */
    public function sendTest(Request $request)
    {
        $user = Auth::user();

        try {
            $user->notify(new PushNotification(
                '🔔 Notificación de Prueba',
                'Esta es una notificación de prueba desde la API. ¡El sistema de WebSocket está funcionando correctamente!',
                'success',
                [
                    'timestamp' => now()->toIsoString(),
                    'source' => 'api_test',
                    'test' => true,
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Notificación de prueba enviada',
                'info' => 'Revisa la aplicación, deberías ver la notificación en tiempo real',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación de prueba',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener las notificaciones del usuario autenticado.
     *
     * Query `read`:
     * - omitido / `0` / `unread` → solo no leídas (default)
     * - `1` / `read` → solo leídas
     * - `all` → todas
     */
    public function getNotifications(Request $request)
    {
        $user = Auth::user();
        $readParam = strtolower(trim((string) $request->input('read', 'unread')));

        $query = $user->notifications()->latest();

        if (in_array($readParam, ['1', 'true', 'read', 'leidas', 'leídas'], true)) {
            $query->whereNotNull('read_at');
        } elseif (in_array($readParam, ['all', 'todas', '*'], true)) {
            // sin filtro de lectura
        } else {
            // Default: no leídas
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Eliminar una notificación del usuario autenticado.
     */
    public function destroy(string $notificationId)
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($notificationId);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notificación no encontrada',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificación eliminada',
        ]);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($notificationId)
    {
        $user = Auth::user();

        $notification = $user->notifications()->find($notificationId);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notificación no encontrada',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
        ]);
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Todas las notificaciones marcadas como leídas',
        ]);
    }

    /**
     * Polling endpoint para notificaciones
     * Usado por el servicio SSE/Polling del frontend
     */
    public function poll(Request $request)
    {
        $user = Auth::user();
        $lastTimestamp = $request->query('last_timestamp');

        $query = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($lastTimestamp) {
            $query->where('created_at', '>', $lastTimestamp);
        }

        $notifications = $query->take(50)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'has_changes' => $notifications->count() > 0,
                'notifications' => $notifications,
                'timestamp' => now()->toIsoString(),
                'unread_count' => $user->unreadNotifications->count()
            ]
        ]);
    }

    /**
     * Contar notificaciones de SP por estatus (rechazada, aprobada, pagada, pendiente)
     * Solo cuenta las notificaciones NO LEÍDAS
     */
    public function countsSPByStatus(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        // Obtener todas las notificaciones no leídas del tipo 'solicitud_pago'
        $notificaciones = $user->unreadNotifications()
            ->whereRaw("JSON_EXTRACT(data, '$.tipo') = ?", ['solicitud_pago'])
            ->get();

        // Contar por subtipo (estatus)
        $counts = [
            'rechazada' => 0,
            'pagada' => 0,
            'pendiente' => 0,
            'en_proceso' => 0,
        ];

        foreach ($notificaciones as $notificacion) {
            $data = json_decode($notificacion->data, true);
            $subtipo = $data['subtipo'] ?? null;
            
            if (isset($counts[$subtipo])) {
                $counts[$subtipo]++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    /**
     * Marcar notificación como leída por tipo y SP específica
     */
    public function markAsReadByTipoAndSP(Request $request)
    {
        $request->validate([
            'solicitud_pago_id' => 'required|integer',
            'subtipo' => 'required|string|in:rechazada,pagada,pendiente,en_proceso',
        ]);

        $user = Auth::user();
        $solicitudPagoId = $request->input('solicitud_pago_id');
        $subtipo = $request->input('subtipo');

        // Buscar la notificación no leída que corresponda a la SP y tipo específico
        $notificaciones = $user->unreadNotifications()
            ->whereRaw("JSON_EXTRACT(data, '$.tipo') = ?", ['solicitud_pago'])
            ->whereRaw("JSON_EXTRACT(data, '$.subtipo') = ?", [$subtipo])
            ->get();

        // Filtrar por solicitud_pago_id
        $notificacionId = null;
        foreach ($notificaciones as $notif) {
            $data = is_string($notif->data) ? json_decode($notif->data, true) : $notif->data;
            if (isset($data['solicitud_pago_id']) && $data['solicitud_pago_id'] == $solicitudPagoId) {
                $notificacionId = $notif->id;
                break;
            }
        }

        if (!$notificacionId) {
            return response()->json([
                'success' => false,
                'message' => 'Notificación no encontrada',
            ], 404);
        }

        // Marcar como leída directamente usando el ID
        $user->unreadNotifications()->where('id', $notificacionId)->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
        ]);
    }
}
