<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para gestionar los pagos realizados a proveedores.
 * Un pago puede aplicar a múltiples solicitudes de pago (relación muchos a muchos).
 * También soporta origen=directo (sin SPP ni autorización).
 */
class PagoSPP extends BaseModel
{
    use HasFactory;

    public const ORIGEN_SPP = 'spp';

    public const ORIGEN_DIRECTO = 'directo';

    protected $connection = 'mysql5';
    protected $table = 'pagos_spp';

    protected $fillable = [
        // folio consecuntivio por empresa
        'folio_pago_spp_consecutivo',

        // spp | directo
        'origen',

        // Comprobante y fechas
        'comprobante_pago',

        // cuenta bancaria de la empresa de construcción
        'cuenta_bancaria_empresa_construcc_id',
        'cuenta_destino_id',
        'cuenta_destino_terminacion',

        // datos basicos de pago
        'empresa_construcc_id',
        'proveedor_id',
        'usuario_registro_id',
        'usuario_registro_nombre',

        'monto_total', //               --> ingo_comprobante.montoTotal
        'fecha_pago', //                --> ingo_comprobante.fechaPago
        'referencia_pago',  //          --> ingo_comprobante.referencia
        'banco_destino',  //            --> ingo_comprobante.bancoDestino
        'titular_cuenta_destino',  //   --> ingo_comprobante.nombreBeneficiario
        'clave_rastreo',  //             --> ingo_comprobante.claveRastreo

        'fecha_registro', // fecha en que se registró el pago en el sistema
        'observaciones',

        // Datos bancarios del proveedor (cuenta destino)
        /**
         * @deprecated no se almacena la inforamción bancaria DEL PROVEEDOR 
         */
        'cuenta_destino',
        'tipo_cuenta_destino',
        'clabe_interbancaria_destino',
        'banco_pago',
        'cuenta_origen',
        'tipo_cuenta_origen',
        'clabe_interbancaria_origen',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'fecha_registro' => 'datetime',
        'monto_total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static $filters = [
        'search' => 'Search',
        'referencia_pago' => 'ReferenciaPago',
        'fecha_pago' => 'FechaPago',
        'fecha_pago_desde' => 'FechaPagoDesde',
        'fecha_pago_hasta' => 'FechaPagoHasta',
        'fecha_registro' => 'FechaRegistro',
        'fecha_registro_desde' => 'FechaRegistroDesde',
        'fecha_registro_hasta' => 'FechaRegistroHasta',
        'banco_pago' => 'BancoPago',
        'banco_destino' => 'BancoDestino',
        'empresa_construcc_id' => 'EmpresaConstruccId',
        'usuario_registro_id' => 'UsuarioRegistroId',
        'monto_min' => 'MontoMin',
        'monto_max' => 'MontoMax',
        'origen' => 'Origen',
    ];


    public static function eagerLodable(): array
    {
        return [
            'solicitudesPago',
            'empresaConstrucc',
            'facturas.complementos',
        ];
    }

    /** ----------------
     * Relaciones
     * ----------------- */

    /**
     * Relación muchos a muchos con SolicitudPago a través de la tabla pivot.
     * Un pago puede aplicar a múltiples solicitudes de pago.
     */
    public function solicitudesPago(): BelongsToMany
    {
        return $this->belongsToMany(
            SolicitudPago::class,
            'pago_solicitud_pago',
            'pago_spp_id',
            'solicitud_pago_id'
        )
            ->withPivot([
                'monto_aplicado',
                'estado_pago',
                'notas',
                'fecha_aplicacion'
            ])
            ->withTimestamps()
            ->using(PagoSolicitudPago::class);
    }

    /**
     * Relación con la empresa constructora que realizó el pago.
     */
    public function empresaConstrucc(): BelongsTo
    {
        return $this->belongsTo(EmpresaConstrucc::class, 'empresa_construcc_id');
    }

    /**
     * Relación con el proveedor al que se realizó el pago.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Facturas asociadas al pago (flujo directo u opcional en spp).
     */
    public function facturas(): HasMany
    {
        return $this->hasMany(PagoFactura::class, 'pago_spp_id');
    }

    /**
     * Complementos de pago asociados.
     */
    public function complementos(): HasMany
    {
        return $this->hasMany(PagoComplemento::class, 'pago_spp_id');
    }

    public function esDirecto(): bool
    {
        return ($this->origen ?? self::ORIGEN_SPP) === self::ORIGEN_DIRECTO;
    }

    /**
     * Documentos faltantes agregados del pago (comprobante + facturas).
     *
     * @return list<string>
     */
    public function documentosFaltantes(): array
    {
        $faltantes = [];

        if (empty($this->comprobante_pago)) {
            $faltantes[] = 'comprobante_pago';
        }

        $facturas = $this->relationLoaded('facturas')
            ? $this->facturas
            : $this->facturas()->with('complementos')->get();

        foreach ($facturas as $factura) {
            foreach ($factura->documentosFaltantes() as $doc) {
                $faltantes[] = $doc;
            }
        }

        return array_values(array_unique($faltantes));
    }

    /** ----------------
     * Scopes
     * ----------------- */

    /**
     * Scope para filtrar pagos por empresa constructora.
     */
    public function scopeDeEmpresa($query, $empresaId)
    {
        return $query->where('empresa_construcc_id', $empresaId);
    }

