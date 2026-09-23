<?php

namespace App\Channels;

use App\Services\FcmService;
use App\Support\ClientApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Canal personalizado para enviar notificaciones push mediante Firebase Cloud Messaging.
 * Filtra tokens por app_key (gestion | nexprov).
 */
class FcmChannel
{
    protected FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Enviar la notificación mediante FCM
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            Log::warning('FCM Channel: La notificación no tiene método toFcm()', [
                'notification' => get_class($notification),
            ]);

            return;
        }

        try {
            $appKeys = method_exists($notification, 'fcmTargetAppKeys')
                ? $notification->fcmTargetAppKeys()
                : [ClientApp::key()];

            $payload = $notification->toFcm($notifiable);

            // toFcm() void: la Notification ya envió (sendFcmToNotifiable). No reenviar.
            if ($payload === null || ! is_array($payload)) {
                return;
            }

            if (! isset($payload['notification']) || ! isset($payload['data'])) {
                Log::error('FCM Channel: Payload inválido', [
                    'payload' => $payload,
                    'notification' => get_class($notification),
                ]);

                return;
            }

            $success = $this->fcmService->sendToNotifiableApps(
                $notifiable,
                $appKeys,
                $payload['notification'],
                $payload['data']
            );

            if ($success) {
                Log::info('FCM Channel: Notificación enviada exitosamente', [
                    'user_id' => $notifiable->id,
                    'app_keys' => $appKeys,
                    'notification' => get_class($notification),
                ]);
            } else {
                Log::warning('FCM Channel: Fallo al enviar notificación', [
                    'user_id' => $notifiable->id,
                    'app_keys' => $appKeys,
                ]);
            }
        } catch (Exception $e) {
            Log::error('FCM Channel: Error enviando notificación', [
                'user_id' => $notifiable->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
