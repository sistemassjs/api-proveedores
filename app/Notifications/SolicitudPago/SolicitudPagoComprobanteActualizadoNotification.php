<?php

namespace App\Notifications\SolicitudPago;

use App\Models\SolicitudPago;
use App\Traits\NotificationStyleTrait;
use App\Services\FcmService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class SolicitudPagoComprobanteActualizadoNotification extends Notification implements ShouldBroadcastNow
{
  use NotificationStyleTrait;

  public $solicitudPagoId;
  public $solicitudPagoFolio;
  public $proveedorId;
  public $userId;
  public $rutaComprobante;
  public $diskComprobante;

  public function __construct(
    string $solicitudPagoFolio,
    int $solicitudPagoId,
    int $proveedorId,
    ?int $userId = null,
    ?string $rutaComprobante = null,
    string $diskComprobante = 'private'
  ) {
    $this->solicitudPagoFolio = $solicitudPagoFolio;
    $this->solicitudPagoId = $solicitudPagoId;
    $this->proveedorId = $proveedorId;
    $this->userId = $userId;
    $this->rutaComprobante = $rutaComprobante;
    $this->diskComprobante = $diskComprobante;
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

    if (
      method_exists($notifiable, 'deviceTokens') &&
      $notifiable->deviceTokens()->where('is_active', true)->exists()
    ) {
      $via[] = 'fcm';
    }

    return $via;
  }

  /**
   * Canal Broadcast (WebSocket)
   */
  public function toBroadcast(object $notifiable): BroadcastMessage
  {
    $data = [
      'tipo' => 'solicitud_pago',
      'subtipo' => 'comprobante_actualizado',
      'titulo' => 'Comprobante de pago actualizado #' . $this->solicitudPagoFolio,
      'mensaje' => "El comprobante de la solicitud de pago #{$this->solicitudPagoFolio} fue actualizado.",
      'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
      'data' => [
        'solicitud_pago_folio' => $this->solicitudPagoFolio,
        'proveedor_id' => $this->proveedorId,
        'estatus' => 'pagada',
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
   * Canal Database
   */
  public function toArray(object $notifiable): array
  {
    $data = [
      'tipo' => 'solicitud_pago',
      'subtipo' => 'comprobante_actualizado',
      'titulo' => 'Comprobante de pago actualizado #' . $this->solicitudPagoFolio,
      'mensaje' => "El comprobante de la solicitud de pago #{$this->solicitudPagoFolio} fue actualizado.",
      'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
      'solicitud_pago_id' => $this->solicitudPagoId,
      'solicitud_pago_folio' => $this->solicitudPagoFolio,
      'proveedor_id' => $this->proveedorId,
      'estatus' => 'pagada',
      'timestamp' => now()->toIso8601String(),
    ];

    return $this->addStylesToData($data);
  }

  /**
   * Canal Mail
   */
  public function toMail(object $notifiable): MailMessage
  {
    $frontendUrl = config('app.frontend_url', config('app.url'));
    $urlSolicitud = $frontendUrl . '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId;

    $mailMessage = (new MailMessage)
      ->subject('Comprobante de pago actualizado #' . $this->solicitudPagoFolio)
      ->view('emails.solicitud-pago.comprobante-actualizado', [
        'notifiable' => $notifiable,
        'solicitudPagoFolio' => $this->solicitudPagoFolio,
        'solicitudPagoId' => $this->solicitudPagoId,
        'proveedorId' => $this->proveedorId,
        'urlSolicitud' => $urlSolicitud,
        'logoAppDataUri' => $this->resolverLogoProveedorBase64(),
      ]);

    if ($this->rutaComprobante && Storage::disk($this->diskComprobante)->exists($this->rutaComprobante)) {
      $extension = pathinfo($this->rutaComprobante, PATHINFO_EXTENSION);
      $mimeTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
      ];

      $mailMessage->attach(
        Storage::disk($this->diskComprobante)->path($this->rutaComprobante),
        [
          'as' => 'comprobante_' . $this->solicitudPagoFolio . '.' . $extension,
          'mime' => $mimeTypes[$extension] ?? 'application/octet-stream',
        ]
      );
    }

    return $mailMessage;
  }

  /**
   * Canal FCM
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
      'title' => 'Comprobante actualizado #' . $this->solicitudPagoFolio,
      'body' => 'El comprobante de tu solicitud de pago fue actualizado.',
    ];

    $data = [
      'tipo' => 'solicitud_pago',
      'subtipo' => 'comprobante_actualizado',
      'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
      'solicitud_pago_folio' => $this->solicitudPagoFolio,
      'proveedor_id' => (string) $this->proveedorId,
      'estatus' => 'pagada',
      'timestamp' => now()->toIso8601String(),
    ];

    $data = $this->addStylesToData($data);

    app(FcmService::class)->sendToTokens($tokens, $notification, $data);
  }

  /**
   * Tipos del trait
   */
  protected function getNotificationTipo(): string
  {
    return 'solicitud_pago';
  }

  protected function getNotificationSubtipo(): string
  {
    return 'comprobante_actualizado';
  }

  private function resolverLogoProveedorBase64(): ?string
  {
    $logo = SolicitudPago::query()
      ->with('proveedor:id,logo')
      ->find($this->solicitudPagoId)?->proveedor?->logo;

    if (!is_string($logo) || trim($logo) === '') {
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

    if (!$logoPath || !is_readable($logoPath)) {
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
