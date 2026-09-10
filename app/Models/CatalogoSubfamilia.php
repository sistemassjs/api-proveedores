<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoSubfamilia extends BaseModel
{
    protected $table = 'catalogo_subfamilias';

    protected $fillable = [
        'familia_id',
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
        'familia_id' => 'FamiliaId',
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

    public function filterByFamiliaId($query, $value)
    {
        return $query->whereIn('familia_id', explode(',', (string) $value));
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

    public function familia(): BelongsTo
    {
        return $this->belongsTo(CatalogoFamilia::class, 'familia_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'subfamilia_id');
    }
}
