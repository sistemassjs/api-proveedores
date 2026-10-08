<?php

namespace App\Models;

use App\Enums\EstadoSolicitudCotizacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SolicitudCotizacion extends BaseModel
{
    use HasFactory;

    protected $table = 'solicitud_cotizaciones';

    public const ORIGEN_PUBLICO = 'publico_cotizador';

    public const ORIGEN_NEXPROV = 'nexprov_interna';

    public const ORIGEN_EMPRESA_TERCERO = 'empresa_tercero';

    protected $fillable = [
        'proveedor_id',
        'folio',
        'origen',
        'estatus',
        'cliente_nombre',
        'cliente_email',
        'cliente_telefono',
        'cliente_whatsapp',
        'cliente_notas',
        'solicitante_empresa',
        'observaciones_internas',
        'vigencia_hasta',
        'politicas',
        'total',
        'respondida_at',
        'cerrada_at',
        'procesada_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'vigencia_hasta' => 'date',
        'respondida_at' => 'datetime',
        'cerrada_at' => 'datetime',
        'procesada_at' => 'datetime',
    ];

    protected static $filters = [
        'estatus' => 'Estatus',
        'origen' => 'Origen',
        'search' => 'Search',
        'fecha_desde' => 'FechaDesde',
        'fecha_hasta' => 'FechaHasta',
    ];

    public static function eagerLodable(): array
    {
        return [
            'proveedor',
            'detalles',
            'respuestas',
            'archivos',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SolicitudCotizacionDetalle::class)->orderBy('orden');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(SolicitudCotizacionRespuesta::class)->orderByDesc('created_at');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(SolicitudCotizacionArchivo::class)->orderByDesc('created_at');
    }

    public function filterByEstatus($query, $value)
    {
        return $query->whereIn('estatus', explode(',', (string) $value));
    }

    public function filterByOrigen($query, $value)
    {
        return $query->whereIn('origen', explode(',', (string) $value));
    }

    public function filterBySearch($query, $value)
    {
        $term = trim((string) $value);

        return $query->where(function ($q) use ($term) {
            $q->where('folio', 'like', "%{$term}%")
                ->orWhere('cliente_nombre', 'like', "%{$term}%")
                ->orWhere('cliente_email', 'like', "%{$term}%")
                ->orWhere('cliente_telefono', 'like', "%{$term}%")
                ->orWhere('cliente_whatsapp', 'like', "%{$term}%")
                ->orWhere('solicitante_empresa', 'like', "%{$term}%");
        });
    }

    public function filterByFechaDesde($query, $value)
    {
        return $query->whereDate('created_at', '>=', $value);
    }

    public function filterByFechaHasta($query, $value)
    {
        return $query->whereDate('created_at', '<=', $value);
    }

    public function recalcularTotal(): void
    {
        $total = (float) $this->detalles()->sum('subtotal');
        $this->update(['total' => round($total, 2)]);
    }

    public static function generarFolio(int $proveedorId): string
    {
        return DB::transaction(function () use ($proveedorId) {
            $ultimo = static::query()
                ->where('proveedor_id', $proveedorId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('folio');

            $numero = 1;
            if ($ultimo && preg_match('/(\d+)$/', (string) $ultimo, $m)) {
                $numero = ((int) $m[1]) + 1;
            }

            return 'SC'.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
        });
    }

    public function marcarEnRevisionSiRecibida(): void
    {
        if ($this->estatus === EstadoSolicitudCotizacion::RECIBIDA->value) {
            $this->update(['estatus' => EstadoSolicitudCotizacion::EN_REVISION->value]);
        }
    }

    /**
     * @return list<string>
     */
    public function politicasLista(): array
    {
        $raw = trim((string) ($this->politicas ?? ''));
        if ($raw === '') {
            return [
                'Sujeto a disponibilidad y cambios de precio.',
                'Los precios están expresados en moneda nacional (M.N.).',
            ];
        }

        $lineas = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $lineas = array_values(array_filter(array_map('trim', $lineas), fn ($l) => $l !== ''));

        return $lineas !== [] ? $lineas : [
            'Sujeto a disponibilidad y cambios de precio.',
            'Los precios están expresados en moneda nacional (M.N.).',
        ];
    }
}
