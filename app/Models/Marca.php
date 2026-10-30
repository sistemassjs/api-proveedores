<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Marca extends BaseModel
{
    use HasFactory;

    protected static $filters = [
        'nombre' => 'nombre',
        'estatus' => 'estatus',
    ];

    protected $fillable = ['nombre', 'descripcion', 'logo', 'proveedor_id', 'activo'];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    // public function lineas()
    // {
    //     return $this->hasMany(Linea::class);
    // }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function scopeDelProveedor($query, $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }

    /**
     * Usado por Filterable / BaseModel::scopeFilter (busca filterByX, no scopeFilterByX).
     */
    public function filterByNombre($query, $value)
    {
        return $query->where('nombre', 'like', "%{$value}%");
    }

    public function filterByEstatus($query, $value)
    {
        $normalized = $this->normalizeEstatusFilter($value);

        return $query->where('estatus', $normalized);
    }

    public function scopeFilterByNombre($query, $value)
    {
        return $this->filterByNombre($query, $value);
    }

    public function scopeFilterByEstatus($query, $value)
    {
        return $this->filterByEstatus($query, $value);
    }

    private function normalizeEstatusFilter($value): string
    {
        $key = strtolower(trim((string) $value));

        return match ($key) {
            'activo', 'activa', 'active' => 'activo',
            'inactivo', 'inactiva', 'inactive' => 'inactivo',
            default => $key,
        };
    }
}
