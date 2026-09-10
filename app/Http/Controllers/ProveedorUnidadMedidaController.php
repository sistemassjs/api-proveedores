<?php

namespace App\Http\Controllers;

use App\Enums\EstadoGeneral;
use App\Http\Requests\UnidadMedidaStoreRequest;
use App\Http\Requests\UnidadMedidaUpdateRequest;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;

/**
 * Unidades de medida globales expuestas bajo la ruta del proveedor
 * (compatibilidad de rutas). El catálogo ya no es por proveedor.
 */
class ProveedorUnidadMedidaController extends Controller
{
    public function all(Request $request, Proveedor $proveedor)
    {
        $originalPaginator = UnidadMedida::query()
            ->where('estatus', EstadoGeneral::ACTIVO->value)
            ->orderBy('nombre')
            ->paginate(10000);

        return $this->paginated($originalPaginator);
    }

    public function index(Request $request, Proveedor $proveedor)
    {
        $filters = $request->only(UnidadMedida::getFilters());
        $originalPaginator = UnidadMedida::filter($filters)
            ->orderBy('nombre')
            ->paginate();

        return $this->paginated($originalPaginator);
    }

    public function store(UnidadMedidaStoreRequest $request, Proveedor $proveedor)
    {
        $unidad = UnidadMedida::create([
            'clave' => $request->clave,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'estatus' => EstadoGeneral::ACTIVO->value,
        ]);

        return $this->success($unidad, 201);
    }

    public function show(Request $request, Proveedor $proveedor, $unidadId)
    {
        $unidad = UnidadMedida::findOrFail($unidadId);

        return $this->success($unidad);
    }

    public function update(UnidadMedidaUpdateRequest $request, Proveedor $proveedor, $unidadId)
    {
        $unidad = UnidadMedida::findOrFail($unidadId);

        $unidad->update($request->only(['clave', 'nombre', 'descripcion', 'estatus']));

        return $this->success($unidad);
    }

    public function destroy(Request $request, Proveedor $proveedor, $unidadId)
    {
        $unidad = UnidadMedida::findOrFail($unidadId);
        $unidad->delete();

        return $this->success(null, 204);
    }
}
