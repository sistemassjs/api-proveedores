<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ConstruccSearchService
{
    /**
     * Búsqueda avanzada de proveedores con filtros múltiples
     */
    public function buscarProveedores(array $filtros): LengthAwarePaginator
    {
        $query = Proveedor::with(['categorias', 'marcas'])
            ->where('estatus', 'activo');

        // Búsqueda por texto en múltiples campos
        if (! empty($filtros['buscar'])) {
            $buscar = $filtros['buscar'];
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre_comercial', 'like', "%{$buscar}%")
                    ->orWhere('razon_social', 'like', "%{$buscar}%")
                    ->orWhere('rfc', 'like', "%{$buscar}%")
                    ->orWhere('descripcion_giro_empresa', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%");
            });
        }

        // Filtro por estado/municipio
        if (! empty($filtros['estado'])) {
            $query->where('estado', 'like', "%{$filtros['estado']}%");
        }

        if (! empty($filtros['municipio'])) {
            $query->where('municipio', 'like', "%{$filtros['municipio']}%");
        }

        // Filtro por tipo de empresa
        if (! empty($filtros['tipos_empresa_id'])) {
            $tiposEmpresa = $this->parseMultipleIds($filtros['tipos_empresa_id']);
            $query->whereIn('tipos_empresa_id', $tiposEmpresa);
        }

        // Filtro por tener productos en categorías específicas
        if (! empty($filtros['categoria_id'])) {
            $categorias = $this->parseMultipleIds($filtros['categoria_id']);
            $query->whereHas('productos', function ($q) use ($categorias) {
                $q->whereIn('categoria_id', $categorias);
            });
        }

        // Filtro por tener productos de marcas específicas
        if (! empty($filtros['marca_id'])) {
            $marcas = $this->parseMultipleIds($filtros['marca_id']);
            $query->whereHas('productos', function ($q) use ($marcas) {
                $q->whereIn('marca_id', $marcas);
            });
        }

        // Solo proveedores con productos activos
        if (! empty($filtros['con_productos'])) {
            $query->whereHas('productos', function ($q) {
                $q->where('activo', true);
            });
        }

        // Ordenamiento
        $ordenPor = $filtros['orden_por'] ?? 'nombre_comercial';
        $direccion = $filtros['direccion'] ?? 'asc';
        $query->orderBy($ordenPor, $direccion);

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    /**
     * Búsqueda avanzada de productos con filtros múltiples mejorada
     */
    public function buscarProductos(array $filtros): LengthAwarePaginator
    {
        $query = Producto::with(['proveedor', 'marca', 'categoria', 'subcategoria', 'unidad_medida'])
            ->where('activo', true);

        // Búsqueda por texto
        if (! empty($filtros['buscar'])) {
            $buscar = $filtros['buscar'];
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('descripcion', 'like', "%{$buscar}%")
                    ->orWhere('sku', 'like', "%{$buscar}%")
                    ->orWhere('codigo_interno', 'like', "%{$buscar}%");
            });
        }

        // Filtros múltiples por IDs
        $this->aplicarFiltrosMultiples($query, $filtros);

        // Rango de precios
        if (! empty($filtros['precio_min'])) {
            $query->where('precio_base', '>=', $filtros['precio_min']);
        }

        if (! empty($filtros['precio_max'])) {
            $query->where('precio_base', '<=', $filtros['precio_max']);
        }

        // Solo productos con stock
        if (! empty($filtros['con_stock'])) {
            $query->where('stock', '>', 0);
        }

        // Solo productos destacados
        if (! empty($filtros['destacado'])) {
            $query->where('destacado', true);
        }

        // Ordenamiento
        $ordenPor = $filtros['orden_por'] ?? 'nombre';
        $direccion = $filtros['direccion'] ?? 'asc';
        $query->orderBy($ordenPor, $direccion);

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    /**
     * Búsqueda de productos específica de un proveedor
     */
    public function buscarProductosProveedor(int $proveedorId, array $filtros): LengthAwarePaginator
    {
        // Agregar filtro de proveedor específico
        $filtros['proveedor_id'] = $proveedorId;

        return $this->buscarProductos($filtros);
    }

    /**
     * Obtiene productos de un proveedor con paginación simple
     */
    public function obtenerProductosProveedor(int $proveedorId, array $filtros): LengthAwarePaginator
    {
        $query = Producto::with(['marca', 'categoria', 'subcategoria', 'unidad_medida'])
            ->where('proveedor_id', $proveedorId)
            ->where('activo', true);

        // Aplicar filtros simples
        $this->aplicarFiltrosMultiples($query, $filtros);

        // Ordenamiento
        $ordenPor = $filtros['orden_por'] ?? 'nombre';
        $direccion = $filtros['direccion'] ?? 'asc';
        $query->orderBy($ordenPor, $direccion);

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    /**
     * Obtiene sugerencias de productos para autocompletado
     */
    public function obtenerSugerenciasProductos(string $termino, ?int $proveedorId = null, int $limite = 10): array
    {
        $query = Producto::where('activo', true)
            ->where(function ($q) use ($termino) {
                $q->where('nombre', 'like', $termino.'%')
                    ->orWhere('sku', 'like', $termino.'%')
                    ->orWhere('codigo_interno', 'like', $termino.'%');
            });

        if ($proveedorId) {
            $query->where('proveedor_id', $proveedorId);
        }

        return $query->limit($limite)
            ->get(['id', 'nombre', 'sku', 'codigo_interno', 'precio_base', 'proveedor_id'])
            ->map(function ($producto) {
                return [
                    'id' => $producto->id,
                    'texto' => "{$producto->nombre} ({$producto->sku})",
                    'sku' => $producto->sku,
                    'codigo' => $producto->codigo_interno,
                    'precio_base' => $producto->precio_base,
                    'proveedor_id' => $producto->proveedor_id,
                ];
            })->toArray();
    }

    /**
     * Obtiene estadísticas generales del módulo
     */
    public function obtenerEstadisticas(): array
    {
        return [
            'totales' => [
                'proveedores_activos' => Proveedor::where('estatus', 'activo')->count(),
                'productos_activos' => Producto::where('activo', true)->count(),
                'categorias_activas' => Categoria::where('activo', true)->count(),
                'marcas_activas' => Marca::where('activo', true)->count(),
            ],
            'productos_por_estado' => [
                'activos' => Producto::where('activo', true)->count(),
                'inactivos' => Producto::where('activo', false)->count(),
                'con_stock' => Producto::where('activo', true)->where('stock', '>', 0)->count(),
                'sin_stock' => Producto::where('activo', true)->where('stock', '=', 0)->count(),
                'destacados' => Producto::where('activo', true)->where('destacado', true)->count(),
            ],
            'precios' => [
                'promedio_general' => Producto::where('activo', true)->avg('precio_base'),
                'precio_minimo' => Producto::where('activo', true)->min('precio_base'),
                'precio_maximo' => Producto::where('activo', true)->max('precio_base'),
            ],
        ];
    }

    /**
     * Obtiene resumen específico de un proveedor
     */
    public function obtenerResumenProveedor(int $proveedorId): array
    {
        $proveedor = Proveedor::find($proveedorId);

        if (! $proveedor) {
            return [];
        }

        return [
            'proveedor' => [
                'id' => $proveedor->id,
                'nombre_comercial' => $proveedor->nombre_comercial,
                'razon_social' => $proveedor->razon_social,
            ],
            'productos' => [
                'total' => $proveedor->productos()->count(),
                'activos' => $proveedor->productos()->where('activo', true)->count(),
                'con_stock' => $proveedor->productos()->where('activo', true)->where('stock', '>', 0)->count(),
                'destacados' => $proveedor->productos()->where('activo', true)->where('destacado', true)->count(),
            ],
            'catalogos' => [
                'categorias' => $proveedor->categorias()->count(),
                'marcas' => $proveedor->marcas()->count(),
                'unidades' => \App\Models\UnidadMedida::query()->count(),
            ],
            'precios' => [
                'promedio' => $proveedor->productos()->where('activo', true)->avg('precio_base'),
                'minimo' => $proveedor->productos()->where('activo', true)->min('precio_base'),
                'maximo' => $proveedor->productos()->where('activo', true)->max('precio_base'),
            ],
        ];
    }

    /**
     * Aplica filtros múltiples a una consulta de productos
     */
    private function aplicarFiltrosMultiples(Builder $query, array $filtros): void
    {
        // Filtro por proveedores múltiples
        if (! empty($filtros['proveedor_id'])) {
            $proveedores = $this->parseMultipleIds($filtros['proveedor_id']);
            $query->whereIn('proveedor_id', $proveedores);
        }

        // Filtro por categorías múltiples
        if (! empty($filtros['categoria_id'])) {
            $categorias = $this->parseMultipleIds($filtros['categoria_id']);
            $query->whereIn('categoria_id', $categorias);
        }

        // Filtro por subcategorías múltiples
        if (! empty($filtros['subcategoria_id'])) {
            $subcategorias = $this->parseMultipleIds($filtros['subcategoria_id']);
            $query->whereIn('subcategoria_id', $subcategorias);
        }

        // Filtro por marcas múltiples
        if (! empty($filtros['marca_id'])) {
            $marcas = $this->parseMultipleIds($filtros['marca_id']);
            $query->whereIn('marca_id', $marcas);
        }
        // Filtro por unidades de medida múltiples
        if (! empty($filtros['unidad_medida_id'])) {
            $unidades = $this->parseMultipleIds($filtros['unidad_medida_id']);
            $query->whereIn('unidad_medida_id', $unidades);
        }
    }

    /**
     * Convierte una cadena de IDs separados por comas en array de enteros
     * Ejemplo: "1,2,3" -> [1, 2, 3]
     *
     * @param  string|int  $ids
     */
    private function parseMultipleIds($ids): array
    {
        if (is_array($ids)) {
            return array_filter(array_map('intval', $ids));
        }

        if (is_string($ids) && str_contains($ids, ',')) {
            return array_filter(array_map('intval', explode(',', $ids)));
        }

        return [(int) $ids];
    }
}
