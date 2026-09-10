<?php

namespace App\Http\Controllers;

use App\Http\Responses\CsvUploadResponse;
use App\Http\Responses\CsvValidateProductResponse;
use App\Jobs\CSVImportJob;
use App\Models\Categoria;
use App\Models\ImportAudit;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Services\CSVImport\CSVImportExportService;
use App\Services\CSVImport\CSVImportProductValidator;
use App\Services\CSVImport\CSVProcessorService;
use App\Support\Catalogo\CatalogoImportPlantilla;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CsvImportController extends Controller
{
    use ApiResponse;

    protected CSVProcessorService $csvProcessor;

    protected CSVImportExportService $exportService;

    public function __construct(CSVProcessorService $csvProcessor, CSVImportExportService $exportService)
    {
        $this->csvProcessor = $csvProcessor;
        $this->exportService = $exportService;
    }

    /**
     * POST /api/proveedores/{id}/csv-import
     * Subida del archivo y análisis
     */
    public function upload(Request $request, $id)
    {
        try {
            // Validar el proveedor
            $proveedor = Proveedor::findOrFail($id);

            // Validar el archivo
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240',
                'delimiter' => 'nullable|string',
                'encoding' => 'nullable|string|in:UTF-8,ISO-8859-1,Windows-1252',
                'has_header' => 'nullable|boolean',
                'preview_rows' => 'nullable|integer|min:-1|max:500',
            ], [
                'file.required' => 'El archivo es obligatorio.',
                'file.file' => 'Debe ser un archivo válido.',
                'file.mimes' => 'El archivo debe ser de tipo CSV o TXT.',
                'file.max' => 'El archivo no debe exceder los 10MB.',
            ]);

            $file = $request->file('file');
            $originalExtension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();

            // Configurar opciones de procesamiento
            $options = [
                'delimiter' => $this->getDelimiter($request->get('delimiter', 'comma')),
                'encoding' => $request->get('encoding', 'UTF-8'),
                'has_header' => $request->get('has_header', true),
                'preview_rows' => $request->get('preview_rows', 100),
                'strict_validation' => true,
                'auto_create_relations' => false,
            ];

            // Procesar archivo CSV y generar preview
            $processingResult = $this->csvProcessor->processCSVPreview($file, $proveedor->id, $options);

            if (! $processingResult['success']) {
                return $this->error($processingResult['error'], 422);
            }

            // Guardar el archivo
            $filename = "csv_import_{$proveedor->id}_".time().'.'.$originalExtension;
            $path = $file->storeAs('imports', $filename, 'local');

            // Crear registro de auditoría
            $jobId = Str::uuid()->toString();
            $audit = ImportAudit::create([
                'job_id' => $jobId,
                'ooptions_read_csv' => $options,
                'proveedor_id' => $proveedor->id,
                'tipo' => 'productos',
                'archivo' => $path,
                'formato' => 'csv',
                'plantilla_version' => CatalogoImportPlantilla::VERSION,
                'plantilla_fecha' => CatalogoImportPlantilla::FECHA,
                'estado' => 'preview',
                // 'fase' => 'analisis_completado',
                'preview_data' => [
                    'file_info' => $processingResult['file_info'],
                    'headers' => $processingResult['headers'],
                    'preview_data' => $processingResult['preview_data'],
                    'validation_summary' => $processingResult['validation_summary'],
                    'quality_metrics' => $processingResult['quality_metrics'],
                    'preview_token' => $processingResult['preview_token'],
                    'plantilla' => [
                        'version' => CatalogoImportPlantilla::VERSION,
                        'fecha' => CatalogoImportPlantilla::FECHA,
                    ],
                ],
                'total_registros' => $processingResult['file_info']['total_rows'],
                'progreso' => 100,
            ]);

            $audit->appendLog('Archivo CSV cargado y analizado correctamente', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'total_rows' => $processingResult['file_info']['total_rows'],
            ]);

            $response = CsvUploadResponse::fromProcessorResult($audit->id, $jobId, $processingResult);

            return $this->success($response->toArray(), 'Archivo cargado y analizado correctamente');
        } catch (Exception $e) {
            Log::error('Error en upload CSV', [
                'proveedor_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Error al procesar el archivo: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/proveedores/{id}/csv-import/confirm
     * Confirmar la importación
     */
    public function confirm(Request $request, $id)
    {
        try {
            // Validar el proveedor
            $proveedor = Proveedor::findOrFail($id);

            // Validar datos de entrada
            $request->validate([
                'audit_id' => 'required|integer|exists:import_audits,id',
                'preview_token' => 'required|string',
                'import_options' => 'nullable|array',
                'import_options.skip_duplicates' => 'nullable|boolean',
                'import_options.update_existing' => 'nullable|boolean',
                'import_options.create_missing_relations' => 'nullable|boolean',
            ]);

            // Extraer valores en variables
            $auditId = $request->input('audit_id');
            $previewToken = $request->input('preview_token');
            $skipDuplicates = $request->input('import_options.skip_duplicates', false);
            $updateExisting = $request->input('import_options.update_existing', false);
            $createMissingRelations = $request->input('import_options.create_missing_relations', false);

            // Buscar el audit
            $audit = ImportAudit::where('id', $auditId)
                ->where('proveedor_id', $proveedor->id)
                // ->where('estado', 'preview')
                ->first();

            if (! $audit) {
                return $this->error('No se encontró la importación o ya fue procesada', 404);
            }

            // Verificar que tenemos el token de preview
            $previewData = $audit->preview_data;
            if (! $previewData || $previewData['preview_token'] !== $request->preview_token) {
                return $this->error('Token de preview inválido', 400);
            }

            // Configurar opciones de importación
            $importOptions = [
                'skip_duplicates' => $skipDuplicates,
                'update_existing' => $updateExisting,
                'create_missing_relations' => $createMissingRelations,
                'chunk_size' => $request->input('import_options.chunk_size', 500),
            ];

            // Actualizar estado a confirmado antes de despachar el job
            $audit->update([
                'estado' => 'confirmado',
                'progreso' => 0,
            ]);

            $audit->appendLog('Importación confirmada, iniciando procesamiento asíncrono', [
                'options' => $importOptions,
                'preview_token' => $request->preview_token,
            ]);

            // Despachar job de importación asíncrono
            CSVImportJob::dispatch($audit, $importOptions)
                ->onQueue('imports')
                ->delay(now()->addSeconds(2)); // Small delay to allow response to return

            // Crear respuesta inmediata
            $response = [
                'success' => true,
                'audit_id' => $audit->id,
                'job_id' => $audit->job_id,
                'estado' => 'confirmado',
                'message' => 'Proceso de importación iniciado',
                'estimated_time' => $this->estimateProcessingTime($audit->total_registros ?? 0),
                'progress_endpoint' => "/api/proveedores/{$proveedor->id}/csv-import/status/{$audit->id}",
                'results_endpoint' => "/api/proveedores/{$proveedor->id}/csv-import/results/{$audit->id}",
            ];

            return $this->success($response, 'Importación iniciada exitosamente. Puede consultar el progreso usando el endpoint proporcionado.');
        } catch (Exception $e) {
            // En caso de error, actualizar el audit
            if (isset($audit)) {
                $audit->update([
                    'estado' => 'error',
                    'fin_proceso' => now(),
                ]);

                $audit->appendLog('Error al confirmar importación', [
                    'error' => $e->getMessage(),
                ], 'error');
            }

            Log::error('Error en confirm CSV import', [
                'proveedor_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Error al confirmar la importación: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/proveedores/{id}/csv-import/status/{auditId}
     * Obtener estado y progreso de una importación
     */
    public function getImportStatus(Request $request, $id, $auditId)
    {
        try {
            // Validar el proveedor
            $proveedor = Proveedor::findOrFail($id);

            // Buscar el registro de auditoría
            $audit = ImportAudit::where('id', $auditId)
                ->where('proveedor_id', $proveedor->id)
                ->first();

            if (! $audit) {
                return $this->error('No se encontró la importación solicitada', 404);
            }

            // Formatear el estado para el frontend
            $status = [
                'audit_id' => $audit->id,
                'estado' => $audit->estado,
                'progreso' => $audit->progreso ?? 0,
                'inicio_proceso' => $audit->inicio_proceso,
                'fin_proceso' => $audit->fin_proceso,
                'total_registros' => $audit->total_registros ?? 0,
                'procesados' => $audit->numero_registros_procesados,
                'estimated_remaining' => $this->estimateRemainingTime($audit),
                'current_phase' => $audit->estado,
                // 'current_phase' => $this->getCurrentProcessingPhase($audit),
                // 'logs' => array_slice($audit->logs ?? [], -3), // Last 3 log entries
                // 'can_cancel' => in_array($audit->estado, ['confirmado', 'procesando']),
                // 'has_errors' => ($audit->errores ?? 0) > 0,
                // 'error_summary' => $this->getErrorSummary($audit),
            ];

            return $this->success($status, 'Estado de importación obtenido correctamente');
        } catch (Exception $e) {
            Log::error('Error obteniendo estado de importación', [
                'proveedor_id' => $id,
                'audit_id' => $auditId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Error al obtener el estado de la importación: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/proveedores/{id}/csv-import/results/{auditId}
     * Obtener resultados de una importación específica
     */
    public function getImportResults(Request $request, $id, $auditId)
    {
        try {
            // Validar el proveedor
            $proveedor = Proveedor::findOrFail($id);

            // Buscar el registro de auditoría
            $audit = ImportAudit::where('id', $auditId)
                ->where('proveedor_id', $proveedor->id)
                ->first();

            if (! $audit) {
                return $this->error('No se encontró la importación solicitada', 404);
            }

            // Formatear los resultados para el frontend
            $results = [
                'audit_id' => $audit->id,
                'estado' => $audit->estado,
                'success' => $audit->estado === 'completado',
                'archivo' => $audit->archivo,
                'tipo' => $audit->tipo,
                'formato' => $audit->formato,
                'fecha_creacion' => $audit->created_at,
                'inicio_proceso' => $audit->inicio_proceso,
                'fin_proceso' => $audit->fin_proceso,
                'processing_time' => $this->calculateProcessingTime($audit),
                'file_info' => [
                    'name' => basename($audit->archivo ?? 'archivo.csv'),
                    'size' => $this->getFileSize($audit->archivo),
                ],
                'estadisticas' => [
                    'total_processed' => $audit->total_registros ?? 0,
                    'created' => $audit->nuevos ?? 0,
                    'updated' => $audit->actualizados ?? 0,
                    'errors' => $audit->errores ?? 0,
                    'success_rate' => $this->calculateSuccessRate($audit),
                ],
                'breakdown' => [
                    'productos' => [
                        'imported' => $audit->nuevos ?? 0,
                        'updated' => $audit->actualizados ?? 0,
                        'errors' => $audit->errores ?? 0,
                        'total' => $audit->total_registros ?? 0,
                    ],
                    // Placeholder para otras categorías - se pueden implementar más tarde
                    'marcas' => [
                        'imported' => $audit->marca_imported,
                        'errors' => $audit->marca_errors,
                        'total' => $audit->marca_total,
                    ],
                    'categorias' => [
                        'imported' => $audit->categoria_imported,
                        'errors' => $audit->categoria_errors,
                        'total' => $audit->categoria_total,
                    ],
                    'unidades' => [
                        'imported' => $audit->unidad_imported,
                        'errors' => $audit->unidad_errors,
                        'total' => $audit->unidad_total,
                    ],
                ],
                'errores_detalle' => $audit->errores_detalle ?? [],
                // 'advertencias_detalle' => [], // Se puede agregar en futuras versiones
                // 'items_importados' => $this->getImportedItems($audit),
                // 'logs' => $audit->logs ?? []
            ];

            return $this->success($results, 'Resultados de importación obtenidos correctamente');
        } catch (Exception $e) {
            Log::error('Error obteniendo resultados de importación', [
                'proveedor_id' => $id,
                'audit_id' => $auditId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Error al obtener los resultados de la importación: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/proveedores/{id}/csv-import/results/{auditId}/export
     * Exportar resultados de importación en diferentes formatos
     */
    public function export(Request $request, $id, $auditId)
    {
        try {
            set_time_limit(5000);
            // Validar el proveedor
            $proveedor = Proveedor::findOrFail($id);

            // Validar parámetros de entrada
            $request->validate([
                'format' => 'nullable|string|in:xlsx,csv,pdf',
                'type' => 'nullable|string|in:report,data,summary',
            ]);

            // Obtener parámetros con valores por defecto
            $format = $request->get('format', 'xlsx');
            $type = $request->get('type', 'report');

            // Buscar el registro de auditoría
            $audit = ImportAudit::where('id', $auditId)
                ->where('proveedor_id', $proveedor->id)
                ->first();

            if (! $audit) {
                return $this->error('No se encontró la importación solicitada', 404);
            }

            // Verificar que la importación esté completada
            if (! in_array($audit->estado, ['completado', 'error'])) {
                return $this->error('La importación debe estar completada para poder exportar los resultados', 400);
            }

            // Log de la exportación
            Log::info('Exportando resultados de importación', [
                'audit_id' => $auditId,
                'proveedor_id' => $id,
                'format' => $format,
                'type' => $type,
                'user' => auth()->user()->id ?? 'anonymous',
            ]);

            // Usar el servicio de exportación
            return $this->exportService->exportImportResults($audit, $format, $type);
        } catch (Exception $e) {
            Log::error('Error exportando resultados de importación', [
                'proveedor_id' => $id,
                'audit_id' => $auditId,
                'format' => $request->get('format', 'xlsx'),
                'type' => $request->get('type', 'report'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Error al exportar los resultados: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/proveedores/{id}/csv-import/validate-producto
     * Validar un producto específico
     */
    public function validateProducto(Request $request, $id)
    {
        try {
            // Validar el proveedor
            $proveedor = Proveedor::findOrFail($id);

            // Validar datos de entrada
            $request->validate([
                'producto' => 'required|array',
                'producto.codigo' => 'required|string|max:255',
                'producto.producto' => 'required|string|max:255',
                'producto.descripcion' => 'nullable|string|max:1000',
                'producto.modelo' => 'nullable|string|max:255',
                'producto.marca' => 'nullable|string|max:255',
                'producto.categoria' => 'nullable|string|max:255',
                'producto.subcategoria' => 'nullable|string|max:255',
                'producto.unidad_medida' => 'nullable|string|max:100',
                'producto.precio_base' => 'nullable|numeric|min:0',
                'producto.precio_mayoreo' => 'nullable|numeric|min:0',
                'producto.precio_menudeo' => 'nullable|numeric|min:0',
                'strict_validation' => 'nullable|boolean',
            ]);

            $productoData = $request->get('producto');
            $strictValidation = $request->get('strict_validation', false);

            // Crear validator para este proveedor
            $validator = new CSVImportProductValidator($proveedor->id);

            // Validar el producto usando el servicio de validación
            $validationResult = $validator->validateRow($productoData, 1);

            // Verificar si ya existe el producto
            $existingProduct = Producto::where('codigo_interno', $productoData['codigo'])
                ->where('proveedor_id', $proveedor->id)
                ->first();

            $isValid = empty($validationResult['errors']);

            $existingProductData = $existingProduct ? [
                'id' => $existingProduct->id,
                'nombre' => $existingProduct->nombre,
                'precio_base' => $existingProduct->precio,
                'updated_at' => $existingProduct->updated_at,
            ] : null;

            $recommendedActions = $this->getRecommendedActions(['is_valid' => $isValid, 'errors' => $validationResult['errors']], $existingProduct);

            $response = CsvValidateProductResponse::fromValidation(
                $validationResult,
                ! is_null($existingProduct),
                $existingProductData,
                $recommendedActions
            );

            if ($isValid) {
                return $this->success($response->toArray(), 'Producto validado correctamente');
            } else {
                return $this->success($response->toArray(), 'Producto no válido');
            }
        } catch (Exception $e) {
            Log::error('Error en validateProducto', [
                'proveedor_id' => $id,
                'error' => $e->getMessage(),
                'producto_data' => $request->get('producto', []),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Error al validar el producto: '.$e->getMessage(), 500);
        }
    }

    /**
     * Ejecutar la importación de productos desde el CSV.
     *
     * Procesa los datos obtenidos desde la vista previa en caché, valida, registra catálogos,
     * y realiza inserciones/actualizaciones en la base de datos en chunks.
     *
     * @param  ImportAudit  $audit  Registro de auditoría de la importación.
     * @param  array  $options  Opciones adicionales de importación (ej. ['skip_duplicates' => true]).
     * @return array{
     *     success: bool,
     *     stats?: array{
     *         total_processed: int,
     *         created: int,
     *         updated: int,
     *         errors: int,
     *         skipped: int,
     *         success_rate: float
     *     },
     *     processing_time?: float,
     *     error_details?: array<int, mixed>,
     *     error?: string
     * }
     *
     * Formato de retorno:
     * - En caso de éxito:
     *   [
     *     'success' => true,
     *     'stats' => [...],
     *     'processing_time' => (float),
     *     'error_details' => [...]
     *   ]
     *
     * - En caso de error:
     *   [
     *     'success' => false,
     *     'error' => (string),
     *     'stats' => [...],
     *     'error_details' => [...]
     *   ]
     */
    private function executeImport(ImportAudit $audit, array $options): array
    {
        $startTime = microtime(true);
        $stats = [
            'total_processed' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0,
            'success_rate' => 0,
        ];
        $errorDetails = [];

        try {
            // Obtener datos de la tabla temporal usando el preview_token
            $previewToken = $audit->preview_data['preview_token'] ?? null;
            // Intentar primero desde tabla temporal, luego fallback a caché
            $cachedData = $this->csvProcessor->getTempTablePreviewData($previewToken);
            if (! $cachedData) {
                $cachedData = $this->csvProcessor->getCachedPreviewData($previewToken);
            }

            if (! $cachedData) {
                throw new \Exception('Datos de preview expirados. Por favor, suba el archivo nuevamente.');
            }

            $productsData = $cachedData['full_data'];
            $proveedorId = $audit->proveedor_id;

            // --- 1. Extraer y registrar catálogos ---
            $catalogos = [
                'marcas' => collect($productsData)->pluck('marca')->filter()->unique()->values()->toArray(),
                'unidades' => collect($productsData)->pluck('unidad_medida')->filter()->unique()->values()->toArray(),
                'categorias' => [],
            ];

            foreach ($productsData as $p) {
                if (! empty($p['categoria']) && ! empty($p['subcategoria'])) {
                    $catalogos['categorias'][$p['categoria']][] = $p['subcategoria'];
                }
            }

            foreach ($catalogos['categorias'] as $cat => $subs) {
                $catalogos['categorias'][$cat] = array_values(array_unique($subs));
            }

            $resultadosImportacionCatalgos = $this->registrarCatalogos($catalogos, $proveedorId);

            $stats['marca_imported'] = $resultadosImportacionCatalgos['marcas']['imported'];
            $stats['marca_errors'] = $resultadosImportacionCatalgos['marcas']['errors'];
            $stats['marca_total'] = $resultadosImportacionCatalgos['marcas']['total'];
            $stats['categoria_imported'] = $resultadosImportacionCatalgos['categorias']['imported'];
            $stats['categoria_errors'] = $resultadosImportacionCatalgos['categorias']['errors'];
            $stats['categoria_total'] = $resultadosImportacionCatalgos['categorias']['total'];
            $stats['unidad_imported'] = $resultadosImportacionCatalgos['unidades']['imported'];
            $stats['unidad_errors'] = $resultadosImportacionCatalgos['unidades']['errors'];
            $stats['unidad_total'] = $resultadosImportacionCatalgos['unidades']['total'];

            // --- 2. Preparar referencias en memoria ---
            $marcasMap = Marca::where('proveedor_id', $proveedorId)->pluck('id', 'nombre')->toArray();
            $unidadesMap = UnidadMedida::query()->pluck('id', 'nombre')->toArray();

            $categoriasMap = Categoria::where('proveedor_id', $proveedorId)
                ->get()
                ->groupBy('nivel')
                ->map(fn ($items) => $items->keyBy('nombre'));

            foreach ($productsData as &$p) {
                $p['marca_id'] = $marcasMap[$p['marca']] ?? null;
                $p['unidad_medida_id'] = $unidadesMap[$p['unidad_medida']] ?? null;

                if (! empty($p['categoria'])) {
                    $cat = $categoriasMap[1][$p['categoria']] ?? null;
                    $sub = null;
                    if ($cat && ! empty($p['subcategoria'])) {
                        $sub = $categoriasMap[2]->where('parent_id', $cat->id)->firstWhere('nombre', $p['subcategoria']);
                    }
                    $p['categoria_id'] = $cat->id ?? null;
                    $p['subcategoria_id'] = $sub->id ?? null;
                }
            }
            unset($p); // romper referencia

            // --- 3. Procesar productos por chunks ---
            $chunkSize = 100;
            $chunks = array_chunk($productsData, $chunkSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                DB::beginTransaction();
                try {
                    $upsertData = [];
                    foreach ($chunk as $productData) {
                        $stats['total_processed']++;

                        // Validación
                        $validator = new CSVImportProductValidator($proveedorId);
                        $validationResult = $validator->validateRow($productData, $stats['total_processed']);
                        $isValid = empty($validationResult['errors']);

                        if (! $isValid && empty($options['skip_duplicates'])) {
                            $stats['errors']++;
                            $errorDetails[] = [
                                'producto' => $productData,
                                'errores' => $validationResult['errors'],
                                'tipo_error' => 'validacion',
                            ];

                            continue;
                        }

                        // Preparar datos para upsert
                        $upsertData[] = [
                            'codigo_interno' => $productData['codigo'],
                            'nombre' => $productData['producto'],
                            'descripcion' => $productData['descripcion'] ?? null,
                            'marca_id' => $productData['marca_id'],
                            'categoria_id' => $productData['categoria_id'],
                            'subcategoria_id' => $productData['subcategoria_id'],
                            'unidad_medida_id' => $productData['unidad_medida_id'],
                            'precio_base' => $productData['precio_base'] ?? 0,
                            'precio_mayoreo' => $productData['precio_mayoreo'] ?? 0,
                            'precio_menudeo' => $productData['precio_menudeo'] ?? 0,
                            'proveedor_id' => $proveedorId,
                        ];
                    }

                    if (! empty($upsertData)) {
                        Producto::upsert(
                            $upsertData,
                            ['codigo_interno', 'proveedor_id'],
                            [
                                'nombre',
                                'descripcion',
                                // 'modelo',
                                'marca_id',
                                'categoria_id',
                                'subcategoria_id',
                                'unidad_medida_id',
                                'precio_base',
                                'precio_mayoreo',
                                'precio_menudeo',
                            ]
                        );

                        $stats['created'] += count($upsertData); // aproximado
                    }

                    DB::commit();

                    // Actualizar progreso
                    $progress = min(100, (($chunkIndex + 1) / count($chunks)) * 100);
                    $audit->update(['progreso' => $progress]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    $stats['errors'] += count($chunk);
                    $errorDetails[] = [
                        'error' => $e->getMessage(),
                        'tipo_error' => 'chunk',
                    ];
                }
            }

            // Calcular tasa de éxito
            if ($stats['total_processed'] > 0) {
                $stats['success_rate'] = round((($stats['created'] + $stats['updated']) / $stats['total_processed']) * 100, 2);
            }

            $processingTime = round(microtime(true) - $startTime, 2);

            // Limpiar tabla temporal después de importación exitosa
            if ($previewToken) {
                $this->csvProcessor->cleanupTempTable($previewToken);
            }

            return [
                'success' => true,
                'stats' => $stats,
                'processing_time' => $processingTime,
                'error_details' => $errorDetails,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $stats,
                'error_details' => $errorDetails,
            ];
        }
    }

    /**
     * Registra los catálogos de marcas, unidades y categorías/subcategorías en la base de datos.
     *
     * @param  array  $catalogos
     *                            Estructura esperada:
     *                            [
     *                            'marcas' => ['Marca1', 'Marca2', ...],
     *                            'unidades' => ['kg', 'm', 'l', ...],
     *                            'categorias' => [
     *                            'Categoria1' => ['Subcategoria1', 'Subcategoria2', ...],
     *                            'Categoria2' => ['SubcategoriaA', 'SubcategoriaB', ...],
     *                            ],
     *                            ]
     * @param  int  $proveedorId  ID del proveedor al que se asociarán los registros
     * @return void
     */
    public function registrarCatalogos(array $catalogos, int $proveedorId): array
    {
        $catalogosImportResults = [
            'marcas' => ['imported' => 0, 'errors' => 0, 'total' => 0],
            'categorias' => ['imported' => 0, 'errors' => 0, 'total' => 0],
            'unidades' => ['imported' => 0, 'errors' => 0, 'total' => 0],
        ];

        // --- Registrar marcas ---
        foreach ($catalogos['marcas'] as $nombreMarca) {
            $catalogosImportResults['marcas']['total']++;
            try {
                Marca::updateOrCreate(
                    ['nombre' => $nombreMarca, 'proveedor_id' => $proveedorId],
                    ['activo' => true]
                );
                $catalogosImportResults['marcas']['imported']++;
            } catch (\Throwable $e) {
                $catalogosImportResults['marcas']['errors']++;
            }
        }

        // --- Registrar unidades de medida ---
        foreach ($catalogos['unidades'] as $nombreUnidad) {
            $catalogosImportResults['unidades']['total']++;
            try {
                UnidadMedida::updateOrCreate(
                    ['nombre' => $nombreUnidad],
                    ['estatus' => 'activo']
                );
                $catalogosImportResults['unidades']['imported']++;
            } catch (\Throwable $e) {
                $catalogosImportResults['unidades']['errors']++;
            }
        }

        // --- Registrar categorías y subcategorías ---
        foreach ($catalogos['categorias'] as $nombreCategoria => $subcategorias) {
            $catalogosImportResults['categorias']['total']++;
            try {
                // Categoría principal
                $categoria = Categoria::updateOrCreate(
                    ['nombre' => $nombreCategoria, 'proveedor_id' => $proveedorId, 'nivel' => 1],
                    ['activo' => true]
                );
                $catalogosImportResults['categorias']['imported']++;

                // Subcategorías
                foreach ($subcategorias as $nombreSubcategoria) {
                    $catalogosImportResults['categorias']['total']++;
                    try {
                        Categoria::updateOrCreate(
                            ['nombre' => $nombreSubcategoria, 'parent_id' => $categoria->id, 'proveedor_id' => $proveedorId, 'nivel' => 2],
                            ['activo' => true]
                        );
                        $catalogosImportResults['categorias']['imported']++;
                    } catch (\Throwable $e) {
                        $catalogosImportResults['categorias']['errors']++;
                    }
                }
            } catch (\Throwable $e) {
                $catalogosImportResults['categorias']['errors']++;
            }
        }

        return $catalogosImportResults;
    }

    /**
     * Obtener acciones recomendadas
     */
    private function getRecommendedActions(array $validationResult, $existingProduct = null): array
    {
        $actions = [];

        if (! $validationResult['is_valid']) {
            $actions[] = [
                'accion' => 'corregir_errores',
                'descripcion' => 'Corregir los errores de validación antes de proceder',
                'prioridad' => 'alta',
            ];
        }

        if ($existingProduct) {
            $actions[] = [
                'accion' => 'actualizar_existente',
                'descripcion' => 'Actualizar el producto existente con los nuevos datos',
                'prioridad' => 'media',
            ];
        }

        if (empty($actions)) {
            $actions[] = [
                'accion' => 'crear_producto',
                'descripcion' => 'El producto puede ser creado sin problemas',
                'prioridad' => 'baja',
            ];
        }

        return $actions;
    }

    /**
     * Calculate processing time from audit record
     */
    private function calculateProcessingTime(ImportAudit $audit): float
    {
        if ($audit->inicio_proceso && $audit->fin_proceso) {
            return round($audit->fin_proceso->diffInSeconds($audit->inicio_proceso, true), 2);
        }

        return 0.0;
    }

    /**
     * Get file size from stored file path
     */
    private function getFileSize(?string $filePath): int
    {
        if (! $filePath) {
            return 0;
        }

        try {
            if (Storage::disk('local')->exists($filePath)) {
                return Storage::disk('local')->size($filePath);
            }
        } catch (Exception $e) {
            Log::warning('Error getting file size', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }

        return 0;
    }

    /**
     * Calculate success rate from audit statistics
     */
    private function calculateSuccessRate(ImportAudit $audit): float
    {
        $totalProcessed = $audit->total_registros ?? 0;
        if ($totalProcessed === 0) {
            return 0.0;
        }

        $successful = ($audit->nuevos ?? 0) + ($audit->actualizados ?? 0);

        return round(($successful / $totalProcessed) * 100, 2);
    }

    /**
     * Get imported items details (placeholder for now)
     * This can be enhanced to return actual imported product details
     */
    private function getImportedItems(ImportAudit $audit): array
    {
        // For now, return a placeholder structure
        // This could be enhanced to query actual imported products
        $items = [];

        $created = $audit->nuevos ?? 0;
        $updated = $audit->actualizados ?? 0;

        // Generate sample items structure based on stats
        for ($i = 0; $i < min($created, 10); $i++) {
            $items[] = [
                'id' => "item_{$i}",
                'name' => "Producto creado {$i}",
                'category' => 'productos',
                'action' => 'created',
            ];
        }

        for ($i = 0; $i < min($updated, 10); $i++) {
            $items[] = [
                'id' => "updated_{$i}",
                'name' => "Producto actualizado {$i}",
                'category' => 'productos',
                'action' => 'updated',
            ];
        }

        return $items;
    }

    /**
     * Convertir string delimiter a carácter
     * Maneja tanto valores string como caracteres directos
     */
    private function getDelimiter(string $delimiter): string
    {
        // Normalizar el valor de entrada
        $delimiter = trim($delimiter);

        // Mapeo de strings a caracteres
        $delimiterMap = [
            'comma' => ',',
            'semicolon' => ';',
            'tab' => "\t",
            'pipe' => '|',
            'colon' => ':',
        ];

        // Si es un string conocido, retornar el carácter
        if (array_key_exists($delimiter, $delimiterMap)) {
            return $delimiterMap[$delimiter];
        }

        // Si ya es un carácter directo, validarlo
        switch ($delimiter) {
            case ',':
            case ';':
            case "\t":
            case '|':
            case ':':
                return $delimiter;
            case 'tab': // fallback por si acaso
                return "\t";
            default:
                // Si no es reconocido, usar coma como default
                Log::warning('Delimitador no reconocido, usando coma como default', [
                    'delimiter_received' => $delimiter,
                ]);

                return ',';
        }
    }

    /**
     * Estimate processing time based on total records
     */
    private function estimateProcessingTime(int $totalRecords): string
    {
        // Roughly 100-150 records per second depending on complexity
        $secondsPerRecord = 0.01; // 100 records per second baseline
        $estimatedSeconds = $totalRecords * $secondsPerRecord;

        if ($estimatedSeconds < 60) {
            return round($estimatedSeconds).' segundos';
        } elseif ($estimatedSeconds < 3600) {
            $minutes = round($estimatedSeconds / 60);

            return $minutes.' minutos';
        } else {
            $hours = floor($estimatedSeconds / 3600);
            $minutes = round(($estimatedSeconds % 3600) / 60);

            return $hours.'h '.$minutes.'m';
        }
    }

    /**
     * Calculate processed records based on progress
     */
    private function calculateProcessedRecords(ImportAudit $audit): int
    {
        $totalRecords = $audit->total_registros ?? 0;
        $progress = $audit->progreso ?? 0;

        return round(($progress / 100) * $totalRecords);
    }

    /**
     * Estimate remaining time based on current progress
     */
    private function estimateRemainingTime(ImportAudit $audit): ?string
    {
        if (! $audit->inicio_proceso || $audit->progreso <= 0) {
            return null;
        }

        $elapsedSeconds = now()->diffInSeconds($audit->inicio_proceso);
        $progress = $audit->progreso;

        if ($progress >= 100) {
            return '0 segundos';
        }

        // Calculate rate and remaining time
        $rate = $progress / $elapsedSeconds; // progress per second
        $remainingProgress = 100 - $progress;
        $remainingSeconds = $remainingProgress / $rate;

        if ($remainingSeconds < 60) {
            return round($remainingSeconds).' segundos';
        } elseif ($remainingSeconds < 3600) {
            return round($remainingSeconds / 60).' minutos';
        } else {
            return round($remainingSeconds / 3600, 1).' horas';
        }
    }

    /**
     * Get current processing phase based on progress and logs
     */
    private function getCurrentProcessingPhase(ImportAudit $audit): string
    {
        $estado = $audit->estado;
        $progreso = $audit->progreso ?? 0;

        switch ($estado) {
            case 'preview':
                return 'Análisis completado';
            case 'confirmado':
                return 'Iniciando procesamiento';
            case 'procesando':
                if ($progreso < 30) {
                    return 'Procesando catálogos';
                } elseif ($progreso < 95) {
                    return 'Importando productos';
                } else {
                    return 'Finalizando importación';
                }
            case 'completado':
                return 'Importación completada';
            case 'error':
                return 'Error en importación';
            default:
                return 'Estado desconocido';
        }
    }

    /**
     * Get error summary for status display
     */
    private function getErrorSummary(ImportAudit $audit): array
    {
        $errorsCount = $audit->errores ?? 0;
        $errorDetails = $audit->errores_detalle ?? [];

        if ($errorsCount === 0) {
            return [
                'total' => 0,
                'types' => [],
                'recent' => [],
            ];
        }

        // Group errors by type
        $errorTypes = [];
        foreach ($errorDetails as $error) {
            $type = $error['tipo_error'] ?? 'general';
            $errorTypes[$type] = ($errorTypes[$type] ?? 0) + 1;
        }

        return [
            'total' => $errorsCount,
            'types' => $errorTypes,
            'recent' => array_slice($errorDetails, -3), // Last 3 errors
        ];
    }
}
