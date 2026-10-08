<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCotizacionArchivo extends BaseModel
{
    use HasFactory;

    protected $table = 'solicitud_cotizacion_archivos';

    protected $fillable = [
        'proveedor_id',
        'solicitud_cotizacion_id',
        'respuesta_id',
        'creado_por_user_id',
        'tipo',
        'nombre',
        'pdf_path',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCotizacion::class, 'solicitud_cotizacion_id');
    }

    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(SolicitudCotizacionRespuesta::class, 'respuesta_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }
}
