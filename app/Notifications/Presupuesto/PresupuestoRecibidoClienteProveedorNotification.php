<?php

namespace App\Notifications\Presupuesto;

use App\Models\Presupuesto;
use App\Services\FcmService;
use App\Support\PresupuestoNotificationContent;
use App\Traits\NotificationStyleTrait;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Cliente del presupuesto con cuenta en la app (otro proveedor): recibió presupuesto o reenvío.
 */
class PresupuestoRecibidoClienteProveedorNotification extends Notification implements ShouldBroadcastNow
{
    use NotificationStyleTrait;

    public function __construct(
        public Presupuesto $presupuesto,
        public bool $esReenvio = false
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
            'url_publica' => (string) ($base['url_publica'] ?? ''),
            'presupuesto_id' => (string) $base['presupuesto_id'],
            'presupuesto_numero' => (string) $base['presupuesto_numero'],
            'proveedor_emisor_id' => (string) $base['proveedor_emisor_id'],
            'proveedor_receptor_id' => (string) ($base['proveedor_receptor_id'] ?? ''),
            'usuario_envio_nombre' => (string) $base['usuario_envio_nombre'],
            'empresa_emisora_nombre' => (string) $base['empresa_emisora_nombre'],
            'fecha_emision' => (string) ($base['fecha_emision'] ?? ''),
            'destinatario_nombre' => (string) ($base['destinatario_nombre'] ?? ''),
            'empresa_logo_url' => (string) ($base['empresa_logo_url'] ?? ''),
            'es_reenvio' => $base['es_reenvio'] ? '1' : '0',
            'timestamp' => (string) $base['timestamp'],
        ];

        $data = $this->addStylesToData($data);

        app(FcmService::class)->sendToTokens($tokens, $notification, $data);
    }

    private function baseData(): array
    {
        $total = number_format((float) $this->presupuesto->total, 2).' '.($this->presupuesto->term_cond_moneda ?? 'MXN');

        if ($this->esReenvio) {
            $eventoTitulo = 'actualizado';
            $mensajeBase = 'Incluye cambios · Total '.$total;
            $evento = 'reenvio';
        } else {
            $eventoTitulo = 'recibido';
            $mensajeBase = 'Pendiente de autorización · Total '.$total;
            $evento = 'solicitud_autorizacion';
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $urlPublica = $this->presupuesto->token_publico
            ? $frontendUrl.'/public/presupuesto/'.$this->presupuesto->token_publico
            : $frontendUrl.'/public/presupuesto/'.$this->presupuesto->id;

        return array_merge([
            'tipo' => 'presupuesto',
            'subtipo' => 'recibido_cliente_proveedor',
            'titulo' => PresupuestoNotificationContent::tituloBandeja($this->presupuesto, $eventoTitulo),
            'mensaje' => PresupuestoNotificationContent::mensajeConHechos($mensajeBase, $this->presupuesto),
            'action_url' => '/pages/proveedor/presupuestos/preview/'.$this->presupuesto->id,
            'url_publica' => $urlPublica,
            'presupuesto_id' => $this->presupuesto->id,
            'proveedor_emisor_id' => $this->presupuesto->proveedor_id,
            'proveedor_receptor_id' => $this->presupuesto->proveedor_receptor_id,
            'usuario_envio_id' => $this->presupuesto->user_id,
            'evento' => $evento,
            'es_reenvio' => $this->esReenvio,
            'timestamp' => now()->toIso8601String(),
        ], PresupuestoNotificationContent::camposEstructurados($this->presupuesto, $eventoTitulo));
    }

    protected function getNotificationTipo(): string
    {
        return 'presupuesto';
    }

    protected function getNotificationSubtipo(): string
    {
        return 'recibido_cliente_proveedor';
    }
}
