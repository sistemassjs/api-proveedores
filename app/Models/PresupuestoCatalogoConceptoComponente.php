<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoCatalogoConceptoComponente extends BaseModel
{
    protected $table = 'presupuesto_catalogo_concepto_componentes';

    protected $fillable = [
        'catalogo_concepto_id',
        'orden',
        'categoria',
        'catalogo_concepto_componente_id',
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

    public function padre(): BelongsTo
    {
        return $this->belongsTo(PresupuestoCatalogoConcepto::class, 'catalogo_concepto_id');
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(PresupuestoCatalogoConcepto::class, 'catalogo_concepto_componente_id');
    }
}
