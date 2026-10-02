<?php

namespace App\Http\Controllers;

use App\Enums\EstadoGeneral;
use App\Http\Requests\Categoria\CategoriaStoreRequest;
use App\Http\Requests\Categoria\CategoriaUpdateRequest;
use App\Http\Resources\CategoriaAcordeonResource;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Http\Request;

class ProveedorCategoriaController extends Controller
{
    public function all(Request $request, Proveedor $proveedor)
    {
        // Obtener todas las categorías activas para el proveedor con las subcategorías (hijas)
        $data = Categoria::with(['children'])
            ->whereNull('parent_id')
            ->where('proveedor_id', $proveedor->id)
            ->where('estatus', EstadoGeneral::ACTIVO->value)
            ->paginate(10000);

        $categorias = CategoriaResource::collection($data)->resolve();

        return $this->paginated($data->setCollection(collect($categorias)));
    }

    public function index(Request $request, Proveedor $proveedor)
    {
        $filters = $request->only(Categoria::getFilters());

        $sortBy = $request->input('sort_by', 'nombre');
        $order = $request->input('order', 'asc');
        $perPage = $request->input('per_page', 10);

        $query = Categoria::query()
            ->with(['children'])
            ->whereNull('parent_id')
            ->filter($filters)
            ->where('proveedor_id', $proveedor->id)
            ->orderBy($sortBy, $order);

        $paginator = $query->paginate($perPage);

        $data = CategoriaResource::collection(($paginator))->resolve();

        return $this->paginated($paginator->setCollection(collect($data)));
    }

    public function index_sub_categorias(Request $request, Proveedor $proveedor, $categoriaId)
    {
        $categoriaPadre = Categoria::findOrFail($categoriaId);

        if ($categoriaPadre->proveedor_id !== $proveedor->id) {
            // return $this->error('El producto no pertenece a este proveedor.', 403);
            throw new RelationNotFoundException('El producto no pertenece a este proveedor.', 403);
        }

        $filters = $request->only(['nombre', 'estatus']);
        $data = $categoriaPadre->children()->filter($filters)->paginate();

        return $this->paginated($data);
    }

    public function store(CategoriaStoreRequest $request, Proveedor $proveedor)
    {
        $categoriaPadreId = $request->input('categoria_padre_id');
        $nivel = 0;

        if ($categoriaPadreId) {
            $padre = Categoria::findOrFail($categoriaPadreId);
            $nivel = $padre->nivel + 1;

            if ($nivel > 2) {
                return $this->error('Solo se permiten hasta 2 niveles de subcategorías.', 422);
            }
        }

        $categoria = Categoria::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'parent_id' => $categoriaPadreId,
            'proveedor_id' => $proveedor->id,
            'nivel' => $nivel,
        ]);

        return $this->success($categoria, 201);
    }

    public function show(Request $request, Proveedor $proveedor, $categoriaId)
    {
        $categoria = Categoria::findOrFail($categoriaId);

        return $this->success($categoria);
    }

    public function update(CategoriaUpdateRequest $request, Proveedor $proveedor, $categoriaId)
    {
        $categoria = Categoria::where('proveedor_id', $proveedor->id)
            ->findOrFail($categoriaId);

        $categoriaPadreId = $request->has('categoria_padre_id')
            ? $request->input('categoria_padre_id')
            : $categoria->parent_id;
        $nivel = 0;

        if ($categoriaPadreId) {
            $padre = Categoria::where('proveedor_id', $proveedor->id)
                ->findOrFail($categoriaPadreId);
            $nivel = $padre->nivel + 1;

            if ($nivel > 2) {
                return $this->error('Solo se permiten hasta 2 niveles de subcategorías.', 422);
            }
        }

        $categoria->update([
            'nombre' => $request->input('nombre', $categoria->nombre),
            'descripcion' => $request->input('descripcion', $categoria->descripcion),
            'parent_id' => $categoriaPadreId,
            'nivel' => $nivel,
        ]);

        return $this->success($categoria->fresh());
    }

    public function destroy(Request $request, Proveedor $proveedor, $categoriaId)
    {
        // Buscar la categoría
        $categoria = Categoria::findOrFail($categoriaId);

        // Actualizar el estado en lugar de eliminar
        $categoria->estatus = EstadoGeneral::INACTIVO->value;
        $categoria->save();

        return $this->success(null, 204);
    }

    public function categoriasConSubcatCountProductos(Request $request, Proveedor $proveedor)
    {
        $categorias = Categoria::with([
            'children' => function ($query) use ($proveedor) {
                $query->withCount([
                    'productosSubcategoria as productos_count' => function ($q) use ($proveedor) {
                        $q->where('productos.proveedor_id', $proveedor->id);
                    },
                ]);
            },
        ])
            ->withCount([
                'productos as productos_count' => function ($q) use ($proveedor) {
                    $q->where('productos.proveedor_id', $proveedor->id);
                },
            ])
            ->whereNull('parent_id')
            ->where('proveedor_id', $proveedor->id)
            ->get();

        return $this->success(
            CategoriaAcordeonResource::collection($categorias),
            'Listado de categorías con subcategorías con contador de productos.'
        );
    }
}
