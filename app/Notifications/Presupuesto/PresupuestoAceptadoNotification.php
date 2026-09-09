<?php

namespace App\Notifications\Presupuesto;

use App\Models\Presupuesto;
use App\Support\PresupuestoNotificationContent;
use App\Support\PresupuestoPdf;
use App\Traits\NotificationStyleTrait;
use App\Services\FcmService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class PresupuestoAceptadoNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public function __construct(
        public Presupuesto $presupuesto
    ) {}

    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];

        if (
            method_exists($notifiable, 'deviceTokens') &&
            $notifiable->deviceTokens()->where('is_active', true)->exists()
        ) {
            $via[] = 'fcm';
        }

        return $via;
    }

    /**
     * Broadcast
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->addStylesToData($this->baseData()));
    }

    public function broadcastType(): string
    {
        return 'presupuesto';
    }

    /**
     * Database
     */
    public function toArray(object $notifiable): array
    {
        return $this->addStylesToData($this->baseData());
    }

    /**
     * Mail
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $urlDetalle = $frontendUrl . '/pages/proveedor/presupuestos/preview/' . $this->presupuesto->id;

        $mail = (new MailMessage)
            ->subject('Presupuesto aceptado #' . $this->presupuesto->numero_presupuesto)
            ->view('emails.presupuesto.notificacion-aceptado', [
                'notifiable' => $notifiable,
                'presupuesto' => $this->presupuesto,
                'urlDetalle' => $urlDetalle,
                'proveedorLogo' => $this->resolverLogoProveedorBase64(),
            ]);

        try {
            $this->presupuesto->loadMissing(Presupuesto::eagerLodable());
            $pdf = PresupuestoPdf::renderPdfBinary($this->presupuesto);

            $mail->attachData(
                $pdf,
                'Presupuesto_' . $this->presupuesto->numero_presupuesto . '.pdf',
                ['mime' => 'application/pdf']
            );
        } catch (\Throwable $e) {
            // opcional: log si quieres visibilidad
        }

        return $mail;
    }

    /**
     * FCM (correcto)
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

        $base = $this->addStylesToData($this->baseData());

        $notification = [
            'title' => $base['titulo'],
            'body' => $base['mensaje'],
        ];

        $data = [
            'titulo' => (string) $base['titulo'],
            'mensaje' => (string) $base['mensaje'],
            'tipo' => 'presupuesto',
            'subtipo' => (string) $base['subtipo'],
            'action_url' => (string) $base['action_url'],
            'presupuesto_id' => (string) $base['presupuesto_id'],
            'presupuesto_numero' => (string) $base['presupuesto_numero'],
            'proveedor_id' => (string) $base['proveedor_id'],
            'estatus' => (string) $base['estatus'],
            'usuario_envio_nombre' => (string) $base['usuario_envio_nombre'],
            'empresa_emisora_nombre' => (string) $base['empresa_emisora_nombre'],
            'fecha_emision' => (string) ($base['fecha_emision'] ?? ''),
            'destinatario_nombre' => (string) ($base['destinatario_nombre'] ?? ''),
            'empresa_logo_url' => (string) ($base['empresa_logo_url'] ?? ''),
            'timestamp' => (string) $base['timestamp'],
        ];

        $data = $this->addStylesToData($data);

        app(FcmService::class)->sendToTokens($tokens, $notification, $data);
    }

    /**
     * Base data
     */
    private function baseData(): array
    {
        $ctx = PresupuestoNotificationContent::contexto($this->presupuesto);
        $mensajeBase = 'Emisión '.$ctx['fecha_emision_display'].' · '.$ctx['emisor_empresa'];

        return array_merge([
            'tipo' => 'presupuesto',
            'subtipo' => 'aceptado',
            'titulo' => PresupuestoNotificationContent::tituloBandeja($this->presupuesto, 'aceptado'),
            'mensaje' => PresupuestoNotificationContent::mensajeConHechos($mensajeBase, $this->presupuesto),
            'action_url' => '/pages/proveedor/presupuestos/preview/'.$this->presupuesto->id,
            'presupuesto_id' => $this->presupuesto->id,
            'proveedor_id' => $this->presupuesto->proveedor_id,
            'usuario_envio_id' => $this->presupuesto->user_id,
            'evento' => 'aceptacion',
            'estatus' => 'aceptado',
            'timestamp' => now()->toIso8601String(),
        ], PresupuestoNotificationContent::camposEstructurados($this->presupuesto, 'aceptado'));
    }

    private function resolverLogoProveedorBase64(): ?string
    {
        $logo = $this->presupuesto->proveedor?->logo;
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

    protected function getNotificationTipo(): string
    {
        return 'presupuesto';
    }

    protected function getNotificationSubtipo(): string
    {
        return 'aceptado';
    }
}
