<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoAnexo extends BaseModel
{
    use HasFactory;

    protected $table = 'presupuesto_anexos';

    protected static $filters = [
        'presupuesto_id' => 'PresupuestoId',
        'titulo' => 'Titulo',
    ];

    protected $fillable = [
        'presupuesto_id',
        'titulo',
        'descripcion',
        'precio',
        'orden',
        'archivo_path',
        'archivo_width',
        'archivo_height',
        'archivo_aspect_ratio',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'orden' => 'integer',
        'archivo_width' => 'integer',
        'archivo_height' => 'integer',
        'archivo_aspect_ratio' => 'float',
    ];

    /**
     * @return string[]
     */
    public static function eagerLodable(): array
    {
        return [
            'presupuesto',
        ];
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function filterByPresupuestoId($query, $value)
    {
        return $query->where('presupuesto_id', $value);
    }

    public function filterByTitulo($query, string $value)
    {
        return $query->where('titulo', 'like', "%{$value}%");
    }
}
