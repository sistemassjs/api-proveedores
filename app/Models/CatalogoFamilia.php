<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoFamilia extends BaseModel
{
    protected $table = 'catalogo_familias';

    protected $fillable = [
        'nombre',
        'codigo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected static $filters = [
        'nombre' => 'Nombre',
        'codigo' => 'Codigo',
        'activo' => 'Activo',
        'search' => 'Search',
    ];

    public function filterByNombre($query, $value)
    {
        return $query->where('nombre', 'like', "%{$value}%");
    }

    public function filterByCodigo($query, $value)
    {
        return $query->where('codigo', 'like', "%{$value}%");
    }

    public function filterByActivo($query, $value)
    {
        return $query->where('activo', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    public function filterBySearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('nombre', 'like', "%{$value}%")
                ->orWhere('codigo', 'like', "%{$value}%");
        });
    }

    public function subfamilias(): HasMany
    {
        return $this->hasMany(CatalogoSubfamilia::class, 'familia_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'familia_id');
    }
}
