<?php

namespace App\Notifications\Usuario;

use App\Services\FcmService;
use App\Traits\NotificationStyleTrait;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UsuarioReasignadoNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public $usuarioId;
    public $usuarioNombre;
    public $usuarioEmail;
    public $rolNombre;
    public $tipoRelacion;
    public $proveedorNombre;
    public $fechaAsignacion;

    public function __construct(
        int $usuarioId,
        string $usuarioNombre,
        string $usuarioEmail,
        string $rolNombre,
        string $tipoRelacion,
        string $proveedorNombre
    ) {
        $this->usuarioId = $usuarioId;
        $this->usuarioNombre = $usuarioNombre;
        $this->usuarioEmail = $usuarioEmail;
        $this->rolNombre = $rolNombre;
        $this->tipoRelacion = $tipoRelacion;
        $this->proveedorNombre = $proveedorNombre;
        $this->fechaAsignacion = now()->toIso8601String();
    }

    /**
     * Canales de notificación
     */
    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];

        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $via[] = 'mail';
        }

        if (method_exists($notifiable, 'deviceTokens') && $notifiable->deviceTokens()->where('is_active', true)->exists()) {
            $via[] = 'fcm';
        }

        return $via;
    }

    /**
     * Canal Broadcast (WebSocket)
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->addStylesToData($this->baseData()));
    }

    public function broadcastType(): string
    {
        return 'usuario-reasignado';
    }

    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * Canal Database
     */
    public function toArray(object $notifiable): array
    {
        return $this->addStylesToData($this->baseData());
    }

    /**
     * Canal Mail
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $urlUsuarios = $frontendUrl . '/pages/panel-admin/usuarios/detail/' . $this->usuarioId;

        $tipoTexto = $this->tipoRelacion === 'PRINCIPAL' ? 'principal' : 'secundario';

        return (new MailMessage)
            ->subject('Usuario asignado a ' . $this->proveedorNombre)
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line("Se ha asignado un usuario {$tipoTexto} a su empresa \"{$this->proveedorNombre}\".")
            ->line("**Nombre:** {$this->usuarioNombre}")
            ->line("**Empresa:** {$this->proveedorNombre}")
            ->line("**Email:** {$this->usuarioEmail}")
            ->line("**Rol:** {$this->rolNombre}")
            ->line('**Tipo:** ' . ucfirst(strtolower($this->tipoRelacion)))
            ->action('Ver usuario', $urlUsuarios)
            ->line('Gracias por usar nuestra aplicación.');
    }

    /**
     * Canal FCM personalizado
     */
    public function toFcm(object $notifiable): void
    {
        $tokens = $notifiable->deviceTokens()
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $payload = $this->baseData();

        $notification = [
            'title' => $payload['titulo'],
            'body' => $payload['mensaje'],
        ];

        $data = $this->addStylesToData([
            'tipo' => $payload['tipo'],
            'subtipo' => $payload['subtipo'],
            'action_url' => $payload['action_url'],
            'user_id' => (string) $this->usuarioId,
            'usuario_nombre' => $this->usuarioNombre,
            'usuario_email' => $this->usuarioEmail,
            'empresa_nombre' => $this->proveedorNombre,
            'proveedor_nombre' => $this->proveedorNombre,
            'rol_nombre' => $this->rolNombre,
            'tipo_relacion' => $this->tipoRelacion,
            'timestamp' => $this->fechaAsignacion,
        ]);

        app(FcmService::class)->sendToTokens($tokens, $notification, $data);
    }

    protected function getNotificationTipo(): string
    {
        return 'usuario';
    }

    protected function getNotificationSubtipo(): string
    {
        return 'reasignado';
    }

    private function baseData(): array
    {
        $tipoTexto = $this->tipoRelacion === 'PRINCIPAL' ? 'principal' : 'secundario';

        return [
            'tipo' => 'usuario',
            'subtipo' => 'reasignado',
            'titulo' => "Usuario asignado: {$this->usuarioNombre}",
            'mensaje' => "El usuario \"{$this->usuarioNombre}\" ha sido asignado como usuario {$tipoTexto} de la empresa \"{$this->proveedorNombre}\".",
            'action_url' => '/pages/panel-admin/usuarios/detail/' . $this->usuarioId,
            'user_id' => $this->usuarioId,
            'usuario_nombre' => $this->usuarioNombre,
            'usuario_email' => $this->usuarioEmail,
            'empresa_nombre' => $this->proveedorNombre,
            'proveedor_nombre' => $this->proveedorNombre,
            'rol_nombre' => $this->rolNombre,
            'tipo_relacion' => $this->tipoRelacion,
            'timestamp' => $this->fechaAsignacion,
        ];
    }
}
