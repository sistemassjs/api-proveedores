<?php

namespace App\Services\CSVImport;

use App\Models\ImportAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CSVImportExportService
{
    private const MAX_ERRORES_PDF = 150;

    /**
     * Exportar resultados de importación en PDF (DomPDF + Blade).
     *
     * @param  string  $format  Solo pdf (compat: otros formatos se rechazan)
     * @param  string  $type  report|summary|errors|data
     */
    public function exportImportResults(ImportAudit $audit, string $format = 'pdf', string $type = 'report'): Response
    {
        try {
            $format = strtolower($format);
            if ($format !== 'pdf') {
                throw new Exception('Solo se admite exportación PDF. Formato recibido: '.$format);
            }

            $type = strtolower($type);
            if (! in_array($type, ['report', 'data', 'summary', 'errors'], true)) {
                throw new Exception("Tipo de exportación no soportado: {$type}");
            }

            if ($type === 'data') {
                $type = 'report';
            }

            $audit->loadMissing('proveedor');

            $data = $this->prepareDataForExport($audit, $type);
            $filename = $this->generateFilename($audit, $type, 'pdf');

            $pdf = Pdf::loadView('catalogo.import-results-pdf', [
                'audit' => $audit,
                'proveedor' => $audit->proveedor,
                'data' => $data,
                'type' => $type,
            ])->setPaper('letter', 'portrait');

            return $pdf->download($filename);
        } catch (Exception $e) {
            Log::error('Error exportando resultados de importación', [
                'audit_id' => $audit->id,
                'format' => $format,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Preparar datos para exportación según el tipo
     */
    private function prepareDataForExport(ImportAudit $audit, string $type): array
    {
        $errores = $audit->errores_detalle ?? [];
        if (! is_array($errores)) {
            $errores = [];
        }

        $totalErrores = count($errores);
        $erroresPdf = array_slice($errores, 0, self::MAX_ERRORES_PDF);

        $base = [
            'audit_id' => $audit->id,
            'proveedor_id' => $audit->proveedor_id,
            'archivo' => $audit->archivo,
            'estado' => $audit->estado,
            'processing_time' => $this->calculateProcessingTime($audit),
            'duracion_texto' => $this->formatProcessingTime($audit),
            'estadisticas' => [
                'total_procesados' => $audit->total_registros ?? 0,
                'nuevos' => $audit->nuevos ?? 0,
                'actualizados' => $audit->actualizados ?? 0,
                'errores' => $audit->errores ?? 0,
                'tasa_exito' => $this->calculateSuccessRate($audit),
            ],
            'breakdown_catalogos' => [
                'marcas' => $this->buildExportCatalogBreakdown(
                    (int) ($audit->marca_imported ?? 0),
                    (int) ($audit->marca_errors ?? 0),
                    (int) ($audit->marca_total ?? 0),
                ),
                'categorias' => $this->buildExportCatalogBreakdown(
                    (int) ($audit->categoria_imported ?? 0),
                    (int) ($audit->categoria_errors ?? 0),
                    (int) ($audit->categoria_total ?? 0),
                ),
                'unidades' => $this->buildExportCatalogBreakdown(
                    (int) ($audit->unidad_imported ?? 0),
                    (int) ($audit->unidad_errors ?? 0),
                    (int) ($audit->unidad_total ?? 0),
                ),
            ],
            'opus' => ($audit->preview_data['opus'] ?? null),
            'errores_detalle' => $erroresPdf,
            'errores_omitidos' => max(0, $totalErrores - count($erroresPdf)),
        ];

        if ($type === 'summary') {
            $base['errores_detalle'] = [];
            $base['errores_omitidos'] = $totalErrores;
        }

        return $base;
    }

    private function generateFilename(ImportAudit $audit, string $type, string $extension): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        return "import_{$type}_audit_{$audit->id}_proveedor_{$audit->proveedor_id}_{$timestamp}.{$extension}";
    }

    private function calculateProcessingTime(ImportAudit $audit): float
    {
        if ($audit->inicio_proceso && $audit->fin_proceso) {
            return round($audit->fin_proceso->diffInSeconds($audit->inicio_proceso, true), 2);
        }

        return (float) ($audit->processing_time ?? 0);
    }

    private function formatProcessingTime(ImportAudit $audit): string
    {
        $seconds = (int) round($this->calculateProcessingTime($audit));

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds < 3600) {
            $minutes = intdiv($seconds, 60);
            $remainingSeconds = $seconds % 60;

            return "{$minutes}m {$remainingSeconds}s";
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return "{$hours}h {$minutes}m";
    }

    private function calculateSuccessRate(ImportAudit $audit): float
    {
        $total = $audit->total_registros ?? 0;
        if ($total === 0) {
            return 0.0;
        }

        $successful = ($audit->nuevos ?? 0) + ($audit->actualizados ?? 0);

        return round(($successful / $total) * 100, 2);
    }

    /**
     * @return array{nuevas:int,existentes:int,procesadas:int,errores:int,total:int,importadas:int}
     */
    private function buildExportCatalogBreakdown(int $created, int $errors, int $total): array
    {
        $existing = max(0, $total - $created - $errors);
        $processed = $created + $existing;

        return [
            'nuevas' => $created,
            'existentes' => $existing,
            'procesadas' => $processed,
            'importadas' => $processed,
            'errores' => $errors,
            'total' => $total,
        ];
    }
}
