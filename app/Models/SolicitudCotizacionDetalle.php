<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCotizacionDetalle extends BaseModel
{
    use HasFactory;

    protected $table = 'solicitud_cotizacion_detalles';

    protected $fillable = [
        'solicitud_cotizacion_id',
        'producto_id',
        'nombre',
        'descripcion',
        'unidad',
        'codigo',
        'imagen',
        'precio_referencia',
        'precio_unitario',
        'cantidad',
        'subtotal',
        'es_sugerencia_empresa',
        'motivo_sugerencia',
        'observaciones_linea',
        'orden',
    ];

    protected $casts = [
        'precio_referencia' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'cantidad' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'es_sugerencia_empresa' => 'boolean',
        'orden' => 'integer',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCotizacion::class, 'solicitud_cotizacion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function recalcularSubtotal(): void
    {
        $this->subtotal = round(((float) $this->cantidad) * ((float) $this->precio_unitario), 2);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $detalle) {
            $detalle->recalcularSubtotal();
        });
    }
}
