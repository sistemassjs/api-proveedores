<?php

namespace App\Http\Controllers;

use App\Enums\EstadoGeneral;
use App\Http\Resources\MarcaResource;
use App\Models\Marca;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Http\Request;

class ProveedorMarcaController extends Controller
{
    public function all(Request $request, Proveedor $proveedor)
    {
        $data = Marca::where('proveedor_id', $proveedor->id)
            ->where('estatus', EstadoGeneral::ACTIVO->value)
            ->withCount('productos') // 👈 esto agrega la columna productos_count
            ->paginate(10000);

        $marcas = MarcaResource::collection($data)->resolve();

        return $this->paginated($data->setCollection(collect($marcas)));
    }

    public function index(Request $request, Proveedor $proveedor)
    {
        $filters = $request->only(Marca::getFilters());
        $sortBy = $request->input('sort_by', 'nombre');
        $order = $request->input('order', 'asc');
        $perPage = (int) $request->input('per_page', 15);

        $originalPaginator = Marca::filter($filters)
            ->where('proveedor_id', $proveedor->id)
            ->withCount('productos') // 👈 esto agrega la columna productos_count
            ->orderBy($sortBy, $order)
            ->paginate($perPage > 0 ? min($perPage, 100) : 15);

        $data = MarcaResource::collection($originalPaginator)->resolve();

        return $this->paginated($originalPaginator->setCollection(collect($data)));
    }

    /**
     * @decrepted
     * Lineas ya no forma parte del modelo de datos.
     */
    public function index_lineas_por_marca(Request $request, Proveedor $proveedor, $marcaId)
    {
        $marca = Marca::findOrFail($marcaId);

        if ($marca->proveedor_id !== $proveedor->id) {
            throw new RelationNotFoundException('La maraca no pertenece a este proveedor.', 403);
        }
        $filters = $request->only(['nombre', 'estatus']);
        $data = $marca->lineas()->filter($filters)->paginate();

        return $this->paginated($data);
    }

    public function store(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $marca = Marca::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'proveedor_id' => $proveedor->id,
        ]);

        return $this->success($marca, 201);
    }

    public function show(Request $request, Proveedor $proveedor, $marcaId)
    {
        $marca = Marca::findOrFail($marcaId);

        return $this->success($marca);
    }

    public function update(Request $request, Proveedor $proveedor, $marcaId)
    {
        $marca = Marca::findOrFail($marcaId);

        $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $marca->update($request->only(['nombre', 'descripcion']));

        return $this->success($marca);
    }

    public function destroy(Request $request, Proveedor $proveedor, $marcaId)
    {
        $marca = Marca::findOrFail($marcaId);
        $marca->delete();

        return $this->success(null, 204);
    }
}
