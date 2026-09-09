<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Serialización de archivos de anexo para API (URL pública; base64 solo bajo demanda).
 */
final class PresupuestoAnexoArchivoResponse
{
    public static function solicitaArchivoBase64(\Illuminate\Http\Request $request): bool
    {
        return $request->boolean('include_archivo_base64')
            || $request->boolean('archivo_base64');
    }

    public static function archivoPathPublico(?string $archivoPath): ?string
    {
        if (! filled($archivoPath)) {
            return null;
        }

        $path = trim((string) $archivoPath);
        if (str_starts_with($path, 'data:image/')) {
            return null;
        }

        return $path;
    }

    public static function archivoUrl(?string $archivoPath): ?string
    {
        if (! filled($archivoPath)) {
            return null;
        }

        $path = trim((string) $archivoPath);
        if (str_starts_with($path, 'data:image/')) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Data URI (data:image/...;base64,...) para edición o vista sin URL pública.
     */
    public static function archivoBase64(?string $archivoPath): ?string
    {
        if (! filled($archivoPath)) {
            return null;
        }

        $path = trim((string) $archivoPath);
        if (str_starts_with($path, 'data:image/')) {
            return $path;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $binary = Storage::disk('public')->get($path);
        if ($binary === '' || $binary === false) {
            return null;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }
}
