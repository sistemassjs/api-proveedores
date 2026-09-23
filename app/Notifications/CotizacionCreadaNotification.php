<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\Cotizacion;
use App\Models\User;
use App\Traits\NotificationCorrelationId;
use App\Traits\TargetsClientApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Notificación enviada al proveedor cuando se crea una nueva cotización
 * desde el módulo de construcción
 */
class CotizacionCreadaNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationCorrelationId;
    use TargetsClientApp;
    use Queueable;

    protected Cotizacion $cotizacion;
    protected User $solicitante;
    protected string $moduloOrigen;

    public function __construct(Cotizacion $cotizacion, User $solicitante, string $moduloOrigen = 'construccion')
    {
        $this->cotizacion = $cotizacion;
        $this->solicitante = $solicitante;
        $this->moduloOrigen = $moduloOrigen;
    }

    public function via(object $notifiable): array
    {
        $channels = ['broadcast', 'database'];

        // Solo agregar email si el correo es válido
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }

        if ($this->notifiableHasFcmTokens($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $urlCotizacion = URL::to('/admin/cotizaciones/' . $this->cotizacion->id);

        return (new MailMessage)
            ->subject('Nueva Cotización Solicitada - #' . $this->cotizacion->id)
            ->view('emails.cotizacion-creada', [
                'notifiable' => $notifiable,
                'cotizacion' => $this->cotizacion,
                'solicitante' => $this->solicitante,
                'moduloOrigen' => $this->moduloOrigen,
                'urlCotizacion' => $urlCotizacion,
            ]);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->getPayloadCotizacion());
    }

    public function broadcastType(): string
    {
        return 'cotizacion-creada';
    }

    public function toFcm(object $notifiable): array
    {
        $title = '🏗️ Nueva Cotización #' . $this->cotizacion->id;
        $body = 'Se ha creado una nueva cotización desde ' . ucfirst($this->moduloOrigen) .
            '. Total: $' . number_format($this->cotizacion->total, 2);

        return [
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => '/assets/icon/favicon.png',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'data' => $this->withClientAppMeta($this->withNotificationCorrelationId([
                'type' => 'cotizacion',
                'entityId' => (string) $this->cotizacion->id,
                'proveedorId' => (string) ($this->cotizacion->proveedor_id ?? ''),
                'action' => 'view',
                'title' => $title,
                'body' => $body,
                'moduloOrigen' => $this->moduloOrigen,
                'url' => '/admin/cotizaciones/' . $this->cotizacion->id,
                'timestamp' => now()->toISOString(),
            ])),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->getPayloadCotizacion();
    }

    private function getPayloadCotizacion(): array
    {
        return $this->withClientAppMeta($this->withNotificationCorrelationId([
            'tipo' => 'Cotizaciones',
            'titulo' => 'Nueva Cotización',
            'mensaje' => 'Se ha creado una nueva cotización #' . $this->cotizacion->id,
            'icono' => '',
            'data' => [
                'id' => $this->cotizacion->id,
                'productos_count' => $this->cotizacion->detalles->count(),
                'total' => $this->cotizacion->total,
                'fecha_creacion' => $this->cotizacion->fecha_cotizacion->format('Y-m-d'),
                'fecha_vencimiento' => $this->cotizacion->fecha_vencimiento->format('Y-m-d'),
                'solicitante' => [
                    'name' => $this->solicitante->name,
                    'email' => $this->solicitante->email,
                ],
            ],
            'url' => URL::to('/pages/proveedor/cotizacion/' . $this->cotizacion->id . '/view'),
            'modulo_origen' => $this->moduloOrigen,
            'timestamp' => now()->toISOString(),
        ]));
    }
}
