<?php

namespace App\Services;

use App\Enums\EstadoImportacion;
use App\Http\Requests\ProveedorImportProducto\ProveedorImportProductoRequest;
use App\Models\Categoria;
use App\Models\ImportAudit;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportService
{
    use ApiResponse;

    /**
     * Process product import
     */
    public function processImport(Request $request, Proveedor $proveedor)
    {
        // Validar el request como si fuera ProveedorImportProductoRequest
        $validator = validator($request->all(), (new ProveedorImportProductoRequest)->rules());

        if ($validator->fails()) {
            return $this->error('Datos de importación inválidos.', 422, $validator->errors());
        }

        $productosData = $request->input('productos', []);
        $importAudit = $this->createImportAudit($proveedor, $productosData);

        try {
            // Ejecutar la importación
            $result = $this->executeBulkImport($productosData, $proveedor, $importAudit);

            // Actualizar auditoría con resultados
            $this->updateImportAuditResults($importAudit, $result);

            return $this->success($result, 'Proceso de importación completado exitosamente.');
        } catch (\Throwable $e) {
            // Registrar error y actualizar auditoría
            $this->handleImportError($importAudit, $e);

            Log::error('Error en importación de productos', [
                'proveedor_id' => $proveedor->id,
                'import_audit_id' => $importAudit->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Error durante la importación: '.$e->getMessage(), 500);
        }
    }

    /**
     * Create import audit entry
     */
    private function createImportAudit(Proveedor $proveedor, array $productosData): ImportAudit
    {
        $importAudit = ImportAudit::create([
            'proveedor_id' => $proveedor->id,
            'archivo' => 'PENDIENTE_ALMACENAR',
            'tipo' => 'productos',
            'formato' => 'json',
            'estado' => EstadoImportacion::PROCESANDO->value,
            // 'fase' => 'processing',s
            'total_registros' => count($productosData),
            'progreso' => 0,
            'inicio_proceso' => now(),
        ]);

        $importAudit->appendLog('Iniciando importación de productos', [
            'total_registros' => count($productosData),
            'proveedor' => $proveedor->nombre,
        ]);

        $importAudit->save();

        return $importAudit;
    }

    /**
     * Execute bulk import with chunking
     */
    private function executeBulkImport(array $productosData, Proveedor $proveedor, ImportAudit $importAudit): array
    {
        $errores = [];
        $chunkSize = 500;

        // Contadores globales
        $productosCreados = [];
        $productosActualizados = [];
        $marcasCreadas = [];
        $marcasActualizadas = [];
        $categoriasCreadas = [];
        $categoriasActualizadas = [];
        $subcategoriasCreadas = [];
        $subcategoriasActualizadas = [];
        $unidadesCreadas = [];
        $unidadesActualizadas = [];

        $totalChunks = ceil(count($productosData) / $chunkSize);
        $currentChunk = 0;

        foreach (array_chunk($productosData, $chunkSize) as $lote) {
            $currentChunk++;

            // Actualizar progreso
            $progreso = ($currentChunk / $totalChunks) * 100;
            $importAudit->update(['progreso' => $progreso]);

            $importAudit->appendLog("Procesando lote {$currentChunk} de {$totalChunks}", [
                'registros_en_lote' => count($lote),
                'progreso' => $progreso,
            ]);
            $importAudit->save();

            DB::beginTransaction();
            try {
                $resultado = $this->processChunk($lote, $proveedor, $importAudit);

                // Acumular resultados
                $productosCreados = array_merge($productosCreados, $resultado['productosCreados']);
                $productosActualizados = array_merge($productosActualizados, $resultado['productosActualizados']);
                $marcasCreadas = array_merge($marcasCreadas, $resultado['marcasCreadas']);
                $marcasActualizadas = array_merge($marcasActualizadas, $resultado['marcasActualizadas']);
                $categoriasCreadas = array_merge($categoriasCreadas, $resultado['categoriasCreadas']);
                $categoriasActualizadas = array_merge($categoriasActualizadas, $resultado['categoriasActualizadas']);
                $subcategoriasCreadas = array_merge($subcategoriasCreadas, $resultado['subcategoriasCreadas']);
                $subcategoriasActualizadas = array_merge($subcategoriasActualizadas, $resultado['subcategoriasActualizadas']);
                $unidadesCreadas = array_merge($unidadesCreadas, $resultado['unidadesCreadas']);
                $unidadesActualizadas = array_merge($unidadesActualizadas, $resultado['unidadesActualizadas']);
                $errores = array_merge($errores, $resultado['errores']);

                DB::commit();

                $importAudit->appendLog("Lote {$currentChunk} procesado exitosamente", [
                    'productos_creados' => count($resultado['productosCreados']),
                    'productos_actualizados' => count($resultado['productosActualizados']),
                    'errores' => count($resultado['errores']),
                ]);
                $importAudit->save();
            } catch (\Throwable $e) {
                DB::rollBack();

                // Registrar todos los items del lote como errores
                foreach ($lote as $item) {
                    $errores[] = [
                        'item' => $item,
                        'error' => $e->getMessage(),
                    ];
                }

                $importAudit->appendLog("Error procesando lote {$currentChunk}", [
                    'error' => $e->getMessage(),
                    'registros_fallidos' => count($lote),
                ]);
                $importAudit->save();
            }
        }

        return [
            'productos' => [
                'creados' => $productosCreados,
                'actualizados' => $productosActualizados,
            ],
            'marcas' => [
                'creados' => $marcasCreadas,
                'actualizados' => $marcasActualizadas,
            ],
            'categorias' => [
                'creados' => $categoriasCreadas,
                'actualizados' => $categoriasActualizadas,
            ],
            'subcategorias' => [
                'creados' => $subcategoriasCreadas,
                'actualizados' => $subcategoriasActualizadas,
            ],
            'unidades' => [
                'creados' => $unidadesCreadas,
                'actualizados' => $unidadesActualizadas,
            ],
            'errores' => $errores,
            'resumen' => [
                'total_intentos' => count($productosData),
                'exitosos' => count($productosCreados) + count($productosActualizados),
                'creados' => count($productosCreados),
                'actualizados' => count($productosActualizados),
                'fallidos' => count($errores),
            ],
        ];
    }

    /**
     * Process a chunk of products
     */
    private function processChunk(array $lote, Proveedor $proveedor, ImportAudit $importAudit): array
    {
        $productosCreados = [];
        $productosActualizados = [];
        $marcasCreadas = [];
        $marcasActualizadas = [];
        $categoriasCreadas = [];
        $categoriasActualizadas = [];
        $subcategoriasCreadas = [];
        $subcategoriasActualizadas = [];
        $unidadesCreadas = [];
        $unidadesActualizadas = [];
        $errores = [];

        // 1. Pre-cargar datos existentes
        $nombresMarcas = collect($lote)->pluck('marca')->filter()->unique();
        $nombresCategorias = collect($lote)->pluck('categoria')->filter()->unique();
        $nombresUnidades = collect($lote)->pluck('unidad_medida')->filter()->unique();

        $marcasExistentes = Marca::where('proveedor_id', $proveedor->id)
            ->whereIn('nombre', $nombresMarcas)
            ->pluck('id', 'nombre');

        $categoriasExistentes = Categoria::where('proveedor_id', $proveedor->id)
            ->whereIn('nombre', $nombresCategorias)
            ->whereNull('parent_id')
            ->pluck('id', 'nombre');

        $unidadesExistentes = UnidadMedida::query()
            ->whereIn('nombre', $nombresUnidades)
            ->pluck('id', 'nombre');

        // 2. Crear las marcas que no existen
        foreach ($nombresMarcas as $nombre) {
            if (! isset($marcasExistentes[$nombre])) {
                $marca = Marca::create([
                    'nombre' => $nombre,
                    'proveedor_id' => $proveedor->id,
                ]);
                $marcasExistentes[$nombre] = $marca->id;
                $marcasCreadas[] = $marca;
            } else {
                $marcasActualizadas[] = $marcasExistentes[$nombre];
            }
        }

        // 3. Crear categorías que no existen
        foreach ($nombresCategorias as $nombre) {
            if (! isset($categoriasExistentes[$nombre])) {
                $categoria = Categoria::create([
                    'nombre' => $nombre,
                    'proveedor_id' => $proveedor->id,
                ]);
                $categoriasExistentes[$nombre] = $categoria->id;
                $categoriasCreadas[] = $categoria;
            } else {
                $categoriasActualizadas[] = $categoriasExistentes[$nombre];
            }
        }

        // 4. Crear unidades que no existen
        foreach ($nombresUnidades as $nombre) {
            if (! isset($unidadesExistentes[$nombre])) {
                $unidad = UnidadMedida::create([
                    'nombre' => $nombre,
                ]);
                $unidadesExistentes[$nombre] = $unidad->id;
                $unidadesCreadas[] = $unidad;
            } else {
                $unidadesActualizadas[] = $unidadesExistentes[$nombre];
            }
        }

        // 5. Procesar subcategorías (necesitan categoría padre)
        foreach ($lote as $item) {
            if (! empty($item['subcategoria']) && ! empty($item['categoria'])) {
                $parentId = $categoriasExistentes[$item['categoria']] ?? null;
                if ($parentId) {
                    $subcategoria = Categoria::firstOrCreate(
                        [
                            'nombre' => $item['subcategoria'],
                            'parent_id' => $parentId,
                            'proveedor_id' => $proveedor->id,
                        ]
                    );
                    if ($subcategoria->wasRecentlyCreated) {
                        $subcategoriasCreadas[] = $subcategoria;
                    } else {
                        $subcategoriasActualizadas[] = $subcategoria;
                    }
                }
            }
        }

        // 6. Crear/actualizar productos
        foreach ($lote as $item) {
            try {
                $producto = Producto::updateOrCreate(
                    [
                        'codigo_interno' => $item['codigo'],
                        'proveedor_id' => $proveedor->id,
                    ],
                    [
                        'nombre' => $item['producto'],
                        'descripcion' => $item['descripcion'] ?? null,
                        'modelo' => $item['modelo'] ?? null,
                        'precio' => $item['precio'],
                        'precio_mayoreo' => $item['precio_mayoreo'] ?? null,
                        'precio_menuedeo' => $item['precio_menuedeo'] ?? null,
                        'marca_id' => $marcasExistentes[$item['marca']] ?? null,
                        'categoria_id' => $categoriasExistentes[$item['categoria']] ?? null,
                        'subcategoria_id' => isset($item['subcategoria'])
                            ? Categoria::where('nombre', $item['subcategoria'])
                                ->where('parent_id', $categoriasExistentes[$item['categoria']] ?? null)
                                ->where('proveedor_id', $proveedor->id)
                                ->value('id')
                            : null,
                        'unidad_medida_id' => $unidadesExistentes[$item['unidad_medida']] ?? null,
                    ]
                );

                if ($producto->wasRecentlyCreated) {
                    $productosCreados[] = $producto;
                } else {
                    $productosActualizados[] = $producto;
                }
            } catch (\Throwable $e) {
                $errores[] = [
                    'item' => $item,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'productosCreados' => $productosCreados,
            'productosActualizados' => $productosActualizados,
            'marcasCreadas' => $marcasCreadas,
            'marcasActualizadas' => $marcasActualizadas,
            'categoriasCreadas' => $categoriasCreadas,
            'categoriasActualizadas' => $categoriasActualizadas,
            'subcategoriasCreadas' => $subcategoriasCreadas,
            'subcategoriasActualizadas' => $subcategoriasActualizadas,
            'unidadesCreadas' => $unidadesCreadas,
            'unidadesActualizadas' => $unidadesActualizadas,
            'errores' => $errores,
        ];
    }

    /**
     * Update import audit with results
     */
    private function updateImportAuditResults(ImportAudit $importAudit, array $result): void
    {
        $importAudit->update([
            'estado' => EstadoImportacion::COMPLETADO->value,
            // 'fase' => 'completed',
            'progreso' => 100,
            'fin_proceso' => now(),
            'nuevos' => $result['resumen']['creados'],
            'actualizados' => $result['resumen']['actualizados'],
            'errores' => $result['resumen']['fallidos'],
            'errores_detalle' => $result['errores'],
        ]);

        $importAudit->appendLog('Importación completada exitosamente', [
            'resumen' => $result['resumen'],
        ]);

        $importAudit->save();
    }

    /**
     * Handle import error
     */
    private function handleImportError(ImportAudit $importAudit, \Throwable $e): void
    {
        $importAudit->update([
            'estado' => EstadoImportacion::ERROR->value,
            // 'fase' => 'completed',
            'fin_proceso' => now(),
        ]);

        $importAudit->appendLog('Error durante la importación', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $importAudit->save();
    }
}
