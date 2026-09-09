<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoEstadoLog extends BaseModel
{
    protected $table = 'presupuesto_estado_logs';

    protected $fillable = [
        'presupuesto_id',
        'user_id',
        'fecha',
        'estado_anterior',
        'estado',
        'nota',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'presupuesto_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
