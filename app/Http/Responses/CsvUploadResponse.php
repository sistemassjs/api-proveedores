<?php

namespace App\Http\Responses;

/**
 * Response para el endpoint de carga CSV
 * POST /api/proveedores/{id}/csv-import
 */
class CsvUploadResponse
{
    public function __construct(
        public int $auditId,
        public string $jobId,
        public string $previewToken,
        public array $fileInfo,
        public array $headers,
        public array $previewData,
        public array $validationSummary,
        public array $validationDetails,
        public array $qualityMetrics,
        public array $processingInfo,
        public array $catalogos,
        public string $estado = 'preview',
        public string $mensaje = 'Archivo CSV analizado correctamente. Revise los datos de vista previa antes de confirmar la importación.'
    ) {}

    /**
     * Convierte la respuesta a array para ApiResponse
     */
    public function toArray(): array
    {
        return [
            'audit_id' => $this->auditId,
            'job_id' => $this->jobId,
            'preview_token' => $this->previewToken,
            'file_info' => $this->fileInfo,
            'headers' => $this->headers,
            'preview_data' => $this->previewData,
            'validation_summary' => $this->validationSummary,
            'validation_details' => $this->validationDetails,
            'quality_metrics' => $this->qualityMetrics,
            'processing_info' => $this->processingInfo,
            'catalogos' => $this->catalogos,
            'estado' => $this->estado,
            'mensaje' => $this->mensaje,
            'plantilla' => [
                'version' => \App\Support\Catalogo\CatalogoImportPlantilla::VERSION,
                'fecha' => \App\Support\Catalogo\CatalogoImportPlantilla::FECHA,
            ],
            'camino' => 'csv-import-servidor',
            'nota' => 'Importación masiva en servidor (temp tables + job). Independiente de productos/bulk (localStorage).',
        ];
    }

    /**
     * Crear desde array de resultados del procesador CSV
     */
    public static function fromProcessorResult(int $auditId, string $jobId, array $processingResult): self
    {
        return new self(
            auditId: $auditId,
            jobId: $jobId,
            previewToken: $processingResult['preview_token'],
            fileInfo: $processingResult['file_info'],
            headers: $processingResult['headers'],
            previewData: $processingResult['preview_data'],
            validationSummary: $processingResult['validation_summary'],
            validationDetails: $processingResult['validation_details'],
            qualityMetrics: $processingResult['quality_metrics'],
            processingInfo: $processingResult['processing_info'],
            catalogos: $processingResult['catalogos'] ?? []
        );
    }
}
