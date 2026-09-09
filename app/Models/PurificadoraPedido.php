<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

class PurificadoraPedido extends BaseModel
{
    public const ESTADO_PENDIENTE = 0;

    public const ESTADO_EN_PROCESO = 1;

    public const ESTADO_COMPLETADO = 2;

    public const ESTADO_CANCELADO = 3;

    public const ESTADO_ELIMINADO = 4;

    public const PRECIO_UNITARIO_DEFAULT = 25.0;

    protected $table = 'purificadora_pedidos';

    protected $hidden = [];

    protected static $filters = [
        'search' => 'Search',
        'nombre' => 'Nombre',
        'celular' => 'Celular',
        'estado' => 'Estado',
        'creacion_desde' => 'CreacionDesde',
        'creacion_hasta' => 'CreacionHasta',
        'actualizacion_desde' => 'ActualizacionDesde',
        'actualizacion_hasta' => 'ActualizacionHasta',
        'estado_fecha_desde' => 'EstadoFechaDesde',
        'estado_fecha_hasta' => 'EstadoFechaHasta',
    ];

    protected $fillable = [
        'nombre',
        'celular',
        'correo',
        'calle',
        'numero',
        'colonia',
        'codigo_postal',
        'municipio',
        'cantidad_garrafones',
        'precio_unitario',
        'total',
        'notas',
        'estado',
        'pendiente_fecha',
        'en_proceso_fecha',
        'completado_fecha',
        'cancelado_fecha',
    ];

    protected $casts = [
        'estado' => 'integer',
        'cantidad_garrafones' => 'integer',
        'precio_unitario' => 'decimal:2',
        'total' => 'decimal:2',
        'pendiente_fecha' => 'datetime',
        'en_proceso_fecha' => 'datetime',
        'completado_fecha' => 'datetime',
        'cancelado_fecha' => 'datetime',
    ];

    /**
     * ISO 8601 con zona (compatible con new Date() en Ionic/Capacitor).
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $this->asDateTime($date)->toIso8601String();
    }

    /**
     * URL absoluta al endpoint GET para abrir desde WhatsApp.
     */
    public function urlWhatsappEnlace(): string
    {
        return URL::route(
            'purificadora-pedidos.marcar-pedido-proceso-whatsapp-enlace',
            ['id' => $this->id]
        );
    }

    public static function columnaFechaPorEstado(int $estado): ?string
    {
        return match ($estado) {
            self::ESTADO_PENDIENTE => 'pendiente_fecha',
            self::ESTADO_EN_PROCESO => 'en_proceso_fecha',
            self::ESTADO_COMPLETADO => 'completado_fecha',
            self::ESTADO_CANCELADO => 'cancelado_fecha',
            default => null,
        };
    }

    public function filterBySearch(Builder $query, string $value): Builder
    {
        return $query->where(function (Builder $q) use ($value) {
            $q->where('nombre', 'like', "%{$value}%")
                ->orWhere('celular', 'like', "%{$value}%");
        });
    }

    public function filterByNombre(Builder $query, string $value): Builder
    {
        return $query->where('nombre', 'like', "%{$value}%");
    }

    public function filterByCelular(Builder $query, string $value): Builder
    {
        return $query->where('celular', 'like', "%{$value}%");
    }

    public function filterByEstado(Builder $query, $value): Builder
    {
        $estados = array_map('intval', explode(',', (string) $value));

        return $query->whereIn('estado', $estados);
    }

    public function filterByCreacionDesde(Builder $query, string $value): Builder
    {
        return $query->whereDate('created_at', '>=', $value);
    }

    public function filterByCreacionHasta(Builder $query, string $value): Builder
    {
        return $query->whereDate('created_at', '<=', $value);
    }

    public function filterByActualizacionDesde(Builder $query, string $value): Builder
    {
        return $query->whereDate('updated_at', '>=', $value);
    }

    public function filterByActualizacionHasta(Builder $query, string $value): Builder
    {
        return $query->whereDate('updated_at', '<=', $value);
    }

    public function filterByEstadoFechaDesde(Builder $query, string $value): Builder
    {
        return $this->aplicarFiltroFechaEstado($query, '>=', $value);
    }

    public function filterByEstadoFechaHasta(Builder $query, string $value): Builder
    {
        return $this->aplicarFiltroFechaEstado($query, '<=', $value);
    }

    protected function aplicarFiltroFechaEstado(Builder $query, string $operador, string $value): Builder
    {
        $estadoFiltro = request()->query('estado');
        if ($estadoFiltro !== null && $estadoFiltro !== '') {
            $columna = self::columnaFechaPorEstado((int) $estadoFiltro);
            if ($columna !== null) {
                return $query->whereDate($columna, $operador, $value);
            }
        }

        return $query->where(function (Builder $q) use ($operador, $value) {
            foreach (
                [
                    'pendiente_fecha',
                    'en_proceso_fecha',
                    'completado_fecha',
                    'cancelado_fecha',
                ] as $columna
            ) {
                $q->orWhereDate($columna, $operador, $value);
            }
        });
    }
}
