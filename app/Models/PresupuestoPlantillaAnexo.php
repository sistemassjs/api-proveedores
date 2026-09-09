<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoPlantillaAnexo extends BaseModel
{
    protected $table = 'presupuesto_plantilla_anexos';

    protected $fillable = [
        'presupuesto_plantilla_id',
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
     * @return array<int, string>
     */
    public static function eagerLodable(): array
    {
        return ['plantilla'];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PresupuestoPlantilla::class, 'presupuesto_plantilla_id');
    }
}
