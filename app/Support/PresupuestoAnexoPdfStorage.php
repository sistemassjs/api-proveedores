<?php

namespace App\Support;

use App\Models\Presupuesto;
use App\Models\PresupuestoPlantilla;
use App\Models\Proveedor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Guardado y conteo de páginas de PDFs de anexo de presupuesto.
 */
final class PresupuestoAnexoPdfStorage
{
    /**
     * Decodifica un data URI de PDF (application/pdf u octet-stream en base64).
     *
     * @throws \InvalidArgumentException
     */
    public static function decodificarPdfDesdeDataUri(string $dataUri): string
    {
        $dataUri = trim($dataUri);
        if ($dataUri === '') {
            throw new \InvalidArgumentException('El PDF del anexo es obligatorio.');
        }

        if (! preg_match('#^data:([^,]+),(.+)$#is', $dataUri, $matches)) {
            throw new \InvalidArgumentException('El PDF del anexo no es válido.');
        }

        $meta = strtolower($matches[1]);
        if (! str_contains($meta, 'base64')) {
            throw new \InvalidArgumentException('El PDF del anexo debe enviarse en base64.');
        }

        if (
            ! str_contains($meta, 'application/pdf')
            && ! str_contains($meta, 'application/octet-stream')
        ) {
            throw new \InvalidArgumentException('El PDF del anexo debe estar en formato application/pdf en base64.');
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('El PDF del anexo no es válido.');
        }

        if (! str_starts_with($binary, '%PDF')) {
            throw new \InvalidArgumentException('El archivo no es un PDF válido.');
        }

        $maxBytes = (int) config('presupuestos.anexo_pdf.max_bytes', 50 * 1024 * 1024);
        if (strlen($binary) > $maxBytes) {
            throw new \InvalidArgumentException('El PDF del anexo supera el tamaño máximo permitido.');
        }

        return $binary;
    }

    /**
     * @return array{path: string, paginas: int}
     */
    public static function guardarPdfBase64(Proveedor $proveedor, Presupuesto $presupuesto, string $dataUri): array
    {
        $path = sprintf(
            'proveedores/%d/presupuestos/%d/anexos-pdf/%s.pdf',
            (int) $proveedor->id,
            (int) $presupuesto->id,
            Str::uuid()->toString()
        );

        return self::guardarPdfBase64EnPath($path, $dataUri);
    }

    /**
     * Guarda un PDF de anexo asociado a una plantilla de presupuesto.
     *
     * @return array{path: string, paginas: int}
     */
    public static function guardarPdfBase64Plantilla(
        Proveedor $proveedor,
        PresupuestoPlantilla $plantilla,
        string $dataUri
    ): array {
        $path = sprintf(
            'presupuesto-plantillas/%d/%d/anexos-pdf/%s.pdf',
            (int) $proveedor->id,
            (int) $plantilla->id,
            Str::uuid()->toString()
        );

        return self::guardarPdfBase64EnPath($path, $dataUri);
    }

    /**
     * @return array{path: string, paginas: int}
     */
    public static function guardarPdfBase64EnPath(string $path, string $dataUri): array
    {
        $binary = self::decodificarPdfDesdeDataUri($dataUri);

        Storage::disk('public')->put($path, $binary);

        $absolutePath = Storage::disk('public')->path($path);
        self::prepararParaImportacionFpdi($absolutePath);
        $paginas = self::contarPaginasPdf($absolutePath);

        return [
            'path' => $path,
            'paginas' => $paginas,
        ];
    }

    public static function contarPaginasPdf(string $absolutePath): int
    {
        if (! is_file($absolutePath)) {
            return 1;
        }

        $fpdi = self::contarPaginasConFpdi($absolutePath);
        if ($fpdi !== null) {
            return $fpdi;
        }

        $smalot = self::contarPaginasConSmalot($absolutePath);
        if ($smalot !== null) {
            return $smalot;
        }

        $regex = self::contarPaginasHeuristica($absolutePath);
        if ($regex !== null) {
            Log::info('Conteo de páginas de anexo PDF por heurística en archivo.', [
                'path' => $absolutePath,
                'paginas' => $regex,
            ]);

            return $regex;
        }

        Log::warning('No fue posible contar páginas del anexo PDF; se asume 1.', [
            'path' => $absolutePath,
        ]);

        return 1;
    }

    public static function prepararParaImportacionFpdi(string $absolutePath): void
    {
        self::asegurarLegiblePorFpdi($absolutePath);
    }

    private static function asegurarLegiblePorFpdi(string $absolutePath): void
    {
        if (self::fpdiPuedeLeer($absolutePath)) {
            return;
        }

        if (self::normalizarConGhostscript($absolutePath) && self::fpdiPuedeLeer($absolutePath)) {
            return;
        }

        Log::warning('Anexo PDF guardado pero FPDI no puede importarlo; el merge podría omitir este archivo.', [
            'path' => $absolutePath,
        ]);
    }

    private static function fpdiPuedeLeer(string $absolutePath): bool
    {
        try {
            $pdf = new Fpdi();
            $pdf->setSourceFile($absolutePath);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function normalizarConGhostscript(string $absolutePath): bool
    {
        $bin = config('presupuestos.anexo_pdf.ghostscript_bin');
        if (! is_string($bin) || trim($bin) === '') {
            return false;
        }

        $bin = trim($bin);
        if (! is_file($bin)) {
            return false;
        }

        $out = $absolutePath.'.norm.pdf';
        if (is_file($out)) {
            @unlink($out);
        }

        $cmd = sprintf(
            '"%s" -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile="%s" "%s"',
            str_replace('"', '', $bin),
            str_replace('"', '', $out),
            str_replace('"', '', $absolutePath)
        );

        try {
            $output = [];
            $exitCode = 1;
            @exec($cmd, $output, $exitCode);
            if ($exitCode !== 0 || ! is_file($out) || filesize($out) === 0) {
                if (is_file($out)) {
                    @unlink($out);
                }

                return false;
            }

            if (! @rename($out, $absolutePath)) {
                @unlink($out);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            if (is_file($out)) {
                @unlink($out);
            }
            Log::warning('Ghostscript no pudo normalizar anexo PDF.', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function contarPaginasConFpdi(string $absolutePath): ?int
    {
        try {
            $pdf = new Fpdi();
            $count = $pdf->setSourceFile($absolutePath);

            return max(1, (int) $count);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function contarPaginasConSmalot(string $absolutePath): ?int
    {
        try {
            $parser = new PdfParser();
            $document = $parser->parseFile($absolutePath);
            $pages = $document->getPages();
            $count = is_countable($pages) ? count($pages) : 0;

            return $count > 0 ? max(1, $count) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function contarPaginasHeuristica(string $absolutePath): ?int
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false || $content === '') {
            return null;
        }

        if (preg_match_all('/\/Type\s*\/Page(?!s)\b/', $content, $matches)) {
            $n = count($matches[0]);

            return $n > 0 ? $n : null;
        }

        return null;
    }
}
