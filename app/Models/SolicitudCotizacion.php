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
        'observaciones_internas',
        'total',
        'respondida_at',
        'cerrada_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'respondida_at' => 'datetime',
        'cerrada_at' => 'datetime',
    ];

    protected static $filters = [
        'estatus' => 'Estatus',
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

    public function filterByEstatus($query, $value)
    {
        return $query->whereIn('estatus', explode(',', (string) $value));
    }

    public function filterBySearch($query, $value)
    {
        $term = trim((string) $value);

        return $query->where(function ($q) use ($term) {
            $q->where('folio', 'like', "%{$term}%")
                ->orWhere('cliente_nombre', 'like', "%{$term}%")
                ->orWhere('cliente_email', 'like', "%{$term}%")
                ->orWhere('cliente_telefono', 'like', "%{$term}%")
                ->orWhere('cliente_whatsapp', 'like', "%{$term}%");
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
}
