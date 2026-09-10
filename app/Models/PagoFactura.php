<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PagoFactura extends BaseModel
{
    protected $connection = 'mysql5';

    protected $table = 'pago_facturas';

    public const METODO_PUE = 'PUE';

    public const METODO_PPD = 'PPD';

    protected $fillable = [
        'pago_spp_id',
        'folio_factura',
        'ruta_archivo_factura_pdf',
        'ruta_archivo_factura_xml',
        'metodo_pago',
        'monto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PagoSPP::class, 'pago_spp_id');
    }

    public function complementos(): HasMany
    {
        return $this->hasMany(PagoComplemento::class, 'pago_factura_id');
    }

    public function esPpd(): bool
    {
        return strtoupper((string) $this->metodo_pago) === self::METODO_PPD;
    }

    /**
     * Documentos faltantes de esta factura (y complemento si aplica PPD).
     *
     * @return list<string>
     */
    public function documentosFaltantes(): array
    {
        $faltantes = [];

        if (empty($this->ruta_archivo_factura_pdf)) {
            $faltantes[] = 'factura_pdf';
        }

        if (empty($this->ruta_archivo_factura_xml)) {
            $faltantes[] = 'factura_xml';
        }

        if (config('pagos.marcar_complemento_faltante_si_ppd', true) && $this->esPpd()) {
            if (! $this->tieneComplementoPdf()) {
                $faltantes[] = 'complemento_pago_pdf';
            }
            if (! $this->tieneComplementoXml()) {
                $faltantes[] = 'complemento_pago_xml';
            }
        }

        return array_values(array_unique($faltantes));
    }

    public function tieneComplementoPdf(): bool
    {
        $complementos = $this->relationLoaded('complementos')
            ? $this->complementos
            : $this->complementos()->get();

        return $complementos->contains(fn (PagoComplemento $c) => ! empty($c->ruta_archivo_pdf));
    }

    public function tieneComplementoXml(): bool
    {
        $complementos = $this->relationLoaded('complementos')
            ? $this->complementos
            : $this->complementos()->get();

        return $complementos->contains(fn (PagoComplemento $c) => ! empty($c->ruta_archivo_xml));
    }

    public function archivoPdfExiste(): bool
    {
        return $this->ruta_archivo_factura_pdf
            && Storage::disk('private')->exists($this->ruta_archivo_factura_pdf);
    }

    public function archivoXmlExiste(): bool
    {
        return $this->ruta_archivo_factura_xml
            && Storage::disk('private')->exists($this->ruta_archivo_factura_xml);
    }
}
