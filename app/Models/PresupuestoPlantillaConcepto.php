<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoPlantillaConcepto extends BaseModel
{
    public const TIPO_CONCEPTO = 'concepto';

    public const TIPO_PARRAFO = 'parrafo';

    protected $table = 'presupuesto_plantilla_conceptos';

    protected $fillable = [
        'presupuesto_plantilla_id',
        'numero',
        'tipo',
        'descripcion',
        'cantidad',
        'unidad',
        'precio_unitario',
        'imagen_path',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:2',
        'numero' => 'integer',
    ];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PresupuestoPlantilla::class, 'presupuesto_plantilla_id');
    }

    public function esParrafo(): bool
    {
        return ($this->tipo ?? self::TIPO_CONCEPTO) === self::TIPO_PARRAFO;
    }
}
