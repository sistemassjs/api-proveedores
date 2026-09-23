<?php

namespace App\Notifications\Auth;

use App\Services\FcmService;
use App\Traits\NotificationStyleTrait;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CuentaVerificadaNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public function __construct(
        public readonly string $email,
        public readonly int $userId,
        public readonly ?string $verifiedAtIso = null
    ) {}

    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];

        if ($this->notifiableHasFcmTokens($notifiable)) {
            $via[] = 'fcm';
        }

        return $via;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = [
            'tipo' => 'usuario',
            'subtipo' => 'email_verificado',
            'titulo' => 'Correo electronico verificado',
            'mensaje' => "El correo \"{$this->email}\" ha sido validado con exito. A partir de este momento, tu perfil tendra la insignia de correo verificado.",
            'action_url' => '/perfil',
            'data' => [
                'insignia_verificado' => true,
                'user_id' => $this->userId,
                'email_verificado' => true,
                'email_verified_at' => $this->verifiedAtIso,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return new BroadcastMessage($this->addStylesToData($data));
    }

    public function toArray(object $notifiable): array
    {
        $data = [
            'tipo' => 'usuario',
            'subtipo' => 'email_verificado',
            'titulo' => 'Correo electronico verificado',
            'mensaje' => "El correo \"{$this->email}\" ha sido validado con exito. A partir de este momento, tu perfil tendra la insignia de correo verificado.",
            'action_url' => '/perfil',
            'insignia_verificado' => true,
            'user_id' => $this->userId,
            'email_verificado' => true,
            'email_verified_at' => $this->verifiedAtIso,
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->addStylesToData($data);
    }

    public function toFcm(object $notifiable): void
    {
        if (! $this->notifiableHasFcmTokens($notifiable)) {
            return;
        }

        $notification = [
            'title' => 'Correo electronico verificado',
            'body' => "El correo \"{$this->email}\" ha sido validado con exito.",
        ];

        $data = [
            'tipo' => 'usuario',
            'subtipo' => 'email_verificado',
            'action_url' => '/perfil',
            'insignia_verificado' => true,
            'user_id' => (string) $this->userId,
            'email_verificado' => 'true',
            'email_verified_at' => $this->verifiedAtIso ?? '',
            'timestamp' => now()->toIso8601String(),
        ];

        $data = $this->addStylesToData($data);
        $this->sendFcmToNotifiable($notifiable, $notification, $data);
    }

    protected function getNotificationTipo(): string
    {
        return 'usuario';
    }

    protected function getNotificationSubtipo(): string
    {
        return 'email_verificado';
    }
}
