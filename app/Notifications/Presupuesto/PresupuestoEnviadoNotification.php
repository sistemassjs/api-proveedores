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

class PresupuestoEnviadoNotification extends Notification implements ShouldBroadcastNow
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
            ->subject('Presupuesto enviado #' . $this->presupuesto->numero_presupuesto)
            ->view('emails.presupuesto.notificacion-enviado', [
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
            // silencio elegante 😌
        }

        return $mail;
    }

    /**
     * FCM (MISMO PATRÓN CORRECTO)
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

        $dataBase = $this->addStylesToData($this->baseData());

        $notification = [
            'title' => $dataBase['titulo'],
            'body' => $dataBase['mensaje'],
        ];

        $data = [
            'titulo' => (string) $dataBase['titulo'],
            'mensaje' => (string) $dataBase['mensaje'],
            'tipo' => 'presupuesto',
            'subtipo' => (string) $dataBase['subtipo'],
            'action_url' => (string) $dataBase['action_url'],
            'presupuesto_id' => (string) $dataBase['presupuesto_id'],
            'presupuesto_numero' => (string) $dataBase['presupuesto_numero'],
            'proveedor_id' => (string) $dataBase['proveedor_id'],
            'estatus' => (string) $dataBase['estatus'],
            'usuario_envio_nombre' => (string) $dataBase['usuario_envio_nombre'],
            'empresa_emisora_nombre' => (string) $dataBase['empresa_emisora_nombre'],
            'fecha_emision' => (string) ($dataBase['fecha_emision'] ?? ''),
            'destinatario_nombre' => (string) ($dataBase['destinatario_nombre'] ?? ''),
            'empresa_logo_url' => (string) ($dataBase['empresa_logo_url'] ?? ''),
            'timestamp' => (string) $dataBase['timestamp'],
        ];

        $data = $this->addStylesToData($data);

        app(FcmService::class)->sendToTokens($tokens, $notification, $data);
    }

    /**
     * Base de datos compartida
     */
    private function baseData(): array
    {
        $ctx = PresupuestoNotificationContent::contexto($this->presupuesto);
        $mensajeBase = 'Para '.$ctx['destinatario'].' · Emisión '.$ctx['fecha_emision_display'];

        return array_merge([
            'tipo' => 'presupuesto',
            'subtipo' => 'enviado',
            'titulo' => PresupuestoNotificationContent::tituloBandeja($this->presupuesto, 'enviado'),
            'mensaje' => PresupuestoNotificationContent::mensajeConHechos($mensajeBase, $this->presupuesto),
            'action_url' => '/pages/proveedor/presupuestos/preview/'.$this->presupuesto->id,
            'presupuesto_id' => $this->presupuesto->id,
            'proveedor_id' => $this->presupuesto->proveedor_id,
            'usuario_envio_id' => $this->presupuesto->user_id,
            'evento' => 'envio',
            'estatus' => 'enviado',
            'timestamp' => now()->toIso8601String(),
        ], PresupuestoNotificationContent::camposEstructurados($this->presupuesto, 'enviado'));
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
        return 'enviado';
    }
}
