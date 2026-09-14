<?php

namespace App\Services\CSVImport;

use App\Models\Categoria;
use App\Models\ImportValidationCache;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio para procesar archivos CSV y generar una vista previa
 *
 * Este servicio se encarga de:
 * - Leer y parsear el archivo CSV.
 * - Validar encabezados y filas (usando CSVImportProductValidator).
 * - Calcular métricas de calidad, desglose de catálogo y sugerencias.
 * - Cachear datos para confirmación.
 * - Retornar una estructura de datos que el controlador puede presentar al usuario.
 *
 * El controlador que lo consume debe:
 * - Invocar `processCSVPreview(...)`.
 * - Procesar el arreglo resultante (éxito, datos para vista previa, métricas, token, etc.).
 * - Manejar errores según el valor de retorno (`success => false`) o excepciones lanzadas.
 */
class CSVProcessorService
{
    /**
     * Procesa un archivo CSV generando una vista previa y validaciones.
     * Optimizado para usar poca memoria con archivos grandes.
     *
     * @param  UploadedFile  $csvFile  Archivo CSV subido.
     * @param  int  $proveedorId  ID del proveedor para contexto.
     * @param  array  $options  Opciones de parseo y validación (delimiter, encoding, etc.).
     * @return array Estructura con:
     *               - success: bool
     *               - preview_token: string|null
     *               - file_info: array
     *               - headers: array ('detected', 'validation', 'mapping_suggestions')
     *               - preview_data: array (primeras filas)
     *               - validation_summary: array
     *               - validation_details: array
     *               - quality_metrics: array (incluye can_proceed, quality_score, recommendation)
     *               - processing_info: array (tiempo, memoria, can_proceed)
     *               - catalogos: array (desglose de productos, marcas, categorías, etc.)
     *               - error y error_type en caso de fallo
     *
     * @throws \Throwable En caso de error inesperado durante el procesamiento.
     */
    public function processCSVPreview(
        UploadedFile $csvFile,
        int $proveedorId,
        array $options = []
    ): array {
        $startTime = microtime(true);
        $token = $this->generatePreviewToken();

        // Opciones por defecto
        $options = array_merge([
            'delimiter' => ',',
            'encoding' => 'UTF-8',
            'has_header' => true,
            'preview_rows' => 100,
            'strict_validation' => false,
            'auto_create_relations' => false,
            'chunk_size' => 500, // Tamaño de chunk para procesamiento
        ], $options);

        try {
            // Crear validador para este proveedor
            $validator = new CSVImportProductValidator($proveedorId);

            // Variables para procesamiento incremental
            $headers = [];
            $previewData = [];
            $totalRows = 0;
            $catalogos = [
                'marcas' => [],
                'categorias' => [],
                'unidades' => [],
            ];
            $validationErrors = [];
            $validationWarnings = [];
            $correctRecords = 0;
            $errorRecords = 0;
            $warningRecords = 0;

            // Crear tabla temporal para almacenar datos completos
            $tableName = $this->createTempTable($token);

            // Buffer para inserción por lotes
            $insertBuffer = [];
            $bufferSize = 0;

            // Procesar archivo usando generador (memoria eficiente)
            foreach ($this->parseCSVGenerator($csvFile, $options) as $rowIndex => $row) {
                // Capturar headers en la primera iteración
                if ($rowIndex === 0 && $options['has_header']) {
                    $headers = array_keys($row);
                }

                $totalRows++;

                // Añadir a vista previa si está dentro del límite
                if (count($previewData) < $options['preview_rows']) {
                    $previewData[] = $row;

                    // Validar fila para preview
                    $rowValidation = $validator->validateRow($row, $rowIndex + 1);
                    if (! empty($rowValidation['errors'])) {
                        $errorRecords++;
                        if (count($validationErrors) < 20) { // Limitar errores almacenados
                            $validationErrors[] = [
                                'row' => $rowIndex + 1,
                                'errors' => $rowValidation['errors'],
                            ];
                        }
                    } elseif (! empty($rowValidation['warnings'])) {
                        $warningRecords++;
                        if (count($validationWarnings) < 20) {
                            $validationWarnings[] = [
                                'row' => $rowIndex + 1,
                                'warnings' => $rowValidation['warnings'],
                            ];
                        }
                    } else {
                        $correctRecords++;
                    }
                }

                // Extraer catálogos únicos (sin almacenar todos los datos)
                if (! empty($row['marca']) && ! in_array($row['marca'], $catalogos['marcas'])) {
                    $catalogos['marcas'][] = trim($row['marca']);
                }
                if (! empty($row['unidad_medida']) && ! in_array($row['unidad_medida'], $catalogos['unidades'])) {
                    $catalogos['unidades'][] = trim($row['unidad_medida']);
                }
                foreach (['unidad_contenido', 'unidad_base'] as $unidadCol) {
                    if (! empty($row[$unidadCol])) {
                        $u = trim((string) $row[$unidadCol]);
                        if ($u !== '' && ! in_array($u, $catalogos['unidades'], true)) {
                            $catalogos['unidades'][] = $u;
                        }
                    }
                }
                if (! empty($row['categoria'])) {
                    $cat = trim($row['categoria']);
                    if (! isset($catalogos['categorias'][$cat])) {
                        $catalogos['categorias'][$cat] = [];
                    }
                    if (! empty($row['subcategoria'])) {
                        $subcat = trim($row['subcategoria']);
                        if (! in_array($subcat, $catalogos['categorias'][$cat])) {
                            $catalogos['categorias'][$cat][] = $subcat;
                        }
                    }
                }

                // Preparar datos para inserción en tabla temporal
                $insertBuffer[] = $this->prepareRowForTempTable($row, $rowIndex + 1);
                $bufferSize++;

                // Insertar en lotes cuando el buffer alcance el tamaño del chunk
                if ($bufferSize >= $options['chunk_size']) {
                    $this->insertChunkToTempTable($tableName, $insertBuffer);
                    $insertBuffer = [];
                    $bufferSize = 0;

                    // Liberar memoria periódicamente
                    if ($totalRows % 2000 === 0) {
                        gc_collect_cycles();
                    }
                }
            }

            // Insertar cualquier dato restante en el buffer
            if (! empty($insertBuffer)) {
                $this->insertChunkToTempTable($tableName, $insertBuffer);
            }

            // Validar encabezados
            $headerValidation = [];
            if ($options['has_header'] && ! empty($headers)) {
                $headerValidation = $validator->validateHeaders($headers);
            }

            // Crear resumen de validación
            $validationSummary = [
                'total_records' => min($totalRows, $options['preview_rows']),
                'correct_records' => $correctRecords,
                'warning_records' => $warningRecords,
                'error_records' => $errorRecords,
                'errors_by_type' => [],
            ];

            // Generar métricas de calidad basadas en el preview
            $qualityMetrics = $this->generateQualityMetricsFromSummary($validationSummary, $totalRows);

            // Generar análisis del catálogo (usando datos ya extraídos)
            $catalogBreakdown = $this->generateCatalogBreakdownFromExtracted(
                $catalogos,
                $proveedorId,
                $totalRows
            );

            // Guardar metadata en ImportValidationCache
            ImportValidationCache::create([
                'token' => $token,
                'proveedor_id' => $proveedorId,
                'file_name' => $csvFile->getClientOriginalName(),
                'total_rows' => $totalRows,
                'validation_data' => [
                    'temp_table' => $tableName,
                    'catalogos_data' => $catalogos,
                    'headers' => $headers,
                    'options' => $options,
                    'file_info' => [
                        'original_name' => $csvFile->getClientOriginalName(),
                        'size' => $csvFile->getSize(),
                        'mime_type' => $csvFile->getMimeType(),
                    ],
                    'created_at' => now()->toISOString(),
                ],
                'expires_at' => now()->addHours(2),
            ]);

            $processingTime = round((microtime(true) - $startTime), 2);

            // Limpiar memoria antes de retornar
            unset($insertBuffer);
            gc_collect_cycles();

            return [
                'success' => true,
                'preview_token' => $token,
                'file_info' => [
                    'name' => $csvFile->getClientOriginalName(),
                    'size' => $csvFile->getSize(),
                    'total_rows' => $totalRows,
                    'preview_rows' => count($previewData),
                    'encoding' => $options['encoding'],
                    'delimiter' => $options['delimiter'],
                ],
                'headers' => [
                    'detected' => $headers,
                    'validation' => $headerValidation,
                    'mapping_suggestions' => $this->generateHeaderMappingSuggestions($headers, $validator),
                ],
                'preview_data' => $previewData,
                'validation_summary' => $validationSummary,
                'validation_details' => array_merge($validationErrors, $validationWarnings),
                'quality_metrics' => $qualityMetrics,
                'processing_info' => [
                    'processing_time' => $processingTime,
                    'memory_used' => round(memory_get_peak_usage(true) / 1024 / 1024, 2).' MB',
                    'can_proceed' => $qualityMetrics['can_proceed'] ?? false,
                ],
                'catalogos' => $catalogBreakdown,
            ];
        } catch (\Throwable $e) {
            Log::error('Error procesando la vista previa del CSV', [
                'error' => $e->getMessage(),
                'file' => $csvFile->getClientOriginalName(),
                'proveedor_id' => $proveedorId,
                'trace' => $e->getTraceAsString(),
            ]);

            // Limpiar tabla temporal si se creó
            if (isset($tableName)) {
                try {
                    DB::statement("DROP TABLE IF EXISTS {$tableName}");
                } catch (\Exception $cleanupException) {
                    Log::warning('No se pudo limpiar tabla temporal', ['table' => $tableName]);
                }
            }

            return [
                'success' => false,
                'error' => 'Error procesando el archivo CSV: '.$e->getMessage(),
                'error_type' => 'processing_error',
            ];
        }
    }

