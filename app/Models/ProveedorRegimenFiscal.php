<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProveedorRegimenFiscal extends BaseModel
{
    use HasFactory;

    protected $table = 'proveedor_regimenes_fiscales';

    protected $fillable = [
        'proveedor_id',
        'clave',
        'nombre',
        'fecha_alta',
        'fecha_fin',
        'es_principal',
        'origen',
    ];

    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_fin' => 'date',
        'es_principal' => 'boolean',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
}