    /**
     * Scope para filtrar pagos por rango de fechas.
     */
    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_pago', [$fechaInicio, $fechaFin]);
    }

    /**
     * Scope para filtrar pagos por referencia.
     */
    public function scopePorReferencia($query, $referencia)
    {
        return $query->where('referencia_pago', 'like', "%{$referencia}%");
    }

    /** ----------------
     * Filtros
     * ----------------- */

    /**
     * Filtro de búsqueda global.
     * Busca en: referencia, banco, observaciones, titular cuenta.
     */
    public function filterBySearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('referencia_pago', 'like', "%$value%")
                ->orWhere('banco_pago', 'like', "%$value%")
                ->orWhere('banco_destino', 'like', "%$value%")
                ->orWhere('observaciones', 'like', "%$value%")
                ->orWhere('titular_cuenta_destino', 'like', "%$value%")
                ->orWhere('usuario_registro_nombre', 'like', "%$value%");
        });
    }

    public function filterByReferenciaPago($query, $value)
    {
        return $query->where('referencia_pago', 'like', "%$value%");
    }

    public function filterByFechaPago($query, $value)
    {
        return $query->whereDate('fecha_pago', $value);
    }

    public function filterByFechaPagoDesde($query, $value)
    {
        return $query->whereDate('fecha_pago', '>=', $value);
    }

    public function filterByFechaPagoHasta($query, $value)
    {
        return $query->whereDate('fecha_pago', '<=', $value);
    }

    public function filterByFechaRegistro($query, $value)
    {
        return $query->whereDate('fecha_registro', $value);
    }

    public function filterByFechaRegistroDesde($query, $value)
    {
        return $query->whereDate('fecha_registro', '>=', $value);
    }

    public function filterByFechaRegistroHasta($query, $value)
    {
        return $query->whereDate('fecha_registro', '<=', $value);
    }

    public function filterByBancoPago($query, $value)
    {
        return $query->where('banco_pago', 'like', "%$value%");
    }

    public function filterByBancoDestino($query, $value)
    {
        return $query->where('banco_destino', 'like', "%$value%");
    }

    public function filterByEmpresaConstruccId($query, $value)
    {
        $ids = array_filter(
            is_array($value) ? $value : explode(',', (string) $value),
            fn($id) => trim((string) $id) !== ''
        );

        if (empty($ids)) {
            return $query;
        }

        return $query->whereIn('empresa_construcc_id', $ids);
    }

    public function filterByUsuarioRegistroId($query, $value)
    {
        return $query->where('usuario_registro_id', $value);
    }

    public function filterByMontoMin($query, $value)
    {
        return $query->where('monto_total', '>=', $value);
    }

    public function filterByMontoMax($query, $value)
    {
        return $query->where('monto_total', '<=', $value);
    }

    public function filterByOrigen($query, $value)
    {
        return $query->where('origen', $value);
    }

    /** ----------------
     * Métodos de negocio
     * ----------------- */

    /**
     * Calcula el monto total aplicado de este pago a todas las SPP.
     */
    public function montoTotalAplicado()
    {
        return $this->solicitudesPago()->sum('pago_solicitud_pago.monto_aplicado');
    }

    /**
     * Calcula el monto disponible (no aplicado) de este pago.
     */
    public function montoDisponible()
    {
        return $this->monto_total - $this->montoTotalAplicado();
    }

    /**
     * Verifica si el pago está completamente aplicado.
     */
    public function estaCompletamenteAplicado(): bool
    {
        return $this->montoDisponible() <= 0;
    }

    /**
     * Aplica este pago a una solicitud de pago específica.
     * 
     * @param SolicitudPago $solicitudPago
     * @param float $montoAplicar
     * @param string $estadoPago
     * @param string|null $notas
     * @return void
     */
    public function aplicarASolicitudPago(SolicitudPago $solicitudPago, float $montoAplicar, string $estadoPago = 'aplicado', ?string $notas = null)
    {
        // Verificar que hay monto disponible
        if ($this->montoDisponible() < $montoAplicar) {
            throw new \Exception('El monto a aplicar excede el monto disponible del pago.');
        }

        // Adjuntar la solicitud de pago con los datos del pivot
        $this->solicitudesPago()->attach($solicitudPago->id, [
            'monto_aplicado' => $montoAplicar,
            'estado_pago' => $estadoPago,
            'notas' => $notas,
            'fecha_aplicacion' => now(),
        ]);

        // Actualizar los saldos de la solicitud de pago
        $solicitudPago->actualizarSaldos($montoAplicar);
    }

    /**
     * Obtiene las solicitudes de pago asociadas con información del pivot.
     */
    public function obtenerSolicitudesConDetalle()
    {
        return $this->solicitudesPago()
            ->withPivot(['monto_aplicado', 'estado_pago', 'notas', 'fecha_aplicacion'])
            ->get();
    }

    public function getComprobanteUrlAttribute(): ?string
    {
        // Solo si existe comprobante y la relación solicitudesPago tiene datos
        if (!$this->comprobante_pago || !$this->proveedor_id || $this->solicitudesPago->isEmpty()) {
            return null;
        }

        // Tomamos el primer ID de SPP (pivot)
        $sppId = $this->solicitudesPago->first()->id;

        return route('proveedor.spp.descargar-comprobante', [
            'proveedor' => $this->proveedor_id,
            'spp' => $sppId,
            'pago' => $this->id,
        ]);
    }
}
