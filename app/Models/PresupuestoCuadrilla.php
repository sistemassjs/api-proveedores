<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresupuestoCuadrilla extends BaseModel
{
    public const NOMBRE_MAX = 150;

    public const INTEGRANTES_MAX = 500;

    public const CLAVE_MAX = 40;

    public const UNIDAD_MAX = 50;

    protected $table = 'presupuesto_cuadrillas';

    protected $fillable = [
        'presupuesto_id',
        'orden',
        'clave',
        'nombre',
        'integrantes',
        'unidad',
        'costo_unitario',
    ];

    protected $casts = [
        'orden' => 'integer',
        'costo_unitario' => 'decimal:4',
    ];

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function componentesLinea(): HasMany
    {
        return $this->hasMany(PresupuestoConceptoComponente::class, 'presupuesto_cuadrilla_id');
    }

    /** Texto de la fila de matriz: nombre y, si hay, los integrantes entre paréntesis. */
    public function descripcionParaMatriz(): string
    {
        $nombre = trim((string) $this->nombre);
        $integrantes = trim((string) ($this->integrantes ?? ''));
        if ($integrantes === '') {
            return $nombre;
        }

        return mb_substr($nombre.' ('.$integrantes.')', 0, 500);
    }
}
