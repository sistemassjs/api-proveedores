<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Modelo pivot para la relación muchos a muchos entre PagoSPP y SolicitudPago.
 * Representa la aplicación de un pago específico a una solicitud de pago específica.
 */
class PagoSolicitudPago extends Pivot
{
    protected $connection = 'mysql5';
    protected $table = 'pago_solicitud_pago';

    /**
     * Indica que el modelo pivot tiene timestamps.
     */
    public $timestamps = true;

    protected $fillable = [
        'pago_spp_id',
        'solicitud_pago_id',
        'monto_aplicado',
        'saldo_inicial',
        'estado_pago',
        'notas',
        'fecha_aplicacion',
        // Datos de autorización en caso de que el pago sea registrado por un usuario diferente al que autorizó la SPP
        'usuario_autorizo_id',
        'usuario_autorizo_nombre',
        'monto_autorizado',
        'motivo_autorizacion',
        'fecha_autorizacion',
    ];

    protected $casts = [
        'monto_aplicado' => 'decimal:2',
        'saldo_inicial' => 'decimal:2',
        'fecha_aplicacion' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        // Campos de autorización
        'fecha_autorizacion' => 'datetime',
        'monto_autorizado' => 'decimal:2',
    ];


    protected static $filters = [
        'search' => 'Search',
        'estado_pago' => 'EstadoPago',
        'fecha_aplicacion' => 'FechaAplicacion',
        'fecha_aplicacion_desde' => 'FechaAplicacionDesde',
        'fecha_aplicacion_hasta' => 'FechaAplicacionHasta',
    ];
    public static function eagerLodable(): array
    {
        return [
            'pagoSPP',
            'solicitudPago',
        ];
    }

    /**
     * Estados válidos para el pago en relación a una SPP.
     */
    public const ESTADO_APLICADO = 'aplicado';
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_PARCIAL = 'parcial';
    public const ESTADO_COMPLETADO = 'completado';

    /** ----------------
     * Relaciones
     * ----------------- */

    /**
     * Relación con el pago.
     */
    public function pagoSPP(): BelongsTo
    {
        return $this->belongsTo(PagoSPP::class, 'pago_spp_id');
    }

    /**
     * Relación con la solicitud de pago.
     */
    public function solicitudPago(): BelongsTo
    {
        return $this->belongsTo(SolicitudPago::class, 'solicitud_pago_id');
    }

    /** ----------------
     * Scopes
     * ----------------- */

    /**
     * Scope para filtrar por estado.
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado_pago', $estado);
    }

    /**
     * Scope para pagos aplicados.
     */
    public function scopeAplicados($query)
    {
        return $query->where('estado_pago', self::ESTADO_APLICADO);
    }

    /**
     * Scope para pagos pendientes.
     */
    public function scopePendientes($query)
    {
        return $query->where('estado_pago', self::ESTADO_PENDIENTE);
    }

    /**
     * Scope para pagos completados.
     */
    public function scopeCompletados($query)
    {
        return $query->where('estado_pago', self::ESTADO_COMPLETADO);
    }

    /** ----------------
     * Métodos auxiliares
     * ----------------- */

    /**
     * Verifica si el pago está aplicado.
     */
    public function estaAplicado(): bool
    {
        return $this->estado_pago === self::ESTADO_APLICADO;
    }

    /**
     * Verifica si el pago está pendiente.
     */
    public function estaPendiente(): bool
    {
        return $this->estado_pago === self::ESTADO_PENDIENTE;
    }

    /**
     * Verifica si el pago fue rechazado.
     */
    public function fueRechazado(): bool
    {
        return $this->estado_pago === self::ESTADO_RECHAZADO;
    }

    /**
     * Verifica si es un pago parcial.
     */
    public function esParcial(): bool
    {
        return $this->estado_pago === self::ESTADO_PARCIAL;
    }

    /**
     * Verifica si el pago está completado.
     */
    public function estaCompletado(): bool
    {
        return $this->estado_pago === self::ESTADO_COMPLETADO;
    }

    /**
     * Cambia el estado del pago.
     */
    public function cambiarEstado(string $nuevoEstado, ?string $notas = null)
    {
        $this->estado_pago = $nuevoEstado;
        if ($notas) {
            $this->notas = $notas;
        }
        $this->save();
    }

    /**
     * Obtiene todos los estados válidos.
     */
    public static function estadosValidos(): array
    {
        return [
            self::ESTADO_APLICADO,
            self::ESTADO_PENDIENTE,
            self::ESTADO_RECHAZADO,
            self::ESTADO_PARCIAL,
            self::ESTADO_COMPLETADO,
        ];
    }
}
