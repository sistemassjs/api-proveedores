<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\StoreCatalogoFamiliaRequest;
use App\Http\Requests\Catalogo\StoreCatalogoSubfamiliaRequest;
use App\Http\Requests\Catalogo\UpdateCatalogoFamiliaRequest;
use App\Http\Requests\Catalogo\UpdateCatalogoSubfamiliaRequest;
use App\Http\Resources\Catalogo\CatalogoFamiliaResource;
use App\Http\Resources\Catalogo\CatalogoSubfamiliaResource;
use App\Models\CatalogoFamilia;
use App\Models\CatalogoSubfamilia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCatalogoFamiliaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(CatalogoFamilia::getFilters());
        $sortBy = $request->input('sort_by', 'nombre');
        $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->input('per_page', 50);

        $allowed = ['nombre', 'codigo', 'activo', 'created_at', 'id'];
        if (! in_array($sortBy, $allowed, true)) {
            $sortBy = 'nombre';
        }

        $paginator = CatalogoFamilia::query()
            ->with('subfamilias')
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate(max(1, min($perPage, 200)));

        $data = CatalogoFamiliaResource::collection($paginator)->resolve();

        return $this->paginated(
            $paginator->setCollection(collect($data)),
            'Familias del catálogo OPUS.'
        );
    }

    public function all(Request $request): JsonResponse
    {
        $items = CatalogoFamilia::query()
            ->with(['subfamilias' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return $this->success(
            CatalogoFamiliaResource::collection($items),
            'Familias activas del catálogo OPUS.'
        );
    }

    public function store(StoreCatalogoFamiliaRequest $request): JsonResponse
    {
        $familia = CatalogoFamilia::create($request->validated());

        return $this->success(
            new CatalogoFamiliaResource($familia->load('subfamilias')),
            'Familia creada.',
            201
        );
    }

    public function show(CatalogoFamilia $familia): JsonResponse
    {
        return $this->success(
            new CatalogoFamiliaResource($familia->load('subfamilias')),
            'Familia OPUS.'
        );
    }

    public function update(UpdateCatalogoFamiliaRequest $request, CatalogoFamilia $familia): JsonResponse
    {
        $familia->update($request->validated());

        return $this->success(
            new CatalogoFamiliaResource($familia->fresh('subfamilias')),
            'Familia actualizada.'
        );
    }

    public function destroy(CatalogoFamilia $familia): JsonResponse
    {
        if ($familia->productos()->exists()) {
            return $this->error(
                'No se puede eliminar: hay productos asociados. Desactívela en su lugar.',
                null,
                422
            );
        }

        $familia->subfamilias()->delete();
        $familia->delete();

        return $this->success(null, 'Familia eliminada.', 200);
    }

    public function indexSubfamilias(CatalogoFamilia $familia): JsonResponse
    {
        $items = $familia->subfamilias()->orderBy('nombre')->get();

        return $this->success(
            CatalogoSubfamiliaResource::collection($items),
            'Subfamilias de la familia.'
        );
    }

    public function storeSubfamilia(
        StoreCatalogoSubfamiliaRequest $request,
        CatalogoFamilia $familia
    ): JsonResponse {
        $sub = $familia->subfamilias()->create($request->validated());

        return $this->success(
            new CatalogoSubfamiliaResource($sub->load('familia')),
            'Subfamilia creada.',
            201
        );
    }

    public function updateSubfamilia(
        UpdateCatalogoSubfamiliaRequest $request,
        CatalogoFamilia $familia,
        CatalogoSubfamilia $subfamilia
    ): JsonResponse {
        if ((int) $subfamilia->familia_id !== (int) $familia->id) {
            return $this->error('La subfamilia no pertenece a esta familia.', null, 404);
        }

        $subfamilia->update($request->validated());

        return $this->success(
            new CatalogoSubfamiliaResource($subfamilia->fresh('familia')),
            'Subfamilia actualizada.'
        );
    }

    public function destroySubfamilia(
        CatalogoFamilia $familia,
        CatalogoSubfamilia $subfamilia
    ): JsonResponse {
        if ((int) $subfamilia->familia_id !== (int) $familia->id) {
            return $this->error('La subfamilia no pertenece a esta familia.', null, 404);
        }

        if ($subfamilia->productos()->exists()) {
            return $this->error(
                'No se puede eliminar: hay productos asociados. Desactívela en su lugar.',
                null,
                422
            );
        }

        $subfamilia->delete();

        return $this->success(null, 'Subfamilia eliminada.');
    }
}
