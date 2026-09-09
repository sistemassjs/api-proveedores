<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalogo\CatalogoPublicoItemResource;
use App\Models\CatalogoPublicoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoPublicoItemController extends Controller
{
    /**
     * Resumen de empresas del catálogo público (logo + conteo).
     */
    public function empresas(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $query = CatalogoPublicoItem::query()
            ->where('activo', true)
            ->where('mostrar_en_listado', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('empresa', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%");
            });
        }

        $empresas = $query
            ->select('empresa')
            ->selectRaw('COUNT(*) as total_productos')
            ->selectRaw('MAX(logo) as logo')
            ->groupBy('empresa')
            ->orderBy('empresa')
            ->get()
            ->map(fn ($row) => [
                'empresa' => (string) $row->empresa,
                'logo' => $row->logo ? (string) $row->logo : null,
                'total_productos' => (int) $row->total_productos,
                'mostrar_en_listado' => true,
            ])
            ->values()
            ->all();

        return $this->success($empresas, 'Empresas del catálogo público.');
    }

    /**
     * Facets (marca / categoría) para filtros del picker de PPTO.
     * Opcional: ?empresa=Nombre
     */
    public function facets(Request $request): JsonResponse
    {
        $empresa = trim((string) $request->input('empresa', ''));

        $base = CatalogoPublicoItem::query()
            ->where('activo', true)
            ->where('mostrar_en_listado', true);

        if ($empresa !== '') {
            $base->where('empresa', $empresa);
        }

        $valores = static function ($query, string $columna): array {
            $vistos = [];
            $resultado = [];
            $rows = $query->clone()
                ->whereNotNull($columna)
                ->where($columna, '!=', '')
                ->orderBy($columna)
                ->pluck($columna);

            foreach ($rows as $raw) {
                $valor = trim((string) $raw);
                if ($valor === '') {
                    continue;
                }
                $clave = mb_strtolower($valor, 'UTF-8');
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;
                $resultado[] = $valor;
            }

            sort($resultado, SORT_NATURAL | SORT_FLAG_CASE);

            return $resultado;
        };

        return $this->success(
            [
                'marcas' => $valores($base, 'marca'),
                'categorias' => $valores($base, 'categoria'),
            ],
            'Filtros del catálogo público.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(CatalogoPublicoItem::getFilters());
        if (! array_key_exists('activo', $filters) || $filters['activo'] === '' || $filters['activo'] === null) {
            $filters['activo'] = true;
        }
        if (
            ! array_key_exists('mostrar_en_listado', $filters)
            || $filters['mostrar_en_listado'] === ''
            || $filters['mostrar_en_listado'] === null
        ) {
            $filters['mostrar_en_listado'] = true;
        }

        $sortBy = $request->input('sort_by', 'nombre');
        $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->input('per_page', 20);

        $allowedSort = ['nombre', 'codigo', 'empresa', 'categoria', 'marca', 'precio_base', 'id'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'nombre';
        }

        $paginator = CatalogoPublicoItem::query()
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate(max(1, min($perPage, 100)));

        $data = CatalogoPublicoItemResource::collection($paginator)->resolve();

        return $this->paginated(
            $paginator->setCollection(collect($data)),
            'Catálogo público.'
        );
    }

    public function show(CatalogoPublicoItem $catalogoPublicoItem): JsonResponse
    {
        if (! $catalogoPublicoItem->activo) {
            return $this->error('El ítem no está disponible.', null, 404);
        }

        return $this->success(
            new CatalogoPublicoItemResource($catalogoPublicoItem),
            'Ítem del catálogo público.'
        );
    }
}
