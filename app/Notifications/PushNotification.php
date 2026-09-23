<?php

namespace App\Notifications;

use App\Traits\NotificationCorrelationId;
use App\Traits\TargetsClientApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Notification;

class PushNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationCorrelationId;
    use TargetsClientApp;
    use Queueable;

    public $title;
    public $message;
    public $type;
    public $data;
    public $actionUrl;
    public $userId;

    public function __construct(
        string $title, 
        string $message, 
        string $type = 'info', 
        array $data = [], 
        ?string $actionUrl = null,
        ?int $userId = null
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->data = $data;
        $this->actionUrl = $actionUrl;
        $this->userId = $userId;
    }

    /**
     * Canales de notificación
     */
    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];

        if ($this->notifiableHasFcmTokens($notifiable)) {
            $via[] = 'fcm';
        }

        return $via;
    }

    /**
     * Canal Broadcast (WebSocket) — PRIVADO POR USUARIO
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->withClientAppMeta($this->withNotificationCorrelationId([
            'tipo' => $this->type,
            'titulo' => $this->title,
            'mensaje' => $this->message,
            'action_url' => $this->actionUrl,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ])));
    }

    public function broadcastType(): string
    {
        return 'push-notification';
    }

    /**
     * Canal privado por usuario
     */
    public function broadcastOn(): array
    {
        // Usa el userId si está disponible
        $channelId = $this->userId ?? ($this->data['user_id'] ?? null);
        
        if ($channelId) {
            return [new PrivateChannel('App.Models.User.' . $channelId)];
        }
        
        // Fallback a canal público si no hay userId (no recomendado)
        return [new PrivateChannel('notifications')];
    }

    /**
     * Canal Database
     */
    public function toArray(object $notifiable): array
    {
        return $this->withClientAppMeta($this->withNotificationCorrelationId([
            'tipo' => $this->type,
            'titulo' => $this->title,
            'mensaje' => $this->message,
            'action_url' => $this->actionUrl,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ]));
    }

    /**
     * Canal FCM personalizado
     */
    public function toFcm(object $notifiable): array
    {
        // Determinar el ícono según el tipo
        $icon = match($this->type) {
            'success' => '✅',
            'error', 'danger' => '❌',
            'warning' => '⚠️',
            'info' => '🔔',
            default => '🔔',
        };

        return [
            'notification' => [
                'title' => $icon . ' ' . $this->title,
                'body' => $this->message,
            ],
            'data' => $this->withClientAppMeta($this->withNotificationCorrelationId(array_merge(
                $this->data,
                [
                    'tipo' => $this->type,
                    'action_url' => $this->actionUrl,
                    'timestamp' => now()->toIso8601String(),
                ]
            ))),
        ];
    }
}
