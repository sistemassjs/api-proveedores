<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoPlantillaAnexoPdf extends BaseModel
{
    protected $table = 'presupuesto_plantilla_anexo_pdf';

    protected $fillable = [
        'presupuesto_plantilla_id',
        'titulo',
        'orden',
        'archivo_path',
        'paginas',
        'mostrar_estampado',
        'mostrar_numero_pagina',
        'mostrar_datos_presupuesto',
    ];

    protected $casts = [
        'orden' => 'integer',
        'paginas' => 'integer',
        'mostrar_estampado' => 'boolean',
        'mostrar_numero_pagina' => 'boolean',
        'mostrar_datos_presupuesto' => 'boolean',
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
