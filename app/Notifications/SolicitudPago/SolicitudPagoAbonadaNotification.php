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

class SolicitudPagoAbonadaNotification extends Notification implements ShouldBroadcastNow
{
  use NotificationStyleTrait;

  public $solicitudPagoId;
  public $solicitudPagoFolio;
  public $proveedorId;
  public $montoAbonado;
  public $montoRestante;
  public $montoAcumulado;
  public $saldoInicial;
  public $userId;

  public function __construct(
    string $solicitudPagoFolio,
    int $solicitudPagoId,
    int $proveedorId,
    float $montoAbonado = null,
    float $montoRestante = null,
    int $userId = null,
    float $montoAcumulado = null,
    float $saldoInicial = null
  ) {
    $this->solicitudPagoFolio = $solicitudPagoFolio;
    $this->solicitudPagoId = $solicitudPagoId;
    $this->proveedorId = $proveedorId;
    $this->montoAbonado = $montoAbonado;
    $this->montoRestante = $montoRestante;
    $this->userId = $userId;
    $this->montoAcumulado = $montoAcumulado;
    $this->saldoInicial = $saldoInicial;
    $this->solicitudPagoFolio = $solicitudPagoFolio;
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

    if (method_exists($notifiable, 'deviceTokens') && $notifiable->deviceTokens()->where('is_active', true)->exists()) {
      $via[] = 'fcm';
    }

    return $via;
  }

  /**
   * Canal Broadcast
   */
  public function toBroadcast(object $notifiable): BroadcastMessage
  {
    $data = [
      'tipo' => 'solicitud_pago',
      'subtipo' => 'abonada',
      'titulo' => 'Abono registrado en solicitud de pago #' . $this->solicitudPagoFolio,
      'mensaje' => "Se registró un abono en tu solicitud de pago #{$this->solicitudPagoFolio}.",
      'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
      'data' => [
        'solicitud_pago_folio' => $this->solicitudPagoFolio,
        'proveedor_id' => $this->proveedorId,
        'monto_abonado' => $this->montoAbonado,
        'monto_acumulado' => $this->montoAcumulado,
        'saldo_inicial' => $this->saldoInicial,
        'monto_restante' => $this->montoRestante,
        'estatus' => 'abonada',
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
      'subtipo' => 'abonada',
      'titulo' => 'Abono registrado en solicitud de pago #' . $this->solicitudPagoFolio,
      'mensaje' => "Se registró un abono en tu solicitud de pago #{$this->solicitudPagoFolio}.",
      'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
      'solicitud_pago_id' => $this->solicitudPagoId,
      'solicitud_pago_folio' => $this->solicitudPagoFolio,
      'proveedor_id' => $this->proveedorId,
      'monto_abonado' => $this->montoAbonado,
      'monto_acumulado' => $this->montoAcumulado,
      'saldo_inicial' => $this->saldoInicial,
      'monto_restante' => $this->montoRestante,
      'estatus' => 'abonada',
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

    return (new MailMessage)
      ->subject('Abono registrado en solicitud de pago #' . $this->solicitudPagoFolio)
      ->view('emails.solicitud-pago.abonada', [
        'notifiable' => $notifiable,
        'solicitudPagoFolio' => $this->solicitudPagoFolio,
        'solicitudPagoId' => $this->solicitudPagoId,
        'proveedorId' => $this->proveedorId,
        'montoAbonado' => $this->montoAbonado,
        'montoAcumulado' => $this->montoAcumulado,
        'saldoInicial' => $this->saldoInicial,
        'montoRestante' => $this->montoRestante,
        'urlSolicitud' => $urlSolicitud,
        'logoAppDataUri' => $this->resolverLogoProveedorBase64(),
      ]);
  }

  /**
   * Canal FCM personalizado
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
      'title' => 'Abono registrado - SPP #' . $this->solicitudPagoFolio,
      'body' => 'Se registró un abono en tu solicitud de pago.',
    ];

    $data = [
      'tipo' => 'solicitud_pago',
      'subtipo' => 'abonada',
      'action_url' => '/pages/proveedor/sp/detalle/' . $this->solicitudPagoId,
      'solicitud_pago_folio' => $this->solicitudPagoFolio,
      'proveedor_id' => (string) $this->proveedorId,
      'monto_abonado' => $this->montoAbonado  ? (string) $this->montoAbonado : null,
      'monto_acumulado' => $this->montoAcumulado  ? (string) $this->montoAcumulado : null,
      'saldo_inicial' => $this->saldoInicial  ? (string) $this->saldoInicial : null,
      'monto_restante' => $this->montoRestante  ? (string) $this->montoRestante : null,
      'estatus' => 'abonada',
      'timestamp' => now()->toIso8601String(),
    ];

    $data = $this->addStylesToData($data);
    app(FcmService::class)->sendToTokens($tokens, $notification, $data);
  }

  /**
   * Implementación de métodos abstractos del trait
   */
  protected function getNotificationTipo(): string
  {
    return 'solicitud_pago';
  }

  protected function getNotificationSubtipo(): string
  {
    return 'abonada';
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
