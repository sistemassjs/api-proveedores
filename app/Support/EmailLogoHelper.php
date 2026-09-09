<?php

namespace App\Support;

use App\Models\Proveedor;
use Illuminate\Support\Facades\Storage;

/**
 * Data URIs para imágenes en correos HTML (evita bloqueos por URL externas o rutas incorrectas).
 */
final class EmailLogoHelper
{
    private const MAX_DIMENSION = 320;
    private const MAX_BYTES_RAW = 350000;

    public static function gestionPlusLogoAbsolutePath(): ?string
    {
        foreach ([
            public_path('assets/logos/logo-gestionplus.png'),
            public_path('assets/logos/logo-gestionpro.png'),
        ] as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function logoGestionPlusDataUri(): ?string
    {
        $logoPaths = array_values(array_filter([
            self::gestionPlusLogoAbsolutePath(),
            public_path('assets/logos/logo-construcc.png'),
            public_path('assets/logos/logo-facturapro.png'),
        ], fn (?string $path) => $path !== null && is_readable($path)));

        foreach ($logoPaths as $path) {
            $dataUri = self::fileToDataUri($path);
            if ($dataUri) {
                return $dataUri;
            }
        }

        return null;
    }

    /** @deprecated Use logoGestionPlusDataUri() */
    public static function logoGestionProDataUri(): ?string
    {
        return self::logoGestionPlusDataUri();
    }

    /**
     * Logo del proveedor desde disco (misma resolución de rutas que el PDF de presupuestos).
     */
    public static function proveedorDataUri(?Proveedor $proveedor): ?string
    {
        if (! $proveedor || empty($proveedor->logo)) {
            return null;
        }

        if (filter_var($proveedor->logo, FILTER_VALIDATE_URL)) {
            return null;
        }

        $logoPath = null;
        if (strpos($proveedor->logo, '/') === 0) {
            $logoPath = public_path($proveedor->logo);
        } elseif (strpos($proveedor->logo, 'storage/') === 0) {
            $logoPath = public_path($proveedor->logo);
        } else {
            if (Storage::disk('public')->exists($proveedor->logo)) {
                $logoPath = Storage::disk('public')->path($proveedor->logo);
            } else {
                $logoPath = public_path('storage/'.$proveedor->logo);
            }
        }

        return self::fileToDataUri($logoPath);
    }

    private static function fileToDataUri(?string $absolutePath): ?string
    {
        if (! $absolutePath || ! is_readable($absolutePath)) {
            return null;
        }

        $imageData = @file_get_contents($absolutePath);
        if ($imageData === false || $imageData === '') {
            return null;
        }

        [$normalizedData, $mime] = self::normalizeForEmail($imageData, $absolutePath);

        return 'data:'.$mime.';base64,'.base64_encode($normalizedData);
    }

    /**
     * Normaliza imagen para mayor compatibilidad con Gmail:
     * - evita formatos pesados/inestables para cliente de correo
     * - limita dimensiones y tamaño para evitar recortes o fallos de render
     *
     * @return array{0:string,1:string}
     */
    private static function normalizeForEmail(string $imageData, string $absolutePath): array
    {
        $fallbackMime = self::mimeFromExtension($absolutePath);

        if (! function_exists('imagecreatefromstring')) {
            return [$imageData, $fallbackMime];
        }

        $sourceImage = @imagecreatefromstring($imageData);
        if (! $sourceImage) {
            return [$imageData, $fallbackMime];
        }

        try {
            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);
            if ($width <= 0 || $height <= 0) {
                return [$imageData, $fallbackMime];
            }

            $scale = min(
                1,
                self::MAX_DIMENSION / $width,
                self::MAX_DIMENSION / $height
            );

            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));

            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);

            imagecopyresampled(
                $targetImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $width,
                $height
            );

            ob_start();
            imagepng($targetImage, null, 7);
            $optimizedData = (string) ob_get_clean();
            imagedestroy($targetImage);

            if ($optimizedData === '') {
                return [$imageData, $fallbackMime];
            }

            if (strlen($optimizedData) <= self::MAX_BYTES_RAW || strlen($optimizedData) <= strlen($imageData)) {
                return [$optimizedData, 'image/png'];
            }

            return [$imageData, $fallbackMime];
        } finally {
            imagedestroy($sourceImage);
        }
    }

    private static function mimeFromExtension(string $absolutePath): string
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'png' => 'image/png',
            default => 'image/png',
        };
    }
}
