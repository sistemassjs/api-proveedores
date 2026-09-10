<?php

namespace App\Services;

use App\Enums\EstadoImportacion;
use App\Models\Categoria;
use App\Models\ImportAudit;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CsvImportService
{
    private FileParserService $fileParserService;

    private CSVImportProductValidator $validator;

    private int $proveedorId;

    public function __construct(FileParserService $fileParserService, int $proveedorId)
    {
        $this->fileParserService = $fileParserService;
        $this->proveedorId = $proveedorId;
        $this->validator = new CSVImportProductValidator($proveedorId);
    }

    /**
     * Analyze CSV file and extract productos, marcas, categorias, unidades
     *
     * @param  UploadedFile|string  $file
     */
    public function analyzeCsv($file): array
    {
        try {
            // Parse the CSV file
            $rows = $this->fileParserService->parseFile($file);

            if (empty($rows)) {
                return [
                    'success' => false,
                    'error' => 'El archivo CSV está vacío o no se pudo procesar',
                    'data' => null,
                ];
            }

            $headers = array_keys($rows[0]);

            // Validate headers
            $headerValidation = $this->validator->validateHeaders($headers);
            if (! empty($headerValidation['errors'])) {
                return [
                    'success' => false,
                    'error' => 'Encabezados del CSV inválidos',
                    'validation_errors' => $headerValidation['errors'],
                    'data' => null,
                ];
            }

            // Extract unique data from all rows
            $analysis = $this->extractUniqueData($rows);

            // Count valid rows
            $totalRows = count($rows);
            $validRows = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $validation = $this->validator->validateRow($row, $index + 1);
                if (empty($validation['errors'])) {
                    $validRows++;
                } else {
                    $errors[] = [
                        'fila' => $index + 1,
                        'errores' => $validation['errors'],
                    ];
                }
            }

            return [
                'success' => true,
                'data' => [
                    'total_filas' => $totalRows,
                    'filas_validas' => $validRows,
                    'filas_con_errores' => $totalRows - $validRows,
                    'productos' => $analysis['productos'],
                    'marcas' => $analysis['marcas'],
                    'categorias' => $analysis['categorias'],
                    'unidades' => $analysis['unidades'],
                    'errores_validacion' => $errors,
                    'headers' => $headers,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error analyzing CSV file', [
                'proveedor_id' => $this->proveedorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Error al analizar el archivo CSV: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Compare CSV analysis with existing provider data
     */
    public function mergeAnalysis(array $analysisData): array
    {
        try {
            // Get existing data for this provider
            $existingMarcas = Marca::where('proveedor_id', $this->proveedorId)->pluck('nombre')->toArray();
            $existingCategorias = Categoria::where('proveedor_id', $this->proveedorId)->whereNull('parent_id')->pluck('nombre')->toArray();
            $existingUnidades = UnidadMedida::query()->pluck('descripcion')->toArray();
            $existingProductos = Producto::where('proveedor_id', $this->proveedorId)->pluck('codigo_interno')->toArray();

            // Categorize each data type
            $marcasComparison = $this->compareArrays($analysisData['marcas'], $existingMarcas);
            $categoriasComparison = $this->compareArrays($analysisData['categorias'], $existingCategorias);
            $unidadesComparison = $this->compareArrays($analysisData['unidades'], $existingUnidades);
            $productosComparison = $this->compareArrays(array_keys($analysisData['productos']), $existingProductos);

            return [
                'success' => true,
                'data' => [
                    'marcas' => [
                        'nuevas' => $marcasComparison['nuevas'],
                        'existentes' => $marcasComparison['existentes'],
                        'total_csv' => count($analysisData['marcas']),
                        'total_nuevas' => count($marcasComparison['nuevas']),
                        'total_existentes' => count($marcasComparison['existentes']),
                    ],
                    'categorias' => [
                        'nuevas' => $categoriasComparison['nuevas'],
                        'existentes' => $categoriasComparison['existentes'],
                        'total_csv' => count($analysisData['categorias']),
                        'total_nuevas' => count($categoriasComparison['nuevas']),
                        'total_existentes' => count($categoriasComparison['existentes']),
                    ],
                    'unidades' => [
                        'nuevas' => $unidadesComparison['nuevas'],
                        'existentes' => $unidadesComparison['existentes'],
                        'total_csv' => count($analysisData['unidades']),
                        'total_nuevas' => count($unidadesComparison['nuevas']),
                        'total_existentes' => count($unidadesComparison['existentes']),
                    ],
                    'productos' => [
                        'nuevos' => $productosComparison['nuevas'],
                        'existentes' => $productosComparison['existentes'],
                        'total_csv' => count($analysisData['productos']),
                        'total_nuevos' => count($productosComparison['nuevas']),
                        'total_existentes' => count($productosComparison['existentes']),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error merging CSV analysis', [
                'proveedor_id' => $this->proveedorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Error al comparar datos del CSV con datos existentes: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Validate products and separate them into validos, duplicados, error arrays
     *
     * @param  UploadedFile|string  $file
     */
    public function validateProducts($file): array
    {
        try {
            $rows = $this->fileParserService->parseFile($file);

            if (empty($rows)) {
                return [
                    'success' => false,
                    'error' => 'El archivo CSV está vacío',
                    'data' => null,
                ];
            }

            $validos = [];
            $duplicados = [];
            $errores = [];
            $codigosVistosEnCsv = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;
                $codigo = trim($row['codigo'] ?? '');

                // Validate the row
                $validation = $this->validator->validateRow($row, $rowNumber);

                if (! empty($validation['errors'])) {
                    $errores[] = [
                        'fila' => $rowNumber,
                        'data' => $row,
                        'errores' => $validation['errors'],
                    ];

                    continue;
                }

                // Check for duplicates within the CSV
                if (in_array($codigo, $codigosVistosEnCsv)) {
                    $duplicados[] = [
                        'fila' => $rowNumber,
                        'data' => $row,
                        'tipo_duplicado' => 'interno_csv',
                        'mensaje' => "Código '{$codigo}' aparece múltiples veces en el CSV",
                    ];

                    continue;
                }

                // Check for existing products in database
                $existeEnDb = Producto::where('proveedor_id', $this->proveedorId)
                    ->where('codigo_interno', $codigo)
                    ->exists();

                if ($existeEnDb) {
                    $duplicados[] = [
                        'fila' => $rowNumber,
                        'data' => $row,
                        'tipo_duplicado' => 'base_datos',
                        'mensaje' => "Código '{$codigo}' ya existe en la base de datos y será actualizado",
                    ];
                } else {
                    $validos[] = [
                        'fila' => $rowNumber,
                        'data' => $row,
                    ];
                }

                $codigosVistosEnCsv[] = $codigo;
            }

            return [
                'success' => true,
                'data' => [
                    'total_filas' => count($rows),
                    'validos' => $validos,
                    'duplicados' => $duplicados,
                    'errores' => $errores,
                    'resumen' => [
                        'total_validos' => count($validos),
                        'total_duplicados' => count($duplicados),
                        'total_errores' => count($errores),
                        'porcentaje_validos' => count($rows) > 0 ? round((count($validos) / count($rows)) * 100, 2) : 0,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error validating products', [
                'proveedor_id' => $this->proveedorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Error al validar productos: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Execute the actual import and return statistics
     *
     * @param  UploadedFile|string  $file
     */
    public function executeImport($file, array $options = []): array
    {
        DB::beginTransaction();

        try {
            $proveedor = Proveedor::findOrFail($this->proveedorId);
            $rows = $this->fileParserService->parseFile($file);

            // Create import audit
            $importAudit = ImportAudit::create([
                'proveedor_id' => $this->proveedorId,
                'archivo' => is_string($file) ? $file : $file->getClientOriginalName(),
                'tipo' => 'productos',
                'formato' => 'csv',
                'estado' => EstadoImportacion::PROCESANDO->value,
                'total_registros' => count($rows),
                'progreso' => 0,
                'inicio_proceso' => now(),
            ]);

            $importAudit->appendLog('Iniciando importación CSV', [
                'total_registros' => count($rows),
                'proveedor' => $proveedor->nombre,
                'opciones' => $options,
            ]);

            // Process the import
            $result = $this->processImport($rows, $proveedor, $importAudit, $options);

            // Update audit with results
            $importAudit->update([
                'estado' => EstadoImportacion::COMPLETADO->value,
                'progreso' => 100,
                'fin_proceso' => now(),
                'nuevos' => $result['estadisticas']['productos_creados'],
                'actualizados' => $result['estadisticas']['productos_actualizados'],
                'errores' => $result['estadisticas']['errores'],
                'errores_detalle' => $result['errores_detalle'],
            ]);

            $importAudit->appendLog('Importación completada exitosamente', [
                'estadisticas' => $result['estadisticas'],
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'import_audit_id' => $importAudit->id,
                    'estadisticas' => $result['estadisticas'],
                    'errores' => $result['errores_detalle'],
                    'resumen' => [
                        'total_procesados' => count($rows),
                        'exitosos' => $result['estadisticas']['productos_creados'] + $result['estadisticas']['productos_actualizados'],
                        'fallidos' => $result['estadisticas']['errores'],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($importAudit)) {
                $importAudit->update([
                    'estado' => EstadoImportacion::ERROR->value,
                    'fin_proceso' => now(),
                ]);

                $importAudit->appendLog('Error durante la importación', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            Log::error('Error executing CSV import', [
                'proveedor_id' => $this->proveedorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Error al ejecutar la importación: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Extract unique data from CSV rows
     */
    private function extractUniqueData(array $rows): array
    {
        $marcas = [];
        $categorias = [];
        $unidades = [];
        $productos = [];

        foreach ($rows as $row) {
            // Extract marcas
            $marca = trim($row['marca'] ?? '');
            if ($marca && ! in_array($marca, $marcas)) {
                $marcas[] = $marca;
            }

            // Extract categorias
            $categoria = trim($row['categoria'] ?? '');
            if ($categoria && ! in_array($categoria, $categorias)) {
                $categorias[] = $categoria;
            }

            // Extract unidades
            $unidad = trim($row['unidad_medida'] ?? '');
            if ($unidad && ! in_array($unidad, $unidades)) {
                $unidades[] = $unidad;
            }

            // Extract productos
            $codigo = trim($row['codigo'] ?? '');
            $nombre = trim($row['producto'] ?? '');
            if ($codigo && $nombre) {
                $productos[$codigo] = $nombre;
            }
        }

        return [
            'marcas' => $marcas,
            'categorias' => $categorias,
            'unidades' => $unidades,
            'productos' => $productos,
        ];
    }

    /**
     * Compare two arrays and return new and existing items
     */
    private function compareArrays(array $csvData, array $existingData): array
    {
        $nuevas = array_diff($csvData, $existingData);
        $existentes = array_intersect($csvData, $existingData);

        return [
            'nuevas' => array_values($nuevas),
            'existentes' => array_values($existentes),
        ];
    }

    /**
     * Process the actual import of products
     */
    private function processImport(array $rows, Proveedor $proveedor, ImportAudit $importAudit, array $options): array
    {
        $chunkSize = $options['chunk_size'] ?? 100;
        $estadisticas = [
            'productos_creados' => 0,
            'productos_actualizados' => 0,
            'marcas_creadas' => 0,
            'categorias_creadas' => 0,
            'unidades_creadas' => 0,
            'errores' => 0,
        ];
        $errores_detalle = [];

        $totalChunks = ceil(count($rows) / $chunkSize);
        $currentChunk = 0;

        // Pre-load existing data to optimize performance
        $existingMarcas = Marca::where('proveedor_id', $this->proveedorId)->pluck('id', 'nombre');
        $existingCategorias = Categoria::where('proveedor_id', $this->proveedorId)->whereNull('parent_id')->pluck('id', 'nombre');
        $existingSubCategorias = Categoria::where('proveedor_id', $this->proveedorId)->whereNotNull('parent_id')->pluck('id', 'nombre');
        $existingUnidades = UnidadMedida::query()->pluck('id', 'descripcion');

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $currentChunk++;
            $progreso = ($currentChunk / $totalChunks) * 100;

            $importAudit->update(['progreso' => $progreso]);
            $importAudit->appendLog("Procesando lote {$currentChunk} de {$totalChunks}", [
                'registros_en_lote' => count($chunk),
                'progreso' => $progreso,
            ]);

            foreach ($chunk as $index => $row) {
                try {
                    // Create required entities first
                    $marcaId = $this->getOrCreateMarca($row['marca'] ?? '', $proveedor, $existingMarcas, $estadisticas);
                    $categoriaId = $this->getOrCreateCategoria($row['categoria'] ?? '', $proveedor, $existingCategorias, $estadisticas);
                    $subCategoriaId = $this->getOrCreateCategoria($row['subcategoria'] ?? '', $proveedor, $existingCategorias, $estadisticas);
                    $unidadId = $this->getOrCreateUnidadMedida($row['unidad_medida'] ?? '', $proveedor, $existingUnidades, $estadisticas);

                    // Create or update product
                    $producto = Producto::updateOrCreate(
                        [
                            'codigo_interno' => $row['codigo'],
                            'proveedor_id' => $this->proveedorId,
                        ],
                        [
                            'nombre' => $row['producto'],
                            'descripcion' => $row['descripcion'] ?? null,
                            'precio_base' => is_numeric($row['precio'] ?? 0) ? (float) $row['precio'] : 0,
                            'precio_mayoreo' => is_numeric($row['precio_mayoreo'] ?? 0) ? (float) $row['precio_mayoreo'] : null,
                            'precio_menudeo' => is_numeric($row['precio_menudeo'] ?? 0) ? (float) $row['precio_menudeo'] : null,
                            'marca_id' => $marcaId,
                            'categoria_id' => $categoriaId,
                            'subcategoria_id' => $categoriaId,
                            'unidad_medida_id' => $unidadId,
                            'activo' => true,
                        ]
                    );

                    if ($producto->wasRecentlyCreated) {
                        $estadisticas['productos_creados']++;
                    } else {
                        $estadisticas['productos_actualizados']++;
                    }
                } catch (\Exception $e) {
                    $estadisticas['errores']++;
                    $errores_detalle[] = [
                        'fila' => $index + 1,
                        'codigo' => $row['codigo'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'estadisticas' => $estadisticas,
            'errores_detalle' => $errores_detalle,
        ];
    }

    /**
     * Get or create marca
     */
    private function getOrCreateMarca(string $nombre, Proveedor $proveedor, array &$existingMarcas, array &$estadisticas): ?int
    {
        if (empty($nombre)) {
            return null;
        }

        if (isset($existingMarcas[$nombre])) {
            return $existingMarcas[$nombre];
        }

        $marca = Marca::create([
            'nombre' => $nombre,
            'proveedor_id' => $proveedor->id,
        ]);

        $existingMarcas[$nombre] = $marca->id;
        $estadisticas['marcas_creadas']++;

        return $marca->id;
    }

    /**
     * Get or create categoria
     */
    private function getOrCreateCategoria(string $nombre, Proveedor $proveedor, array &$existingCategorias, array &$estadisticas): ?int
    {
        if (empty($nombre)) {
            return null;
        }

        if (isset($existingCategorias[$nombre])) {
            return $existingCategorias[$nombre];
        }

        $categoria = Categoria::create([
            'nombre' => $nombre,
            'proveedor_id' => $proveedor->id,
        ]);

        $existingCategorias[$nombre] = $categoria->id;
        $estadisticas['categorias_creadas']++;

        return $categoria->id;
    }

    /**
     * Get or create unidad medida
     */
    private function getOrCreateUnidadMedida(string $nombre, Proveedor $proveedor, array &$existingUnidades, array &$estadisticas): ?int
    {
        if (empty($nombre)) {
            return null;
        }

        if (isset($existingUnidades[$nombre])) {
            return $existingUnidades[$nombre];
        }

        $unidad = UnidadMedida::create([
            'descripcion' => $nombre,
            'nombre' => $nombre,
        ]);

        $existingUnidades[$nombre] = $unidad->id;
        $estadisticas['unidades_creadas']++;

        return $unidad->id;
    }
}
