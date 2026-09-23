<?php

namespace App\Notifications\ProveedorEmpresa;

use App\Traits\NotificationCorrelationId;
use App\Traits\TargetsClientApp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;

class ProveedorAsociadoAEmpresaNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationCorrelationId;
    use TargetsClientApp;
    use Queueable;

    private int $proveedorId;
    private string $proveedorNombre;
    private int $empresaId;
    private string $empresaNombre;
    private string $empresaRfc;
    private int $usuarioConstruccId;
    private string $usuarioConstruccNombre;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        int $proveedorId,
        string $proveedorNombre,
        int $empresaId,
        string $empresaNombre,
        string $empresaRfc,
        int $usuarioConstruccId,
        string $usuarioConstruccNombre
    ) {
        $this->proveedorId = $proveedorId;
        $this->proveedorNombre = $proveedorNombre;
        $this->empresaId = $empresaId;
        $this->empresaNombre = $empresaNombre;
        $this->empresaRfc = $empresaRfc;
        $this->usuarioConstruccId = $usuarioConstruccId;
        $this->usuarioConstruccNombre = $usuarioConstruccNombre;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['broadcast', 'database'];

        // Agregar canal de email si el proveedor tiene email válido
        if (!empty($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }

        // Agregar canal FCM si el proveedor tiene tokens activos
        if ($this->notifiableHasFcmTokens($notifiable)) {
            $channels[] = 'fcm';
        }

        return $channels;
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->withNotificationCorrelationId([
            'tipo' => 'asociacion_empresa',
            'subtipo' => 'nueva_asociacion',
            'titulo' => 'Nueva Asociación con Empresa',
            'mensaje' => "Has sido vinculado con la empresa {$this->empresaNombre} por {$this->usuarioConstruccNombre}.",
            'action_url' => "/pages/proveedor/empresas/detalle/{$this->empresaId}",
            'data' => [
                'proveedor_id' => $this->proveedorId,
                'proveedor_nombre' => $this->proveedorNombre,
                'empresa_id' => $this->empresaId,
                'empresa_nombre' => $this->empresaNombre,
                'empresa_rfc' => $this->empresaRfc,
                'usuario_construcc_id' => $this->usuarioConstruccId,
                'usuario_construcc_nombre' => $this->usuarioConstruccNombre,
                'estatus' => 'asociado',
            ],
            'timestamp' => now()->toIso8601String(),
        ]));
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'asociacion-empresa';
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->withNotificationCorrelationId([
            'tipo' => 'asociacion_empresa',
            'subtipo' => 'nueva_asociacion',
            'titulo' => 'Nueva Asociación con Empresa',
            'mensaje' => "Has sido vinculado con la empresa {$this->empresaNombre} por {$this->usuarioConstruccNombre}.",
            'action_url' => "/pages/proveedor/empresas/detalle/{$this->empresaId}",
            'proveedor_id' => $this->proveedorId,
            'proveedor_nombre' => $this->proveedorNombre,
            'empresa_id' => $this->empresaId,
            'empresa_nombre' => $this->empresaNombre,
            'empresa_rfc' => $this->empresaRfc,
            'usuario_construcc_id' => $this->usuarioConstruccId,
            'usuario_construcc_nombre' => $this->usuarioConstruccNombre,
            'estatus' => 'asociado',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $urlEmpresa = config('app.frontend_url', config('app.url')) . "/pages/proveedor/empresas/detalle/{$this->empresaId}";

        return (new MailMessage)
            ->subject("Nueva Asociación con Empresa - {$this->empresaNombre}")
            ->view('emails.asociacion-empresa.nueva-asociacion', [
                'proveedorNombre' => $this->proveedorNombre,
                'empresaNombre' => $this->empresaNombre,
                'empresaRfc' => $this->empresaRfc,
                'usuarioConstruccNombre' => $this->usuarioConstruccNombre,
                'urlEmpresa' => $urlEmpresa,
            ]);
    }

    /**
     * Get the FCM representation of the notification.
     */
    public function toFcm(object $notifiable): void
    {
        try {
            if (! $this->notifiableHasFcmTokens($notifiable)) {
                return;
            }

            $notification = [
                'title' => "🏢 Nueva Asociación con Empresa",
                'body' => "Has sido vinculado con {$this->empresaNombre}. Ahora puedes gestionar solicitudes de pago con esta empresa.",
            ];

            $data = $this->withClientAppMeta($this->withNotificationCorrelationId([
                'tipo' => 'asociacion_empresa',
                'subtipo' => 'nueva_asociacion',
                'proveedor_id' => (string) $this->proveedorId,
                'empresa_id' => (string) $this->empresaId,
                'empresa_nombre' => $this->empresaNombre,
                'empresa_rfc' => $this->empresaRfc,
                'usuario_construcc_nombre' => $this->usuarioConstruccNombre,
                'estatus' => 'asociado',
                'timestamp' => now()->toIso8601String(),
            ]));

            $this->sendFcmToNotifiable($notifiable, $notification, $data);

            Log::info('Notificación FCM de asociación empresa enviada', [
                'proveedor_id' => $this->proveedorId,
                'empresa_id' => $this->empresaId,
                'app_keys' => $this->fcmTargetAppKeys(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar notificación FCM de asociación empresa', [
                'error' => $e->getMessage(),
                'proveedor_id' => $this->proveedorId,
                'empresa_id' => $this->empresaId,
            ]);
        }
    }
}
