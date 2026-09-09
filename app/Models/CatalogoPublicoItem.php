<?php

namespace App\Models;

class CatalogoPublicoItem extends BaseModel
{
    protected $table = 'catalogo_publico_items';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'marca',
        'categoria',
        'subcategoria',
        'unidad',
        'modelo',
        'empresa',
        'logo',
        'imagen',
        'precio_base',
        'precio_mayoreo',
        'precio_menudeo',
        'propiedades',
        'activo',
        'mostrar_en_listado',
    ];

    protected static $filters = [
        'search' => 'Search',
        'empresa' => 'Empresa',
        'categoria' => 'Categoria',
        'marca' => 'Marca',
        'activo' => 'Activo',
        'mostrar_en_listado' => 'MostrarEnListado',
        'codigo' => 'Codigo',
    ];

    protected $casts = [
        'precio_base' => 'float',
        'precio_mayoreo' => 'float',
        'precio_menudeo' => 'float',
        'propiedades' => 'array',
        'activo' => 'boolean',
        'mostrar_en_listado' => 'boolean',
    ];

    public static function eagerLodable(): array
    {
        return [];
    }

    public function filterBySearch($query, $value)
    {
        $term = trim((string) $value);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('nombre', 'like', "%{$term}%")
                ->orWhere('codigo', 'like', "%{$term}%")
                ->orWhere('descripcion', 'like', "%{$term}%")
                ->orWhere('empresa', 'like', "%{$term}%")
                ->orWhere('marca', 'like', "%{$term}%");
        });
    }

    public function filterByEmpresa($query, $value)
    {
        return $query->where('empresa', trim((string) $value));
    }

    public function filterByCategoria($query, $value)
    {
        return $query->where('categoria', 'like', '%'.trim((string) $value).'%');
    }

    public function filterByMarca($query, $value)
    {
        return $query->where('marca', 'like', '%'.trim((string) $value).'%');
    }

    public function filterByActivo($query, $value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($bool === null) {
            return $query;
        }

        return $query->where('activo', $bool);
    }

    public function filterByMostrarEnListado($query, $value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($bool === null) {
            return $query;
        }

        return $query->where('mostrar_en_listado', $bool);
    }

    public function filterByCodigo($query, $value)
    {
        return $query->where('codigo', 'like', '%'.trim((string) $value).'%');
    }
}
