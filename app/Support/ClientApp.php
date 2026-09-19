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

    public static function frontendUrl(): string
    {
        return rtrim((string) (self::current()['frontend_url'] ?? config('services.frontend.url')), '/');
    }

    public static function frontendPath(string $path): string
    {
        return self::frontendUrl() . '/' . ltrim($path, '/');
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
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('client_apps.apps', []));
    }
}
