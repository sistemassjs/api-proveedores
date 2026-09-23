<?php

namespace App\Traits;

use App\Services\FcmService;
use App\Support\ClientApp;

/**
 * Destino de push FCM por app cliente (gestion | nexprov).
 * Por defecto: app de la petición actual (X-Client-App), o gestion.
 */
trait TargetsClientApp
{
    /** @var list<string>|null */
    protected ?array $fcmAppKeys = null;

    /**
     * Fija una o más apps destino (fluent).
     */
    public function forClientApps(string ...$keys): static
    {
        $normalized = [];
        foreach ($keys as $key) {
            $normalized[] = ClientApp::normalize($key);
        }
        $this->fcmAppKeys = array_values(array_unique($normalized));

        return $this;
    }

    /**
     * @return list<string>
     */
    public function fcmTargetAppKeys(): array
    {
        if ($this->fcmAppKeys !== null && $this->fcmAppKeys !== []) {
            return $this->fcmAppKeys;
        }

        return [ClientApp::key()];
    }

    /**
     * Meta para payload broadcast / database / FCM data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withClientAppMeta(array $data): array
    {
        $keys = $this->fcmTargetAppKeys();

        return array_merge($data, [
            'app_key' => $keys[0] ?? ClientApp::key(),
        ]);
    }

    protected function notifiableHasFcmTokens(object $notifiable): bool
    {
        return method_exists($notifiable, 'hasActiveDeviceTokensForApps')
            && $notifiable->hasActiveDeviceTokensForApps($this->fcmTargetAppKeys());
    }

    /**
     * @return list<string>
     */
    protected function fcmTokensForNotifiable(object $notifiable): array
    {
        if (! method_exists($notifiable, 'fcmTokensForApps')) {
            return [];
        }

        return $notifiable->fcmTokensForApps($this->fcmTargetAppKeys());
    }

    /**
     * Envía FCM solo a tokens de las apps destino, con app_key correcto por lote.
     *
     * @param  array{title?: string, body?: string}  $notification
     * @param  array<string, mixed>  $data
     */
    protected function sendFcmToNotifiable(object $notifiable, array $notification, array $data): bool
    {
        return app(FcmService::class)->sendToNotifiableApps(
            $notifiable,
            $this->fcmTargetAppKeys(),
            $notification,
            $data
        );
    }
}
