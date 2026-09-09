<?php

namespace App\Support;

/**
 * Redimensiona y comprime imágenes de anexos (almacenamiento y embebido en PDF).
 */
final class PresupuestoAnexoImagenOptimizer
{
    /**
     * @return array{binary: string, width: int, height: int, aspect_ratio: float, extension: string}
     */
    public static function optimizarParaAlmacenamiento(string $binary): array
    {
        $maxLado = (int) config('presupuestos.anexo_imagen.almacenamiento_max_lado_px', 1280);
        $calidad = (int) config('presupuestos.anexo_imagen.almacenamiento_jpeg_calidad', 78);

        return self::redimensionarYExportarJpeg($binary, $maxLado, $calidad);
    }

    /**
     * Data URI más liviana para DomPDF (no modifica el archivo en disco).
     */
    public static function dataUriParaPdf(string $binary): string
    {
        $maxLado = (int) config('presupuestos.anexo_imagen.pdf_max_lado_px', 900);
        $calidad = (int) config('presupuestos.anexo_imagen.pdf_jpeg_calidad', 72);

        $optimizado = self::redimensionarYExportarJpeg($binary, $maxLado, $calidad);

        return 'data:image/jpeg;base64,' . base64_encode($optimizado['binary']);
    }

    /**
     * @return array{binary: string, width: int, height: int, aspect_ratio: float, extension: string}
     */
    private static function redimensionarYExportarJpeg(string $binary, int $maxLado, int $calidad): array
    {
        if (! extension_loaded('gd')) {
            return self::fallbackSinGd($binary);
        }

        $calidad = max(50, min(95, $calidad));
        $maxLado = max(320, min(4096, $maxLado));

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return self::fallbackSinGd($binary);
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($source);

            return self::fallbackSinGd($binary);
        }

        [$dstW, $dstH] = self::dimensionesEncajar($srcW, $srcH, $maxLado);

        $canvas = imagecreatetruecolor($dstW, $dstH);
        if ($canvas === false) {
            imagedestroy($source);

            return self::fallbackSinGd($binary);
        }

        $blanco = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $blanco);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, $calidad);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        if ($jpeg === '') {
            return self::fallbackSinGd($binary);
        }

        $aspect = $dstH > 0 ? round($dstW / $dstH, 6) : 1.0;

        return [
            'binary' => $jpeg,
            'width' => $dstW,
            'height' => $dstH,
            'aspect_ratio' => $aspect,
            'extension' => 'jpg',
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function dimensionesEncajar(int $srcW, int $srcH, int $maxLado): array
    {
        if ($srcW <= $maxLado && $srcH <= $maxLado) {
            return [$srcW, $srcH];
        }

        $ratio = $srcW / $srcH;
        if ($srcW >= $srcH) {
            $dstW = $maxLado;
            $dstH = (int) max(1, round($maxLado / $ratio));
        } else {
            $dstH = $maxLado;
            $dstW = (int) max(1, round($maxLado * $ratio));
        }

        return [$dstW, $dstH];
    }

    /**
     * @return array{binary: string, width: int, height: int, aspect_ratio: float, extension: string}
     */
    private static function fallbackSinGd(string $binary): array
    {
        $info = @getimagesizefromstring($binary);
        $w = is_array($info) ? (int) ($info[0] ?? 0) : 0;
        $h = is_array($info) ? (int) ($info[1] ?? 0) : 0;
        $aspect = $h > 0 && $w > 0 ? round($w / $h, 6) : 1.0;

        return [
            'binary' => $binary,
            'width' => $w,
            'height' => $h,
            'aspect_ratio' => $aspect,
            'extension' => 'jpg',
        ];
    }
}
