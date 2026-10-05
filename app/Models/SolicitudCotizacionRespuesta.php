<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCotizacionRespuesta extends BaseModel
{
    use HasFactory;

    protected $table = 'solicitud_cotizacion_respuestas';

    protected $fillable = [
        'solicitud_cotizacion_id',
        'enviado_por_user_id',
        'canales',
        'mensaje',
        'pdf_path',
        'email_estado',
        'email_error',
        'whatsapp_modo',
        'whatsapp_estado',
        'whatsapp_link',
        'whatsapp_error',
        'payload_resumen',
    ];

    protected $casts = [
        'payload_resumen' => 'array',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCotizacion::class, 'solicitud_cotizacion_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_user_id');
    }
}
