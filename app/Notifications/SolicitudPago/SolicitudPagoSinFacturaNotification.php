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

class SolicitudPagoSinFacturaNotification extends Notification implements ShouldBroadcastNow
{
  use NotificationStyleTrait;
  public string $solicitudPagoFolio;
  public int $solicitudPagoId;
  public int $proveedorId;
  public int $userId;

  public function __construct(
    string $solicitudPagoFolio,
    int $solicitudPagoId,
    int $proveedorId,
    int $userId = null
  ) {
    $this->solicitudPagoFolio = $solicitudPagoFolio;
    $this->solicitudPagoId = $solicitudPagoId;
    $this->proveedorId = $proveedorId;
    $this->userId = $userId;
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

    return (new MailMessage)
      ->subject('Solicitud de pago sin factura #' . $this->solicitudPagoFolio)
      ->view('emails.solicitud-pago.sin-factura', [
        'notifiable' => $notifiable,
        'solicitudPagoFolio' => $this->solicitudPagoFolio,
        'urlSolicitud' => $frontendUrl . '/pages/proveedor/sp/subir-factura/' . $this->solicitudPagoId,
        'logoAppDataUri' => $this->resolverLogoProveedorBase64(),
      ]);
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
        'title' => 'Solicitud de pago sin factura',
        'body' => "La solicitud #{$this->solicitudPagoFolio} no tiene factura.",
      ],
      $this->addStylesToData([
        'action_url' => '/pages/proveedor/sp/subir-factura/' . $this->solicitudPagoId,
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
      'subtipo' => 'sin_factura',
      'titulo' => 'Solicitud de pago sin factura #' . $this->solicitudPagoFolio,
      'mensaje' => "La solicitud de pago #{$this->solicitudPagoFolio} no tiene factura.",
      'action_url' => '/pages/proveedor/sp/subir-factura/' . $this->solicitudPagoId,
      'solicitud_pago_id' => $this->solicitudPagoId,
      'solicitud_pago_folio' => $this->solicitudPagoFolio,
      'proveedor_id' => $this->proveedorId,
      'estatus' => 'sin_factura',
      'timestamp' => now()->toIso8601String(),
    ];
  }

  protected function getNotificationTipo(): string
  {
    return 'solicitud_pago';
  }

  protected function getNotificationSubtipo(): string
  {
    return 'sin_factura';
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
