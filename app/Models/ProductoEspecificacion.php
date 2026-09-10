<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductoEspecificacion extends BaseModel
{
    use HasFactory;

    protected $table = 'producto_especificaciones';

    protected $fillable = [
        'producto_id',
        'atributo',
        'valor',
        'unidad',
        'orden',
    ];

    /**
     * Alias semántico del documento (Clave = Valor).
     */
    public function getClaveAttribute(): string
    {
        return (string) $this->atributo;
    }

    public function setClaveAttribute($value): void
    {
        $this->attributes['atributo'] = $value;
    }

    /**
     * Relación inversa hacia Producto.
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
