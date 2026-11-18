<?php

namespace App\Notifications\SolicitudPago;

use App\Models\SolicitudPago;
use App\Traits\NotificationStyleTrait;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Notifica a usuarios de la empresa pagadora (canal GestionPlus)
 * cuando el cobrador completa la factura (PDF + XML) tras un pago.
 */
class SolicitudPagoFacturaSubidaGestionPlusNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public function __construct(
        public string $solicitudPagoFolio,
        public int $solicitudPagoId,
        public int $proveedorCobradorId,
        public int $empresaGestionplusId,
        public string $empresaCobradoraNombre = '',
        public ?string $rutaFacturaPdf = null,
        public ?string $rutaFacturaXml = null,
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

        $mailMessage = (new MailMessage)
            ->subject('Factura subida - Solicitud de pago #' . $this->solicitudPagoFolio)
            ->view('emails.solicitud-pago.factura-subida', [
                'notifiable' => $notifiable,
                'solicitudPagoFolio' => $this->solicitudPagoFolio,
                'urlSolicitud' => $frontendUrl . $this->actionUrl(),
                'logoAppDataUri' => $this->resolverLogoProveedorBase64(),
            ]);

        if ($this->rutaFacturaPdf && Storage::disk('private')->exists($this->rutaFacturaPdf)) {
            $mailMessage->attach(
                Storage::disk('private')->path($this->rutaFacturaPdf),
                [
                    'as' => 'factura_' . $this->solicitudPagoFolio . '.pdf',
                    'mime' => 'application/pdf',
                ]
            );
        }

        if ($this->rutaFacturaXml && Storage::disk('private')->exists($this->rutaFacturaXml)) {
            $mailMessage->attach(
                Storage::disk('private')->path($this->rutaFacturaXml),
                [
                    'as' => 'factura_' . $this->solicitudPagoFolio . '.xml',
                    'mime' => 'application/xml',
                ]
            );
        }

        return $mailMessage;
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
                'title' => 'Factura subida',
                'body' => "{$nombre} subió la factura de la SPP #{$this->solicitudPagoFolio}.",
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
        $nombre = $this->empresaCobradoraNombre ?: 'Una empresa';

        return [
            'tipo' => $this->getNotificationTipo(),
            'subtipo' => $this->getNotificationSubtipo(),
            'titulo' => 'Factura subida #' . $this->solicitudPagoFolio,
            'mensaje' => "{$nombre} subió la factura de la solicitud #{$this->solicitudPagoFolio}.",
            'action_url' => $this->actionUrl(),
            // proveedor_id = empresa del destinatario (pagadora), filtro multiempresa
            'proveedor_id' => $this->empresaGestionplusId,
            'data' => [
                'solicitud_pago_folio' => $this->solicitudPagoFolio,
                'solicitud_pago_id' => $this->solicitudPagoId,
                'proveedor_id' => $this->empresaGestionplusId,
                'empresa_cobradora_id' => $this->proveedorCobradorId,
                'empresa_gestionplus_id' => $this->empresaGestionplusId,
                'canal' => 'gestionplus',
                'estatus' => 'factura_subida',
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
        return 'factura_subida';
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
