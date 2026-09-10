<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo global de unidades de medida (no scoped por proveedor).
 *
 * @OA\Schema(
 *     schema="UnidadMedida",
 *     required={"nombre"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="nombre", type="string", example="Kilogramo"),
 *     @OA\Property(property="descripcion", type="string", example="Unidad de peso equivalente a mil gramos"),
 *     @OA\Property(property="estatus", type="string", example="activo"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class UnidadMedida extends BaseModel
{
    use HasFactory;

    protected $fillable = ['nombre', 'clave', 'descripcion', 'estatus'];

    protected static $filters = [
        'nombre' => 'Nombre',
        'clave' => 'Clave',
        'estatus' => 'Estatus',
        'search' => 'Search',
    ];

    public function filterByNombre($query, $value)
    {
        return $query->where('nombre', 'like', "%{$value}%");
    }

    public function filterByClave($query, $value)
    {
        return $query->where('clave', 'like', "%{$value}%");
    }

    public function filterByEstatus($query, $value)
    {
        return $query->where('estatus', $value);
    }

    public function filterBySearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('nombre', 'like', "%{$value}%")
                ->orWhere('clave', 'like', "%{$value}%")
                ->orWhere('descripcion', 'like', "%{$value}%");
        });
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'unidad_medida_id');
    }
}
