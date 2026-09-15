<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoConceptoComponente extends BaseModel
{
    protected $table = 'presupuesto_concepto_componentes';

    protected $fillable = [
        'presupuesto_concepto_id',
        'orden',
        'categoria',
        'catalogo_concepto_id',
        'clave_snapshot',
        'descripcion',
        'unidad',
        'cantidad',
        'precio_unitario',
        'importe',
    ];

    protected $casts = [
        'orden' => 'integer',
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'importe' => 'decimal:4',
    ];

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(PresupuestoConcepto::class, 'presupuesto_concepto_id');
    }

    public function catalogoConcepto(): BelongsTo
    {
        return $this->belongsTo(PresupuestoCatalogoConcepto::class, 'catalogo_concepto_id');
    }
}
