<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * URL pública de archivos del disk `public`.
 * Usa APP_URL (p. ej. https://api.rorisafe.com/gestion) y no la raíz del request proxied.
 */
final class PublicStorageUrl
{
    public static function make(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        return Storage::disk('public')->url($relative);
    }
}
