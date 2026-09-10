<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalogo\CatalogoFamiliaResource;
use App\Models\CatalogoFamilia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lectura del catálogo global OPUS (todos los roles autenticados).
 */
class CatalogoFamiliaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(CatalogoFamilia::getFilters());
        if (! array_key_exists('activo', $filters)) {
            $filters['activo'] = true;
        }

        $items = CatalogoFamilia::query()
            ->with(['subfamilias' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->filter($filters)
            ->orderBy('nombre')
            ->get();

        return $this->success(
            CatalogoFamiliaResource::collection($items),
            'Catálogo OPUS (familias).'
        );
    }

    public function show(CatalogoFamilia $familia): JsonResponse
    {
        return $this->success(
            new CatalogoFamiliaResource(
                $familia->load(['subfamilias' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ),
            'Familia OPUS.'
        );
    }
}
