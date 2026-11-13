<?php

namespace App\Notifications\SolicitudPago;

use App\Traits\NotificationStyleTrait;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SolicitudPagoRecibidaGestionPlusNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public function __construct(
        public string $solicitudPagoFolio,
        public int $solicitudPagoId,
        public int $proveedorCobradorId,
        public int $empresaGestionplusId,
        public string $empresaCobradoraNombre = '',
        public ?float $monto = null,
    ) {}

    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];

        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $via[] = 'mail';
        }

        if ($this->notifiableHasFcmTokens($notifiable)) {
            $via[] = 'fcm';
        }

        return $via;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->addStylesToData($this->baseData()));
    }

    public function broadcastType(): string
    {
        return 'solicitud-pago';
    }

    public function toArray(object $notifiable): array
    {
        return $this->addStylesToData($this->baseData());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $nombre = $this->empresaCobradoraNombre ?: 'Una empresa';

        return (new MailMessage)
            ->subject('Nueva solicitud de pago #' . $this->solicitudPagoFolio)
            ->line("{$nombre} te envió la solicitud de pago #{$this->solicitudPagoFolio}.")
            ->action('Ver solicitud', $frontendUrl . $this->actionUrl());
    }

    public function toFcm(object $notifiable): void
    {
        if (! $this->notifiableHasFcmTokens($notifiable)) {
            return;
        }

        $nombre = $this->empresaCobradoraNombre ?: 'Una empresa';

        $this->sendFcmToNotifiable(
            $notifiable,
            [
                'title' => 'Nueva solicitud de pago',
                'body' => "{$nombre} envió la SPP #{$this->solicitudPagoFolio}.",
            ],
            $this->addStylesToData([
                'action_url' => $this->actionUrl(),
            ])
        );
    }

    private function actionUrl(): string
    {
        return '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId . '?modo=recibida';
    }

    private function baseData(): array
    {
        return [
            'tipo' => $this->getNotificationTipo(),
            'subtipo' => $this->getNotificationSubtipo(),
            'titulo' => 'Nueva solicitud de pago #' . $this->solicitudPagoFolio,
            'mensaje' => ($this->empresaCobradoraNombre ?: 'Una empresa')
                . " te envió la solicitud #{$this->solicitudPagoFolio}.",
            'action_url' => $this->actionUrl(),
            // proveedor_id = empresa del destinatario (pagadora), para filtro multiempresa
            'proveedor_id' => $this->empresaGestionplusId,
            'data' => [
                'solicitud_pago_folio' => $this->solicitudPagoFolio,
                'solicitud_pago_id' => $this->solicitudPagoId,
                'proveedor_id' => $this->empresaGestionplusId,
                'empresa_cobradora_id' => $this->proveedorCobradorId,
                'empresa_gestionplus_id' => $this->empresaGestionplusId,
                'monto' => $this->monto,
                'canal' => 'gestionplus',
                'estatus' => 'pendiente',
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function getNotificationTipo(): string
    {
        return 'solicitud_pago';
    }

    protected function getNotificationSubtipo(): string
    {
        return 'recibida_gestionplus';
    }
}
