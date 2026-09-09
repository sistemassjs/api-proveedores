<?php

namespace App\Notifications\Auth;

use App\Models\Proveedor;
use App\Models\User;
use App\Services\FcmService;
use App\Traits\NotificationStyleTrait;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de nuevo usuario registrado
 * Se envía cuando un usuario se registra en el sistema y unicamente notifica al administrador;
 */
class NewUserNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public readonly User $user;
    public readonly Proveedor $proveedor;

    public function __construct(User $user, Proveedor $proveedor)
    {
        $this->user = $user;
        $this->proveedor = $proveedor;
    }

    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];

        if (method_exists($notifiable, 'deviceTokens') && $notifiable->deviceTokens()->where('is_active', true)->exists()) {
            $via[] = 'fcm';
        }

        return $via;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = [
            'tipo' => 'usuario',
            'subtipo' => 'nuevo_usuario',
            'titulo' => 'Nuevo usuario registrado',
            'mensaje' => "El usuario \"{$this->user->name}\" ha sido registrado con exito en GestionPlus para la empresa \"{$this->proveedor->nombre_comercial}\".",
            'action_url' => '/pages/panel-admin/usuarios/detail/' . $this->user->id,
            'data' => [
                'user_id' => $this->user->id,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return new BroadcastMessage($this->addStylesToData($data));
    }

    public function toArray(object $notifiable): array
    {
        $data = [
            'tipo' => 'usuario',
            'subtipo' => 'nuevo_usuario',
            'titulo' => 'Nuevo usuario registrado',
            'mensaje' => "El usuario \"{$this->user->name}\" ha sido registrado con exito en GestionPlus para la empresa \"{$this->proveedor->nombre_comercial}\".",
            'action_url' => '/pages/panel-admin/usuarios/detail/' . $this->user->id,
            'user_id' => $this->user->id,
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->addStylesToData($data);
    }

    public function toFcm(object $notifiable): void
    {
        $tokens = $notifiable->deviceTokens()
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $notification = [
            'title' => 'Nuevo usuario registrado',
            'body' => "El usuario \"{$this->user->name}\" ha sido registrado con exito en GestionPlus para la empresa \"{$this->proveedor->nombre_comercial}\".",
        ];

        $data = [
            'tipo' => 'usuario',
            'subtipo' => 'nuevo_usuario',
            'action_url' => '/pages/panel-admin/usuarios/detail/' . $this->user->id,
            'insignia_verificado' => true,
            'user_id' => (string) $this->user->id,
            'timestamp' => now()->toIso8601String(),
        ];

        $data = $this->addStylesToData($data);
        app(FcmService::class)->sendToTokens($tokens, $notification, $data);
    }

    protected function getNotificationTipo(): string
    {
        return 'usuario';
    }

    protected function getNotificationSubtipo(): string
    {
        return 'nuevo_usuario';
    }
}
