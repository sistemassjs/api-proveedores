<?php

namespace App\Http\Controllers;

use App\Exceptions\Api\Crud\ResourceNotFoundException;
use App\Http\Resources\Tienda\TiendaAccesoRapidoResource;
use App\Http\Resources\Tienda\TiendaProductoDestacadoResource;
use App\Http\Resources\Tienda\TiendaProductoResource;
use App\Http\Resources\Tienda\TiendaProveedorResource;
use App\Models\AccesoRapido;
use App\Models\Producto;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TiendaController extends Controller
{
    private function productoRelations(): array
    {
        return array_values(array_unique(array_merge(
            Producto::eagerLodable(),
            ['proveedor']
        )));
    }

    private function applyProductoFilters($query, Request $request)
    {
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                    ->orWhere('descripcion', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%")
                    ->orWhere('codigo_interno', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('categoria') || $request->filled('categoria_id')) {
            $query->where('categoria_id', $request->get('categoria_id', $request->get('categoria')));
        }

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->get('proveedor_id'));
        }

        if ($request->filled('precio_min')) {
            $query->where('precio_base', '>=', $request->get('precio_min'));
        }

        if ($request->filled('precio_max')) {
            $query->where('precio_base', '<=', $request->get('precio_max'));
        }

        if ($request->boolean('disponible')) {
            $query->where('stock', '>', 0);
        }

        return $query;
    }

    public function accesosRapidos()
    {
        $accesos = AccesoRapido::where('activo', true)
            ->orderBy('orden')
            ->get();

        return $this->success(TiendaAccesoRapidoResource::collection($accesos));
    }

    public function proveedoresPrincipales(Request $request)
    {
        $base = Proveedor::query();

        $query = (clone $base)->where('principal', true);

        if (! $query->exists()) {
            $query = (clone $base)->where('is_proveedor_catalogo', true);
        }

        if (! $query->exists()) {
            $query = $base;
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre_comercial', 'LIKE', "%{$search}%")
                    ->orWhere('razon_social', 'LIKE', "%{$search}%");
            });
        }

        $proveedores = $query
            ->withCount(['productos as total_productos' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderByDesc('calificacion')
            ->orderBy('nombre_comercial')
            ->paginate($request->get('per_page', 15));

        $data = TiendaProveedorResource::collection($proveedores)->resolve();

        return $this->paginated($proveedores->setCollection(collect($data)));
    }

    public function productosDestacados(Request $request)
    {
        $limit = (int) $request->get('limit', 6);

        $query = Producto::where('activo', true)->where('destacado', true);
        $this->applyProductoFilters($query, $request);

        $productos = (clone $query)
            ->with($this->productoRelations())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        // Fallback temporal: si no hay marcados como destacados, usar recientes activos
        if ($productos->isEmpty()) {
            $fallback = Producto::where('activo', true);
            $this->applyProductoFilters($fallback, $request);
            $productos = $fallback
                ->with($this->productoRelations())
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        $productos->each(function ($producto) {
            $producto->motivo = $producto->destacado ? 'oferta' : 'nuevo';
            $producto->descuento = $producto->destacado ? 10 : null;
            $producto->mensaje = $producto->destacado ? '¡Oferta especial!' : 'Recién agregado';
        });

        return $this->success(TiendaProductoDestacadoResource::collection($productos));
    }

    public function productosMasPedidos(Request $request)
    {
        $limit = (int) $request->get('limit', 8);

        // Sin relación de pedidos en el esquema actual: aproximar por stock/actividad
        $query = Producto::where('activo', true);
        $this->applyProductoFilters($query, $request);

        $productos = $query
            ->with($this->productoRelations())
            ->orderByDesc('stock')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->success(TiendaProductoResource::collection($productos));
    }

    public function productosRecientes(Request $request)
    {
        $limit = (int) $request->get('limit', 8);

        $query = Producto::where('activo', true);
        $this->applyProductoFilters($query, $request);

        if ($request->filled('dias')) {
            $dias = (int) $request->get('dias', 30);
            $query->where('created_at', '>=', Carbon::now()->subDays($dias));
        }

        $productos = $query
            ->with($this->productoRelations())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->success(TiendaProductoResource::collection($productos));
    }

    public function show($id)
    {
        $producto = Producto::with($this->productoRelations())->find($id);
        if (! $producto) {
            throw new ResourceNotFoundException('Producto no encontrado.');
        }

        return $this->success(new TiendaProductoResource($producto));
    }
}
