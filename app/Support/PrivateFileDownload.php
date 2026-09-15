<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga de archivos en disco private con nombre y Content-Type según extensión real.
 * Comprobantes de pago: pdf, jpg, jpeg, png.
 */
final class PrivateFileDownload
{
    /** @var list<string> */
    public const EXTENSIONES_COMPROBANTE = ['pdf', 'jpg', 'jpeg', 'png'];

    public static function extension(?string $path, string $default = 'pdf'): string
    {
        $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        return in_array($ext, self::EXTENSIONES_COMPROBANTE, true) ? $ext : $default;
    }

    public static function mime(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }

    /**
     * @param  string  $nombreBase  Sin extensión (ej. comprobante_SPP-12)
     */
    public static function download(
        string $relativePath,
        string $nombreBase,
        string $disk = 'private'
    ): BinaryFileResponse {
        $ext = self::extension($relativePath);
        $filename = $nombreBase.'.'.$ext;

        return response()->download(
            Storage::disk($disk)->path($relativePath),
            $filename,
            ['Content-Type' => self::mime($ext)]
        );
    }
}
