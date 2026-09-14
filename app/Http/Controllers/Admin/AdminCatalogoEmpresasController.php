<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProveedorProductoResource;
use App\Http\Resources\ProveedorResource;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\PublicStorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestión admin del catálogo de productos por empresa (reemplazo del feed catalogo-publico).
 */
class AdminCatalogoEmpresasController extends Controller
{
    /**
     * Empresas tipo catálogo (cards). Opcional: solo_con_productos=1, search.
     */
    public function empresas(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $soloConProductos = filter_var($request->input('solo_con_productos', false), FILTER_VALIDATE_BOOLEAN);
        $incluirNoCatalogo = filter_var($request->input('incluir_no_catalogo', false), FILTER_VALIDATE_BOOLEAN);

        $query = Proveedor::query();

        if (! $incluirNoCatalogo) {
            $query->where('is_proveedor_catalogo', true);
        }

        if ($soloConProductos) {
            $query->whereHas('productos', function ($q) {
                $q->where('activo', true);
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('nombre_comercial', 'like', "%{$search}%")
                    ->orWhere('rfc', 'like', "%{$search}%");
            });
        }

        $empresas = $query
            ->withCount([
                'productos as total_productos',
                'productos as total_publicados' => function ($q) {
                    $q->where('activo', true)->where('mostrar_en_catalogo_publico', true);
                },
            ])
            ->orderByRaw('COALESCE(NULLIF(razon_social, ""), nombre_comercial)')
            ->get()
            ->map(function (Proveedor $proveedor) {
                $nombre = trim((string) ($proveedor->razon_social ?: $proveedor->nombre_comercial));

                return [
                    'proveedor_id' => (int) $proveedor->id,
                    'empresa' => $nombre !== '' ? $nombre : ('Proveedor #'.$proveedor->id),
                    'razon_social' => $proveedor->razon_social,
                    'nombre_comercial' => $proveedor->nombre_comercial,
                    'rfc' => $proveedor->rfc,
                    'logo' => PublicStorageUrl::make($proveedor->logo),
                    'is_proveedor_catalogo' => (bool) $proveedor->is_proveedor_catalogo,
                    'total_productos' => (int) $proveedor->total_productos,
                    'total_publicados' => (int) $proveedor->total_publicados,
                ];
            })
            ->values()
            ->all();

        return $this->success($empresas, 'Empresas de catálogo.');
    }

    /**
     * Productos de una empresa (admin).
     */
    public function productos(Request $request, Proveedor $proveedor): JsonResponse
    {
        $filters = $request->only(Producto::getFilters());
        $filters['proveedor_id'] = $proveedor->id;

        $sortBy = $request->input('sort_by', 'nombre');
        $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = min(max(1, (int) $request->input('per_page', 40)), 100);

        $allowedSort = ['nombre', 'codigo_interno', 'precio_base', 'id', 'created_at', 'mostrar_en_catalogo_publico'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'nombre';
        }

        $paginator = Producto::query()
            ->with(['marca', 'categoria', 'subcategoria', 'unidad_medida', 'familia', 'subfamilia'])
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = ProveedorProductoResource::collection($paginator)->resolve();

        return $this->paginated(
            $paginator->setCollection(collect($data)),
            'Productos de la empresa.'
        );
    }

    /**
     * Acción masiva: publicar / despublicar productos en catálogo público (picker PPTOs).
     *
     * - Por IDs: `producto_ids[]`
     * - Por filtro actual (seleccionar todos): `aplicar_filtro=true` + mismos filtros del listado (`search`, `mostrar_en_catalogo_publico`)
     */
    public function bulkFlags(Request $request, Proveedor $proveedor): JsonResponse
    {
        $aplicarFiltro = filter_var($request->input('aplicar_filtro', false), FILTER_VALIDATE_BOOLEAN);

        $rules = [
            'mostrar_en_catalogo_publico' => ['required', 'boolean'],
            'aplicar_filtro' => ['sometimes', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'filtro_mostrar_en_catalogo_publico' => ['nullable', 'in:0,1,true,false'],
        ];

        if (! $aplicarFiltro) {
            $rules['producto_ids'] = ['required', 'array', 'min:1'];
            $rules['producto_ids.*'] = [
                'integer',
                Rule::exists('productos', 'id')->where(fn ($q) => $q->where('proveedor_id', $proveedor->id)),
            ];
        }

        $validated = $request->validate($rules, [
            'producto_ids.required' => 'Selecciona al menos un producto o aplica el filtro completo.',
            'mostrar_en_catalogo_publico.required' => 'Indica si publicar o despublicar.',
        ]);

        $publicar = (bool) $validated['mostrar_en_catalogo_publico'];

        $query = Producto::query()->where('proveedor_id', $proveedor->id);

        if ($aplicarFiltro) {
            $filters = [
                'proveedor_id' => $proveedor->id,
            ];
            $search = trim((string) ($validated['search'] ?? ''));
            if ($search !== '') {
                $filters['search'] = $search;
            }
            if (array_key_exists('filtro_mostrar_en_catalogo_publico', $validated)
                && $validated['filtro_mostrar_en_catalogo_publico'] !== null
                && $validated['filtro_mostrar_en_catalogo_publico'] !== '') {
                $filters['mostrar_en_catalogo_publico'] = $validated['filtro_mostrar_en_catalogo_publico'];
            }
            $query->filter($filters);
        } else {
            $query->whereIn('id', $validated['producto_ids']);
        }

        $updated = $query->update([
            'mostrar_en_catalogo_publico' => $publicar,
            'updated_at' => now(),
        ]);

        return $this->success(
            [
                'actualizados' => $updated,
                'mostrar_en_catalogo_publico' => $publicar,
                'aplicar_filtro' => $aplicarFiltro,
            ],
            $publicar
                ? 'Productos publicados en el catálogo.'
                : 'Productos retirados del catálogo público.'
        );
    }

    /**
     * Activar empresa como catálogo (atajo desde la UI de catálogo).
     */
    public function marcarComoCatalogo(Request $request, Proveedor $proveedor): JsonResponse
    {
        $proveedor->is_proveedor_catalogo = true;
        $proveedor->save();

        return $this->success(
            new ProveedorResource($proveedor->fresh(Proveedor::eagerLodable())),
            'Empresa marcada como catálogo.'
        );
    }
}
