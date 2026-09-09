<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * URLs de archivos PDF de anexo (sin base64 en listados).
 */
final class PresupuestoAnexoPdfArchivoResponse
{
    public static function archivoUrl(?string $archivoPath): ?string
    {
        if (! filled($archivoPath)) {
            return null;
        }

        $path = trim((string) $archivoPath);
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function archivoAbsoluto(?string $archivoPath): ?string
    {
        if (! filled($archivoPath)) {
            return null;
        }

        $path = trim((string) $archivoPath);
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }
}
