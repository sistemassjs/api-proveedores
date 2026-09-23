<?php

namespace App\Support;

/**
 * Apps cliente (GestionPlus / NexProv) en esta petición.
 * Default: gestion — si el header no viene.
 */
final class ClientApp
{
    private static ?string $currentKey = null;

    public static function key(): string
    {
        return self::$currentKey ?? (string) config('client_apps.default', 'gestion');
    }

    public static function setCurrent(?string $key): void
    {
        self::$currentKey = self::normalize($key);
    }

    public static function normalize(?string $key): string
    {
        $key = strtolower(trim((string) $key));
        $apps = array_keys(config('client_apps.apps', []));

        if ($key !== '' && in_array($key, $apps, true)) {
            return $key;
        }

        return (string) config('client_apps.default', 'gestion');
    }

    /**
     * @return array{name: string, frontend_url: string, logo: string}
     */
    public static function current(): array
    {
        $key = self::key();
        $apps = config('client_apps.apps', []);

        return $apps[$key] ?? $apps[(string) config('client_apps.default', 'gestion')] ?? [
            'name' => (string) config('app.name', 'GestionPlus'),
            'frontend_url' => (string) config('services.frontend.url', 'http://localhost:4300'),
            'logo' => 'assets/logos/logo-gestionplus.png',
        ];
    }

    public static function name(): string
    {
        return (string) (self::current()['name'] ?? config('app.name', 'GestionPlus'));
    }

    public static function nameFor(?string $key): string
    {
        $normalized = self::normalize($key);
        $apps = config('client_apps.apps', []);

        return (string) ($apps[$normalized]['name'] ?? self::name());
    }

    public static function frontendUrl(): string
    {
        return rtrim((string) (self::current()['frontend_url'] ?? config('services.frontend.url')), '/');
    }

    public static function frontendUrlFor(?string $key): string
    {
        $normalized = self::normalize($key);
        $apps = config('client_apps.apps', []);
        $cfg = $apps[$normalized] ?? self::current();

        return rtrim((string) ($cfg['frontend_url'] ?? self::frontendUrl()), '/');
    }

    public static function frontendPath(string $path): string
    {
        return self::frontendUrl() . '/' . ltrim($path, '/');
    }

    public static function frontendPathFor(?string $key, string $path): string
    {
        return self::frontendUrlFor($key) . '/' . ltrim($path, '/');
    }

    public static function logoRelativePath(?string $key = null): string
    {
        if ($key !== null) {
            $apps = config('client_apps.apps', []);
            $normalized = self::normalize($key);

            return (string) ($apps[$normalized]['logo'] ?? 'assets/logos/logo-gestionplus.png');
        }

        return (string) (self::current()['logo'] ?? 'assets/logos/logo-gestionplus.png');
    }

    /**
     * URL pública del logo.
     * Prioriza logo_url de config; si no, endpoint API (Apache en prod no sirve /gestion/assets).
     */
    public static function logoWebUrl(?string $key = null): string
    {
        $normalized = $key !== null ? self::normalize($key) : self::key();
        $apps = config('client_apps.apps', []);
        $cfg = $apps[$normalized] ?? self::current();

        if (! empty($cfg['logo_url'])) {
            return (string) $cfg['logo_url'];
        }

        $relative = ltrim((string) ($cfg['logo'] ?? 'assets/logos/logo-gestionplus.png'), '/');
        $file = basename($relative);

        return url('/api/public/brand-logos/'.$file);
    }

    /**
     * Colores de plantillas auth (header / CTA) según la app.
     *
     * @return array{
     *     header: string,
     *     header_end: string,
     *     cta: string,
     *     cta_end: string,
     *     cta_text: string,
     *     cta_shadow: string,
     *     accent: string,
     *     link: string
     * }
     */
    public static function mailTheme(?string $key = null): array
    {
        $normalized = $key !== null ? self::normalize($key) : self::key();
        $apps = config('client_apps.apps', []);
        $defaults = $apps[(string) config('client_apps.default', 'gestion')]['mail'] ?? [
            'header' => '#2b6cb0',
            'header_end' => '#1d4e89',
            'cta' => '#FFC107',
            'cta_end' => '#FFD54F',
            'cta_text' => '#000000',
            'cta_shadow' => 'rgba(255, 193, 7, 0.4)',
            'accent' => '#FFC107',
            'link' => '#93c5fd',
        ];

        $mail = $apps[$normalized]['mail'] ?? [];

        return array_merge($defaults, is_array($mail) ? $mail : []);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('client_apps.apps', []));
    }
}