    /**
     * Obtiene todos los datos del CSV apoyándose en parseCSV().
     */
    public function getFullDataFromCSV(UploadedFile|string $file, array $options): array
    {
        // Si es string => lo convertimos a UploadedFile
        if (is_string($file)) {
            $path = Storage::path($file);
            $file = new UploadedFile(
                $path,
                basename($path),
                mime_content_type($path),
                null,
                true // marca como "test mode" para evitar validaciones de $_FILES
            );
        }

        $parsed = $this->parseCSV($file, $options);

        return $parsed['data'];
    }

    /**
     * Parsea un CSV según las opciones dadas.
     * NOTA: Este método carga todo el archivo en memoria. Para archivos grandes usar parseCSVGenerator.
     *
     * @return array ['headers' => array, 'data' => array, 'total_rows' => int]
     *
     * @throws \Exception Si el archivo está vacío.
     */
    private function parseCSV(UploadedFile $file, array $options): array
    {
        $data = [];
        $headers = [];
        $totalRows = 0;
        $isFirstRow = true;

        // Usar el generador para leer el archivo
        foreach ($this->parseCSVGenerator($file, $options) as $row) {
            if ($isFirstRow && $options['has_header']) {
                $headers = array_keys($row);
                $isFirstRow = false;
            }
            $data[] = $row;
            $totalRows++;
        }

        if ($totalRows === 0) {
            throw new \Exception('El archivo CSV está vacío');
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Generador para leer archivo CSV línea por línea sin cargar todo en memoria.
     *
     * @param  UploadedFile|string  $file  Archivo o ruta del archivo
     * @param  array  $options  Opciones de procesamiento
     *
     * @throws \Exception
     */
    public function parseCSVGenerator($file, array $options): \Generator
    {
        $delimiter = $options['delimiter'] ?? ',';
        $encoding = $options['encoding'] ?? 'UTF-8';
        $hasHeader = $options['has_header'] ?? true;
        $skipEmptyRows = $options['skip_empty_rows'] ?? true;

        // Obtener la ruta real del archivo
        if ($file instanceof UploadedFile) {
            $filePath = $file->getRealPath();
        } else {
            $filePath = Storage::path($file);
        }

        // Abrir archivo para lectura
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \Exception('No se pudo abrir el archivo CSV');
        }

        try {
            $headers = [];
            $rowNumber = 0;
            $isFirstRow = true;

            // Detectar BOM y omitirlo si existe
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                // No es BOM UTF-8, regresar al inicio
                rewind($handle);
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                // Omitir filas vacías si está configurado
                if ($skipEmptyRows && empty(array_filter($row))) {
                    continue;
                }

                // Convertir encoding si es necesario
                if ($encoding !== 'UTF-8') {
                    $row = array_map(function ($value) use ($encoding) {
                        return mb_convert_encoding($value, 'UTF-8', $encoding);
                    }, $row);
                }

                // Limpiar espacios en blanco
                $row = array_map('trim', $row);

                // Procesar headers
                if ($isFirstRow && $hasHeader) {
                    $headers = $row;
                    $isFirstRow = false;

                    continue;
                }

                // Convertir a array asociativo si hay headers
                if (! empty($headers)) {
                    $associativeRow = [];
                    foreach ($headers as $index => $header) {
                        $associativeRow[$header] = isset($row[$index]) ? $row[$index] : '';
                    }
                    yield $associativeRow;
                } else {
                    yield $row;
                }

                // Liberar memoria periódicamente
                if ($rowNumber % 1000 === 0) {
                    gc_collect_cycles();
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Genera métricas de calidad para los datos importados
     */
    private function generateQualityMetrics(array $validationResults, array $csvData): array
    {
        $summary = $validationResults['summary'];
        $totalRows = $summary['total_records'];

        if ($totalRows === 0) {
            return ['can_proceed' => false, 'quality_score' => 0];
        }

        $successRate = ($summary['correct_records'] / $totalRows) * 100;
        $warningRate = ($summary['warning_records'] / $totalRows) * 100;
        $errorRate = ($summary['error_records'] / $totalRows) * 100;

        $qualityScore = 0;
        if ($successRate >= 90) {
            $qualityScore += 50;
        } elseif ($successRate >= 70) {
            $qualityScore += 30;
        } elseif ($successRate >= 50) {
            $qualityScore += 15;
        }

        if ($warningRate <= 20) {
            $qualityScore += 25;
        } elseif ($warningRate <= 40) {
            $qualityScore += 15;
        }

        if ($errorRate <= 10) {
            $qualityScore += 25;
        } elseif ($errorRate <= 30) {
            $qualityScore += 10;
        }

        return [
            'quality_score' => round($qualityScore, 1),
            'success_rate' => round($successRate, 2),
            'warning_rate' => round($warningRate, 2),
            'error_rate' => round($errorRate, 2),
            'can_proceed' => $errorRate <= 50, // Puede proceder si menos del 50% tiene errores
            'recommendation' => $this->getQualityRecommendation($qualityScore, $errorRate),
            'estimated_processing_time' => $this->estimateProcessingTime($totalRows),
            'error_distribution' => $summary['errors_by_type'] ?? [],
        ];
    }

    /**
     * Valida un lote de filas para generar un resumen y detalles.
     *
     * @return array ['summary' => array, 'validation_results' => array]
     */
    private function validateBatch(array $previewData, CSVImportProductValidator $validator): array
    {
        $validationResults = [];
        $summary = [
            'total_records' => count($previewData),
            'correct_records' => 0,
            'warning_records' => 0,
            'error_records' => 0,
            'errors_by_type' => [],
        ];

        foreach ($previewData as $index => $row) {
            $rowValidation = $validator->validateRow($row, $index + 1);

            $status = 'correcto';
            if (! empty($rowValidation['errors'])) {
                $status = 'error';
                $summary['error_records']++;
            } elseif (! empty($rowValidation['warnings'])) {
                $status = 'advertencia';
                $summary['warning_records']++;
            } else {
                $summary['correct_records']++;
            }

            $validationResults[] = [
                'row_index' => $index + 1,
                'status' => $status,
                'errors' => $rowValidation['errors'],
                'warnings' => $rowValidation['warnings'],
                'data' => $row,
            ];
        }

        return [
            'summary' => $summary,
            'validation_results' => $validationResults,
        ];
    }

    /**
     * Genera sugerencias de mapeo de encabezados
     */
    private function generateHeaderMappingSuggestions(array $detectedHeaders, CSVImportProductValidator $validator): array
    {
        $expectedHeaders = $validator->getExpectedHeaders();
        $suggestions = [];

        foreach ($detectedHeaders as $detectedHeader) {
            $bestMatch = $this->findBestHeaderMatch($detectedHeader, array_keys($expectedHeaders));
            if ($bestMatch) {
                $suggestions[] = [
                    'detected' => $detectedHeader,
                    'suggested' => $bestMatch,
                    'confidence' => $this->calculateHeaderSimilarity($detectedHeader, $bestMatch),
                    'required' => $expectedHeaders[$bestMatch] === 'required',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Encuentra la mejor coincidencia para un encabezado usando similitud
     */
    private function findBestHeaderMatch(string $detectedHeader, array $expectedHeaders): ?string
    {
        $bestMatch = null;
        $highestSimilarity = 0;

        $normalizedDetected = strtolower(trim($detectedHeader));

        foreach ($expectedHeaders as $expectedHeader) {
            $normalizedExpected = strtolower($expectedHeader);

            // Coincidencia exacta
            if ($normalizedDetected === $normalizedExpected) {
                return $expectedHeader;
            }

            // Coincidencia parcial
            similar_text($normalizedDetected, $normalizedExpected, $similarity);

            if ($similarity > $highestSimilarity && $similarity > 60) {
                $highestSimilarity = $similarity;
                $bestMatch = $expectedHeader;
            }
        }

        return $bestMatch;
    }

    /**
     * Calcula el porcentaje de similitud entre dos encabezados
     */
    private function calculateHeaderSimilarity(string $header1, string $header2): float
    {
        similar_text(strtolower($header1), strtolower($header2), $percent);

        return round($percent, 1);
    }

    /**
     * Obtiene la recomendación de calidad según la puntuación y el porcentaje de error
     */
    private function getQualityRecommendation(float $score, float $errorRate): string
    {
        if ($score >= 80) {
            return 'Excelente calidad de datos. Recomendado proceder con la importación.';
        } elseif ($score >= 60) {
            return 'Buena calidad de datos. Revisar advertencias antes de proceder.';
        } elseif ($score >= 40) {
            return 'Calidad moderada. Revisar y corregir errores antes de importar.';
        } else {
            return 'Baja calidad de datos. Se recomienda revisar y corregir el archivo antes de importar.';
        }
    }

    /**
     * Estima el tiempo de procesamiento basado en la cantidad de filas
     */
    private function estimateProcessingTime(int $totalRows): string
    {
        $seconds = ceil($totalRows / 100); // Aproximadamente 100 registros por segundo

        if ($seconds < 60) {
            return "{$seconds} segundos";
        } elseif ($seconds < 3600) {
            $minutes = ceil($seconds / 60);

            return "{$minutes} minutos";
        } else {
            $hours = floor($seconds / 3600);
            $remainingMinutes = ceil(($seconds % 3600) / 60);

            return "{$hours}h {$remainingMinutes}m";
        }
    }

    /**
     * Guarda en caché los datos de vista previa para confirmación posterior
     */
    private function cachePreviewData(string $token, array $data): void
    {
        // Guardar en Redis/base de datos por 1 hora
        Cache::put("csv_preview:{$token}", $data, 3600);

        // También guardar en ImportValidationCache para persistencia
        ImportValidationCache::create([
            'token' => $token,
            'proveedor_id' => $data['proveedor_id'],
            'file_name' => $data['file_info']['original_name'],
            'total_rows' => count($data['full_data']),
            'validation_data' => $data,
            'expires_at' => now()->addHour(),
        ]);
    }

    /**
     * Recupera los datos de vista previa en caché
     */
    public function getCachedPreviewData(string $token): ?array
    {
        // Intentar primero desde la caché
        $data = Cache::get("csv_preview:{$token}");

        if (! $data) {
            // Intentar desde la base de datos
            $cached = ImportValidationCache::where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            if ($cached) {
                $data = $cached->validation_data;
                // Volver a cachear para acceso rápido
                Cache::put("csv_preview:{$token}", $data, 3600);
            }
        }

        return $data;
    }

    /**
     * Crea tabla temporal y almacena los datos de vista previa para confirmación posterior
     * Usa tablas normales con nombre único para evitar conflictos multiusuario
     *
     * @param  string  $token  Token único de identificación
     * @param  array  $data  Datos completos del CSV a almacenar
     */
    private function storePreviewDataInTempTable(string $token, array $data): void
    {
        // Usar nombre de tabla único con timestamp para evitar conflictos
        $tableName = 'csv_import_temp_'.substr($token, 0, 16).'_'.time();

        try {
            // Verificar si la tabla ya existe (por seguridad adicional)
            $tableExists = DB::select("SHOW TABLES LIKE '{$tableName}'");
            if (! empty($tableExists)) {
                // Si existe, agregar sufijo aleatorio
                $tableName .= '_'.mt_rand(1000, 9999);
            }

            // Crear tabla normal con estructura del CSV
            DB::statement("CREATE TABLE {$tableName} (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(255) NULL,
                producto VARCHAR(500) NULL,
                descripcion TEXT NULL,
                marca VARCHAR(255) NULL,
                categoria VARCHAR(255) NULL,
                subcategoria VARCHAR(255) NULL,
                unidad_medida VARCHAR(100) NULL,
                precio DECIMAL(12,4) NULL,
                precio_mayoreo DECIMAL(12,4) NULL,
                precio_menudeo DECIMAL(12,4) NULL,
                row_index INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_row_index (row_index),
                INDEX idx_codigo (codigo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Insertar datos por lotes para mejor rendimiento
            $insertData = [];
            foreach ($data['full_data'] as $index => $row) {
                $insertData[] = [
                    'codigo' => ! empty($row['codigo']) ? substr(trim($row['codigo']), 0, 255) : null,
                    'producto' => ! empty($row['producto']) ? substr(trim($row['producto']), 0, 500) : null,
                    'descripcion' => ! empty($row['descripcion']) ? trim($row['descripcion']) : null,
                    'marca' => ! empty($row['marca']) ? substr(trim($row['marca']), 0, 255) : null,
                    'categoria' => ! empty($row['categoria']) ? substr(trim($row['categoria']), 0, 255) : null,
                    'subcategoria' => ! empty($row['subcategoria']) ? substr(trim($row['subcategoria']), 0, 255) : null,
                    'unidad_medida' => ! empty($row['unidad_medida']) ? substr(trim($row['unidad_medida']), 0, 100) : null,
                    'precio' => ! empty($row['precio']) && is_numeric($row['precio']) ? (float) $row['precio'] : null,
                    'precio_mayoreo' => ! empty($row['precio_mayoreo']) && is_numeric($row['precio_mayoreo']) ? (float) $row['precio_mayoreo'] : null,
                    'precio_menudeo' => ! empty($row['precio_menudeo']) && is_numeric($row['precio_menudeo']) ? (float) $row['precio_menudeo'] : null,
                    'row_index' => $index + 1,
                ];
            }

            // Insertar en lotes de 500 registros para evitar timeouts
            $chunks = array_chunk($insertData, 500);
            foreach ($chunks as $chunk) {
                DB::table($tableName)->insert($chunk);
            }

            // Guardar metadata en ImportValidationCache para referencia
            ImportValidationCache::create([
                'token' => $token,
                'proveedor_id' => $data['proveedor_id'],
                'file_name' => $data['file_info']['original_name'],
                'total_rows' => count($data['full_data']),
                'validation_data' => [
                    'temp_table' => $tableName,
                    'catalogos_data' => $data['catalogos_data'],
                    'headers' => $data['headers'],
                    'options' => $data['options'],
                    'file_info' => $data['file_info'],
                    'created_at' => $data['created_at'],
                ],
                'expires_at' => now()->addHours(2), // 2 horas para dar tiempo suficiente
            ]);

            Log::info('Tabla temporal creada exitosamente', [
                'token' => $token,
                'table_name' => $tableName,
                'total_rows' => count($data['full_data']),
                'proveedor_id' => $data['proveedor_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error creando tabla temporal', [
                'token' => $token,
                'table_name' => $tableName ?? 'undefined',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Limpiar tabla si se creó pero falló después
            if (isset($tableName)) {
                try {
                    DB::statement("DROP TABLE IF EXISTS {$tableName}");
                } catch (\Exception $cleanupException) {
                    Log::error('Error limpiando tabla después de fallo', [
                        'table_name' => $tableName,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            // Fallback al método de caché original
            $this->cachePreviewData($token, $data);
        }
    }

    /**
     * Recupera los datos de vista previa desde la tabla temporal
     *
     * @param  string  $token  Token único de identificación
     * @return array|null Datos del preview o null si no se encuentra
     */
    public function getTempTablePreviewData(string $token): ?array
    {
        try {
            // Buscar metadata en ImportValidationCache
            $cached = ImportValidationCache::where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            if (! $cached || ! isset($cached->validation_data['temp_table'])) {
                Log::info('No se encontró metadata para el token', ['token' => $token]);

                return null;
            }

            $tableName = $cached->validation_data['temp_table'];
            $metadata = $cached->validation_data;

            // Verificar si la tabla existe
            $tableExists = DB::select("SHOW TABLES LIKE '{$tableName}'");
            if (empty($tableExists)) {
                Log::warning('Tabla temporal no encontrada', [
                    'table_name' => $tableName,
                    'token' => $token,
                    'proveedor_id' => $cached->proveedor_id,
                ]);

                return null;
            }

            // Obtener todos los datos de la tabla temporal ordenados
            $tempData = DB::table($tableName)
                ->orderBy('row_index')
                ->get()
                ->map(function ($row) {
                    return [
                        'codigo' => $row->codigo,
                        'producto' => $row->producto,
                        'descripcion' => $row->descripcion,
                        'marca' => $row->marca,
                        'categoria' => $row->categoria,
                        'subcategoria' => $row->subcategoria,
                        'unidad_medida' => $row->unidad_medida,
                        'precio' => $row->precio,
                        'precio_mayoreo' => $row->precio_mayoreo,
                        'precio_menudeo' => $row->precio_menudeo,
                    ];
                })
                ->toArray();

            Log::info('Datos recuperados de tabla temporal', [
                'token' => $token,
                'table_name' => $tableName,
                'rows_retrieved' => count($tempData),
            ]);

            // Reconstruir la estructura completa de datos
            return [
                'full_data' => $tempData,
                'catalogos_data' => $metadata['catalogos_data'] ?? [],
                'headers' => $metadata['headers'] ?? [],
                'options' => $metadata['options'] ?? [],
                'proveedor_id' => $cached->proveedor_id,
                'file_info' => $metadata['file_info'] ?? [],
                'created_at' => $metadata['created_at'] ?? now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Error recuperando datos de tabla temporal', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback al método de caché original
            return $this->getCachedPreviewData($token);
        }
    }

    /**
     * Elimina la tabla temporal y los datos asociados
     *
     * @param  string  $token  Token único de identificación
     * @return bool True si se eliminó correctamente
     */
    public function cleanupTempTable(string $token): bool
    {
        try {
            // Buscar metadata
            $cached = ImportValidationCache::where('token', $token)->first();

            if ($cached && isset($cached->validation_data['temp_table'])) {
                $tableName = $cached->validation_data['temp_table'];

                // Eliminar tabla si existe
                $tableExists = DB::select("SHOW TABLES LIKE '{$tableName}'");
                if (! empty($tableExists)) {
                    DB::statement("DROP TABLE {$tableName}");
                    Log::info('Tabla temporal eliminada', [
                        'table_name' => $tableName,
                        'token' => $token,
                        'proveedor_id' => $cached->proveedor_id,
                    ]);
                }
            }

            // Eliminar registro de ImportValidationCache
            ImportValidationCache::where('token', $token)->delete();

            // Eliminar caché si existe
            Cache::forget("csv_preview:{$token}");

            return true;
        } catch (\Exception $e) {
            Log::error('Error eliminando tabla temporal', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Limpia tablas temporales expiradas (método para ejecutar periódicamente)
     *
     * @return int Número de tablas limpiadas
     */
    public function cleanupExpiredTempTables(): int
    {
        $cleanedCount = 0;

        try {
            // Buscar registros expirados
            $expiredRecords = ImportValidationCache::where('expires_at', '<', now())
                ->whereNotNull('validation_data')
                ->get();

            foreach ($expiredRecords as $record) {
                if (isset($record->validation_data['temp_table'])) {
                    $tableName = $record->validation_data['temp_table'];

                    // Verificar si la tabla existe antes de intentar eliminarla
                    $tableExists = DB::select("SHOW TABLES LIKE '{$tableName}'");
                    if (! empty($tableExists)) {
                        DB::statement("DROP TABLE {$tableName}");
                        $cleanedCount++;

                        Log::info('Tabla temporal expirada eliminada', [
                            'table_name' => $tableName,
                            'token' => $record->token,
                            'expired_at' => $record->expires_at,
                        ]);
                    }
                }

                // Eliminar el registro de metadata
                $record->delete();
            }
        } catch (\Exception $e) {
            Log::error('Error limpiando tablas temporales expiradas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $cleanedCount;
    }

    /**
     * Genera un token único para la vista previa
     */
    private function generatePreviewToken(): string
    {
        return hash('sha256', Str::random(32).microtime(true));
    }

    /**
     * Elimina datos de vista previa expirados
     */
    public function cleanupExpiredPreviews(): int
    {
        return ImportValidationCache::where('expires_at', '<', now())->delete();
    }

    /**
     * Genera los catálogos únicos de marcas, unidades y categorías/subcategorías a partir de los datos CSV.
     *
     * @param  array  $csvData  Datos del CSV en formato de array asociativo.
     * @param  int  $proveedorId  ID del proveedor asociado (puede usarse para filtrar o registrar).
     * @return array<string, array>
     *                              Estructura de retorno:
     *                              [
     *                              'marcas' => ['Marca1', 'Marca2', ...],
     *                              'unidades' => ['kg', 'm', 'l', ...],
     *                              'categorias' => [
     *                              'Categoria1' => ['Subcategoria1', 'Subcategoria2', ...],
     *                              'Categoria2' => ['SubcategoriaA', 'SubcategoriaB', ...],
     *                              ...
     *                              ],
     *                              ]
     */
    public function getArrayCatalogos(array $csvData): array
    {
        return [
            'marcas' => $this->extractUniqueValues($csvData, 'marcas'),
            'unidades' => $this->extractUniqueValues($csvData, 'unidades'),
            'categorias' => $this->extractUniqueCategories($csvData, 'categoria', 'subcategoria'),
        ];
    }

    /**
     * Genera un desglose detallado del catálogo con análisis de existentes vs nuevos
     *
     * @param  array  $csvData  Datos de las filas del CSV
     * @param  int  $proveedorId  ID del proveedor para las consultas
     * @return array Desglose detallado con análisis de catálogo
     */
    public function generateCatalogBreakdown(array $csvData, int $proveedorId): array
    {
        // Extraer valores únicos del CSV
        $csvMarcas = $this->extractUniqueValues($csvData, 'marca');
        $csvCategorias = $this->extractUniqueValues($csvData, 'categoria');
        $csvSubcategorias = $this->extractUniqueValues($csvData, 'subcategoria');
        $csvUnidades = $this->extractUniqueValues($csvData, 'unidad_medida');
        $csvProductos = $this->extractUniqueValues($csvData, 'codigo');

        // Obtener catálogos existentes desde la base de datos
        $existingMarcas = $this->getExistingMarcas($proveedorId);
        $existingCategorias = $this->getExistingCategorias($proveedorId);
        $existingSubcategorias = $this->getExistingSubcategorias($proveedorId);
        $existingUnidades = $this->getExistingUnidades($proveedorId);
        $existingProductos = $this->getExistingProductos($proveedorId);

        return [
            'productos' => $this->analyzeProductos($csvProductos, $existingProductos, $csvData),
            'marcas' => $this->analyzeMarcas($csvMarcas, $existingMarcas),
            'categorias' => $this->analyzeCategorias($csvCategorias, $existingCategorias),
            'subcategorias' => $this->analyzeSubcategorias($csvSubcategorias, $existingSubcategorias),
            'unidades' => $this->analyzeUnidades($csvUnidades, $existingUnidades),
        ];
    }

    /**
     * Extrae valores únicos de un campo específico en los datos del CSV
     */
    private function extractUniqueValues(array $csvData, string $field): array
    {
        $values = collect($csvData)
            ->pluck($field)
            ->filter(function ($value) {
                return ! empty($value) && trim($value) !== '';
            })
            ->map(function ($value) {
                return trim($value);
            })
            ->unique()
            ->values()
            ->toArray();

        return $values;
    }

    /**
     * Obtiene las marcas existentes para el proveedor
     */
    private function getExistingMarcas(int $proveedorId): Collection
    {
        return Marca::where('proveedor_id', $proveedorId)
            ->where('activo', true)
            ->select('id', 'nombre', 'descripcion')
            ->get();
    }

    /**
     * Obtiene las categorías existentes para el proveedor
     */
    private function getExistingCategorias(int $proveedorId): Collection
    {
        return Categoria::where('proveedor_id', $proveedorId)
            ->where('activo', true)
            ->whereNull('parent_id') // Solo categorías principales
            ->select('id', 'nombre', 'descripcion', 'nivel')
            ->get();
    }

    /**
     * Obtiene las subcategorías existentes para el proveedor
     */
    private function getExistingSubcategorias(int $proveedorId): Collection
    {
        return Categoria::where('proveedor_id', $proveedorId)
            ->where('activo', true)
            ->whereNotNull('parent_id') // Solo subcategorías
            ->select('id', 'nombre', 'descripcion', 'parent_id', 'nivel')
            ->with('parent:id,nombre')
            ->get();
    }

    /**
     * Get existing unidades de medida (catálogo global)
     */
    private function getExistingUnidades(int $proveedorId): Collection
    {
        return UnidadMedida::query()
            ->where('estatus', 'activo')
            ->select('id', 'nombre', 'clave', 'descripcion')
            ->get();
    }

    /**
     * Get existing productos for the proveedor
     */
    private function getExistingProductos(int $proveedorId): Collection
    {
        return Producto::where('proveedor_id', $proveedorId)
            ->select('id', 'codigo_interno', 'sku', 'nombre')
            ->get();
    }

    /**
     * Analyze productos: existing vs new vs duplicates
     */
    private function analyzeProductos(array $csvProductos, Collection $existingProductos, array $csvData): array
    {
        $existingCodigos = $existingProductos->pluck('codigo_interno')->toArray();

        $nuevos = array_diff($csvProductos, $existingCodigos);
        $existentes = array_intersect($csvProductos, $existingCodigos);

        // Detect duplicates within CSV data
        $codigoCounts = array_count_values($csvProductos);
        $duplicados = array_filter($codigoCounts, function ($count) {
            return $count > 1;
        });

        return [
            'total' => count($csvProductos),
            'nuevos' => count($nuevos),
            'existentes' => count($existentes),
            'duplicados' => count($duplicados),
            'duplicados_detail' => array_keys($duplicados),
        ];
    }

    /**
     * Analyze marcas: existing vs new
     */
    private function analyzeMarcas(array $csvMarcas, Collection $existingMarcas): array
    {
        $existingNames = $existingMarcas->pluck('nombre')->map('strtolower')->toArray();
        $csvMarcasLower = array_map('strtolower', $csvMarcas);

        $nuevas = [];
        $existentes = [];

        foreach ($csvMarcas as $marca) {
            if (in_array(strtolower($marca), $existingNames)) {
                $existentes[] = $marca;
            } else {
                $nuevas[] = $marca;
            }
        }

        // Merge existing data with new items
        $mergedData = $existingMarcas->map(function ($marca) {
            return [
                'id' => $marca->id,
                'nombre' => $marca->nombre,
                'descripcion' => $marca->descripcion,
                'es_nueva' => false,
            ];
        })->toArray();

        // Add new marcas
        foreach ($nuevas as $marca) {
            $mergedData[] = [
                'id' => null,
                'nombre' => $marca,
                'descripcion' => null,
                'es_nueva' => true,
            ];
        }

        return [
            'total' => count($csvMarcas),
            'nuevas' => count($nuevas),
            'existentes' => count($existentes),
            'data' => $mergedData,
        ];
    }

    /**
     * Analyze categorias: existing vs new
     */
    private function analyzeCategorias(array $csvCategorias, Collection $existingCategorias): array
    {
        $existingNames = $existingCategorias->pluck('nombre')->map('strtolower')->toArray();

        $nuevas = [];
        $existentes = [];

        foreach ($csvCategorias as $categoria) {
            if (in_array(strtolower($categoria), $existingNames)) {
                $existentes[] = $categoria;
            } else {
                $nuevas[] = $categoria;
            }
        }

        // Merge existing data with new items
        $mergedData = $existingCategorias->map(function ($categoria) {
            return [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'descripcion' => $categoria->descripcion,
                'nivel' => $categoria->nivel,
                'es_nueva' => false,
            ];
        })->toArray();

        // Add new categorias
        foreach ($nuevas as $categoria) {
            $mergedData[] = [
                'id' => null,
                'nombre' => $categoria,
                'descripcion' => null,
                'nivel' => 1,
                'es_nueva' => true,
            ];
        }

        return [
            'total' => count($csvCategorias),
            'nuevas' => count($nuevas),
            'existentes' => count($existentes),
            'data' => $mergedData,
        ];
    }

    /**
     * Analyze subcategorias: existing vs new
     */
    private function analyzeSubcategorias(array $csvSubcategorias, Collection $existingSubcategorias): array
    {
        if (empty($csvSubcategorias)) {
            return [
                'total' => 0,
                'nuevas' => 0,
                'existentes' => 0,
                'data' => [],
            ];
        }

        $existingNames = $existingSubcategorias->pluck('nombre')->map('strtolower')->toArray();

        $nuevas = [];
        $existentes = [];

        foreach ($csvSubcategorias as $subcategoria) {
            if (in_array(strtolower($subcategoria), $existingNames)) {
                $existentes[] = $subcategoria;
            } else {
                $nuevas[] = $subcategoria;
            }
        }

        // Merge existing data with new items
        $mergedData = $existingSubcategorias->map(function ($subcategoria) {
            return [
                'id' => $subcategoria->id,
                'nombre' => $subcategoria->nombre,
                'descripcion' => $subcategoria->descripcion,
                'parent_id' => $subcategoria->parent_id,
                'parent_nombre' => $subcategoria->parent ? $subcategoria->parent->nombre : null,
                'nivel' => $subcategoria->nivel,
                'es_nueva' => false,
            ];
        })->toArray();

        // Add new subcategorias
        foreach ($nuevas as $subcategoria) {
            $mergedData[] = [
                'id' => null,
                'nombre' => $subcategoria,
                'descripcion' => null,
                'parent_id' => null,
                'parent_nombre' => null,
                'nivel' => 2,
                'es_nueva' => true,
            ];
        }

        return [
            'total' => count($csvSubcategorias),
            'nuevas' => count($nuevas),
            'existentes' => count($existentes),
            'data' => $mergedData,
        ];
    }

    /**
     * Analyze unidades de medida: existing vs new
     */
    private function analyzeUnidades(array $csvUnidades, Collection $existingUnidades): array
    {
        $existingNames = $existingUnidades->pluck('nombre')->map('strtolower')->toArray();

        $nuevas = [];
        $existentes = [];

        foreach ($csvUnidades as $unidad) {
            if (in_array(strtolower($unidad), $existingNames)) {
                $existentes[] = $unidad;
            } else {
                $nuevas[] = $unidad;
            }
        }

        // Merge existing data with new items
        $mergedData = $existingUnidades->map(function ($unidad) {
            return [
                'id' => $unidad->id,
                'nombre' => $unidad->nombre,
                'clave' => $unidad->clave,
                'descripcion' => $unidad->descripcion,
                'es_nueva' => false,
            ];
        })->toArray();

        // Add new unidades
        foreach ($nuevas as $unidad) {
            $mergedData[] = [
                'id' => null,
                'nombre' => $unidad,
                'clave' => null,
                'descripcion' => null,
                'es_nueva' => true,
            ];
        }

        return [
            'total' => count($csvUnidades),
            'nuevas' => count($nuevas),
            'existentes' => count($existentes),
            'data' => $mergedData,
        ];
    }

    /******************************************* */
    /******************************************* */
    /******************************************* */

    /**
     * Extrae un arreglo asociativo de categorías con sus subcategorías únicas a partir de un dataset.
     *
     * @param  array  $csvData  Datos del CSV en formato array asociativo.
     * @param  string  $categoryField  Nombre del campo que representa la categoría.
     * @param  string  $subcategoryField  Nombre del campo que representa la subcategoría.
     * @return array<string, array<string>>
     *                                      Estructura de retorno:
     *                                      [
     *                                      "Categoria1" => ["Subcategoria1", "Subcategoria2", ...],
     *                                      "Categoria2" => ["SubcategoriaA", "SubcategoriaB", ...],
     *                                      ...
     *                                      ]
     */
    private function extractUniqueCategories(array $csvData, string $categoryField, string $subcategoryField): array
    {
        return collect($csvData)
            ->filter(fn ($row) => ! empty(trim($row[$categoryField] ?? '')) && ! empty(trim($row[$subcategoryField] ?? '')))
            ->groupBy(fn ($row) => trim($row[$categoryField]))
            ->map(
                fn ($items) => $items
                    ->pluck($subcategoryField)
                    ->map('trim')
                    ->unique()
                    ->values()
                    ->toArray()
            )
            ->toArray();
    }

    /**
     * Crea una tabla temporal para almacenar los datos del CSV
     *
     * @param  string  $token  Token único para identificar la tabla
     * @return string Nombre de la tabla creada
     *
     * @throws \Exception Si no se puede crear la tabla
     */
    private function createTempTable(string $token): string
    {
        $tableName = 'csv_import_temp_'.substr($token, 0, 16).'_'.time();

        try {
            // Verificar si existe y generar nombre único si es necesario
            $tableExists = DB::select("SHOW TABLES LIKE '{$tableName}'");
            if (! empty($tableExists)) {
                $tableName .= '_'.mt_rand(1000, 9999);
            }

            // Crear tabla temporal con estructura optimizada
            // `payload` JSON = fila CSV completa (PropiedadN_*, familia, tags, etc.)
            DB::statement("CREATE TABLE {$tableName} (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(255) NULL,
                producto VARCHAR(500) NULL,
                descripcion TEXT NULL,
                marca VARCHAR(255) NULL,
                categoria VARCHAR(255) NULL,
                subcategoria VARCHAR(255) NULL,
                unidad_medida VARCHAR(100) NULL,
                precio DECIMAL(12,4) NULL,
                precio_mayoreo DECIMAL(12,4) NULL,
                precio_menudeo DECIMAL(12,4) NULL,
                modelo VARCHAR(255) NULL,
                payload JSON NULL,
                row_index INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_row_index (row_index),
                INDEX idx_codigo (codigo),
                INDEX idx_marca (marca),
                INDEX idx_categoria (categoria)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            Log::info('Tabla temporal creada', ['table_name' => $tableName]);

            return $tableName;
        } catch (\Exception $e) {
            Log::error('Error creando tabla temporal', [
                'table_name' => $tableName ?? 'undefined',
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('No se pudo crear la tabla temporal: '.$e->getMessage());
        }
    }

    /**
     * Prepara una fila del CSV para inserción en la tabla temporal
     *
     * @param  array  $row  Datos de la fila
     * @param  int  $rowIndex  Índice de la fila
     * @return array Datos preparados para inserción
     */
    private function prepareRowForTempTable(array $row, int $rowIndex): array
    {
        $normalized = $this->normalizeCsvRowKeys($row);

        $precio = $normalized['precio'] ?? null;
        $precioMayoreo = $normalized['precio_mayoreo'] ?? null;
        $precioMenudeo = $normalized['precio_menudeo'] ?? null;

        return [
            'codigo' => ! empty($normalized['codigo']) ? substr(trim((string) $normalized['codigo']), 0, 255) : null,
            'producto' => ! empty($normalized['producto']) ? substr(trim((string) $normalized['producto']), 0, 500) : null,
            'descripcion' => ! empty($normalized['descripcion']) ? trim((string) $normalized['descripcion']) : null,
            'marca' => ! empty($normalized['marca']) ? substr(trim((string) $normalized['marca']), 0, 255) : null,
            'categoria' => ! empty($normalized['categoria']) ? substr(trim((string) $normalized['categoria']), 0, 255) : null,
            'subcategoria' => ! empty($normalized['subcategoria']) ? substr(trim((string) $normalized['subcategoria']), 0, 255) : null,
            'unidad_medida' => ! empty($normalized['unidad_medida']) ? substr(trim((string) $normalized['unidad_medida']), 0, 100) : null,
            'precio' => $precio !== null && $precio !== '' && is_numeric($precio) ? (float) $precio : null,
            'precio_mayoreo' => $precioMayoreo !== null && $precioMayoreo !== '' && is_numeric($precioMayoreo) ? (float) $precioMayoreo : null,
            'precio_menudeo' => $precioMenudeo !== null && $precioMenudeo !== '' && is_numeric($precioMenudeo) ? (float) $precioMenudeo : null,
            'modelo' => ! empty($normalized['modelo']) ? substr(trim((string) $normalized['modelo']), 0, 255) : null,
            'payload' => json_encode($normalized, JSON_UNESCAPED_UNICODE),
            'row_index' => $rowIndex,
        ];
    }

    /**
     * Normaliza claves de fila CSV a minúsculas (Propiedad1_Clave → propiedad1_clave).
     */
    private function normalizeCsvRowKeys(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $out[strtolower(trim($key))] = is_string($value) ? trim($value) : $value;
        }

        return $out;
    }

    /**
     * Inserta un chunk de datos en la tabla temporal
     *
     * @param  string  $tableName  Nombre de la tabla
     * @param  array  $chunk  Datos a insertar
     */
    private function insertChunkToTempTable(string $tableName, array $chunk): void
    {
        try {
            DB::beginTransaction();
            DB::table($tableName)->insert($chunk);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error insertando chunk en tabla temporal', [
                'table_name' => $tableName,
                'chunk_size' => count($chunk),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Genera métricas de calidad basadas en el resumen de validación
     *
     * @param  array  $validationSummary  Resumen de validación
     * @param  int  $totalRows  Total de filas en el archivo
     * @return array Métricas de calidad
     */
    private function generateQualityMetricsFromSummary(array $validationSummary, int $totalRows): array
    {
        $previewRecords = $validationSummary['total_records'];

        if ($previewRecords === 0) {
            return ['can_proceed' => false, 'quality_score' => 0];
        }

        $successRate = ($validationSummary['correct_records'] / $previewRecords) * 100;
        $warningRate = ($validationSummary['warning_records'] / $previewRecords) * 100;
        $errorRate = ($validationSummary['error_records'] / $previewRecords) * 100;

        $qualityScore = 0;
        if ($successRate >= 90) {
            $qualityScore += 50;
        } elseif ($successRate >= 70) {
            $qualityScore += 30;
        } elseif ($successRate >= 50) {
            $qualityScore += 15;
        }

        if ($warningRate <= 20) {
            $qualityScore += 25;
        } elseif ($warningRate <= 40) {
            $qualityScore += 15;
        }

        if ($errorRate <= 10) {
            $qualityScore += 25;
        } elseif ($errorRate <= 30) {
            $qualityScore += 10;
        }

        return [
            'quality_score' => round($qualityScore, 1),
            'success_rate' => round($successRate, 2),
            'warning_rate' => round($warningRate, 2),
            'error_rate' => round($errorRate, 2),
            'can_proceed' => $errorRate <= 50,
            'recommendation' => $this->getQualityRecommendation($qualityScore, $errorRate),
            'estimated_processing_time' => $this->estimateProcessingTime($totalRows),
            'error_distribution' => $validationSummary['errors_by_type'] ?? [],
        ];
    }

    /**
     * Genera el desglose del catálogo desde datos ya extraídos
     *
     * @param  array  $catalogos  Catálogos extraídos del CSV
     * @param  int  $proveedorId  ID del proveedor
     * @param  int  $totalRows  Total de filas procesadas
     * @return array Desglose del catálogo
     */
    private function generateCatalogBreakdownFromExtracted(array $catalogos, int $proveedorId, int $totalRows): array
    {
        // Obtener catálogos existentes desde la base de datos
        $existingMarcas = $this->getExistingMarcas($proveedorId);
        $existingCategorias = $this->getExistingCategorias($proveedorId);
        $existingUnidades = $this->getExistingUnidades($proveedorId);

        // Analizar marcas
        $marcasAnalysis = $this->analyzeMarcas($catalogos['marcas'], $existingMarcas);

        // Analizar categorías (solo nombres principales)
        $categoriasNames = array_keys($catalogos['categorias']);
        $categoriasAnalysis = $this->analyzeCategorias($categoriasNames, $existingCategorias);

        // Analizar subcategorías (todas las subcategorías)
        $allSubcategorias = [];
        foreach ($catalogos['categorias'] as $subcats) {
            $allSubcategorias = array_merge($allSubcategorias, $subcats);
        }
        $allSubcategorias = array_unique($allSubcategorias);
        $existingSubcategorias = $this->getExistingSubcategorias($proveedorId);
        $subcategoriasAnalysis = $this->analyzeSubcategorias($allSubcategorias, $existingSubcategorias);

        // Analizar unidades
        $unidadesAnalysis = $this->analyzeUnidades($catalogos['unidades'], $existingUnidades);

        // Estimar productos (basado en el total de filas)
        $productosAnalysis = [
            'total' => $totalRows,
            'nuevos' => 0, // Se determinará durante la importación real
            'existentes' => 0,
            'duplicados' => 0,
            'duplicados_detail' => [],
        ];

        return [
            'productos' => $productosAnalysis,
            'marcas' => $marcasAnalysis,
            'categorias' => $categoriasAnalysis,
            'subcategorias' => $subcategoriasAnalysis,
            'unidades' => $unidadesAnalysis,
        ];
    }

    /**
     * Obtiene un generador para leer datos desde un archivo CSV
     * Útil para el Job cuando necesite procesar el archivo completo
     *
     * @param  string  $filePath  Ruta del archivo
     * @param  array  $options  Opciones de procesamiento
     */
    public function getCSVDataGenerator(string $filePath, array $options): \Generator
    {
        // Reutilizar el generador existente
        return $this->parseCSVGenerator($filePath, $options);
    }

    /**
     * Obtiene datos de la tabla temporal con paginación
     *
     * @param  string  $tableName  Nombre de la tabla
     * @param  int  $limit  Límite de registros
     * @param  int  $offset  Desplazamiento
     */
    public function getTempTableDataPaginated(string $tableName, int $limit = 1000, int $offset = 0): array
    {
        try {
            return DB::table($tableName)
                ->orderBy('row_index')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($row) {
                    $base = [
                        'codigo' => $row->codigo,
                        'producto' => $row->producto,
                        'descripcion' => $row->descripcion,
                        'marca' => $row->marca,
                        'categoria' => $row->categoria,
                        'subcategoria' => $row->subcategoria,
                        'unidad_medida' => $row->unidad_medida,
                        'precio' => $row->precio,
                        'precio_mayoreo' => $row->precio_mayoreo,
                        'precio_menudeo' => $row->precio_menudeo,
                        'modelo' => $row->modelo,
                    ];

                    $payload = [];
                    if (! empty($row->payload)) {
                        $decoded = is_string($row->payload)
                            ? json_decode($row->payload, true)
                            : (array) $row->payload;
                        if (is_array($decoded)) {
                            $payload = $decoded;
                        }
                    }

                    // payload gana (fila CSV completa); base rellena si falta algo
                    return array_merge($base, $payload);
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error obteniendo datos paginados de tabla temporal', [
                'table_name' => $tableName,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
