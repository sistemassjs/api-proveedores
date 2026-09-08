<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Convierte archivos del disk `public` a data URI (base64).
 * Útil para endpoints públicos donde el front no puede confiar en /storage.
 */
final class PublicStorageBase64
{
    public static function make(?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null) {
            return null;
        }

        $value = trim($pathOrUrl);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

        $relative = self::toRelativePublicPath($value);
        if ($relative === null) {
            return null;
        }

        try {
            if (! Storage::disk('public')->exists($relative)) {
                return null;
            }

            $binary = Storage::disk('public')->get($relative);
            if ($binary === '' || $binary === false) {
                return null;
            }

            $extension = strtolower((string) pathinfo($relative, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'image/jpeg',
            };

            return 'data:'.$mime.';base64,'.base64_encode($binary);
        } catch (\Throwable $e) {
            Log::warning('No se pudo convertir archivo público a base64', [
                'path' => $relative,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Normaliza path relativo, URL /storage/... o URL absoluta del disk public.
     */
    public static function toRelativePublicPath(?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null) {
            return null;
        }

        $value = trim($pathOrUrl);
        if ($value === '' || str_starts_with($value, 'data:')) {
            return null;
        }

        // URL absoluta → quedarnos con el path
        if (preg_match('/^https?:\/\//i', $value) === 1) {
            $parsed = parse_url($value, PHP_URL_PATH);
            $value = is_string($parsed) ? $parsed : $value;
        }

        $relative = ltrim($value, '/');

        // /storage/foo.png → foo.png (disk public)
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        // A veces viene /gestion/storage/... detrás de proxy
        if (str_contains($relative, '/storage/')) {
            $parts = explode('/storage/', $relative, 2);
            $relative = $parts[1] ?? $relative;
        }

        $relative = ltrim($relative, '/');

        return $relative !== '' ? $relative : null;
    }
}
