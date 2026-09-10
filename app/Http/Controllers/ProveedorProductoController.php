<?php

namespace App\Http\Controllers;

use App\Exceptions\Api\Crud\ResourceNotFoundException;
use App\Http\Requests\Producto\ProductoBulkStoreRequest;
use App\Http\Requests\Producto\ProductoStoreRequest;
use App\Http\Requests\Producto\ProductoUpdateLogoRequest;
use App\Http\Requests\Producto\ProductoUpdateRequest;
use App\Http\Resources\ProveedorProductoResource;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\Catalogo\ProveedorProductoBulkService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProveedorProductoController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Proveedor $proveedor)
    {
        $filters = $request->only(Producto::getFilters());

        $sortBy = $request->input('sort_by', 'nombre');
        $order = $request->input('order', 'asc');
        $perPage = $request->input('per_page', 10);

        $query = Producto::query()
            ->with(Producto::eagerLodable())
            ->filter($filters)
            ->where('proveedor_id', $proveedor->id)
            ->orderBy($sortBy, $order);

        $paginator = $query->paginate($perPage);

        $data = ProveedorProductoResource::collection($paginator)->resolve();

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
        $data = $request->validated();
        $data['proveedor_id'] = $proveedor->id;
        $especificaciones = $data['especificaciones'] ?? null;
        unset($data['especificaciones']);

        $producto = DB::transaction(function () use ($data, $especificaciones) {
            $producto = Producto::create($data);
            $this->syncEspecificaciones($producto, $especificaciones);

            return $producto->fresh(Producto::eagerLodable());
        });

        return $this->success(new ProveedorProductoResource($producto), 'Producto creado.', 201);
    }

    public function bulkStore(
        ProductoBulkStoreRequest $request,
        Proveedor $proveedor,
        ProveedorProductoBulkService $bulkService
    ) {
        $validated = $request->validated();
        $result = $bulkService->import(
            $proveedor,
            $validated['productos'],
            $validated['metadata']['importId'] ?? null
        );

        $message = empty($result['errors'])
            ? 'Importación masiva completada.'
            : 'Importación masiva completada con advertencias.';

        return $this->success($result, $message, empty($result['errors']) ? 200 : 207);
    }

    public function update(ProductoUpdateRequest $request, Proveedor $proveedor, $productoId)
    {
        $producto = Producto::findOrFail($productoId);
        if ((int) $producto->proveedor_id !== (int) $proveedor->id) {
            throw new ResourceNotFoundException('Producto no relacionado al proveedor.');
        }

        $data = $request->validated();
        $hasEspecs = array_key_exists('especificaciones', $data);
        $especificaciones = $data['especificaciones'] ?? null;
        unset($data['especificaciones']);

        $producto = DB::transaction(function () use ($producto, $data, $hasEspecs, $especificaciones) {
            $producto->update($data);
            if ($hasEspecs) {
                $this->syncEspecificaciones($producto, $especificaciones ?? []);
            }

            return $producto->fresh(Producto::eagerLodable());
        });

        return $this->success(new ProveedorProductoResource($producto));
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
        $producto->delete();

        return $this->success(message: 'Producto eliminado correctamente.');
    }

    private function syncEspecificaciones(Producto $producto, ?array $especificaciones): void
    {
        if ($especificaciones === null) {
            return;
        }

        $producto->especificaciones()->delete();

        foreach (array_values($especificaciones) as $i => $item) {
            $atributo = $item['atributo'] ?? $item['clave'] ?? null;
            if ($atributo === null || $atributo === '') {
                continue;
            }

            $producto->especificaciones()->create([
                'atributo' => $atributo,
                'valor' => $item['valor'] ?? '',
                'unidad' => $item['unidad'] ?? null,
                'orden' => $item['orden'] ?? $i,
            ]);
        }
    }
}
