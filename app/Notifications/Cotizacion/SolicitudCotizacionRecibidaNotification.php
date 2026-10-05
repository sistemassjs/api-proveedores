<?php

namespace App\Notifications\Cotizacion;

use App\Channels\FcmChannel;
use App\Models\SolicitudCotizacion;
use App\Support\ClientApp;
use App\Traits\NotificationCorrelationId;
use App\Traits\TargetsClientApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica a usuarios del proveedor (solo NexProv) cuando llega una solicitud pública.
 */
class SolicitudCotizacionRecibidaNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationCorrelationId;
    use Queueable;
    use TargetsClientApp;

    public function __construct(
        protected SolicitudCotizacion $solicitud
    ) {
        $this->forClientApps('nexprov');
    }

    public function via(object $notifiable): array
    {
        $channels = ['broadcast', 'database'];

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
        $url = $this->urlDetalle();

        return (new MailMessage)
            ->subject('Nueva solicitud de cotización '.$this->solicitud->folio)
            ->view('emails.cotizacion.solicitud-recibida', [
                'notifiable' => $notifiable,
                'solicitud' => $this->solicitud,
                'urlDetalle' => $url,
            ]);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastType(): string
    {
        return 'solicitud-cotizacion-recibida';
    }

    public function toFcm(object $notifiable): array
    {
        $title = 'Nueva cotización '.$this->solicitud->folio;
        $body = $this->solicitud->cliente_nombre.' solicitó cotización. Total ref.: $'
            .number_format((float) $this->solicitud->total, 2);

        return [
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => '/assets/icon/favicon.png',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'data' => $this->withClientAppMeta($this->withNotificationCorrelationId([
                'type' => 'solicitud-cotizacion',
                'entityId' => (string) $this->solicitud->id,
                'proveedorId' => (string) $this->solicitud->proveedor_id,
                'action' => 'view',
                'title' => $title,
                'body' => $body,
                'url' => $this->urlDetalle(),
                'timestamp' => now()->toISOString(),
            ])),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    private function payload(): array
    {
        return $this->withClientAppMeta($this->withNotificationCorrelationId([
            'tipo' => 'Cotizaciones',
            'titulo' => 'Nueva solicitud de cotización',
            'mensaje' => 'Solicitud '.$this->solicitud->folio.' de '.$this->solicitud->cliente_nombre,
            'icono' => '',
            'data' => [
                'id' => $this->solicitud->id,
                'folio' => $this->solicitud->folio,
                'cliente_nombre' => $this->solicitud->cliente_nombre,
                'total' => (float) $this->solicitud->total,
                'lineas' => $this->solicitud->detalles->count(),
            ],
            'url' => $this->urlDetalle(),
            'timestamp' => now()->toISOString(),
        ]));
    }

    private function urlDetalle(): string
    {
        return ClientApp::frontendPathFor(
            'nexprov',
            'pages/proveedor/cotizaciones/solicitudes/'.$this->solicitud->id
        );
    }
}
