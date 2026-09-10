<?php

namespace App\Services\CSVImport;

use App\Jobs\ImportProcessorJob;
use App\Models\Categoria;
use App\Models\ImportAudit;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportProcessorService
{
    private const CHUNK_SIZE = 1000;

    private const LARGE_IMPORT_THRESHOLD = 5000;

    private array $catalogCache = [];

    /**
     * Process import with optimized chunking and jobs for large imports
     */
    public function processImport(array $productosData, Proveedor $proveedor, ImportAudit $importAudit, bool $usePreview = true): array
    {
        $totalRecords = count($productosData);

        // Decidir si usar jobs o procesamiento síncrono
        if ($totalRecords > self::LARGE_IMPORT_THRESHOLD) {
            return $this->processWithJobs($productosData, $proveedor, $importAudit, $usePreview);
        }

        return $this->processSynchronously($productosData, $proveedor, $importAudit);
    }

    /**
     * Process with queue jobs for large imports
     */
    private function processWithJobs(array $productosData, Proveedor $proveedor, ImportAudit $importAudit, bool $usePreview = true): array
    {
        $importAudit->appendLog('Importación grande detectada. Usando sistema de colas.', [
            'total_registros' => count($productosData),
            'chunk_size' => self::CHUNK_SIZE,
            'use_preview' => $usePreview,
        ]);

        // Actualizar estado para indicar que se está procesando en cola
        $importAudit->update([
            'estado' => 'queued',
            'fase' => $usePreview ? 'preview' : 'processing',
        ]);

        // Dividir en chunks y crear jobs
        $chunks = array_chunk($productosData, self::CHUNK_SIZE);
        $jobIds = [];

        foreach ($chunks as $index => $chunk) {
            $job = new ImportProcessorJob($importAudit->id, $chunk, $index, count($chunks), $usePreview);
            $jobIds[] = dispatch($job)->getJobId();
        }

        $importAudit->appendLog('Jobs de procesamiento creados', [
            'chunks_count' => count($chunks),
            'job_ids' => $jobIds,
        ]);

        return [
            'processing_type' => 'async',
            'chunks_count' => count($chunks),
            'job_ids' => $jobIds,
            'import_audit_id' => $importAudit->id,
            'message' => 'Importación iniciada en segundo plano. Use el endpoint de estado para monitorear el progreso.',
        ];
    }

    /**
     * Process synchronously for smaller imports
     */
    private function processSynchronously(array $productosData, Proveedor $proveedor, ImportAudit $importAudit): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $importAudit->appendLog('Procesando importación sincronizada', [
            'total_registros' => count($productosData),
            'chunk_size' => self::CHUNK_SIZE,
        ]);

        // Inicializar cache de catálogos
        $this->initializeCatalogCache($proveedor);

        $result = [
            'productos_creados' => 0,
            'productos_actualizados' => 0,
            'errores' => [],
            'error_types' => [],
            'processing_time' => 0,
            'memory_usage' => 0,
        ];

        $chunks = array_chunk($productosData, self::CHUNK_SIZE);
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $chunkResult = $this->processChunkWithTransaction($chunk, $proveedor, $importAudit, $index + 1, $totalChunks);

            // Agregar resultados
            $result['productos_creados'] += $chunkResult['productos_creados'];
            $result['productos_actualizados'] += $chunkResult['productos_actualizados'];
            $result['errores'] = array_merge($result['errores'], $chunkResult['errores']);

            // Agregar tipos de error únicos
            foreach ($chunkResult['error_types'] as $errorType) {
                if (! in_array($errorType, $result['error_types'])) {
                    $result['error_types'][] = $errorType;
                }
            }
        }

        // Calcular métricas finales
        $result['processing_time'] = round(microtime(true) - $startTime, 2);
        $result['memory_usage'] = round((memory_get_peak_usage() - $startMemory) / 1024 / 1024, 2);

        // Actualizar audit con estadísticas finales
        $this->updateImportAuditStats($importAudit, $result);

        return [
            'processing_type' => 'sync',
            'result' => $result,
            'import_audit_id' => $importAudit->id,
        ];
    }

    /**
     * Process chunk with transaction and savepoints
     */
    private function processChunkWithTransaction(array $chunk, Proveedor $proveedor, ImportAudit $importAudit, int $chunkNumber, int $totalChunks): array
    {
        $result = [
            'productos_creados' => 0,
            'productos_actualizados' => 0,
            'errores' => [],
            'error_types' => [],
        ];

        DB::beginTransaction();

        try {
            // Crear savepoint
            DB::unprepared('SAVEPOINT chunk_'.$chunkNumber);

            $importAudit->appendLog("Procesando chunk {$chunkNumber} de {$totalChunks}", [
                'chunk_size' => count($chunk),
            ]);

            // Procesar catálogos primero
            $this->processCatalogs($chunk, $proveedor);

            // Procesar productos usando upsert y bulk insert optimizados
            $chunkResult = $this->processProductsOptimized($chunk, $proveedor);

            $result['productos_creados'] = $chunkResult['created'];
            $result['productos_actualizados'] = $chunkResult['updated'];
            $result['errores'] = $chunkResult['errors'];
            $result['error_types'] = $chunkResult['error_types'];

            DB::commit();

            // Actualizar progreso
            $progress = ($chunkNumber / $totalChunks) * 100;
            $importAudit->update(['progreso' => $progress]);

            $importAudit->appendLog("Chunk {$chunkNumber} procesado exitosamente", [
                'productos_creados' => $result['productos_creados'],
                'productos_actualizados' => $result['productos_actualizados'],
                'errores' => count($result['errores']),
            ]);
        } catch (\Throwable $e) {
            // Rollback to savepoint
            DB::unprepared('ROLLBACK TO SAVEPOINT chunk_'.$chunkNumber);
            DB::rollback();

            $errorType = get_class($e);
            $result['error_types'][] = $errorType;

            // Registrar todos los items del chunk como errores
            foreach ($chunk as $item) {
                $result['errores'][] = [
                    'item' => $item,
                    'error' => $e->getMessage(),
                    'error_type' => $errorType,
                    'chunk' => $chunkNumber,
                ];
            }

            $importAudit->appendLog("Error procesando chunk {$chunkNumber}", [
                'error' => $e->getMessage(),
                'error_type' => $errorType,
                'chunk_size' => count($chunk),
            ]);

            Log::error('Error en chunk de importación', [
                'chunk_number' => $chunkNumber,
                'proveedor_id' => $proveedor->id,
                'import_audit_id' => $importAudit->id,
                'error' => $e->getMessage(),
                'error_type' => $errorType,
            ]);
        }

        return $result;
    }

    /**
     * Initialize catalog cache for optimization
     */
    private function initializeCatalogCache(Proveedor $proveedor): void
    {
        $cacheKey = "import_catalogs_{$proveedor->id}";

        $this->catalogCache = Cache::remember($cacheKey, 300, function () use ($proveedor) {
            return [
                'marcas' => Marca::where('proveedor_id', $proveedor->id)->pluck('id', 'nombre')->toArray(),
                'categorias' => Categoria::where('proveedor_id', $proveedor->id)
                    ->whereNull('parent_id')
                    ->pluck('id', 'nombre')
                    ->toArray(),
                'unidades' => UnidadMedida::query()->pluck('id', 'nombre')->toArray(),
            ];
        });
    }

    /**
     * Process catalogs (marcas, categorias, unidades)
     */
    private function processCatalogs(array $chunk, Proveedor $proveedor): void
    {
        // Extraer datos únicos para cada catálogo
        $marcasData = collect($chunk)->pluck('marca')->filter()->unique()->values();
        $categoriasData = collect($chunk)->pluck('categoria')->filter()->unique()->values();
        $unidadesData = collect($chunk)->pluck('unidad_medida')->filter()->unique()->values();

        // Crear marcas faltantes
        $this->createMissingCatalogItems($marcasData, 'marcas', Marca::class, $proveedor);

        // Crear categorías faltantes
        $this->createMissingCatalogItems($categoriasData, 'categorias', Categoria::class, $proveedor);

        // Crear unidades faltantes
        $this->createMissingCatalogItems($unidadesData, 'unidades', UnidadMedida::class, $proveedor);
    }

    /**
     * Create missing catalog items using bulk insert
     */
    private function createMissingCatalogItems($items, string $cacheKey, string $modelClass, Proveedor $proveedor): void
    {
        $existing = $this->catalogCache[$cacheKey] ?? [];
        $missing = $items->filter(function ($item) use ($existing) {
            return ! isset($existing[$item]);
        });

        if ($missing->isNotEmpty()) {
            $isUnidad = $modelClass === UnidadMedida::class;

            $insertData = $missing->map(function ($item) use ($proveedor, $isUnidad) {
                $row = [
                    'nombre' => $item,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (! $isUnidad) {
                    $row['proveedor_id'] = $proveedor->id;
                }

                return $row;
            })->toArray();

            $modelClass::insert($insertData);

            // Actualizar cache
            $newQuery = $modelClass::query()->whereIn('nombre', $missing->toArray());
            if (! $isUnidad) {
                $newQuery->where('proveedor_id', $proveedor->id);
            }
            $newItems = $newQuery->pluck('id', 'nombre');

            $this->catalogCache[$cacheKey] = array_merge($existing, $newItems->toArray());
        }
    }

    /**
     * Process products with optimized upsert and bulk insert
     */
    private function processProductsOptimized(array $chunk, Proveedor $proveedor): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => [], 'error_types' => []];

        // Separar productos existentes y nuevos
        $codigos = collect($chunk)->pluck('codigo');
        $existingProducts = Producto::where('proveedor_id', $proveedor->id)
            ->whereIn('codigo_interno', $codigos)
            ->pluck('codigo_interno')
            ->toArray();

        $productsToUpdate = [];
        $productsToInsert = [];

        foreach ($chunk as $item) {
            try {
                $productData = $this->prepareProductData($item, $proveedor);

                if (in_array($item['codigo'], $existingProducts)) {
                    $productsToUpdate[] = $productData;
                } else {
                    $productsToInsert[] = $productData;
                }
            } catch (\Throwable $e) {
                $errorType = get_class($e);
                $result['errors'][] = [
                    'item' => $item,
                    'error' => $e->getMessage(),
                    'error_type' => $errorType,
                ];

                if (! in_array($errorType, $result['error_types'])) {
                    $result['error_types'][] = $errorType;
                }
            }
        }

        // Bulk insert for new products
        if (! empty($productsToInsert)) {
            Producto::insert($productsToInsert);
            $result['created'] = count($productsToInsert);
        }

        // Bulk update for existing products using upsert
        if (! empty($productsToUpdate)) {
            Producto::upsert(
                $productsToUpdate,
                ['codigo_interno', 'proveedor_id'], // uniqueBy
                ['nombre', 'descripcion', 'precio', 'precio_mayoreo', 'precio_menuedeo', 'marca_id', 'categoria_id', 'unidad_medida_id', 'updated_at'] // update columns
            );
            $result['updated'] = count($productsToUpdate);
        }

        return $result;
    }

    /**
     * Prepare product data for insertion/update
     */
    private function prepareProductData(array $item, Proveedor $proveedor): array
    {
        $marcaId = isset($item['marca']) ? ($this->catalogCache['marcas'][$item['marca']] ?? null) : null;
        $categoriaId = isset($item['categoria']) ? ($this->catalogCache['categorias'][$item['categoria']] ?? null) : null;
        $unidadId = isset($item['unidad_medida']) ? ($this->catalogCache['unidades'][$item['unidad_medida']] ?? null) : null;

        return [
            'codigo_interno' => $item['codigo'],
            'nombre' => $item['producto'],
            'descripcion' => $item['descripcion'] ?? null,
            'modelo' => $item['modelo'] ?? null,
            'precio' => $item['precio'] ?? null,
            'precio_mayoreo' => $item['precio_mayoreo'] ?? null,
            'precio_menuedeo' => $item['precio_menuedeo'] ?? null,
            'marca_id' => $marcaId,
            'categoria_id' => $categoriaId,
            'unidad_medida_id' => $unidadId,
            'proveedor_id' => $proveedor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Update import audit with final statistics
     */
    private function updateImportAuditStats(ImportAudit $importAudit, array $result): void
    {
        $importAudit->update([
            'estado' => 'completed',
            'fase' => 'completed',
            'progreso' => 100,
            'fin_proceso' => now(),
            'nuevos' => $result['productos_creados'],
            'actualizados' => $result['productos_actualizados'],
            'errores' => count($result['errores']),
            'errores_detalle' => $result['errores'],
            'error_types' => $result['error_types'],
            'processing_time' => $result['processing_time'],
            'memory_usage' => $result['memory_usage'],
        ]);

        $importAudit->appendLog('Importación completada', [
            'productos_creados' => $result['productos_creados'],
            'productos_actualizados' => $result['productos_actualizados'],
            'errores' => count($result['errores']),
            'processing_time' => $result['processing_time'].'s',
            'memory_usage' => $result['memory_usage'].'MB',
        ]);

        // Limpiar cache de catálogos
        Cache::forget("import_catalogs_{$importAudit->proveedor_id}");
    }
}
