<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PagoComplemento extends BaseModel
{
    protected $connection = 'mysql5';

    protected $table = 'pago_complementos';

    protected $fillable = [
        'pago_factura_id',
        'pago_spp_id',
        'folio_complemento',
        'ruta_archivo_pdf',
        'ruta_archivo_xml',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(PagoFactura::class, 'pago_factura_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PagoSPP::class, 'pago_spp_id');
    }

    public function archivoPdfExiste(): bool
    {
        return $this->ruta_archivo_pdf
            && Storage::disk('private')->exists($this->ruta_archivo_pdf);
    }

    public function archivoXmlExiste(): bool
    {
        return $this->ruta_archivo_xml
            && Storage::disk('private')->exists($this->ruta_archivo_xml);
    }
}
