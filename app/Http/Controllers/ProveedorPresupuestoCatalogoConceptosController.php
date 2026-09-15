<?php

namespace App\Http\Controllers;

use App\Http\Requests\Presupuesto\ProveedorStorePresupuestoCatalogoConceptoRequest;
use App\Http\Requests\Presupuesto\ProveedorUpdatePresupuestoCatalogoConceptoRequest;
use App\Http\Resources\Catalogo\CatalogoEmpresaProductoSugerenciaResource;
use App\Http\Resources\Presupuesto\PresupuestoSugerenciaLineaResource;
use App\Http\Resources\Presupuesto\ProveedorPresupuestoCatalogoConceptoResource;
use App\Models\PresupuestoCatalogoConcepto;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\Presupuesto\PresupuestoMatrizCalculoService;
use App\Support\PresupuestoAnexoArchivoResponse;
use App\Support\PresupuestoAnexoImagenOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ProveedorPresupuestoCatalogoConceptosController extends Controller
{
    private bool $logEnabled = true;

    public function __construct(
        private PresupuestoMatrizCalculoService $matrizCalculo
    ) {
    }

    /**
     * Listado del catálogo de conceptos del proveedor.
     */
    public function index(Request $request, Proveedor $proveedor): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
        }

        $filters = $request->only(PresupuestoCatalogoConcepto::getFilters());
        $filters['proveedor_id'] = $proveedor->id;

        $sortBy = $request->input('sort_by', 'descripcion');
        $order = $request->input('order', 'asc');
        $perPage = $request->input('per_page', 15);

        $allowedSort = ['descripcion', 'categoria', 'unidad', 'precio_unitario', 'created_at', 'id', 'clave', 'es_compuesto'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'descripcion';
        }
        $order = strtolower((string) $order) === 'desc' ? 'desc' : 'asc';

        $originalPaginator = PresupuestoCatalogoConcepto::query()
            ->filter($filters)
            ->withCount('componentes')
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = ProveedorPresupuestoCatalogoConceptoResource::collection($originalPaginator)->resolve();

        return $this->paginated($originalPaginator->setCollection(collect($data)));
    }

    /**
     * Sugerencias combinadas: catálogo interno del proveedor + productos publicados (empresas catálogo).
     */
    public function sugerencias(Request $request, Proveedor $proveedor): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
        }

        $origen = strtolower(trim((string) $request->input('origen', 'todos')));
        if (! in_array($origen, ['todos', 'concepto', 'catalogo'], true)) {
            $origen = 'todos';
        }

        $search = trim((string) $request->input('search', ''));
        $categoria = trim((string) $request->input('categoria', ''));
        $empresa = trim((string) $request->input('empresa', ''));
        $empresaProveedorId = $request->input('proveedor_id', $request->input('empresa_proveedor_id'));
        $familia = trim((string) $request->input('familia', ''));
        $perPage = (int) $request->input('per_page', 50);
        $limit = max(1, min($perPage, 100));

        $conceptosItems = [];
        $catalogoItems = [];

        if (in_array($origen, ['todos', 'concepto'], true) && $empresa === '' && ($empresaProveedorId === null || $empresaProveedorId === '')) {
            $conceptosQuery = PresupuestoCatalogoConcepto::query()
                ->where('proveedor_id', $proveedor->id)
                ->where('activo', true);

            if ($search !== '') {
                $conceptosQuery->filter(['search' => $search]);
            }
            if (in_array($categoria, ['producto', 'servicio'], true)) {
                $conceptosQuery->where('categoria', $categoria);
            }

            $conceptos = $conceptosQuery
                ->orderBy('descripcion')
                ->limit($limit)
                ->get();

            foreach ($conceptos as $concepto) {
                $conceptosItems[] = [
                    'origen' => 'concepto',
                    'id' => $concepto->id,
                    'nombre' => $concepto->descripcion,
                    'unidad' => $concepto->unidad,
                    'precio_unitario' => (float) $concepto->precio_unitario,
                    'empresa' => null,
                    'logo' => null,
                    'categoria_ui' => $concepto->categoria,
                    'es_compuesto' => (bool) $concepto->es_compuesto,
                    'clave' => $concepto->clave,
                    'marca' => null,
                    'familia' => null,
                    'subcategoria' => null,
                    'descripcion' => $concepto->descripcion,
                    'codigo' => $concepto->clave,
                    'imagen_url' => PresupuestoAnexoArchivoResponse::archivoUrl($concepto->imagen_path),
                    'imagen_path' => PresupuestoAnexoArchivoResponse::archivoPathPublico($concepto->imagen_path),
                    'imagen_base64' => PresupuestoAnexoArchivoResponse::solicitaArchivoBase64($request)
                        ? PresupuestoAnexoArchivoResponse::archivoBase64($concepto->imagen_path)
                        : null,
                ];
            }
        }

        $incluyeCatalogo = in_array($origen, ['todos', 'catalogo'], true)
            && ($categoria === '' || $categoria === 'todos' || $categoria === 'producto');

        if ($incluyeCatalogo) {
            $catalogoQuery = Producto::query()
                ->where('activo', true)
                ->where('mostrar_en_catalogo_publico', true)
                ->whereHas('proveedor', function ($q) {
                    $q->where('is_proveedor_catalogo', true);
                })
                ->with(['unidad_medida', 'marca', 'familia', 'subfamilia', 'proveedor']);

            if ($empresaProveedorId !== null && $empresaProveedorId !== '') {
                $catalogoQuery->where('proveedor_id', (int) $empresaProveedorId);
            } elseif ($empresa !== '') {
                $catalogoQuery->whereHas('proveedor', function ($q) use ($empresa) {
                    $q->where('is_proveedor_catalogo', true)
                        ->where(function ($inner) use ($empresa) {
                            $inner->where('razon_social', $empresa)
                                ->orWhere('nombre_comercial', $empresa);
                        });
                });
            }

            if ($search !== '') {
                $catalogoQuery->filter(['search' => $search]);
            }
            if ($familia !== '') {
                $catalogoQuery->whereHas('familia', function ($q) use ($familia) {
                    $q->where('nombre', $familia);
                });
            }

            $catalogoRows = $catalogoQuery
                ->orderBy('nombre')
                ->limit($limit)
                ->get();

            $catalogoItems = CatalogoEmpresaProductoSugerenciaResource::collection($catalogoRows)->resolve();
        }

        // Productos de empresas catálogo primero (agrupable por empresa en UI), luego mis conceptos.
        $items = array_merge($catalogoItems, $conceptosItems);
        $data = PresupuestoSugerenciaLineaResource::collection(collect($items))->resolve();

        return $this->success($data, 'Sugerencias de línea.');
    }

    /**
     * Crear concepto en el catálogo.
     */
    public function store(
        ProveedorStorePresupuestoCatalogoConceptoRequest $request,
        Proveedor $proveedor
    ): JsonResponse {
        try {
            $user = $request->user();

            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
            }

            $validated = $request->validated();
            $esCompuesto = (bool) ($validated['es_compuesto'] ?? false);
            $clave = $this->matrizCalculo->normalizarClave($validated['clave'] ?? null);

            $imagenPath = null;
            $base64 = $validated['imagen_base64'] ?? null;
            if (is_string($base64) && trim($base64) !== '') {
                $imagenPath = $this->guardarImagenBase64((int) $proveedor->id, $base64);
            }

            $concepto = DB::transaction(function () use ($validated, $proveedor, $esCompuesto, $clave, $imagenPath) {
                $concepto = PresupuestoCatalogoConcepto::create([
                    'proveedor_id' => $proveedor->id,
                    'descripcion' => $validated['descripcion'],
                    'categoria' => $validated['categoria'],
                    'es_compuesto' => $esCompuesto,
                    'clave' => $clave,
                    'unidad' => $validated['unidad'],
                    'precio_unitario' => $esCompuesto ? 0 : (float) ($validated['precio_unitario'] ?? 0),
                    'imagen_path' => $imagenPath,
                ]);

                if ($esCompuesto) {
                    return $this->matrizCalculo->sincronizarComponentesCatalogo(
                        $concepto,
                        $validated['componentes'] ?? []
                    );
                }

                return $concepto->load('componentes');
            });

            $this->log('Concepto agregado al catálogo de presupuestos', [
                'catalogo_concepto_id' => $concepto->id,
                'proveedor_id' => $proveedor->id,
                'es_compuesto' => $esCompuesto,
            ]);

            return $this->success(
                new ProveedorPresupuestoCatalogoConceptoResource($concepto),
                'Concepto agregado al catálogo.',
                201
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            $this->log('Error al crear concepto de catálogo', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible crear el concepto del catálogo.', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar concepto del catálogo.
     */
    public function show(
        Request $request,
        Proveedor $proveedor,
        PresupuestoCatalogoConcepto $presupuestoCatalogoConcepto
    ): JsonResponse {
        $user = $request->user();

        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
        }

        if ((int) $presupuestoCatalogoConcepto->proveedor_id !== (int) $proveedor->id) {
            return $this->error('El concepto no pertenece a este proveedor.', null, 403);
        }

        $presupuestoCatalogoConcepto->load(['componentes']);

        return $this->success(
            new ProveedorPresupuestoCatalogoConceptoResource($presupuestoCatalogoConcepto)
        );
    }

    /**
     * Actualizar concepto del catálogo.
     */
    public function update(
        ProveedorUpdatePresupuestoCatalogoConceptoRequest $request,
        Proveedor $proveedor,
        PresupuestoCatalogoConcepto $presupuestoCatalogoConcepto
    ): JsonResponse {
        try {
            $user = $request->user();

            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
            }

            if ((int) $presupuestoCatalogoConcepto->proveedor_id !== (int) $proveedor->id) {
                return $this->error('El concepto no pertenece a este proveedor.', null, 403);
            }

            $validated = $request->validated();

            if (array_key_exists('activo', $validated) && ! array_key_exists('descripcion', $validated)) {
                $presupuestoCatalogoConcepto->update([
                    'activo' => (bool) $validated['activo'],
                ]);

                $mensaje = $presupuestoCatalogoConcepto->activo
                    ? 'Concepto reactivado correctamente.'
                    : 'Concepto dado de baja correctamente.';

                $this->log($mensaje, [
                    'catalogo_concepto_id' => $presupuestoCatalogoConcepto->id,
                    'activo' => $presupuestoCatalogoConcepto->activo,
                ]);

                return $this->success(
                    new ProveedorPresupuestoCatalogoConceptoResource($presupuestoCatalogoConcepto->fresh()),
                    $mensaje
                );
            }

            $imagenPath = $presupuestoCatalogoConcepto->imagen_path;

            $base64 = $validated['imagen_base64'] ?? null;
            if (is_string($base64) && trim($base64) !== '') {
                $nuevoPath = $this->guardarImagenBase64((int) $proveedor->id, $base64);
                if ($nuevoPath !== null) {
                    $this->eliminarImagenSiExiste($imagenPath);
                    $imagenPath = $nuevoPath;
                }
            } elseif (! empty($validated['eliminar_imagen'])) {
                $this->eliminarImagenSiExiste($imagenPath);
                $imagenPath = null;
            } elseif (array_key_exists('imagen_path', $validated) && $validated['imagen_path'] === null) {
                $this->eliminarImagenSiExiste($imagenPath);
                $imagenPath = null;
            }

            $esCompuesto = array_key_exists('es_compuesto', $validated)
                ? (bool) $validated['es_compuesto']
                : (bool) $presupuestoCatalogoConcepto->es_compuesto;

            $payload = [
                'descripcion' => $validated['descripcion'],
                'categoria' => $validated['categoria'],
                'unidad' => $validated['unidad'],
                'es_compuesto' => $esCompuesto,
                'imagen_path' => $imagenPath,
            ];
            if (array_key_exists('clave', $validated)) {
                $payload['clave'] = $this->matrizCalculo->normalizarClave($validated['clave']);
            }
            if (array_key_exists('activo', $validated)) {
                $payload['activo'] = (bool) $validated['activo'];
            }
            if (! $esCompuesto && array_key_exists('precio_unitario', $validated)) {
                $payload['precio_unitario'] = (float) $validated['precio_unitario'];
            }

            $concepto = DB::transaction(function () use (
                $presupuestoCatalogoConcepto,
                $payload,
                $esCompuesto,
                $validated
            ) {
                $presupuestoCatalogoConcepto->update($payload);
                $presupuestoCatalogoConcepto->refresh();

                if ($esCompuesto) {
                    $componentes = $validated['componentes']
                        ?? $presupuestoCatalogoConcepto->componentes()
                            ->get()
                            ->map(fn ($c) => [
                                'orden' => $c->orden,
                                'categoria' => $c->categoria,
                                'catalogo_concepto_componente_id' => $c->catalogo_concepto_componente_id,
                                'clave_snapshot' => $c->clave_snapshot,
                                'descripcion' => $c->descripcion,
                                'unidad' => $c->unidad,
                                'cantidad' => $c->cantidad,
                                'precio_unitario' => $c->precio_unitario,
                            ])
                            ->all();

                    return $this->matrizCalculo->sincronizarComponentesCatalogo(
                        $presupuestoCatalogoConcepto,
                        $componentes
                    );
                }

                $presupuestoCatalogoConcepto->componentes()->delete();

                return $presupuestoCatalogoConcepto->fresh(['componentes']);
            });

            $this->log('Concepto de catálogo actualizado', [
                'catalogo_concepto_id' => $concepto->id,
                'es_compuesto' => $esCompuesto,
            ]);

            return $this->success(
                new ProveedorPresupuestoCatalogoConceptoResource($concepto),
                'Concepto actualizado correctamente.'
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            $this->log('Error al actualizar concepto de catálogo', [
                'catalogo_concepto_id' => $presupuestoCatalogoConcepto->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible actualizar el concepto del catálogo.', [$e->getMessage()], 500);
        }
    }

    /**
     * Dar de baja concepto del catálogo (soft: activo = false).
     */
    public function destroy(
        Request $request,
        Proveedor $proveedor,
        PresupuestoCatalogoConcepto $presupuestoCatalogoConcepto
    ): JsonResponse {
        try {
            $user = $request->user();

            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
            }

            if ((int) $presupuestoCatalogoConcepto->proveedor_id !== (int) $proveedor->id) {
                return $this->error('El concepto no pertenece a este proveedor.', null, 403);
            }

            $presupuestoCatalogoConcepto->update(['activo' => false]);

            $this->log('Concepto dado de baja del catálogo', [
                'catalogo_concepto_id' => $presupuestoCatalogoConcepto->id,
            ]);

            return $this->success(
                new ProveedorPresupuestoCatalogoConceptoResource($presupuestoCatalogoConcepto->fresh()),
                'Concepto dado de baja correctamente.'
            );
        } catch (Throwable $e) {
            $this->log('Error al dar de baja concepto de catálogo', [
                'catalogo_concepto_id' => $presupuestoCatalogoConcepto->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible dar de baja el concepto del catálogo.', [$e->getMessage()], 500);
        }
    }

    private function guardarImagenBase64(int $proveedorId, string $dataUri): ?string
    {
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $dataUri, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            return null;
        }

        $optimizado = PresupuestoAnexoImagenOptimizer::optimizarParaAlmacenamiento($binary);
        $extension = $optimizado['extension'] ?? 'jpg';

        $path = sprintf(
            'proveedores/%d/presupuestos/catalogo-conceptos/%s.%s',
            $proveedorId,
            Str::uuid()->toString(),
            $extension
        );

        Storage::disk('public')->put($path, $optimizado['binary']);

        return $path;
    }

    private function eliminarImagenSiExiste(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        $path = trim((string) $path);
        if (str_starts_with($path, 'data:image/')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function log(string $message, array $data = []): void
    {
        if (! $this->logEnabled) {
            return;
        }

        Log::info($message, $data);
    }
}
