<?php

namespace App\Jobs;

use App\Models\Categoria;
use App\Models\ImportAudit;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Services\CSVImport\CSVImportProductValidator;
use App\Services\CSVImport\CSVProcessorService;
use App\Services\Catalogo\CatalogoOpusHomologacionService;
use App\Support\Catalogo\CatalogoImportPlantilla;
use Exception;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CSVImportJob implements ShouldQueue
{
    use BusQueueable, Dispatchable, InteractsWithQueue, SerializesModels;

    protected ImportAudit $importAudit;

    protected array $options;

    // Job configuration
    public int $timeout = 1800; // 30 minutes

    public int $tries = 3;

    public array $backoff = [60, 180, 300]; // Exponential backoff

    // Processing state
    protected array $processingStats = [
        'total_registros' => 0,
        'productos_nuevos' => 0,
        'productos_actualizados' => 0,
        'productos_error' => 0,
        'marcas_nuevas' => 0,
        'marcas_existentes' => 0,
        'categorias_nuevas' => 0,
        'categorias_existentes' => 0,
        'unidades_nuevas' => 0,
        'unidades_existentes' => 0,
        'opus_homologados' => 0,
        'opus_sin_match' => 0,
    ];

    protected array $errorDetails = [];

    protected array $catalogMappings = [
        'marcas' => [],
        'categorias' => [],
        'unidades' => [],
    ];

    protected float $startTime;

    /** Resuelto en handle() para no serializar el servicio en el payload del job. */
    protected ?CatalogoOpusHomologacionService $opusMatch = null;

    /** Evita saturar la consola / BD en imports de decenas de miles de filas. */
    protected int $lastLoggedProgressPct = -1;

    /**
     * Create a new job instance.
     */
    public function __construct(ImportAudit $importAudit, array $options = [])
    {
        $this->importAudit = $importAudit;
        $this->options = array_merge([
            'chunk_size' => CatalogoImportPlantilla::JOB_CHUNK_DEFAULT,
            'skip_duplicates' => false,
            'update_existing' => true,
            'create_missing_relations' => true,
        ], $options);

        $this->options['chunk_size'] = max(
            CatalogoImportPlantilla::JOB_CHUNK_MIN,
            min(
                CatalogoImportPlantilla::JOB_CHUNK_MAX,
                (int) $this->options['chunk_size']
            )
        );
    }

    /**
     * Execute the job.
     */
    public function handle(CSVProcessorService $csvProcessor, CatalogoOpusHomologacionService $opusMatch): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($this->timeout);
        }
        @ini_set('max_execution_time', (string) $this->timeout);
        @ini_set('memory_limit', CatalogoImportPlantilla::MEMORY_LIMIT_JOB);

        $this->opusMatch = $opusMatch;
        $this->startTime = microtime(true);
        $validator = new CSVImportProductValidator($this->importAudit->proveedor_id);

        Log::info('Starting CSV import job', [
            'audit_id' => $this->importAudit->id,
            'proveedor_id' => $this->importAudit->proveedor_id,
            'options' => $this->options,
            'php_max_execution_time' => ini_get('max_execution_time'),
            'php_memory_limit' => ini_get('memory_limit'),
        ]);

        try {
            // Initialize processing state
            $this->initializeProcessing();

            // Get preview token and validate
            $previewToken = $this->importAudit->preview_data['preview_token'] ?? null;
            if (! $previewToken) {
                throw new Exception('Preview token not found in audit data');
            }

            // Obtener metadata desde la tabla temporal
            $cached = \App\Models\ImportValidationCache::where('token', $previewToken)
                ->where('expires_at', '>', now())
                ->first();

            if (! $cached || ! isset($cached->validation_data['temp_table'])) {
                throw new Exception('Datos de preview expirados o no encontrados');
            }

            $tableName = $cached->validation_data['temp_table'];
            $catalogosData = $cached->validation_data['catalogos_data'] ?? [];

            // Obtener total de registros de la tabla temporal
            $totalRegistros = DB::table($tableName)->count();
            $this->processingStats['total_registros'] = $totalRegistros;

            $this->logProcessingStep('Iniciando procesamiento desde tabla temporal', [
                'total_registros' => $totalRegistros,
                'tabla' => $tableName,
                'preview_token' => $previewToken,
            ]);

            // Procesar catálogos primero (usando datos ya extraídos en preview)
            $this->processCatalogsFromExtracted($catalogosData);
            $this->updateProgress(15, 0); // 15% después de catálogos

            // Procesar productos en chunks desde la tabla temporal
            $chunkSize = (int) $this->options['chunk_size'];
            $offset = 0;
            $processedCount = $this->importAudit->numero_registros_procesados;

            while ($offset < $totalRegistros) {
                // Obtener chunk desde tabla temporal
                $chunk = $csvProcessor->getTempTableDataPaginated($tableName, $chunkSize, $offset);

                if (empty($chunk)) {
                    break;
                }

                // Procesar chunk de productos
                DB::transaction(function () use ($chunk, $validator, &$processedCount) {
                    $this->processProductChunk($chunk, $validator, $processedCount);
                });

                $processedCount += count($chunk);
                $offset += $chunkSize;

                // Actualizar progreso basado en registros procesados
                $progressPercentage = 15 + (($processedCount / $totalRegistros) * 80); // 15-95%
                $this->updateProgress(min(95, $progressPercentage), $processedCount);

                // Liberar memoria después de cada chunk
                unset($chunk);
                if (($offset / $chunkSize) % 25 === 0) {
                    gc_collect_cycles();
                }

                // Log de auditoría solo cada ~5% (menos I/O y menos ruido en consola)
                $pctDone = (int) floor(($processedCount / max(1, $totalRegistros)) * 100);
                if ($pctDone >= $this->lastLoggedProgressPct + 5) {
                    $this->lastLoggedProgressPct = $pctDone;
                    $this->logProcessingStep('Progreso de importación', [
                        'procesados' => $processedCount,
                        'total' => $totalRegistros,
                        'porcentaje' => $pctDone.'%',
                    ]);
                }
            }

            // Finalizar procesamiento
            $this->finalizeProcessing($csvProcessor, $previewToken);

            $this->logProcessingStep('Importación completada exitosamente', [
                'estadisticas' => $this->processingStats,
                'tiempo_total' => round(microtime(true) - $this->startTime, 2).' segundos',
                'registros_procesados' => $processedCount,
            ]);
        } catch (Exception $e) {
            $this->handleJobFailure($e);
            throw $e;
        }
    }

    /**
     * Initialize processing state
     */
    protected function initializeProcessing(): void
    {
        $this->updateAuditState('procesando', 0);

        $this->importAudit->update([
            'inicio_proceso' => now(),
            'progreso' => 0,
        ]);

        $this->logProcessingStep('Iniciando proceso de importación', [
            'opciones' => $this->options,
        ]);
    }

    /**
     * Process all catalog data (brands, categories, units)
     */
    protected function processCatalogs(array $csvData, CSVImportProductValidator $validator): void
    {
        $this->logProcessingStep('Iniciando procesamiento de catálogos');

        // Extract unique catalog data
        $catalogData = $this->extractCatalogData($csvData);

        // Process each catalog type
        $this->processBrands($catalogData['marcas']);
        // $this->updateProgress(10);

        $this->processCategories($catalogData['categorias']);
        // $this->updateProgress(20);

        $this->processUnits($catalogData['unidades']);
        // $this->updateProgress(30);

        $this->logProcessingStep('Catálogos procesados', [
            'marcas' => count($this->catalogMappings['marcas']),
            'categorias' => count($this->catalogMappings['categorias']),
            'unidades' => count($this->catalogMappings['unidades']),
        ]);
    }

    /**
     * Extract unique catalog data from CSV
     */
    protected function extractCatalogData(array $csvData): array
    {
        $catalogs = [
            'marcas' => [],
            'unidades' => [],
            'categorias' => [],
        ];

        foreach ($csvData as $row) {
            // Extract brands
            if (! empty($row['marca']) && ! in_array($row['marca'], $catalogs['marcas'])) {
                $catalogs['marcas'][] = trim($row['marca']);
            }

            // Extract units
            if (! empty($row['unidad_medida']) && ! in_array($row['unidad_medida'], $catalogs['unidades'])) {
                $catalogs['unidades'][] = trim($row['unidad_medida']);
            }

            // Extract categories with hierarchy
            if (! empty($row['categoria'])) {
                $categoria = trim($row['categoria']);
                $subcategoria = ! empty($row['subcategoria']) ? trim($row['subcategoria']) : null;

                if (! isset($catalogs['categorias'][$categoria])) {
                    $catalogs['categorias'][$categoria] = [];
                }

                if ($subcategoria && ! in_array($subcategoria, $catalogs['categorias'][$categoria])) {
                    $catalogs['categorias'][$categoria][] = $subcategoria;
                }
            }
        }

        return $catalogs;
    }

    /**
     * Process brands
     */
    protected function processBrands(array $brands): void
    {
        $this->logProcessingStep('Procesando marcas', ['total' => count($brands)]);

        foreach ($brands as $brandName) {
            try {
                $marca = Marca::firstOrCreate(
                    [
                        'nombre' => $brandName,
                        'proveedor_id' => $this->importAudit->proveedor_id,
                    ],
                    [
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $this->catalogMappings['marcas'][$brandName] = $marca->id;

                if ($marca->wasRecentlyCreated) {
                    $this->processingStats['marcas_nuevas']++;
                } else {
                    $this->processingStats['marcas_existentes']++;
                }
            } catch (Exception $e) {
                Log::warning('Error procesando marca', [
                    'marca' => $brandName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process categories with hierarchy
     */
    protected function processCategories(array $categories): void
    {
        $this->logProcessingStep('Procesando categorías', ['total' => count($categories)]);

        foreach ($categories as $categoriaName => $subcategorias) {
            try {
                // Create main category
                $categoria = Categoria::firstOrCreate(
                    [
                        'nombre' => $categoriaName,
                        'proveedor_id' => $this->importAudit->proveedor_id,
                        'nivel' => 1,
                        'parent_id' => null,
                    ],
                    [
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $this->catalogMappings['categorias'][$categoriaName] = $categoria->id;

                if ($categoria->wasRecentlyCreated) {
                    $this->processingStats['categorias_nuevas']++;
                } else {
                    $this->processingStats['categorias_existentes']++;
                }

                // Create subcategories
                foreach ($subcategorias as $subcategoriaName) {
                    $subcategoria = Categoria::firstOrCreate(
                        [
                            'nombre' => $subcategoriaName,
                            'parent_id' => $categoria->id,
                            'proveedor_id' => $this->importAudit->proveedor_id,
                            'nivel' => 2,
                        ],
                        [
                            'activo' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $this->catalogMappings['categorias'][$subcategoriaName] = $subcategoria->id;

                    if ($subcategoria->wasRecentlyCreated) {
                        $this->processingStats['categorias_nuevas']++;
                    } else {
                        $this->processingStats['categorias_existentes']++;
                    }
                }
            } catch (Exception $e) {
                Log::warning('Error procesando categoría', [
                    'categoria' => $categoriaName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process units
     */
    protected function processUnits(array $units): void
    {
        $this->logProcessingStep('Procesando unidades de medida', ['total' => count($units)]);

        foreach ($units as $unitName) {
            try {
                $unidad = UnidadMedida::firstOrCreate(
                    [
                        'nombre' => $unitName,
                    ],
                    [
                        'estatus' => 'activo',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $this->catalogMappings['unidades'][$unitName] = $unidad->id;

                if ($unidad->wasRecentlyCreated) {
                    $this->processingStats['unidades_nuevas']++;
                } else {
                    $this->processingStats['unidades_existentes']++;
                }
            } catch (Exception $e) {
                Log::warning('Error procesando unidad', [
                    'unidad' => $unitName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process products in chunks
     */
    protected function processProducts(array $csvData, CSVImportProductValidator $validator): void
    {
        $this->logProcessingStep('Iniciando procesamiento de productos');

        $chunkSize = $this->options['chunk_size'];
        $totalProducts = count($csvData);
        $processedCount = $this->importAudit->numero_registros_procesados;

        // Process products in chunks
        $chunks = array_chunk($csvData, $chunkSize);
        $totalChunks = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $this->processProductChunk($chunk, $validator, $processedCount);
                $processedCount += count($chunk);

                // Update progress (30% to 95%)
                $progressPercentage = 30 + (($processedCount / $totalProducts) * 65);
                $this->updateProgress(min(95, $progressPercentage), $processedCount);

                $this->logProcessingStep(
                    'Chunk procesado '.($chunkIndex + 1)."/{$totalChunks}",
                    [
                        'productos_procesados' => $processedCount,
                        'total_productos' => $totalProducts,
                    ]
                );
            } catch (Exception $e) {
                Log::error('Error procesando chunk de productos', [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);

                // Continue with next chunk
                $this->processingStats['productos_error'] += count($chunk);

                continue;
            }
        }
    }

    /**
     * Process a chunk of products
     */
    protected function processProductChunk(array $productChunk, CSVImportProductValidator $validator, int $baseRowNumber): void
    {
        foreach ($productChunk as $index => $productData) {
            $rowNumber = $baseRowNumber + $index + 1;

            try {
                // Validate product data
                $validationResult = $validator->validateRow($productData, $rowNumber);

                if (! empty($validationResult['errors'])) {
                    $this->recordProductError($rowNumber, $productData, $validationResult['errors']);

                    continue;
                }

                // Process single product
                $this->processSingleProduct($productData, $rowNumber);
            } catch (Exception $e) {
                $this->recordProductError($rowNumber, $productData, [$e->getMessage()]);
                Log::warning('Error procesando producto individual', [
                    'row' => $rowNumber,
                    'codigo' => $productData['codigo'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process a single product
     */
    protected function processSingleProduct(array $productData, int $rowNumber): void
    {
        // Map relationships
        $marcaId = $this->catalogMappings['marcas'][$productData['marca']] ?? null;
        $categoriaId = null;
        $subcategoriaId = null;

        if (! empty($productData['categoria'])) {
            $categoriaId = $this->catalogMappings['categorias'][$productData['categoria']] ?? null;
            if (! empty($productData['subcategoria'])) {
                $subcategoriaId = $this->catalogMappings['categorias'][$productData['subcategoria']] ?? null;
            }
        }

        $unidadId = $this->catalogMappings['unidades'][$productData['unidad_medida']] ?? null;

        $familiaTxt = trim((string) ($productData['familia'] ?? ''));
        $subfamiliaTxt = trim((string) ($productData['subfamilia'] ?? ''));
        if ($familiaTxt === '') {
            $familiaTxt = trim((string) ($productData['categoria'] ?? ''));
        }
        if ($subfamiliaTxt === '') {
            $subfamiliaTxt = trim((string) ($productData['subcategoria'] ?? ''));
        }
        $opus = $this->opusMatch->match($familiaTxt, $subfamiliaTxt);
        if ($opus['matched']) {
            $this->processingStats['opus_homologados']++;
        } elseif ($familiaTxt !== '' || $subfamiliaTxt !== '') {
            $this->processingStats['opus_sin_match']++;
        }

        $precioBase = $productData['precio'] ?? $productData['precio_base'] ?? null;

        // Prepare product data
        $productAttributes = [
            'codigo_interno' => $productData['codigo'],
            'proveedor_id' => $this->importAudit->proveedor_id,
        ];

        $productValues = [
            'nombre' => $productData['producto'],
            'descripcion' => $productData['descripcion'] ?? null,
            'marca_id' => $marcaId,
            'categoria_id' => $categoriaId,
            'subcategoria_id' => $subcategoriaId,
            'unidad_medida_id' => $unidadId,
            'familia_id' => $opus['familia_id'],
            'subfamilia_id' => $opus['subfamilia_id'],
            'precio_base' => $this->parseNullablePrice($precioBase),
            'precio_mayoreo' => $this->parseNullablePrice($productData['precio_mayoreo'] ?? null),
            'precio_menudeo' => $this->parseNullablePrice($productData['precio_menudeo'] ?? null),
            'tipo' => $this->nullableString($productData['tipo'] ?? null),
            'modelo' => $this->nullableString($productData['modelo'] ?? null),
            'codigo_fabricante' => $this->nullableString($productData['codigo_fabricante'] ?? null),
            'codigo_barras' => $this->nullableString($productData['codigo_barras'] ?? null),
            'presentacion' => $this->nullableString($productData['presentacion'] ?? null),
            'cantidad_contenida' => isset($productData['cantidad_contenida']) && is_numeric($productData['cantidad_contenida'])
                ? (float) $productData['cantidad_contenida'] : null,
            'factor_conversion' => isset($productData['factor_conversion']) && is_numeric($productData['factor_conversion'])
                ? (float) $productData['factor_conversion'] : null,
            'disponibilidad' => $this->nullableString($productData['disponibilidad'] ?? null),
            'tiempo_entrega' => $this->nullableString($productData['tiempo_entrega'] ?? null),
            'url_producto' => $this->nullableString($productData['url_producto'] ?? null),
            'tags' => $this->parseTags($productData['tags'] ?? null),
            'activo' => true,
            'updated_at' => now(),
        ];

        // Create or update product
        if ($this->options['update_existing']) {
            $producto = Producto::updateOrCreate(
                $productAttributes,
                array_merge($productValues, ['created_at' => now()])
            );

            if ($producto->wasRecentlyCreated) {
                $this->processingStats['productos_nuevos']++;
            } else {
                $this->processingStats['productos_actualizados']++;
            }
        } else {
            // Only create if doesn't exist
            $existing = Producto::where($productAttributes)->first();
            if (! $existing) {
                $producto = Producto::create(array_merge($productAttributes, $productValues, ['created_at' => now()]));
                $this->processingStats['productos_nuevos']++;
            } else {
                if (! $this->options['skip_duplicates']) {
                    $this->recordProductError($rowNumber, $productData, ['Producto ya existe y update_existing está desactivado']);
                }

                return;
            }
        }

        $this->syncPropiedadesFromRow($producto, $productData);
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    protected function parseTags(mixed $value): ?array
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $value) ?: [])));
    }

    protected function syncPropiedadesFromRow(Producto $producto, array $row): void
    {
        $props = [];
        foreach ($row as $key => $value) {
            if (! is_string($key) || ! preg_match('/^propiedad(\d+)_clave$/i', $key, $m)) {
                continue;
            }
            $n = $m[1];
            $clave = trim((string) $value);
            $valor = trim((string) ($row["propiedad{$n}_valor"] ?? $row["Propiedad{$n}_Valor"] ?? ''));
            if ($clave === '') {
                continue;
            }
            $props[] = [
                'atributo' => $clave,
                'valor' => $valor,
                'orden' => (int) $n,
            ];
        }

        if ($props === []) {
            return;
        }

        $producto->especificaciones()->delete();
        foreach ($props as $prop) {
            $producto->especificaciones()->create($prop);
        }
    }

    /**
     * Parse price value
     */
    protected function parsePrice($price): float
    {
        $parsed = $this->parseNullablePrice($price);

        return $parsed ?? 0.0;
    }

    /**
     * Acepta null/vacío → null; 0 y números >= 0 → float; inválidos → null.
     */
    protected function parseNullablePrice(mixed $price): ?float
    {
        if ($price === null) {
            return null;
        }

        if (is_string($price)) {
            $price = trim($price);
            if ($price === '' || strtolower($price) === 'null') {
                return null;
            }
        }

        if (is_numeric($price)) {
            $value = (float) $price;

            return $value < 0 ? null : $value;
        }

        $cleanPrice = preg_replace('/[^0-9.]/', '', (string) $price);
        if ($cleanPrice === '' || $cleanPrice === null) {
            return null;
        }

        $value = (float) $cleanPrice;

        return $value < 0 ? null : $value;
    }

    /**
     * Record product processing error
     */
    protected function recordProductError(int $rowNumber, array $productData, array $errors): void
    {
        $this->processingStats['productos_error']++;

        $this->errorDetails[] = [
            'row' => $rowNumber,
            'producto' => $productData['codigo'] ?? 'N/A',
            'nombre' => $productData['producto'] ?? 'N/A',
            'errores' => $errors,
            'tipo_error' => 'validacion',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Finalize processing and cleanup
     */
    protected function finalizeProcessing(CSVProcessorService $csvProcessor, string $previewToken): void
    {
        // Update final statistics
        $this->updateAuditState('completado', 100);

        $processingTime = round(microtime(true) - $this->startTime, 2);

        $this->importAudit->update([
            'fin_proceso' => now(),
            'nuevos' => $this->processingStats['productos_nuevos'],
            'actualizados' => $this->processingStats['productos_actualizados'],
            'errores' => $this->processingStats['productos_error'],
            'errores_detalle' => $this->errorDetails,
            'processing_time' => $processingTime,
            'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            // Catalog statistics
            'marca_imported' => $this->processingStats['marcas_nuevas'],
            'marca_errors' => 0, // We don't track catalog errors separately for now
            'marca_total' => $this->processingStats['marcas_nuevas'] + $this->processingStats['marcas_existentes'],
            'categoria_imported' => $this->processingStats['categorias_nuevas'],
            'categoria_errors' => 0,
            'categoria_total' => $this->processingStats['categorias_nuevas'] + $this->processingStats['categorias_existentes'],
            'unidad_imported' => $this->processingStats['unidades_nuevas'],
            'unidad_errors' => 0,
            'unidad_total' => $this->processingStats['unidades_nuevas'] + $this->processingStats['unidades_existentes'],
        ]);

        $this->importAudit->appendLog('Homologación OPUS', [
            'homologados' => $this->processingStats['opus_homologados'],
            'sin_match' => $this->processingStats['opus_sin_match'],
        ]);

        $previewData = $this->importAudit->preview_data ?? [];
        $previewData['opus'] = [
            'homologados' => $this->processingStats['opus_homologados'],
            'sin_match' => $this->processingStats['opus_sin_match'],
        ];
        $this->importAudit->update([
            'preview_data' => $previewData,
            'logs' => $this->importAudit->logs,
        ]);

        // Cleanup temporary data
        $this->cleanupTemporaryData($csvProcessor, $previewToken);
    }

    /**
     * Cleanup temporary tables and cache
     */
    protected function cleanupTemporaryData(CSVProcessorService $csvProcessor, string $previewToken): void
    {
        try {
            $csvProcessor->cleanupTempTable($previewToken);
            $this->logProcessingStep('Datos temporales limpiados exitosamente');
        } catch (Exception $e) {
            Log::warning('Error limpiando datos temporales', [
                'preview_token' => $previewToken,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update audit state and progress
     */
    protected function updateAuditState(string $estado, ?float $progreso = null): void
    {
        $updateData = ['estado' => $estado];

        if ($progreso !== null) {
            $updateData['progreso'] = $progreso;
        }

        $this->importAudit->update($updateData);
    }

    /**
     * Update progress percentage
     */
    protected function updateProgress(float $percentage, int $count_registros_procesados): void
    {
        $pct = min(100, max(0, $percentage));
        // Evitar UPDATE por cada chunk: solo cuando cambia al menos 1 punto porcentual
        $prev = (float) ($this->importAudit->progreso ?? 0);
        if ($pct < 100 && abs($pct - $prev) < 1) {
            $this->importAudit->numero_registros_procesados = $count_registros_procesados;

            return;
        }

        $this->importAudit->update([
            'progreso' => $pct,
            'numero_registros_procesados' => $count_registros_procesados,
        ]);
    }

    /**
     * Log processing step
     */
    protected function logProcessingStep(string $message, array $context = []): void
    {
        $this->importAudit->appendLog($message, $context);
        $this->importAudit->save();
    }

    /**
     * Handle job failure
     */
    protected function handleJobFailure(Exception $e): void
    {
        Log::error('CSV Import Job failed', [
            'audit_id' => $this->importAudit->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'processing_stats' => $this->processingStats,
        ]);

        $this->updateAuditState('error', $this->importAudit->progreso);

        $this->importAudit->update([
            'fin_proceso' => now(),
            'errores_detalle' => array_merge($this->errorDetails, [[
                'error' => 'Job failure: '.$e->getMessage(),
                'tipo_error' => 'sistema',
                'timestamp' => now()->toISOString(),
            ]]),
            'processing_time' => round(microtime(true) - $this->startTime, 2),
            'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        $this->logProcessingStep('Error crítico en importación', [
            'error' => $e->getMessage(),
            'estadisticas_parciales' => $this->processingStats,
        ]);

        $previewToken = $this->importAudit->preview_data['preview_token'] ?? null;
        if ($previewToken) {
            try {
                app(CSVProcessorService::class)->cleanupTempTable($previewToken);
            } catch (Exception $cleanupError) {
                Log::warning('Error limpiando datos temporales tras fallo de job', [
                    'preview_token' => $previewToken,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Procesa catálogos desde datos ya extraídos en el preview
     * Más eficiente en memoria ya que no necesita volver a procesar todo el CSV
     *
     * @param  array  $catalogosData  Datos de catálogos extraídos en preview
     */
    protected function processCatalogsFromExtracted(array $catalogosData): void
    {
        $this->logProcessingStep('Iniciando procesamiento de catálogos desde datos extraídos');

        // Procesar marcas
        if (isset($catalogosData['marcas']) && ! empty($catalogosData['marcas'])) {
            $this->processBrands($catalogosData['marcas']);
        }

        // Procesar categorías con jerarquía
        if (isset($catalogosData['categorias']) && ! empty($catalogosData['categorias'])) {
            $this->processCategories($catalogosData['categorias']);
        }

        // Procesar unidades
        if (isset($catalogosData['unidades']) && ! empty($catalogosData['unidades'])) {
            $this->processUnits($catalogosData['unidades']);
        }

        $this->logProcessingStep('Catálogos procesados exitosamente', [
            'marcas_procesadas' => count($this->catalogMappings['marcas']),
            'categorias_procesadas' => count($this->catalogMappings['categorias']),
            'unidades_procesadas' => count($this->catalogMappings['unidades']),
            'estadisticas' => [
                'marcas_nuevas' => $this->processingStats['marcas_nuevas'],
                'marcas_existentes' => $this->processingStats['marcas_existentes'],
                'categorias_nuevas' => $this->processingStats['categorias_nuevas'],
                'categorias_existentes' => $this->processingStats['categorias_existentes'],
                'unidades_nuevas' => $this->processingStats['unidades_nuevas'],
                'unidades_existentes' => $this->processingStats['unidades_existentes'],
            ],
        ]);

        // Liberar memoria después de procesar catálogos
        gc_collect_cycles();
    }
}
