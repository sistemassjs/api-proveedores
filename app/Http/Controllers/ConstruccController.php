<?php

namespace App\Http\Controllers;

use App\Http\Requests\Construcc\ProveedoresBusquedaRequest;
use App\Http\Requests\Construcc\ProveedoresFilterRequest;
use App\Http\Resources\Construcc\ConstruccCategoriaResource;
use App\Http\Resources\Construcc\ConstruccMarcaResource;
use App\Http\Resources\Construcc\ConstruccProductoResource;
use App\Http\Resources\Construcc\ConstruccProveedorResource;
use App\Http\Resources\Construcc\ConstruccUnidadResource;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Services\ConstruccSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para el módulo de construcción
 *
 * Maneja todas las operaciones relacionadas con proveedores, productos,
 * búsquedas avanzadas y catálogos para el sistema de construcción.
 */
class ConstruccController extends Controller
{
    protected ConstruccSearchService $searchService;

    public function __construct(ConstruccSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /*
    |--------------------------------------------------------------------------
    | PROVEEDORES - Con Paginación
    |--------------------------------------------------------------------------
    */

    /**
     * Lista paginada de proveedores con filtros básicos
     */
    public function proveedores(ProveedoresFilterRequest $request): JsonResponse
    {
        $filtros = [
            'buscar' => $request->buscar,
            'estado' => $request->estado,
            'municipio' => $request->municipio,
            'tipos_empresa_id' => $request->tipos_empresa_id,
            'con_productos' => $request->con_productos,
            'sort_by' => $request->sort_by,
            'order' => $request->order,
            'per_page' => $request->per_page,
        ];

        $proveedores = Proveedor::with([
            // 'categorias', 'marcas', 'unidades'
        ])
            // ->where('estatus', 'activo')
            // ->where('estatus', 'registrado')
            ->when($filtros['buscar'], function ($query, $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('nombre_comercial', 'like', "%{$buscar}%")
                        ->orWhere('razon_social', 'like', "%{$buscar}%")
                        ->orWhere('rfc', 'like', "%{$buscar}%");
                });
            })
            ->when($filtros['estado'], function ($query, $estado) {
                $query->where('estado', 'like', "%{$estado}%");
            })
            ->when($filtros['municipio'], function ($query, $municipio) {
                $query->where('municipio', 'like', "%{$municipio}%");
            })
            ->when($filtros['con_productos'], function ($query) {
                $query->whereHas(
                    'productos',
                    // function ($q) {
                    //     $q->where('estaut', true);
                    // }
                );
            })
            ->orderBy($filtros['sort_by'], $filtros['order'])
            ->paginate($filtros['per_page']);

        $data = ConstruccProveedorResource::collection($proveedores)->resolve();

