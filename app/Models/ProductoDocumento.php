<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoDocumento extends BaseModel
{
    protected $table = 'producto_documentos';

    protected $fillable = [
        'producto_id',
        'tipo',
        'nombre',
        'url',
        'mime',
        'orden',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
