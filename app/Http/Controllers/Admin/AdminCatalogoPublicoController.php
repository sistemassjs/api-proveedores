<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCatalogoPublicoImportRequest;
use App\Http\Requests\Admin\AdminCatalogoPublicoUpdateEmpresaRequest;
use App\Http\Requests\Admin\AdminCatalogoPublicoUpdateRequest;
use App\Http\Resources\Catalogo\CatalogoPublicoImportResultResource;
use App\Http\Resources\Catalogo\CatalogoPublicoItemResource;
use App\Models\CatalogoPublicoItem;
use App\Services\CatalogoPublico\CatalogoPublicoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AdminCatalogoPublicoController extends Controller
{
    public function __construct(private CatalogoPublicoImportService $importService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(CatalogoPublicoItem::getFilters());
        $sortBy = $request->input('sort_by', 'nombre');
        $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->input('per_page', 15);

        $allowedSort = [
            'nombre', 'codigo', 'empresa', 'categoria', 'marca',
            'precio_base', 'activo', 'created_at', 'updated_at', 'id',
        ];
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
            'Catálogo público paginado.'
        );
    }

    public function show(CatalogoPublicoItem $catalogoPublicoItem): JsonResponse
    {
        return $this->success(
            new CatalogoPublicoItemResource($catalogoPublicoItem),
            'Ítem del catálogo público.'
        );
    }

    public function import(AdminCatalogoPublicoImportRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->import($request->file('file'));

            return $this->success(
                new CatalogoPublicoImportResultResource($result),
                'Importación del catálogo público completada.'
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            return $this->error('No fue posible importar el catálogo público.', [$e->getMessage()], 500);
        }
    }

    public function update(
        AdminCatalogoPublicoUpdateRequest $request,
        CatalogoPublicoItem $catalogoPublicoItem
    ): JsonResponse {
        $catalogoPublicoItem->update($request->validated());

        return $this->success(
            new CatalogoPublicoItemResource($catalogoPublicoItem->fresh()),
            'Ítem actualizado.'
        );
    }

    public function empresas(Request $request): JsonResponse
    {
        $filters = $request->only(CatalogoPublicoItem::getFilters());

        $empresas = CatalogoPublicoItem::query()
            ->filter($filters)
            ->select('empresa')
            ->selectRaw('COUNT(*) as total_productos')
            ->selectRaw('MAX(logo) as logo')
            ->selectRaw('MAX(mostrar_en_listado) as mostrar_en_listado')
            ->groupBy('empresa')
            ->orderBy('empresa')
            ->get()
            ->map(fn ($row) => [
                'empresa' => (string) $row->empresa,
                'logo' => $row->logo ? (string) $row->logo : null,
                'total_productos' => (int) $row->total_productos,
                'mostrar_en_listado' => (bool) $row->mostrar_en_listado,
            ])
            ->values()
            ->all();

        return $this->success($empresas, 'Empresas del catálogo público.');
    }

    public function facets(): JsonResponse
    {
        $valores = static function (string $columna): array {
            $vistos = [];
            $resultado = [];

            $rows = CatalogoPublicoItem::query()
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
                'empresas' => $valores('empresa'),
                'marcas' => $valores('marca'),
                'categorias' => $valores('categoria'),
            ],
            'Filtros del catálogo público.'
        );
    }

    public function updateEmpresa(AdminCatalogoPublicoUpdateEmpresaRequest $request): JsonResponse
    {
        $actual = trim((string) $request->input('empresa_actual'));
        $nueva = $request->filled('empresa')
            ? trim((string) $request->input('empresa'))
            : $actual;
        $hasLogo = $request->exists('logo');
        $hasMostrar = $request->exists('mostrar_en_listado');

        if ($nueva === '') {
            return $this->error('El nombre de la empresa es obligatorio.', null, 422);
        }

        try {
            $actualizados = (int) DB::transaction(function () use ($actual, $nueva, $hasLogo, $hasMostrar, $request) {
                $query = CatalogoPublicoItem::query()->where('empresa', $actual);
                $total = (clone $query)->count();
                if ($total === 0) {
                    throw new RuntimeException('No se encontró la empresa indicada.');
                }

                if ($nueva !== $actual) {
                    $codigos = (clone $query)->pluck('codigo');
                    $hayConflicto = CatalogoPublicoItem::query()
                        ->where('empresa', $nueva)
                        ->whereIn('codigo', $codigos)
                        ->exists();
                    if ($hayConflicto) {
                        throw new RuntimeException(
                            'No se puede renombrar: ya hay productos con el mismo código en la empresa destino.'
                        );
                    }
                }

                $payload = ['empresa' => $nueva];
                if ($hasLogo) {
                    $logo = $request->input('logo');
                    $payload['logo'] = is_string($logo) && trim($logo) !== '' ? trim($logo) : null;
                }
                if ($hasMostrar) {
                    $payload['mostrar_en_listado'] = $request->boolean('mostrar_en_listado');
                }

                $query->update($payload);

                return $total;
            });
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $fresh = CatalogoPublicoItem::query()->where('empresa', $nueva)->first();

        return $this->success(
            [
                'empresa' => $nueva,
                'logo' => $fresh?->logo,
                'mostrar_en_listado' => (bool) ($fresh?->mostrar_en_listado ?? true),
                'actualizados' => $actualizados,
            ],
            'Empresa actualizada.'
        );
    }

    public function destroy(CatalogoPublicoItem $catalogoPublicoItem): JsonResponse
    {
        $catalogoPublicoItem->delete();

        return $this->success(null, 'Producto eliminado del catálogo público.');
    }

    public function destroyEmpresa(Request $request): JsonResponse
    {
        $empresa = trim((string) $request->input('empresa', ''));
        if ($empresa === '') {
            return $this->error('Debe indicar la empresa a eliminar.', null, 422);
        }

        $eliminados = CatalogoPublicoItem::query()->where('empresa', $empresa)->delete();
        if ($eliminados === 0) {
            return $this->error('No se encontró la empresa indicada.', null, 404);
        }

        return $this->success(
            ['empresa' => $empresa, 'eliminados' => $eliminados],
            'Empresa y sus productos eliminados del catálogo público.'
        );
    }
}
