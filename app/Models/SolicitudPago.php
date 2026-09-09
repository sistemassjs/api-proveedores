<?php

namespace App\Models;

use App\Enums\EstadoCuentaBancaria;
use App\Enums\EstadoSolicitud;
use App\Enums\EstadoSP;
use App\Events\SpChangeEstadoGeneralEvent;
use App\Traits\Filterable;
use App\Traits\MarksAsNotified;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\PagoSolicitudPago;
use Illuminate\Support\Facades\Log;

class SolicitudPago extends BaseModel
{
    use Filterable, HasFactory, MarksAsNotified;

    // protected $dispatchesEvents = [
    //     'updated' => SpChangeEstadoGeneralEvent::class,
    // ];

    protected $connection = 'mysql5';
    protected $table = 'solicitudes_pago';

    protected $fillable = [
        'numero_folio_solicitud', // CONSECUTIVO POR PROVEEDOR
        'folio_sp_consecutivo', // MANEJO INTERNO cONSTRUCC
        'descripcion_concepto',
        'ruta_archivo_factura_xml',
        'ruta_archivo_factura_pdf',
        'ruta_archivo_cotizacion',
        'estado_solicitud',
        'ruta_archivo_comprobante_pago',
        'proveedor_id',
        // Datos del usuario que registra la SP en  
        'empresa_construcc_id',
        'usuario_id',
        'usuario_nombre',
        'cuenta_bancaria_empresa_construcc_id',
        // 
        'cotizacion_id',
        'sucursal_id',

        // 
        // 'fecha_confirmacion_pago',
        // 'fecha_registro_pendiente',
        // 'fecha_inicio_procesamiento',
        // 'fecha_con_comprobante',
        // 'fecha_rechazado',
        // 'fecha_aprobado',

        //
        'motivo_rechazo',
        'monto_total',

        // DEPRECATED: Estos campos se mantienen por compatibilidad pero están deprecados
        // Los valores reales se calculan desde la tabla pivot PagoSolicitudPago
        // Usar métodos: calcularSaldoRestante() y calcularMontoAbonado()
        'monto_abonado',      // @deprecated Usar calcularMontoAbonado()
        'saldo_pendiente',    // @deprecated Usar calcularSaldoRestante()
        'pago_completo',      // @deprecated Usar estaPagadaCompletamente()

        'verificada',
        'tipo',
        // FIXME: tipo_id se migrara a una tabla y este almacenara el id asignado al tipo de SP
        // valores actuales que se aklmacenana en tipo como string: 1: DIRECTA, 2: REQUISICION
        'tipo_id',
        'obra_id',
        'observaciones',
        'notas',
        'utilizara',
        'equipo',
        'equipo_id',
        'notas_abono',
        'fecha_rechazo',
        'fecha_pago',
        'notification_id',

        // Nuevos campos
        'dg',
        'dg_fecha',
        'dt',
        'dt_fecha',
        'pc',
        'pc_fecha',
        'si',
        'si_fecha',
        'da',
        'da_fecha',
        'ro',
        'ro_fecha',

        // Campos de tracking para OC
        'referencia_oc',
        'origen_oc',
        'monto_oc_original',

        // Tracking de carga factura
        'fecha_subida_factura_xml',
        'fecha_subida_factura_pdf',
        'usuario_construcc_subio_factura_id',
        'usuario_construcc_subio_factura_rol',

        // 
        'visto_rechazada',

        // 
        'folio_factura',
        'datos_factura_xml',
        'tiene_factura', // define a las sp sin factura. en cualquier fase de la SP se puede subir la factura
        'item_visto',

        // CAMPOS 
        'nombre_beneficiario_pago',
        'clave_rastreo_pago',
        'banco_pago',
        'fecha_comprobante_pago',


        // Campos para autorización parcial
        'monto_autorizado',
        'usuario_autorizo_parcial_id',
        'usuario_autorizo_parcial_nombre',
        'motivo_autorizacion_parcial',
        'fecha_autorizacion_parcial',

        // Especificacion de facturacion
        'datos_facturacion_id', // Si USO, MP, FP estan en null datos_facturacion_id contiene todos los daots de facturacion
        'razon_social_id',
        'uso',  // uso_cfdi: puedene ser nullos
        'mp',   // metodo_pago: puedene ser nullos 
        'fp',   // forma_pago: puedene ser nullos 
        'rf', // regimen fiscal

        //migracion add usario que genera la spp.
        'usuario_creador_id',


        // CAMPOS PARA VALIDACION DE MONTO PARCIAL
        'ro_monto',
        'ro_usuario_id',
        'ro_validacion_fecha',
        'ro_motivo',

    ];

