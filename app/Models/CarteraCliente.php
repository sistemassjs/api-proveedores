<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarteraCliente extends BaseModel
{
    protected $table = 'cartera_clientes';

    protected static $filters = [
        'proveedor_id' => 'ProveedorId',
        'nombre' => 'Nombre',
        'empresa' => 'Empresa',
        'puesto' => 'Puesto',
        'search' => 'Search',
        'activo' => 'Activo',
    ];

    protected $fillable = [
        'proveedor_id',
        'nombre',
        'puesto',
        'empresa',
        'alias_empresa',
        'telefono',
        'correo',
        'logo_path',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Proveedor dueño de la cartera.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Presupuestos que usan este cliente como receptor.
     */
    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class, 'empresa_receptora_id');
    }

    /**
     * Scope por proveedor.
     */
    public function scopeByProveedor($query, int $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }

    /**
     * Filtro por proveedor.
     */
    public function filterByProveedorId($query, $value)
    {
        return $query->whereIn('proveedor_id', explode(',', (string) $value));
    }

    /**
     * Filtro por nombre.
     */
    public function filterByNombre($query, string $value)
    {
        return $query->where('nombre', 'like', "%{$value}%");
    }

    /**
     * Filtro por empresa.
     */
    public function filterByEmpresa($query, string $value)
    {
        return $query->where('empresa', 'like', "%{$value}%");
    }

    /**
     * Filtro por puesto.
     */
    public function filterByPuesto($query, string $value)
    {
        return $query->where('puesto', 'like', "%{$value}%");
    }

    /**
     * Búsqueda general en múltiples campos (nombre, empresa, teléfono, correo, ID).
     */
    public function filterBySearch($query, string $value)
    {
        $numericId = null;
        if (preg_match('/CLI-?(\d+)/i', $value, $m)) {
            $numericId = (int) $m[1];
        } elseif (ctype_digit($value)) {
            $numericId = (int) $value;
        }

        return $query->where(function ($q) use ($value, $numericId) {
            $q->where('nombre', 'like', "%{$value}%")
                ->orWhere('empresa', 'like', "%{$value}%")
                ->orWhere('alias_empresa', 'like', "%{$value}%")
                ->orWhere('telefono', 'like', "%{$value}%")
                ->orWhere('correo', 'like', "%{$value}%");
            if ($numericId !== null) {
                $q->orWhere('id', $numericId);
            }
        });
    }

    /**
     * Filtro por activo (true|false|1|0).
     */
    public function filterByActivo($query, $value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            return $query;
        }

        return $query->where('activo', $bool);
    }
}
