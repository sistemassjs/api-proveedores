<?php

namespace App\Notifications\SolicitudPago;

use App\Models\SolicitudPago;
use App\Traits\NotificationStyleTrait;
use App\Services\FcmService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class SolicitudPagoRechazadaSinAutorizacionNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public $solicitudPagoId;
    public $solicitudPagoFolio;
    public $proveedorId;
    public $motivo;
    public $userId;

    public function __construct(string $solicitudPagoFolio, int $solicitudPagoId, int $proveedorId, string $motivo = null, int $userId = null)
    {
        $this->solicitudPagoFolio = $solicitudPagoFolio;
        $this->solicitudPagoId = $solicitudPagoId;
        $this->proveedorId = $proveedorId;
        $this->motivo = $motivo;
        $this->userId = $userId;
        $this->solicitudPagoFolio = $solicitudPagoFolio;
    }

    /**
     * Canales de notificación
     */
    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];

        // Solo agregar email si el correo es válido
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $via[] = 'mail';
        }

        if (method_exists($notifiable, 'deviceTokens') && $notifiable->deviceTokens()->where('is_active', true)->exists()) {
            $via[] = 'fcm';
        }

        return $via;
    }

    /**
     * Canal Broadcast (WebSocket) — PRIVADO POR USUARIO
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data  = [
            'tipo' => 'solicitud_pago',   // Categoría base
            'subtipo' => 'rechazada-sin-autorizacion',     // Tipo específico
            'titulo' => 'Solicitud de pago rechazada #' . $this->solicitudPagoFolio,
            'mensaje' => "Tu solicitud de pago #{$this->solicitudPagoFolio} ha sido rechazada durante la verificación.",
            'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
            'data' => [
                'solicitud_pago_folio' => $this->solicitudPagoFolio,
                'proveedor_id' => $this->proveedorId,
                'motivo' => $this->motivo,
                'estatus' => 'rechazada',
                'razon' => 'rechazada',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return new BroadcastMessage($this->addStylesToData($data));
    }

    public function broadcastType(): string
    {
        return 'solicitud-pago';
    }

    /**
     * Canal privado por usuario
     */
    public function broadcastOn(): array
    {
        // Laravel automáticamente envía al canal privado del notifiable (usuario)
        // Esto enviará a: private-App.Models.User.{userId}
        return [];
    }

    /**
     * Canal Database
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'solicitud_pago',   // Categoría base
            'subtipo' => 'rechazada-sin-autorizacion',     // Tipo específico
            'titulo' => 'Solicitud de pago rechazada #' . $this->solicitudPagoFolio,
            'mensaje' => "Tu solicitud de pago #{$this->solicitudPagoFolio} ha sido rechazada durante la verificación.",
            'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
            'solicitud_pago_id' => $this->solicitudPagoId,
            'solicitud_pago_folio' => $this->solicitudPagoFolio,
            'proveedor_id' => $this->proveedorId,
            'motivo' => $this->motivo,
            'estatus' => 'rechazada',
            'razon' => 'rechazada',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Canal Mail
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $urlSolicitud = $frontendUrl . '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId;

        return (new MailMessage)
            ->subject('Solicitud de Pago Rechazada Durante Verificación #' . $this->solicitudPagoFolio)
            ->view('emails.solicitud-pago.rechazada-sin-autorizacion', [
                'notifiable' => $notifiable,
                'solicitudPagoFolio' => $this->solicitudPagoFolio,
                'solicitudPagoId' => $this->solicitudPagoId,
                'proveedorId' => $this->proveedorId,
                'motivo' => $this->motivo,
                'urlSolicitud' => $urlSolicitud,
                'logoAppDataUri' => $this->resolverLogoProveedorBase64(),
            ]);
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

        $notification = [
            'title' => 'Solicitud de pago rechazada #' . $this->solicitudPagoFolio,
            'body' => $this->motivo
                ? "Tu solicitud de pago fue rechazada durante la verificación. Motivo: {$this->motivo}"
                : "Tu solicitud de pago fue rechazada durante la verificación.",
        ];

        $data = [
            'tipo' => 'solicitud_pago',   // Categoría base
            'subtipo' => 'rechazada-sin-autorizacion',     // Tipo específico
            'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
            'solicitud_pago_folio' => $this->solicitudPagoFolio,
            'proveedor_id' => (string) $this->proveedorId,
            'motivo' => $this->motivo,
            'estatus' => 'rechazada',
            'razon' => 'rechazada',
            'timestamp' => now()->toIso8601String(),
        ];

        app(FcmService::class)->sendToTokens($tokens, $notification, $data);
    }


    /**
     * Implementación de métodos abstractos del trait
     */
    protected function getNotificationTipo(): string
    {
        return 'solicitud_pago';
    }

    protected function getNotificationSubtipo(): string
    {
        return 'rechazada-sin-autorizacion';
    }

    private function resolverLogoProveedorBase64(): ?string
    {
        $logo = SolicitudPago::query()
            ->with('proveedor:id,logo')
            ->find($this->solicitudPagoId)?->proveedor?->logo;

        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }
        if (str_starts_with($logo, 'data:image')) {
            return $logo;
        }
        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            return null;
        }

        $logoPath = null;
        if (str_starts_with($logo, '/') || str_starts_with($logo, 'storage/')) {
            $logoPath = public_path($logo);
        } elseif (Storage::disk('public')->exists($logo)) {
            $logoPath = Storage::disk('public')->path($logo);
        } else {
            $logoPath = public_path('storage/' . $logo);
        }

        if (! $logoPath || ! is_readable($logoPath)) {
            return null;
        }

        $binary = @file_get_contents($logoPath);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }
}