    protected static $filters = [
        'search' => 'Search',
        'numero_folio_solicitud' => 'NumeroFolioSolicitud',
        'folio_factura' => 'FolioFactura',
        'descripcion_concepto' => 'DescripcionConcepto',
        'estado_solicitud' => 'EstadoSolicitud',
        'proveedor_id' => 'ProveedorId',
        'empresa_construcc_id' => 'EmpresaConstruccId',
        'usuario_id' => 'UsuarioId',
        'usuario_nombre' => 'UsuarioNombre',
        'cotizacion_id' => 'CotizacionId',

        'fecha_registro_pendiente' => 'FechaRegistroPendiente',
        'fecha_registro_pendiente_desde' => 'FechaRegistroPendienteDesde',
        'fecha_registro_pendiente_hasta' => 'FechaRegistroPendienteHasta',
        'fecha_inicio_procesamiento' => 'FechaInicioProcesamiento',
        'fecha_inicio_procesamiento_desde' => 'FechaInicioProcesamientoDesde',
        'fecha_inicio_procesamiento_hasta' => 'FechaInicioProcesamientoHasta',
        'fecha_confirmacion_pago' => 'FechaConfirmacionPago',
        'fecha_confirmacion_pago_desde' => 'FechaConfirmacionPagoDesde',
        'fecha_confirmacion_pago_hasta' => 'FechaConfirmacionPagoHasta',
        'fecha_con_comprobante' => 'FechaConComprobante',
        'fecha_con_comprobante_desde' => 'FechaConComprobanteDesde',
        'fecha_con_comprobante_hasta' => 'FechaConComprobanteHasta',
        'fecha_aprobado' => 'FechaAprobado',
        'fecha_aprobado_desde' => 'FechaAprobadoDesde',
        'fecha_aprobado_hasta' => 'FechaAprobadoHasta',
        'fecha_rechazo' => 'FechaRechazo',
        'fecha_rechazo_desde' => 'FechaRechazoDesde',
        'fecha_rechazo_hasta' => 'FechaRechazo1Hasta',
        'fecha_pago' => 'FechaPago',
        'fecha_pago_desde' => 'FechaPagoDesde',
        'fecha_pago_hasta' => 'FechaPagoHasta',

        // Filtros para los nuevos campos
        'dg' => 'Dg',
        'dt' => 'Dt',
        'pc' => 'Pc',
        'si' => 'Si',
        'da' => 'Da',
        'ro' => 'Ro',

        // Filtros para campos OC
        'referencia_oc' => 'ReferenciaOc',
        'origen_oc' => 'OrigenOc',

        // Filtro para verificada
        'verificada' => 'Verificada',

        // Filtros para tipo, tipo_id y obra_id
        'tipo' => 'Tipo',
        'tipo_id' => 'TipoId',
        'obra_id' => 'ObraId',

        //
        'visto_rechazada' => 'VistoRechazada',

        // Filtro para sp sin factura 
        'tiene_factura' => 'TieneFactura',
        'item_visto' => 'SPVista',

        // Filtro bandera: incluir datos de facturación en la respuesta
        'with_datos_facturacion' => 'WithDatosFacturacion',

        // Limitar al listado a las N solicitudes más recientes (por fecha_registro_pendiente)
        'ultimas_spp' => 'UltimasSpp',

    ];

    protected $casts = [
        'fecha_registro_pendiente' => 'datetime',
        'fecha_inicio_procesamiento' => 'datetime',
        'fecha_confirmacion_pago' => 'datetime',
        'fecha_con_comprobante' => 'datetime',
        'fecha_aprobado' => 'datetime',
        'fecha_rechazo' => 'datetime',
        'fecha_pago' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // Nuevos campos como enum numérico
        'dg' => EstadoSolicitud::class,
        'dg_fecha' => 'datetime',
        'dt' => EstadoSolicitud::class,
        'dt_fecha' => 'datetime',
        'pc' => EstadoSolicitud::class,
        'pc_fecha' => 'datetime',
        'si' => EstadoSolicitud::class,
        'si_fecha' => 'datetime',
        'da' => EstadoSolicitud::class,
        'da_fecha' => 'datetime',
        'ro' => EstadoSolicitud::class,
        'ro_fecha' => 'datetime',

        // Campos de abono
        'monto_total' => 'decimal:2',
        'monto_abonado' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
        'pago_completo' => 'boolean',
        'verificada' => 'boolean',

        // Campos de tracking OC
        'origen_oc' => 'boolean',
        'monto_oc_original' => 'decimal:2',

        'visto_rechazada' => 'boolean',

        // Datos XML como JSON
        'datos_factura_xml' => 'array',
        'tiene_factura' => 'boolean',
        'item_visto' => 'boolean',
    ];

    /** ----------------
     * Eager loading disponible
     * ----------------- */
    public static function eagerLodable(): array
    {
        return [
            'proveedor',
            'sucursal',
            'empresaConstrucc',
            'cotizacion',
            'cuentasBancarias',
            'ordenCompra',
            'usuarioCreador',
            'pagos',
        ];
    }

