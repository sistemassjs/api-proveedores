<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalogo\CatalogoEmpresaProductoDetalleResource;
use App\Http\Resources\Catalogo\CatalogoEmpresaProductoSugerenciaResource;
use App\Models\CatalogoFamilia;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\PublicStorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo público para picker de presupuestos: empresas tipo catálogo + productos publicados.
 * Reemplaza la lectura de catalogo_publico_items en el flujo PPTOs.
 */
class CatalogoEmpresasController extends Controller
{
    /**
     * Cards de empresas (proveedores is_proveedor_catalogo con productos publicados).
     * Search: nombre de empresa o de producto público (resultado agrupado por empresa).
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $query = Proveedor::query()
            ->where('is_proveedor_catalogo', true)
            ->whereHas('productos', function ($q) {
                $q->where('activo', true)
                    ->where('mostrar_en_catalogo_publico', true);
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('nombre_comercial', 'like', "%{$search}%")
                    ->orWhereHas('productos', function ($pq) use ($search) {
                        $pq->where('activo', true)
                            ->where('mostrar_en_catalogo_publico', true)
                            ->where(function ($inner) use ($search) {
                                $inner->where('nombre', 'like', "%{$search}%")
                                    ->orWhere('descripcion', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%")
                                    ->orWhere('codigo_interno', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $empresas = $query
            ->withCount([
                'productos as total_productos' => function ($q) {
                    $q->where('activo', true)
                        ->where('mostrar_en_catalogo_publico', true);
                },
            ])
            ->orderByRaw('COALESCE(NULLIF(razon_social, ""), nombre_comercial)')
            ->get()
            ->map(function (Proveedor $proveedor) {
                $nombre = trim((string) ($proveedor->razon_social ?: $proveedor->nombre_comercial));

                return [
                    'proveedor_id' => (int) $proveedor->id,
                    'empresa' => $nombre !== '' ? $nombre : ('Proveedor #'.$proveedor->id),
                    'logo' => PublicStorageUrl::make($proveedor->logo),
                    'total_productos' => (int) $proveedor->total_productos,
                ];
            })
            ->values()
            ->all();

        return $this->success($empresas, 'Empresas del catálogo.');
    }

    /**
     * Productos públicos de una empresa catálogo (shape compatible con sugerencias PPTOs).
     */
    public function productos(Request $request, Proveedor $proveedor): JsonResponse
    {
        if (! $this->esEmpresaCatalogo($proveedor)) {
            return $this->error('La empresa no participa en el catálogo público.', null, 404);
        }

        $search = trim((string) $request->input('search', ''));
        $familia = trim((string) $request->input('familia', ''));
        $subfamilia = trim((string) $request->input('subfamilia', ''));
        $familiaId = $request->input('familia_id');
        $subfamiliaId = $request->input('subfamilia_id');
        $perPage = (int) $request->input('per_page', 50);
        $limit = max(1, min($perPage, 100));

        $query = $this->productosPublicosQuery($proveedor)
            ->with(['unidad_medida', 'marca', 'familia', 'subfamilia', 'proveedor']);

        if ($search !== '') {
            $query->filter(['search' => $search]);
        }

        if ($familiaId !== null && $familiaId !== '') {
            $query->where('familia_id', (int) $familiaId);
        } elseif ($familia !== '') {
            $query->whereHas('familia', function ($q) use ($familia) {
                $q->where('nombre', $familia);
            });
        }

        if ($subfamiliaId !== null && $subfamiliaId !== '') {
            $query->where('subfamilia_id', (int) $subfamiliaId);
        } elseif ($subfamilia !== '') {
            $query->whereHas('subfamilia', function ($q) use ($subfamilia) {
                $q->where('nombre', $subfamilia);
            });
        }

        $rows = $query->orderBy('nombre')->limit($limit)->get();
        $data = CatalogoEmpresaProductoSugerenciaResource::collection($rows)->resolve();

        return $this->success($data, 'Productos del catálogo.');
    }

    /**
     * Facets OPUS (familia / subfamilia) dentro de una empresa. Sin marca.
     */
    public function facets(Request $request, Proveedor $proveedor): JsonResponse
    {
        if (! $this->esEmpresaCatalogo($proveedor)) {
            return $this->error('La empresa no participa en el catálogo público.', null, 404);
        }

        $base = $this->productosPublicosQuery($proveedor);

        $familiaIds = (clone $base)
            ->whereNotNull('familia_id')
            ->distinct()
            ->pluck('familia_id')
            ->filter()
            ->values();

        $familias = CatalogoFamilia::query()
            ->whereIn('id', $familiaIds)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n, 'UTF-8'))
            ->values()
            ->all();

        $subfamilias = (clone $base)
            ->whereNotNull('subfamilia_id')
            ->with('subfamilia:id,nombre')
            ->get()
            ->pluck('subfamilia.nombre')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n, 'UTF-8'))
            ->sort()
            ->values()
            ->all();

        return $this->success(
            [
                // Compat UI picker: "categorias" = familias OPUS; marcas vacío (no es filtro).
                'familias' => $familias,
                'subfamilias' => $subfamilias,
                'categorias' => $familias,
                'marcas' => [],
            ],
            'Filtros OPUS del catálogo.'
        );
    }

    public function show(Request $request, Proveedor $proveedor, Producto $producto): JsonResponse
    {
        if (! $this->esEmpresaCatalogo($proveedor)) {
            return $this->error('La empresa no participa en el catálogo público.', null, 404);
        }

        if ((int) $producto->proveedor_id !== (int) $proveedor->id) {
            return $this->error('El producto no pertenece a la empresa indicada.', null, 404);
        }

        if (! $producto->activo || ! $producto->mostrar_en_catalogo_publico) {
            return $this->error('El producto no está disponible en el catálogo público.', null, 404);
        }

        $producto->load(['unidad_medida', 'marca', 'familia', 'subfamilia', 'proveedor']);

        return $this->success(
            new CatalogoEmpresaProductoDetalleResource($producto),
            'Producto del catálogo.'
        );
    }

    private function esEmpresaCatalogo(Proveedor $proveedor): bool
    {
        return (bool) $proveedor->is_proveedor_catalogo;
    }

    private function productosPublicosQuery(Proveedor $proveedor)
    {
        return Producto::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('activo', true)
            ->where('mostrar_en_catalogo_publico', true);
    }
}