        return $this->paginated($proveedores->setCollection(collect($data)));
    }

    /**
     * Búsqueda avanzada de proveedores con filtros múltiples
     */
    public function buscarProveedores(ProveedoresBusquedaRequest $request): JsonResponse
    {
        $filtros = $request->only([
            'buscar',
            'estado',
            'municipio',
            'tipos_empresa_id',
            'categoria_id',
            'marca_id',
            'con_productos',
        ]);
        $filtros['sort_by'] = $request->orden_por;
        $filtros['order'] = $request->direccion;
        $filtros['per_page'] = $request->per_page;

        $proveedores = $this->searchService->buscarProveedores($filtros);

        $data = ConstruccProveedorResource::collection($proveedores)->resolve();

        return $this->paginated($proveedores->setCollection(collect($data)));
    }

    /**
     * Productos de un proveedor específico con paginación
     */
    public function productosPorProveedor(Request $request, Proveedor $proveedor): JsonResponse
    {
        $request->validate([
            'categoria_id' => 'nullable|string',
            'marca_id' => 'nullable|string',
            'con_stock' => 'nullable|boolean',
            'destacado' => 'nullable|boolean',
            'sort_by' => 'nullable|in:nombre,precio_base,stock,created_at,updated_at',
            'order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $filtros = $request->only(['categoria_id', 'marca_id', 'con_stock', 'destacado']);
        $filtros['sort_by'] = $request->sort_by ?? 'nombre';
        $filtros['order'] = $request->order ?? 'asc';
        $filtros['per_page'] = $request->per_page ?? 20;

        $productos = $this->searchService->obtenerProductosProveedor($proveedor->id, $filtros);

        $data = ConstruccProductoResource::collection($productos)->resolve();

        return $this->paginated($productos->setCollection(collect($data)));
    }

    /**
     * Búsqueda avanzada en productos de un proveedor específico
     */
    public function buscarProductosProveedor(Request $request, Proveedor $proveedor): JsonResponse
    {
        $request->validate([
            'buscar' => 'nullable|string|min:2',
            'categoria_id' => 'nullable|string',
            'marca_id' => 'nullable|string',
            'precio_min' => 'nullable|numeric|min:0',
            'precio_max' => 'nullable|numeric|min:0',
            'con_stock' => 'nullable|boolean',
            'destacado' => 'nullable|boolean',
            'sort_by' => 'nullable|in:nombre,precio_base,stock,created_at,updated_at',
            'order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $filtros = $request->only([
            'buscar',
            'categoria_id',
            'marca_id',
            'precio_min',
            'precio_max',
            'con_stock',
            'destacado',
        ]);
        $filtros['sort_by'] = $request->orden_por ?? 'nombre';
        $filtros['order'] = $request->direccion ?? 'asc';
        $filtros['per_page'] = $request->per_page ?? 20;

        $productos = $this->searchService->buscarProductosProveedor($proveedor->id, $filtros);

        $data = ConstruccProductoResource::collection($productos)->resolve();

        return $this->paginated($productos->setCollection(collect($data)));
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCTOS - Con Paginación
    |--------------------------------------------------------------------------
    */

    /**
     * Búsqueda general de productos con filtros múltiples
     */
    public function buscarProductos(Request $request): JsonResponse
    {
        $request->validate([
            'buscar' => 'nullable|string|min:2',
            'proveedor_id' => 'nullable|string',     // Múltiples proveedores: "1,2,3"
            'categoria_id' => 'nullable|string',     // Múltiples categorías: "1,2,3"
            'subcategoria_id' => 'nullable|string',  // Múltiples subcategorías: "1,2,3"
            'marca_id' => 'nullable|string',         // Múltiples marcas: "1,2,3"
            'unidad_medida_id' => 'nullable|string', // Múltiples unidades: "1,2,3"
            'precio_min' => 'nullable|numeric|min:0',
            'precio_max' => 'nullable|numeric|min:0',
            'con_stock' => 'nullable|boolean',
            'destacado' => 'nullable|boolean',
            'sort_by' => 'nullable|in:nombre,precio_base,stock,created_at,updated_at',
            'order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $filtros = $request->only([
            'buscar',
            'proveedor_id',
            'categoria_id',
            'subcategoria_id',
            'marca_id',
            'unidad_medida_id',
            'precio_min',
            'precio_max',
            'con_stock',
            'destacado',
        ]);
        $filtros['sort_by'] = $request->orden_por ?? 'nombre';
        $filtros['order'] = $request->direccion ?? 'asc';
        $filtros['per_page'] = $request->per_page ?? 20;

        $productos = $this->searchService->buscarProductos($filtros);

        $data = ConstruccProductoResource::collection($productos)->resolve();

        return $this->paginated($productos->setCollection(collect($data)));
    }

    /**
     * Obtiene los filtros disponibles para productos
     */
    public function filtrosProductos(Request $request): JsonResponse
    {
        $filtros = [
            'proveedores' => Proveedor::where('estatus', 'activo')
                ->has('productos')
                ->get(['id', 'nombre_comercial'])
                ->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre_comercial]),

            'categorias' => Categoria::whereHas('productos')
                ->where('activo', true)
                ->get(['id', 'nombre'])
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre]),

            'marcas' => Marca::whereHas('productos')
                ->where('activo', true)
                ->get(['id', 'nombre'])
                ->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre]),

            'unidades' => UnidadMedida::whereHas('productos')
                ->get(['id', 'nombre', 'clave'])
                ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre, 'clave' => $u->clave]),

            'rango_precios' => [
                'min' => Producto::where('activo', true)->min('precio_base') ?? 0,
                'max' => Producto::where('activo', true)->max('precio_base') ?? 0,
            ],
        ];

        return $this->success($filtros, 'Filtros disponibles obtenidos correctamente.');
    }

    /**
     * Sugerencias de productos para autocompletado
     */
    public function sugerenciasProductos(Request $request): JsonResponse
    {
        $request->validate([
            'termino' => 'required|string|min:1',
            'proveedor_id' => 'nullable|exists:proveedores,id',
            'limite' => 'nullable|integer|min:5|max:50',
        ]);

        $sugerencias = $this->searchService->obtenerSugerenciasProductos(
            $request->termino,
            $request->proveedor_id,
            $request->limite ?? 10
        );

        return $this->success(['sugerencias' => $sugerencias]);
    }

    /*
    |--------------------------------------------------------------------------
    | CATÁLOGOS - Sin Paginación (Para Dropdowns)
    |--------------------------------------------------------------------------
    */

    /**
     * Marcas de un proveedor específico (sin paginación)
     */
    public function marcasProveedor(Request $request, Proveedor $proveedor): JsonResponse
    {
        $marcas = $proveedor->marcas()
            ->where('activo', true)
            ->withCount(['productos' => function ($query) {
                $query->where('activo', true);
            }])
            ->orderBy('nombre')
            ->get();

        return $this->success([
            'marcas' => ConstruccMarcaResource::collection($marcas),
            'total' => $marcas->count(),
        ]);
    }

    /**
     * Categorías de un proveedor con subcategorías anidadas (sin paginación)
     */
    public function categoriasProveedor(Request $request, Proveedor $proveedor): JsonResponse
    {
        $request->validate([
            'incluir_subcategorias' => 'nullable|boolean',
            'solo_padres' => 'nullable|boolean',
        ]);

        $query = $proveedor->categorias()
            ->where('activo', true)
            ->withCount(['productos' => function ($query) {
                $query->where('activo', true);
            }]);

        if ($request->boolean('solo_padres')) {
            $query->whereNull('parent_id');
        }

        if ($request->boolean('incluir_subcategorias')) {
            $query->with(['children' => function ($q) {
                $q->where('activo', true)
                    ->withCount(['productos' => function ($query) {
                        $query->where('activo', true);
                    }]);
            }]);
        }

        $categorias = $query->orderBy('nombre')->get();

        return $this->success([
            'categorias' => ConstruccCategoriaResource::collection($categorias),
            'total' => $categorias->count(),
        ]);
    }

    /**
     * Unidades de medida de un proveedor específico (sin paginación)
     */
    public function unidadesProveedor(Request $request, Proveedor $proveedor): JsonResponse
    {
        $unidades = UnidadMedida::query()
            ->withCount(['productos' => function ($query) use ($proveedor) {
                $query->where('proveedor_id', $proveedor->id)->where('activo', true);
            }])
            ->orderBy('nombre')
            ->get();

        return $this->success([
            'unidades' => ConstruccUnidadResource::collection($unidades),
            'total' => $unidades->count(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ESTADÍSTICAS Y REPORTES
    |--------------------------------------------------------------------------
    */

    /**
     * Estadísticas generales del módulo
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $estadisticas = $this->searchService->obtenerEstadisticas();

        return $this->success($estadisticas, 'Estadísticas obtenidas correctamente.');
    }

    /**
     * Resumen específico de un proveedor
     */
    public function resumenProveedor(Request $request, Proveedor $proveedor): JsonResponse
    {
        $resumen = $this->searchService->obtenerResumenProveedor($proveedor->id);

        return $this->success($resumen, 'Resumen del proveedor obtenido correctamente.');
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN Y METADATOS
    |--------------------------------------------------------------------------
    */

    /**
     * Filtros disponibles globalmente
     */
    public function filtrosDisponibles(Request $request): JsonResponse
    {
        $filtros = [
            'proveedores' => [
                'disponibles' => ['nombre_comercial', 'razon_social', 'rfc', 'estado', 'municipio'],
                'ordenamiento' => ['nombre_comercial', 'razon_social', 'created_at', 'updated_at'],
            ],
            'productos' => [
                'disponibles' => [
                    'buscar',
                    'proveedor_id',
                    'categoria_id',
                    'subcategoria_id',
                    'marca_id',
                    'precio_min',
                    'precio_max',
                    'con_stock',
                    'destacado',
                ],
                'ordenamiento' => ['nombre', 'precio_base', 'stock', 'created_at', 'updated_at'],
            ],
        ];

        return $this->success($filtros, 'Configuración de filtros obtenida correctamente.');
    }

    /**
     * Opciones de ordenamiento disponibles
     */
    public function opcionesOrdenamiento(Request $request): JsonResponse
    {
        $opciones = [
            'proveedores' => [
                ['value' => 'nombre_comercial', 'label' => 'Nombre Comercial'],
                ['value' => 'razon_social', 'label' => 'Razón Social'],
                ['value' => 'created_at', 'label' => 'Fecha de Registro'],
                ['value' => 'updated_at', 'label' => 'Última Actualización'],
            ],
            'productos' => [
                ['value' => 'nombre', 'label' => 'Nombre'],
                ['value' => 'precio_base', 'label' => 'Precio'],
                ['value' => 'stock', 'label' => 'Stock'],
                ['value' => 'created_at', 'label' => 'Fecha de Registro'],
                ['value' => 'updated_at', 'label' => 'Última Actualización'],
            ],
        ];

        return $this->success($opciones, 'Opciones de ordenamiento obtenidas correctamente.');
    }
}
