<?php

namespace App\Http\Controllers;

use App\Exceptions\Api\Crud\ResourceNotFoundException;
use App\Http\Requests\Producto\ProductoStoreRequest;
use App\Http\Requests\Producto\ProductoUpdateLogoRequest;
use App\Http\Requests\Producto\ProductoUpdateRequest;
use App\Http\Resources\ProveedorProductoResource;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProveedorProductoController extends Controller
{
    use ApiResponse;

    public function __construct() {}

    public function index(Request $request, Proveedor $proveedor)
    {
        // Filtros dinámicos
        $filters = $request->only(Producto::getFilters());

        $sortBy = $request->input('sort_by', 'nombre');
        $order = $request->input('order', 'asc');
        $perPage = $request->input('per_page', 10);

        $query = Producto::query()
            ->with(Producto::eagerLodable()) // carga relaciones para evitar N+1
            ->filter($filters)
            ->where('proveedor_id', $proveedor->id)
            ->orderBy($sortBy, $order);

        $paginator = $query->paginate($perPage);

        // Transformación con Resource
        $data = ProveedorProductoResource::collection($paginator)->resolve();

        // Devuelve paginado con colección transformada
        return $this->paginated($paginator->setCollection(collect($data)));
    }

    public function show(Request $request, Proveedor $proveedor, $productoId)
    {
        $producto = Producto::with(Producto::eagerLodable())->findOrFail($productoId);
        if ($producto->proveedor_id !== $proveedor->id) {
            throw new ResourceNotFoundException('Producto no relacionado al proveedor.');
        }

        return $this->success(new ProveedorProductoResource($producto));
    }

    public function store(ProductoStoreRequest $request, Proveedor $proveedor)
    {
        // ✅ Verificar que el producto pertenezca al proveedor
        // if ($producto->proveedor_id !== $proveedor->id) {
        //     return $this->error('El producto no pertenece a este proveedor.', 403);
        // }

        // ✅ Validar los datos del request,
        $data = $request->validated();
        $data['proveedor_id'] = $proveedor->id;

        $producto = Producto::create($data);

        return $this->success(new ProveedorProductoResource($producto));
    }

    public function update(ProductoUpdateRequest $request, Proveedor $proveedor, $productoId)
    {
        $producto = Producto::findOrFail($productoId);
        $producto->update($request->validated());

        return $this->success(new ProveedorProductoResource(($producto->fresh(Producto::eagerLodable()))));
    }

    public function updateLogo(ProductoUpdateLogoRequest $request, Proveedor $proveedor, $productoId)
    {
        $producto = Producto::findOrFail($productoId);
        if ($producto->imagen_principal && ! preg_match('/^https?:\/\//', $producto->imagen_principal)) {
            Storage::disk('public')->delete($producto->imagen_principal);
        }

        $file = $request->file('logo');
        $filename = "logo_producto_{$producto->id}_".time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('uploads', $filename, 'public');

        $producto->update(['imagen_principal' => $path]);

        return $this->success(new ProveedorProductoResource($producto->fresh(Producto::eagerLodable())));
    }

    public function destroy(Request $request, Proveedor $proveedor, $productoId)
    {
        $producto = Producto::findOrFail($productoId);
        // $producto->sucursales()->detach();
        $producto->delete();

        return $this->success(message: 'Producto eliminado correctamente.');
    }
}