    /** ----------------
     * Relaciones
     * ----------------- */

    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function empresaConstrucc(): BelongsTo
    {
        return $this->belongsTo(EmpresaConstrucc::class, 'empresa_construcc_id');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id')->with('detalles');
    }

    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(SolicitudPagoCuentaBancaria::class);
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'referencia_oc', 'numero_orden');
    }

    // [2026-01-28 19:35:14] local.ERROR: Error al listar SPP del proveedor {"proveedor_id":8,"error":"Call to undefined relationship [pagos] on model [App\\Models\\SolicitudPago].","trace":"#0 C:\\repositorio\\app\\api-proveedores\\vendor\\laravel\\framework\\src\\Illuminate\\Database\\Eloquent\\Builder.php(939): Illuminate\\Database\\Eloquent\\RelationNotFoundException::make(Object(App\\Models\\SolicitudPago), 'pagos')

    // public function pagos(): BelongsToMany
    // {
    //     return $this->belongsToMany(
    //         PagoSPP::class,
    //         'pago_solicitud_pago',
    //         'solicitud_pago_id',
    //         'pago_spp_id'
    //     )
    //         ->withPivot([
    //             'monto_aplicado',
    //             'estado_pago',
    //             'notas',
    //             'fecha_aplicacion'
    //         ])
    //         ->withTimestamps();
    // }
    public function pagos(): BelongsToMany
    {
        return $this->belongsToMany(
            PagoSPP::class,
            'pago_solicitud_pago',
            'solicitud_pago_id',
            'pago_spp_id'
        )
            ->using(PagoSolicitudPago::class) // 🔥 IMPORTANTE
            ->withPivot([
                'monto_aplicado',
                'estado_pago',
                'notas',
                'fecha_aplicacion',
                'usuario_autorizo_id',
                'usuario_autorizo_nombre',
                'monto_autorizado',
                'motivo_autorizacion',
                'fecha_autorizacion',
            ])
            ->withTimestamps();
    }

    /**
     * Relación con el modelo pivot para acceso directo
     */
    public function pagosSolicitudPago(): HasMany
    {
        return $this->hasMany(PagoSolicitudPago::class, 'solicitud_pago_id');
    }

    /** ----------------
     * Filtros básicos (ejemplo nuevos)
     * ----------------- */
    public function filterByDg($query, $value)
    {
        return $query->where('dg', $value instanceof EstadoSolicitud ? $value->value : $value);
    }

    public function filterByDt($query, $value)
    {
        return $query->where('dt', $value instanceof EstadoSolicitud ? $value->value : $value);
    }

    public function filterByPc($query, $value)
    {
        return $query->where('pc', $value instanceof EstadoSolicitud ? $value->value : $value);
    }

    public function filterBySi($query, $value)
    {
        return $query->where('si', $value instanceof EstadoSolicitud ? $value->value : $value);
    }

    public function filterByDa($query, $value)
    {
        return $query->where('da', $value instanceof EstadoSolicitud ? $value->value : $value);
    }

    public function filterByRo($query, $value)
    {
        return $query->where('ro', $value instanceof EstadoSolicitud ? $value->value : $value);
    }

    public function filterByReferenciaOc($query, $value)
    {
        return $query->where('referencia_oc', 'like', "%{$value}%");
    }

    public function filterByOrigenOc($query, $value)
    {
        return $query->where('origen_oc', (bool) $value);
    }

    public function filterByVerificada($query, $value)
    {
        return $query->where('verificada', (bool) $value);
    }

    public function filterByTipo($query, $value)
    {
        return $query->where('tipo', $value);
    }

    public function filterByTipoId($query, $value)
    {
        return $query->where('tipo_id', $value);
    }

    public function filterByObraId($query, $value)
    {
        return $query->where('obra_id', $value);
    }

    /** ----------------
     * Filtros
     * ----------------- */

    /**
     * Filtro de búsqueda global
     * Busca en múltiples campos: folio, concepto, observaciones, usuario, referencia OC y empresa
     */
    public function filterBySearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('numero_folio_solicitud', 'like', "%$value%")
                ->orWhere('folio_factura', 'like', "%$value%")
                ->orWhere('descripcion_concepto', 'like', "%$value%")
                ->orWhere('observaciones', 'like', "%$value%")
                ->orWhere('usuario_nombre', 'like', "%$value%")
                ->orWhere('referencia_oc', 'like', "%$value%")
                ->orWhereHas('empresaConstrucc', function ($empresa) use ($value) {
                    $empresa->where('nombre', 'like', "%$value%");
                });
        });
    }

    public function filterByNumeroFolioSolicitud($query, $value)
    {
        return $query->where('numero_folio_solicitud', 'like', "%$value%");
    }

    public function filterByDescripcionConcepto($query, $value)
    {
        return $query->where('descripcion_concepto', 'like', "%$value%");
    }

    public function filterByEstadoSolicitud($query, $value)
    {
        $estados = is_array($value)
            ? $value
            : explode(',', $value);

        $estados = array_filter($estados, fn($estado) => filled($estado));

        if (empty($estados)) {
            return $query;
        }

        return $query->whereIn('estado_solicitud', $estados);
    }


    public function filterByProveedorId($query, $value)
    {
        return $query->whereIn('proveedor_id', explode(',', $value));
    }

    public function filterByFechaRegistroPendiente($query, $value)
    {
        return $query->whereDate('fecha_registro_pendiente', $value);
    }

    public function filterByFechaInicioProcesamiento($query, $value)
    {
        return $query->whereDate('fecha_inicio_procesamiento', $value);
    }

    public function filterByFechaConfirmacionPago($query, $value)
    {
        return $query->whereDate('fecha_confirmacion_pago', $value);
    }

    public function filterByEmpresaConstruccId($query, $value)
    {
        // Evitar aplicar el filtro cuando el valor viene vacío (",", null, espacios, etc.)
        $ids = array_filter(
            is_array($value) ? $value : explode(',', (string) $value),
            fn($id) => trim((string) $id) !== ''
        );

        if (empty($ids)) {
            // Si no hay IDs válidos, no filtramos para evitar consultas "colgadas"
            return $query;
        }

        return $query->whereIn('empresa_construcc_id', $ids);
    }

    public function filterByUsuarioId($query, $value)
    {
        // Evitar aplicar el filtro cuando el valor viene vacío (",", null, espacios, etc.)
        $ids = array_filter(
            is_array($value) ? $value : explode(',', (string) $value),
            fn($id) => trim((string) $id) !== ''
        );

        if (empty($ids)) {
            // Si no hay IDs válidos, no filtramos para evitar consultas "colgadas"
            return $query;
        }

        return $query->whereIn('usuario_id', $ids);
        // return $query->where('usuario_id', $value);
    }

    public function filterByUsuarioNombre($query, $value)
    {
        return $query->where('usuario_nombre', 'like', "%$value%");
    }

    public function filterByCotizacionId($query, $value)
    {
        return $query->whereIn('cotizacion_id', explode(',', $value));
    }

    /** ----------------
     * Filtros por rango de fechas
     * ----------------- */
    public function filterByFechaRegistroPendienteDesde($query, $value)
    {
        return $query->whereDate('fecha_registro_pendiente', '>=', $value);
    }

    public function filterByFechaRegistroPendienteHasta($query, $value)
    {
        return $query->whereDate('fecha_registro_pendiente', '<=', $value);
    }

    public function filterByFechaInicioProcesamientoDesde($query, $value)
    {
        return $query->whereDate('fecha_inicio_procesamiento', '>=', $value);
    }

    public function filterByFechaInicioProcesamientoHasta($query, $value)
    {
        return $query->whereDate('fecha_inicio_procesamiento', '<=', $value);
    }

    public function filterByFechaConfirmacionPagoDesde($query, $value)
    {
        return $query->whereDate('fecha_confirmacion_pago', '>=', $value);
    }

    public function filterByFechaConfirmacionPagoHasta($query, $value)
    {
        return $query->whereDate('fecha_confirmacion_pago', '<=', $value);
    }

    public function filterByFechaConComprobante($query, $value)
    {
        return $query->whereDate('fecha_con_comprobante', $value);
    }

    public function filterByFechaConComprobanteDesde($query, $value)
    {
        return $query->whereDate('fecha_con_comprobante', '>=', $value);
    }

    public function filterByFechaConComprobanteHasta($query, $value)
    {
        return $query->whereDate('fecha_con_comprobante', '<=', $value);
    }

    public function filterByFechaAprobado($query, $value)
    {
        return $query->whereDate('fecha_aprobado', $value);
    }

    public function filterByFechaAprobadoDesde($query, $value)
    {
        return $query->whereDate('fecha_aprobado', '>=', $value);
    }

    public function filterByFechaAprobadoHasta($query, $value)
    {
        return $query->whereDate('fecha_aprobado', '<=', $value);
    }

    public function filterByFechaRechazo($query, $value)
    {
        return $query->whereDate('fecha_rechazo', $value);
    }

    public function filterByFechaRechazoDesde($query, $value)
    {
        return $query->whereDate('fecha_rechazo', '>=', $value);
    }

    public function filterByFechaRechazoHasta($query, $value)
    {
        return $query->whereDate('fecha_rechazo', '<=', $value);
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


    /** --------------------------------
     * Filtros bandera
     * --------------------------------*/

    /**
     * Filtro bandera: incluir datos de facturación en la respuesta final
     */
    public function filterByWithDatosFacturacion($query, $value)
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->addResponseFlag('datos_facturacion', true);
        }

        return $query; // No se modifica el query
    }

    public function filterByUltimasSpp($query, $value)
    {
        return $query->ultimasSpp((int) $value);
    }

    /** ----------------
     * Métodos para manejo de pagos parciales
     * ----------------- */


    /**
     * Actualiza los montos abonados y saldos pendientes de la solicitud de pago y ajusta el estado de la solicitud: 
     *  Si el monto toltal abonado es igual o mayor al monto total, la solicitud se marca como PAGADO.
     * @param float $montoAbono Monto que se va a abonar a la solicitud de pago
     * @return bool Indica si la solicitud de pago ha sido completamente pagada 
     */
    public function actualizarSaldos($montoAbono)
    {
        $nuevoMontoAbonado = $this->monto_abonado + $montoAbono;
        $nuevoSaldoPendiente = $this->monto_total - $nuevoMontoAbonado;
        $pagoCompleto = $nuevoSaldoPendiente <= 0;

        // si viene con monot_autprizado este se va a 0

        // la spp pasa a pewndiente

        //agregar bandrera de abonada 

        $this->update([
            'monto_abonado' => $nuevoMontoAbonado,
            'saldo_pendiente' => max(0, $nuevoSaldoPendiente),
            // 'estado_solicitud' => $pagoCompleto ? EstadoSP::PAGADO->value : EstadoSP::AUTORIZADA->value,
            // si es un abono la spp passa a estado pendiente y se activa la bandera de abono
            'estado_solicitud' => $pagoCompleto ? EstadoSP::PAGADO->value : EstadoSP::PENDIENTE->value,
            // ESTO PASA AL REGISTRO DEL PAGO
            'notas_abono' => "Abono de $montoAbono aplicado. Total abonado: $nuevoMontoAbonado. Saldo pendiente: $nuevoSaldoPendiente.",
            'monto_autorizado' => null, // Si se está aplicando un abono, se limpia el monto autorizado para evitar confusiones
            'usuario_autorizo_parcial_id' => null,
            'usuario_autorizo_parcial_nombre' => null,
            'motivo_autorizacion_parcial' => null,
            'fecha_autorizacion_parcial' => null,
        ]);

        return $pagoCompleto;
    }

    public function inicializarSaldos()
    {
        if ($this->saldo_pendiente == 0 && $this->monto_abonado == 0) {
            $this->update([
                'saldo_pendiente' => $this->monto_total,
                'monto_abonado' => 0,
                'pago_completo' => false,
            ]);
        }
    }

    public function filterByVistoRechazada($query, $value)
    {
        return $query->where('visto_rechazada', (bool) $value);
    }

    public function filterByTieneFactura($query, $value)
    {
        return $query->where('tiene_factura', (bool) $value);
    }

    public function filterBySPVista($query, $value)
    {
        return $query->where('item_visto', (bool) $value);
    }

    public function filterByFolioFactura($query, $value)
    {
        return $query->where('folio_factura', 'like', "%{$value}%");
    }

    /**
     * Sincroniza las cuentas bancarias de la solicitud de pago
     * Si no se especifica un método, se da de baja
     */
    public function sincronizarCuentasBancarias(array $cuentasBancarias)
    {
        // Obtener IDs de cuentas bancarias actuales
        $idsActuales = $this->cuentasBancarias()->pluck('cuenta_bancaria_id')->toArray();

        // Obtener IDs de cuentas bancarias del array
        $idsNuevos = collect($cuentasBancarias)->pluck('cuenta_bancaria_id')->toArray();

        // Eliminar cuentas que ya no están en el array
        $idsAEliminar = array_diff($idsActuales, $idsNuevos);
        $this->cuentasBancarias()->whereIn('cuenta_bancaria_id', $idsAEliminar)->delete();

        // Actualizar o crear cuentas bancarias
        foreach ($cuentasBancarias as $cuentaData) {
            $cuentaBancaria = CuentaBancaria::find($cuentaData['cuenta_bancaria_id']);
            if (! $cuentaBancaria) {
                continue;
            }

            // Preparar datos para la tabla pivote
            $pivotData = [
                'alias' => $cuentaBancaria->alias,
                'banco_clave' => $cuentaBancaria->banco_clave,
                'banco_nombre' => $cuentaBancaria->banco_nombre,
                'cuenta' => $cuentaBancaria->cuenta,
                'clabe' => $cuentaBancaria->clabe,
                'tarjeta' => $cuentaBancaria->tarjeta,
                'titular_cuenta' => $cuentaBancaria->titular_cuenta,
                'referencia' => $cuentaBancaria->referencia ?? '',
                'sucursal' => $cuentaBancaria->sucursal,
                'swift' => $cuentaBancaria->swift,
                'preferida' => $cuentaBancaria->preferida,
            ];

            // Si hay datos específicos en el array, los sobrescribe
            if (isset($cuentaData['datos_especificos'])) {
                $pivotData = array_merge($pivotData, $cuentaData['datos_especificos']);
            }

            // Actualizar o crear en la tabla pivote
            $this->cuentasBancarias()->updateOrCreate(
                ['cuenta_bancaria_id' => $cuentaData['cuenta_bancaria_id']],
                $pivotData
            );
        }
    }

    /** ----------------
     * Utilidades s
     * ----------------- */

    /**
     * Generar siguiente número de folio para una nueva solicitud de pago para un proveedor
     * nopmeclatura:
     *  SP-
     *  proveedor abrevicado en tres letras mayusculas, sacadas paartir del nomnre comerical
     *  seguido de un guion
     *  6 digitos consecutivos, iniciando en 00001
     *  pasado los numoers posibles con 6 digitos, se aumenta a 7 digitos y asi sucesivamente
     *
     *
     * ej. SP-ABC-000001
     */
    public static function generarNumeroFolio(Proveedor $proveedor)
    {
        // Contamos cuántas solicitudes tiene el proveedor
        $count = self::where('proveedor_id', $proveedor->id)->count();

        // El siguiente número será count + 1
        $siguienteNumero = $count + 1;

        // Tomamos las primeras 3 letras del nombre comercial, solo letras
        $proveedorClave = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $proveedor->nombre_comercial), 0, 3));

        // Generamos el folio con formato SP-ABC-000001
        // return sprintf('SP-%s-%06d', $proveedorClave, $siguienteNumero);
        return sprintf('%04d', $siguienteNumero);
    }


    /** ----------------
     * Métodos de negocio para Órdenes de Compra
     * ----------------- */
    public function esDeOrdenCompra(): bool
    {
        return $this->origen_oc ?? true;
    }

    public function scopeWhereFromOrdenCompra(Builder $query): Builder
    {
        return $query->where('origen_oc', true);
    }

    /**
     * Restringe el query a las N solicitudes más recientes según fecha_registro_pendiente (y desempate por PK).
     * Debe aplicarse cuando ya están el resto de condiciones (proveedor, filtros, etc.).
     */
    public function scopeUltimasSpp(Builder $query, int $n): Builder
    {
        if ($n <= 0) {
            return $query;
        }

        $model = $query->getModel();
        $table = $model->getTable();
        $pk = $model->getKeyName();

        $clone = clone $query;
        $clone->reorder();

        $ids = $clone
            ->orderByDesc("{$table}.fecha_registro_pendiente")
            ->orderByDesc("{$table}.{$pk}")
            ->limit($n)
            ->pluck("{$table}.{$pk}");

        if ($ids->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn("{$table}.{$pk}", $ids);
    }

    public function getOrdenCompraAsociada()
    {
        return $this->ordenCompra;
    }

    public function validarMontoContraOC(): bool
    {
        return true;
        if (! $this->esDeOrdenCompra() || ! $this->monto_oc_original) {
            return true; // No aplica validación si no es de OC
        }

        return $this->monto_total <= $this->monto_oc_original;
    }

    /**
     * Calcula el saldo restante de la Solicitud de Pago (SPP). En base a los pagos aplicados.
     * saldo_restante = montoSPP - sum(pagos)
     */
    public function calcularSaldoRestante(): float
    {
        $totalPagado = (float) $this->pagos()
            ->wherePivotIn('estado_pago', [
                PagoSolicitudPago::ESTADO_APLICADO,
                PagoSolicitudPago::ESTADO_COMPLETADO,
                PagoSolicitudPago::ESTADO_PARCIAL,
            ])
            ->sum('pago_solicitud_pago.monto_aplicado');

        return max(0, (float) $this->monto_total - $totalPagado);
    }

    /**
     * Calcula el monto total abonado de la Solicitud de Pago (SPP). En base a los pagos aplicados.
     * monto_abonado = sum(pagos aplicados)
     */
    public function calcularMontoAbonado(): float
    {
        return (float) $this->pagos()
            ->wherePivotIn('estado_pago', [
                PagoSolicitudPago::ESTADO_APLICADO,
                PagoSolicitudPago::ESTADO_COMPLETADO,
                PagoSolicitudPago::ESTADO_PARCIAL,
            ])
            ->sum('pago_solicitud_pago.monto_aplicado');
    }

    /**
     * Verifica si la SPP está pagada completamente
     */
    public function estaPagadaCompletamente(): bool
    {
        return $this->calcularSaldoRestante() <= 0;
    }

    /**
     * Accessor para obtener el saldo restante calculado dinámicamente
     * Uso: $spp->saldo_restante_calculado
     */
    public function getSaldoRestanteCalculadoAttribute(): float
    {
        return $this->calcularSaldoRestante();
    }

    /**
     * Accessor para obtener el monto abonado calculado dinámicamente
     * Uso: $spp->monto_abonado_calculado
     */
    public function getMontoAbonadoCalculadoAttribute(): float
    {
        return $this->calcularMontoAbonado();
    }

    /**
     * Indica si la Solicitud de Pago ya tiene factura completa (PDF + XML).
     * Además sincroniza el flag tiene_factura cuando corresponde.
     */
    public function tieneFactura(): bool
    {
        $tienePdf = ! empty($this->ruta_archivo_factura_pdf);
        $tieneXml = ! empty($this->ruta_archivo_factura_xml);

        $tieneFacturaCompleta = $tienePdf && $tieneXml;

        // Mantener sincronizado el flag persistido
        if ($tieneFacturaCompleta && ! $this->tiene_factura) {
            $this->update(['tiene_factura' => true]);
        }

        return $tieneFacturaCompleta;
    }


    /**
     * Valida la especificación de factura contra los datos esperados (InterAPI + overrides SP).
     *
     * @param array $datosXml
     * @param array $datosFacturacion
     * @return array Lista de errores. Vacía si todo es válido.
     */
    public function validarEspecificacionFactura(array $datosXml, array $datosFacturacion): array
    {
        $errores = [];

        $norm = function ($v) {
            if ($v === null || $v === '') return null;
            return strtoupper(trim((string) $v));
        };

        $usoEsperado    = $norm($datosFacturacion['uso_cfdi'] ?? null);
        $mpEsperado     = $norm($datosFacturacion['metodo_pago'] ?? null);
        $fpEsperado     = $norm($datosFacturacion['forma_pago'] ?? null);
        $rfEsperado     = $norm($datosFacturacion['regimen_fiscal'] ?? null);
        $cpEsperado     = $norm($datosFacturacion['codigo_postal'] ?? null);
        $rfcEsperado    = $norm($datosFacturacion['rfc'] ?? null);
        $totalEsperado  = isset($datosFacturacion['total']) ? round((float)$datosFacturacion['total'], 2) : null;
        $monedaEsperada = $norm($datosFacturacion['moneda'] ?? null);

        $usoXml    = $norm($datosXml['uso_cfdi'] ?? null);
        $mpXml     = $norm($datosXml['metodo_pago'] ?? null);
        $fpXml     = $norm($datosXml['forma_pago'] ?? null);
        $rfXml     = $norm($datosXml['regimen_fiscal_receptor'] ?? null);
        $cpXml     = $norm($datosXml['codigo_postal_receptor'] ?? null);
        $rfcXml    = $norm($datosXml['rfc_receptor'] ?? null);
        $totalXml  = isset($datosXml['total']) && $datosXml['total'] !== '' ? round((float)$datosXml['total'], 2) : null;
        $monedaXml = $norm($datosXml['moneda'] ?? null);

        if ($usoEsperado && $usoXml && $usoXml !== $usoEsperado) {
            $errores['uso_cfdi'] = "Uso CFDI no coincide ({$usoEsperado} vs {$usoXml})";
        }

        if ($mpEsperado && $mpXml && $mpXml !== $mpEsperado) {
            $errores['metodo_pago'] = "Método de pago no coincide ({$mpEsperado} vs {$mpXml})";
        }

        if ($fpEsperado && $fpXml && $fpXml !== $fpEsperado) {
            $errores['forma_pago'] = "Forma de pago no coincide ({$fpEsperado} vs {$fpXml})";
        }

        if ($rfEsperado && $rfXml && $rfXml !== $rfEsperado) {
            $errores['regimen_fiscal'] = "Régimen fiscal no coincide ({$rfEsperado} vs {$rfXml})";
        }

        if ($cpEsperado && $cpXml && $cpXml !== $cpEsperado) {
            $errores['codigo_postal'] = "Código postal no coincide ({$cpEsperado} vs {$cpXml})";
        }

        if ($rfcEsperado && $rfcXml && $rfcXml !== $rfcEsperado) {
            $errores['rfc'] = "RFC no coincide ({$rfcEsperado} vs {$rfcXml})";
        }

        if ($totalEsperado !== null && $totalXml !== null && $totalXml !== $totalEsperado) {
            $errores['total'] = "Total no coincide ({$totalEsperado} vs {$totalXml})";
        }

        if ($monedaEsperada && $monedaXml && $monedaXml !== $monedaEsperada) {
            $errores['moneda'] = "Moneda no coincide ({$monedaEsperada} vs {$monedaXml})";
        }

        return $errores;
    }

    /**
     * Envía por correo el comprobante de pago al usuario principal del proveedor.
     */
    public function enviarCorreoComprobantePagoAProveedor(
        ?string $rutaComprobante = null,
        string $diskComprobante = 'private'
    ): void {
        $this->loadMissing('proveedor');

        $usuarioPrincipal = $this->proveedor?->usuarioPrincipal();
        if (! $usuarioPrincipal || ! filter_var($usuarioPrincipal->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $rutaComprobante = $rutaComprobante ?: $this->ruta_archivo_comprobante_pago;
        if (! $rutaComprobante || ! Storage::disk($diskComprobante)->exists($rutaComprobante)) {
            return;
        }

        $frontendUrl = config('app.frontend_url', config('app.url'));
        $urlSolicitud = rtrim($frontendUrl, '/') . '/pages/proveedor/sp/detalle/' . $this->id;
        $extension = strtolower(pathinfo($rutaComprobante, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        Mail::send('emails.solicitud-pago.comprobante-actualizado', [
            'notifiable' => $usuarioPrincipal,
            'solicitudPagoFolio' => $this->numero_folio_solicitud,
            'solicitudPagoId' => $this->id,
            'proveedorId' => $this->proveedor_id,
            'urlSolicitud' => $urlSolicitud,
            'logoAppDataUri' => $this->resolverLogoProveedorBase64(),
        ], function ($message) use ($usuarioPrincipal, $rutaComprobante, $diskComprobante, $extension, $mimeTypes) {
            $message->to($usuarioPrincipal->email, $usuarioPrincipal->name ?? null)
                ->subject('Comprobante de pago actualizado #' . $this->numero_folio_solicitud)
                ->attach(Storage::disk($diskComprobante)->path($rutaComprobante), [
                    'as' => 'comprobante_' . $this->numero_folio_solicitud . '.' . $extension,
                    'mime' => $mimeTypes[$extension] ?? 'application/octet-stream',
                ]);
        });
    }

    /**
     * Envía por correo la factura (PDF/XML) al correo de la empresa de Construcc.
     */
    public function enviarCorreoFacturaAEmpresaConstrucc(
        ?string $rutaFacturaPdf = null,
        ?string $rutaFacturaXml = null,
        string $diskArchivos = 'private'
    ): void {
        Log::info('🟢 ENVIAR CORREO FACTURA A EMPRESA CONSTRUCC: ', [
            'rutaFacturaPdf' => $rutaFacturaPdf,
            'rutaFacturaXml' => $rutaFacturaXml,
            'diskArchivos' => $diskArchivos,
            'empresaConstrucc' => $this->empresaConstrucc->razon_social,
            'empresaConstrucc' => $this->empresaConstrucc->email,
        ]);

        $this->loadMissing('empresaConstrucc');

        $emailEmpresa = $this->empresaConstrucc?->email;
        if (! $emailEmpresa || ! filter_var($emailEmpresa, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $rutaFacturaPdf = $rutaFacturaPdf ?: $this->ruta_archivo_factura_pdf;
        $rutaFacturaXml = $rutaFacturaXml ?: $this->ruta_archivo_factura_xml;

        if (
            (! $rutaFacturaPdf || ! Storage::disk($diskArchivos)->exists($rutaFacturaPdf)) &&
            (! $rutaFacturaXml || ! Storage::disk($diskArchivos)->exists($rutaFacturaXml))
        ) {
            return;
        }

        $frontendUrl = config('app.frontend_url', config('app.url'));
        $urlSolicitud = rtrim($frontendUrl, '/') . '/pages/proveedor/sp/detalle/' . $this->id;
        $empresaNotifiable = (object) [
            'name' => $this->empresaConstrucc->razon_social ?: $this->empresaConstrucc->nombre ?: 'Empresa',
        ];

        Mail::send('emails.solicitud-pago.factura-subida', [
            'notifiable' => $empresaNotifiable,
            'solicitudPagoFolio' => $this->numero_folio_solicitud,
            'urlSolicitud' => $urlSolicitud,
            'logoAppDataUri' => $this->resolverLogoProveedorBase64(),
        ], function ($message) use ($emailEmpresa, $rutaFacturaPdf, $rutaFacturaXml, $diskArchivos) {
            $message->to($emailEmpresa)->subject('Factura subida - Solicitud de pago #' . $this->numero_folio_solicitud);

            if ($rutaFacturaPdf && Storage::disk($diskArchivos)->exists($rutaFacturaPdf)) {
                $message->attach(Storage::disk($diskArchivos)->path($rutaFacturaPdf), [
                    'as' => 'factura_' . $this->numero_folio_solicitud . '.pdf',
                    'mime' => 'application/pdf',
                ]);
            }

            if ($rutaFacturaXml && Storage::disk($diskArchivos)->exists($rutaFacturaXml)) {
                $message->attach(Storage::disk($diskArchivos)->path($rutaFacturaXml), [
                    'as' => 'factura_' . $this->numero_folio_solicitud . '.xml',
                    'mime' => 'application/xml',
                ]);
            }
        });
    }

    private function resolverLogoProveedorBase64(): ?string
    {
        $logo = $this->proveedor?->logo;
        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }
        if (str_starts_with($logo, 'data:image')) {
            return $logo;
        }
        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            return null;
        }

        $logoPath = null;
        if (str_starts_with($logo, '/') || str_starts_with($logo, 'storage/')) {
            $logoPath = public_path($logo);
        } elseif (Storage::disk('public')->exists($logo)) {
            $logoPath = Storage::disk('public')->path($logo);
        } else {
            $logoPath = public_path('storage/' . $logo);
        }

        if (! $logoPath || ! is_readable($logoPath)) {
            return null;
        }

        $binary = @file_get_contents($logoPath);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }



    /**
     * 
     */




    /**
     * Último mes (basado en fecha_registro_pendiente)
     */
    public function scopeUltimoMes($query)
    {
        return $query->where('fecha_registro_pendiente', '>=', Carbon::now()->subMonth());
    }

    /**
     * Autorizadas
     */
    public function scopeAutorizadas($query)
    {
        return $query->where('estado_solicitud', EstadoSP::AUTORIZADA->value);
    }

    /**
     * Pendientes SIN pagos
     */
    public function scopePendientesSinPago($query)
    {
        return $query->where('estado_solicitud', EstadoSP::PENDIENTE->value)
            ->whereDoesntHave('pagos');
    }

    /**
     * Abonadas (pendientes CON pagos válidos)
     */
    public function scopeAbonadas($query)
    {
        return $query->where('estado_solicitud', EstadoSP::PENDIENTE->value)
            ->whereHas('pagos', function ($q) {
                $q->whereIn('estado_pago', [
                    PagoSolicitudPago::ESTADO_APLICADO,
                    PagoSolicitudPago::ESTADO_PARCIAL,
                    PagoSolicitudPago::ESTADO_COMPLETADO,
                ]);
            });
    }

    /**
     * Rechazadas
     */
    public function scopeRechazadas($query)
    {
        return $query->where('estado_solicitud', EstadoSP::RECHAZADA->value);
    }

    /**
     * Rechazadas del último mes (por fecha_rechazo)
     */
    public function scopeRechazadasUltimoMes($query)
    {
        return $query->where('estado_solicitud', EstadoSP::RECHAZADA->value)
            ->where('fecha_rechazo', '>=', Carbon::now()->subMonth());
    }

    /**
     * Todas del último mes
     */
    public function scopeTodasUltimoMes($query)
    {
        return $query->where('fecha_registro_pendiente', '>=', Carbon::now()->subMonth(2));
    }
}
