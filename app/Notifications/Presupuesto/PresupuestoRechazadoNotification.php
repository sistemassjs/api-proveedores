<?php

namespace App\Notifications\Presupuesto;

use App\Models\Presupuesto;
use App\Services\FcmService;
use App\Support\PresupuestoNotificationContent;
use App\Support\PresupuestoPdf;
use App\Traits\NotificationStyleTrait;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Notificación al proveedor cuando el cliente rechaza el presupuesto.
 */
class PresupuestoRechazadoNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public function __construct(
        public Presupuesto $presupuesto,
        public ?string $motivoRechazo = null
    ) {}

    public function via(object $notifiable): array
    {
        $via = ['broadcast', 'database'];
        if (
            method_exists($notifiable, 'deviceTokens')
            && $notifiable->deviceTokens()->where('is_active', true)->exists()
        ) {
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
        return 'presupuesto';
    }

    public function toArray(object $notifiable): array
    {
        return $this->addStylesToData($this->baseData());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $urlDetalle = $frontendUrl . '/pages/proveedor/presupuestos/preview/' . $this->presupuesto->id;

        $mail = (new MailMessage)
            ->subject('Presupuesto rechazado #' . $this->presupuesto->numero_presupuesto)
            ->view('emails.presupuesto.notificacion-rechazado', [
                'notifiable' => $notifiable,
                'presupuesto' => $this->presupuesto,
                'motivoRechazo' => $this->motivoRechazo,
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
        }

        return $mail;
    }

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
            'motivo_rechazo' => (string) ($base['motivo_rechazo'] ?? ''),
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

    private function baseData(): array
    {
        $esCorreccion = $this->motivoRechazo !== null && trim((string) $this->motivoRechazo) !== '';
        $eventoTitulo = $esCorreccion ? 'correccion' : 'rechazado';

        $mensajeBase = $esCorreccion || $this->motivoRechazo
            ? 'Motivo: '.\Illuminate\Support\Str::limit(trim((string) $this->motivoRechazo), 90)
            : 'Sin motivo indicado';

        return array_merge([
            'tipo' => 'presupuesto',
            'subtipo' => $esCorreccion ? 'correccion_solicitada' : 'rechazado',
            'titulo' => PresupuestoNotificationContent::tituloBandeja($this->presupuesto, $eventoTitulo),
            'mensaje' => PresupuestoNotificationContent::mensajeConHechos($mensajeBase, $this->presupuesto),
            'motivo_rechazo' => $this->motivoRechazo,
            'action_url' => '/pages/proveedor/presupuestos/preview/'.$this->presupuesto->id,
            'presupuesto_id' => $this->presupuesto->id,
            'proveedor_id' => $this->presupuesto->proveedor_id,
            'usuario_envio_id' => $this->presupuesto->user_id,
            'evento' => $esCorreccion ? 'solicitud_correccion' : 'rechazo',
            'estatus' => 'rechazado',
            'timestamp' => now()->toIso8601String(),
        ], PresupuestoNotificationContent::camposEstructurados($this->presupuesto, $eventoTitulo));
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
        return 'rechazado';
    }
}
