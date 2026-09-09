<?php

namespace App\Notifications\SolicitudPago;

use App\Models\SolicitudPago;
use App\Services\FcmService;
use App\Traits\NotificationStyleTrait;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class SolicitudPagoFacturaSubidaNotification extends Notification implements ShouldBroadcastNow
{
  use NotificationStyleTrait;

    public string $solicitudPagoFolio;
    public int $solicitudPagoId;
    public int $proveedorId;
    public int $userId;
    public ?string $rutaFacturaPdf;
    public ?string $rutaFacturaXml;

  public function __construct(
     string $solicitudPagoFolio,
     int $solicitudPagoId,
     int $proveedorId,
     ?int $userId = null,
     ?string $rutaFacturaPdf = null,
     ?string $rutaFacturaXml = null
  ) {
   $this->solicitudPagoFolio= $solicitudPagoFolio;
   $this->solicitudPagoId= $solicitudPagoId;
   $this->proveedorId= $proveedorId;
   $this->userId= $userId;
   $this->rutaFacturaPdf= $rutaFacturaPdf;
   $this->rutaFacturaXml= $rutaFacturaXml;
  }

  /**
   * Canales
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
   * Broadcast
   */
  public function toBroadcast(object $notifiable): BroadcastMessage
  {
    return new BroadcastMessage(
      $this->addStylesToData($this->baseData())
    );
  }

  public function broadcastType(): string
  {
    return 'solicitud-pago';
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

    $mailMessage = (new MailMessage)
      ->subject('Factura subida - Solicitud de pago #' . $this->solicitudPagoFolio)
      ->view('emails.solicitud-pago.factura-subida', [
        'notifiable' => $notifiable,
        'solicitudPagoFolio' => $this->solicitudPagoFolio,
        'urlSolicitud' => $frontendUrl . '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
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

  /**
   * FCM
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

    app(FcmService::class)->sendToTokens(
      $tokens,
      [
        'title' => 'Factura subida',
        'body' => "La solicitud #{$this->solicitudPagoFolio} ya cuenta con factura.",
      ],
      $this->addStylesToData([
        'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
      ])
    );
  }

  /**
   * Datos base compartidos
   */
  private function baseData(): array
  {
    return [
      'tipo' => 'solicitud_pago',
      'subtipo' => 'factura_subida',
      'titulo' => 'Factura subida #' . $this->solicitudPagoFolio,
      'mensaje' => "La solicitud de pago #{$this->solicitudPagoFolio} ya cuenta con factura.",
      'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
      'solicitud_pago_id' => $this->solicitudPagoId,
      'solicitud_pago_folio' => $this->solicitudPagoFolio,
      'proveedor_id' => $this->proveedorId,
      'estatus' => 'factura_subida',
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
