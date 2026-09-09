<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\MarksAsNotified;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Presupuesto extends BaseModel
{
    use HasFactory, MarksAsNotified, Filterable;

    protected $table = 'presupuestos';

    protected static $filters = [
        'search' => 'Search',
        'uuid' => 'Uuid',
        'numero_presupuesto' => 'NumeroPresupuesto',
        'proveedor_id' => 'ProveedorId',
        'proveedor_receptor_id' => 'ProveedorReceptorId',
        'empresa_receptora_id' => 'EmpresaReceptoraId',
        'user_id' => 'UserId',
        'fecha_emision' => 'FechaEmision',
        'fecha_desde' => 'FechaDesde',
        'fecha_hasta' => 'FechaHasta',
        'fecha_vencimiento_desde' => 'FechaVencimientoDesde',
        'fecha_vencimiento_hasta' => 'FechaVencimientoHasta',
        'con_iva' => 'ConIva',
        'total' => 'Total',
        'estado' => 'Estado',
        'item_visto' => 'ItemVisto',
        'segmento' => 'Segmento',
        'ultimas_presupuestos' => 'UltimasPresupuestos',
    ];

    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_ENVIADO = 'enviado';
    public const ESTADO_ACEPTADO = 'aceptado';
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_RECHAZADO_CON_OBSERVACION = 'rechazado_con_observacion';
    public const ESTADO_VENCIDO = 'vencido';

    /**
     * Receptor del presupuesto (quien recibe la cotización):
     * - Sin empresa_receptora_id: solo datos en texto (empresa_receptora_*), p. ej. cliente que no está en cartera.
     * - Con empresa_receptora_id: id de {@see CarteraCliente} del emisor (FK); la relación empresaReceptora() aplica.
     * - Proveedor del catálogo: empresa_receptora_id null; proveedor_receptor_id → {@see Proveedor} (FK).
     * proveedor_id: proveedor emisor del presupuesto. user_id: usuario que creó/editó el registro.
     * configuracion_condiciones: JSON (términos/opciones); no usar para id de receptor (columna dedicada).
     */
    protected $fillable = [
        'uuid',
        'numero_presupuesto',
        'fecha_emision',
        'fecha_vencimiento',
        'concepto_general',
        'nombre_presupuesto',
        'titulo_anexos',
        'titulo_anexos_pdf',
        'subtotal',
        /**
         * NOTE: 
         */
        'porcentaje_descuento',
        'cantidad_descuento',
        'con_iva',
        'iva_porcentaje',
        'iva_total',
        'total',
        'empresa_receptora_nombre',
        'empresa_receptora_puesto',
        'empresa_receptora_empresa',
        'empresa_receptora_alias',
        'empresa_receptora_telefono',
        'empresa_receptora_correo',
        // Términos y condiciones
        'term_cond_dias_vigencia',
        'term_cond_moneda',
        'term_cond_tiempo_entrega_dias',
        /**
         * null: no se llista para exporttar
         * 1.Los trabajos se iniciarán una vez confirmada la autorización del presupuesto (por defaul)
         * 2.Los trabajos iniciarán una vez Recibido el anticipo del porcentaje de ___ %
         */
        'term_cond_inicio_trabajo',
        'term_cond_inicio_trabajo_porcentaje', // solo aplica para inicio por anticipo (%)
        'term_cond_inicio_trabajo_cantidad', // solo aplica para inicio por anticipo (monto)
        'term_cond_impuestos_en_pdf',
        'term_cond_iva',
        // Garantía
        'obs_garantia_dias',
        // Estructura escalable de términos/alcances
        // (traslados/viáticos viven en term_cond_visibilidad; columnas obs_traslados/obs_viaticos droppeadas)
        'term_cond_textos_libres',
        'term_cond_visibilidad',
        'validacion_alcances',
        // Configuración de condiciones
        'configuracion_condiciones',
        // Motivo de rechazo
        'motivo_rechazo',
        'estado',
        'item_visto',
        'notification_id',
        'token_publico',
        'pdf_theme',
        'config_mostrar_totales',
        'ppto_config',
        'proveedor_id',
        'config_emisor_presupuesto_id',
        'empresa_emisora_nombre',
        'empresa_emisora_puesto',
        'empresa_emisora_telefono',
        'empresa_emisora_correo',
        'incluir_leyenda_atentamente',
        'empresa_emisora_nombre_comercial',
        'empresa_receptora_id',
        'proveedor_receptor_id',
        'user_id',
    ];

    /**
     * Términos y condiciones: term_cond_* (columnas explícitas).
     * Observaciones: obs_* (columnas explícitas).
     */
    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'con_iva' => 'boolean',
        'config_mostrar_totales' => 'boolean',
        'ppto_config' => 'array',
        'incluir_leyenda_atentamente' => 'boolean',
        'term_cond_impuestos_en_pdf' => 'boolean',
        'item_visto' => 'boolean',
        'subtotal' => 'decimal:2',
        'porcentaje_descuento' => 'integer',
        'cantidad_descuento' => 'decimal:2',
        'iva_porcentaje' => 'decimal:2',
        'iva_total' => 'decimal:2',
        'total' => 'decimal:2',
        'term_cond_iva' => 'decimal:2',
        'term_cond_inicio_trabajo_cantidad' => 'decimal:2',
        'term_cond_textos_libres' => 'array',
        'term_cond_visibilidad' => 'array',
        'validacion_alcances' => 'array',
        'configuracion_condiciones' => 'array',
    ];

    /**
     * Constantes para enunciados de términos y condiciones.
     */
    public const ENUNCIADO_VIGENCIA = 'Este presupuesto tiene una vigencia de %d días naturales a partir de su fecha de emisión.';
    public const ENUNCIADOS_MONEDA = [
        'MXN' => 'Los precios están expresados en pesos mexicanos (MXN).',
        'USD' => 'Los precios están expresados en dólares estadounidenses (USD).',
        'EUR' => 'Los precios están expresados en euros (EUR).',
    ];
    public const ENUNCIADO_IVA_INCLUIDO = 'Los precios incluyen el Impuesto al Valor Agregado (IVA) al %d%%.';
    public const ENUNCIADO_IVA_NO_INCLUIDO = 'Los precios no incluyen el Impuesto al Valor Agregado (IVA).';
    public const ENUNCIADO_TIEMPO_ENTREGA = 'El tiempo estimado de entrega o ejecución total de los trabajos será de %d días naturales.';
    public const ENUNCIADO_INICIO_TRABAJOS_AUTORIZACION = 'Los trabajos se iniciarán una vez confirmada la autorización del presupuesto.';
    public const ENUNCIADO_INICIO_TRABAJOS_ANTICIPO_PORCENTAJE = 'Los trabajos se iniciarán una vez recibido un anticipo del %d%%.';
    public const ENUNCIADO_INICIO_TRABAJOS_ANTICIPO_CANTIDAD = 'Los trabajos se iniciarán una vez recibida la cantidad de $ %s.';
    public const ENUNCIADO_INICIO_TRABAJOS_ANTICIPO_PLACEHOLDER = 'Los trabajos se iniciarán una vez recibido un anticipo (porcentaje o monto).';

    /**
     * Constantes para enunciados de observaciones.
     */
    // public const ENUNCIADO_GARANTIA = 'Garantía de %d días a partir de la finalización o entrega.';
    public const ENUNCIADO_GARANTIA = 'Garantía de %s a partir de la finalización o entrega.';

    public const ENUNCIADO_PAGO_TOTAL_CONFORMIDAD = 'El pago total de los trabajos será hasta que sean recibidos de total conformidad por el cliente.';
    public const ENUNCIADO_GARANTIA_CALIDAD = 'El proveedor garantiza la calidad de los trabajos/materiales suministrados.';
    public const ENUNCIADO_CORRECCION_DEFECTOS = 'El proveedor se compromete a corregir defectos atribuibles a su ejecución.';
    public const ENUNCIADO_INCLUYE_MATERIALES_INSUMOS = 'Este presupuesto incluye materiales, insumos, refacciones y demás necesarios para la correcta realización de los trabajos.';
    public const ENUNCIADO_INCLUYE_TRASLADOS = 'Este presupuesto incluye gastos de traslado de materiales, insumos, refacciones y del personal necesario.';
    public const ENUNCIADO_INCLUYE_VIATICOS = 'Este presupuesto incluye viáticos del personal necesarios para la ejecución de los trabajos aquí presupuestados.';
    public const ENUNCIADO_ALCANCE_INCLUYE_TODOS_COSTOS = 'El presupuesto incluye todos los costos necesarios para la correcta ejecución del servicio.';
    public const ENUNCIADO_ALCANCE_SIN_COSTOS_ADICIONALES = 'No se reconocerán costos adicionales no autorizados previamente.';
    public const ENUNCIADO_ALCANCE_ADICIONALES_AUTORIZACION = 'Cualquier trabajo adicional deberá ser autorizado por escrito.';

    /** @deprecated Obs que ya no se utilizan */
    /* public const ENUNCIADO_REVISION_TECNICA = 'Requiere revisión técnica previa.'; */
    /** @deprecated Obs que ya no se utilizan */
    /* public const ENUNCIADO_CONDICIONES_SITIO = 'Condiciones del sitio de trabajo deben ser adecuadas.'; */



    /**
     * Construye enunciados clasificados por sección para PDF/API.
     *
     * @return array{terminos: array<int, string>, validaciones: array<int, string>, observaciones: array<int, string>}
     */
    public function getEnunciadosClasificados(): array
    {
        $terminos = [];
        $validaciones = [];
        $observaciones = [];
        $config = is_array($this->configuracion_condiciones) ? $this->configuracion_condiciones : [];
        $visibilidad = is_array($this->term_cond_visibilidad) ? $this->term_cond_visibilidad : [];
        $visibilidadEstricta = $visibilidad !== [];

        // 1. vigencia
        if (self::terminoActivoPersistido($config, 'vigencia_activo', $this->term_cond_dias_vigencia > 0) && $this->term_cond_dias_vigencia > 0) {
            $terminos[] = sprintf(self::ENUNCIADO_VIGENCIA, (int) $this->term_cond_dias_vigencia);
        }

        // 2. moneda
        if (self::terminoActivoPersistido($config, 'moneda_activo', ! empty($this->term_cond_moneda))) {
            $moneda = $this->term_cond_moneda ?? 'MXN';
            $terminos[] = self::ENUNCIADOS_MONEDA[$moneda]
                ?? sprintf('Los precios estan expresados en la moneda %s.', $moneda);
        }

        // 3. impuestos
        $mostrarTotalesDocumento = (bool) ($this->config_mostrar_totales ?? true);
        if ($mostrarTotalesDocumento && self::terminoActivoPersistido($config, 'impuestos_activo', $this->term_cond_impuestos_en_pdf !== false) && $this->term_cond_impuestos_en_pdf !== false) {
            $ivaPct = (float) ($this->term_cond_iva ?? 16);
            $terminos[] = $this->con_iva
                ? sprintf(self::ENUNCIADO_IVA_INCLUIDO, (int) $ivaPct)
                : self::ENUNCIADO_IVA_NO_INCLUIDO;
        }

        // 4. tiempo entrega
        if (
            self::terminoActivoPersistido($config, 'tiempo_entrega_activo', $this->term_cond_tiempo_entrega_dias !== null && $this->term_cond_tiempo_entrega_dias > 0)
            && $this->term_cond_tiempo_entrega_dias !== null
            && $this->term_cond_tiempo_entrega_dias > 0
        ) {
            $terminos[] = sprintf(self::ENUNCIADO_TIEMPO_ENTREGA, (int) $this->term_cond_tiempo_entrega_dias);
        }

        // 5. inicio trabajos: autorización o anticipo (% o monto)
        if (self::terminoActivoPersistido($config, 'inicio_trabajos_activo', $this->term_cond_inicio_trabajo !== null)) {
            self::appendInicioTrabajosEnunciados(
                $terminos,
                $config,
                (int) ($this->term_cond_inicio_trabajo ?? 1),
                $this->term_cond_inicio_trabajo_porcentaje !== null ? (float) $this->term_cond_inicio_trabajo_porcentaje : null,
                $this->term_cond_inicio_trabajo_cantidad !== null ? (float) $this->term_cond_inicio_trabajo_cantidad : null,
            );
        }

        // 6-9. cláusulas fijas con visibilidad configurable
        $defaultVisibilidad = $visibilidadEstricta ? false : true;
        if (self::flagVisible($visibilidad, 'pago_contra_conformidad', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_PAGO_TOTAL_CONFORMIDAD;
        }
        if (
            self::terminoActivoPersistido($config, 'garantia_activo', $this->obs_garantia_dias > 0)
            && $this->obs_garantia_dias > 0
        ) {
            $duracion = self::formatearDuracion((int) $this->obs_garantia_dias);
            $terminos[] = sprintf(self::ENUNCIADO_GARANTIA, $duracion);
        }
        if (self::flagVisible($visibilidad, 'garantia_calidad', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_GARANTIA_CALIDAD;
        }
        if (self::flagVisible($visibilidad, 'correccion_defectos', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_CORRECCION_DEFECTOS;
        }
        if (self::flagVisible($visibilidad, 'incluye_materiales_insumos', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_INCLUYE_MATERIALES_INSUMOS;
        }
        if (self::flagVisible($visibilidad, 'incluye_traslados', $visibilidadEstricta ? false : true)) {
            $terminos[] = self::ENUNCIADO_INCLUYE_TRASLADOS;
        }
        if (self::flagVisible($visibilidad, 'incluye_viaticos', $visibilidadEstricta ? false : true)) {
            $terminos[] = self::ENUNCIADO_INCLUYE_VIATICOS;
        }

        // 10. textos libres (máximo 4)
        $textosLibres = is_array($this->term_cond_textos_libres) ? $this->term_cond_textos_libres : [];
        if ($textosLibres === []) {
            $textosLibres = self::legacyTextosLibresFromConfig($config, 'condicionantes_adicionales_');
        }
        foreach (array_slice($textosLibres, 0, 4) as $txtRaw) {
            $txt = trim((string) $txtRaw);
            if ($txt !== '') {
                $terminos[] = $txt;
            }
        }

        // Validación y alcances
        $alcances = is_array($this->validacion_alcances) ? $this->validacion_alcances : [];
        $defaultAlcance = $alcances !== [] ? false : true;
        if (self::flagVisible($alcances, 'incluye_todos_los_costos', $defaultAlcance)) {
            $validaciones[] = self::ENUNCIADO_ALCANCE_INCLUYE_TODOS_COSTOS;
        }
        if (self::flagVisible($alcances, 'sin_costos_adicionales_no_autorizados', $defaultAlcance)) {
            $validaciones[] = self::ENUNCIADO_ALCANCE_SIN_COSTOS_ADICIONALES;
        }
        if (self::flagVisible($alcances, 'adicionales_requieren_autorizacion_escrita', $defaultAlcance)) {
            $validaciones[] = self::ENUNCIADO_ALCANCE_ADICIONALES_AUTORIZACION;
        }


        // self::appendObservacionesAdicionalesDesdeConfig($observaciones, $config);

        return [
            'terminos' => $terminos,
            'validaciones' => $validaciones,
            'observaciones' => $observaciones,
        ];
    }

    /**
     * Construye la lista de enunciados de términos y condiciones para el PDF.
     *
     * @return array<int, string>
     */
    public function getTerminosEnunciados(): array
    {
        return $this->getEnunciadosClasificados()['terminos'];
    }

    /**
     * Construye la lista de enunciados de validación y alcances para el PDF.
     *
     * @return array<int, string>
     */
    public function getValidacionesEnunciados(): array
    {
        return $this->getEnunciadosClasificados()['validaciones'];
    }

    /**
     * Construye la lista de enunciados de observaciones para el PDF.
     *
     * @return array<int, string>
     */
    public function getObservacionesEnunciados(): array
    {
        return $this->getEnunciadosClasificados()['observaciones'];
    }

    /**
     * Construye enunciados de términos desde un array (ej: datos de formulario).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function buildTerminosEnunciadosFromArray(array $data): array
    {
        return self::buildEnunciadosClasificadosFromArray($data)['terminos'];
    }

    /**
     * Construye enunciados de validaciones/alcances desde un array (ej: datos de formulario).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function buildValidacionesEnunciadosFromArray(array $data): array
    {
        return self::buildEnunciadosClasificadosFromArray($data)['validaciones'];
    }

    /**
     * Construye enunciados clasificados por sección desde un array (ej: datos de formulario).
     *
     * @param  array<string, mixed>  $data
     * @return array{terminos: array<int, string>, validaciones: array<int, string>, observaciones: array<int, string>}
     */
    public static function buildEnunciadosClasificadosFromArray(array $data): array
    {
        $terminos = [];
        $validaciones = [];
        $observaciones = [];
        $conIva = $data['con_iva'] ?? true;
        $ivaPct = (float) ($data['term_cond_iva'] ?? $data['iva_porcentaje'] ?? 16);
        $config = is_array($data['configuracion_condiciones'] ?? null) ? $data['configuracion_condiciones'] : [];
        $visibilidad = is_array($data['term_cond_visibilidad'] ?? null) ? $data['term_cond_visibilidad'] : [];
        $visibilidadEstricta = $visibilidad !== [];

        // 1. vigencia
        if (
            self::terminoActivoFormulario($config, 'vigencia_activo')
            && ! empty($data['term_cond_dias_vigencia'])
            && (int) $data['term_cond_dias_vigencia'] > 0
        ) {
            $terminos[] = sprintf(self::ENUNCIADO_VIGENCIA, (int) $data['term_cond_dias_vigencia']);
        }

        // 2. moneda
        if (self::terminoActivoFormulario($config, 'moneda_activo')) {
            $terminos[] = self::buildEnunciadoMoneda($data['term_cond_moneda'] ?? null);
        }

        // 3. impuestos
        $mostrarTotalesDocumento = ! array_key_exists('config_mostrar_totales', $data)
            || (bool) $data['config_mostrar_totales'];
        $mostrarImpuestos = $data['term_cond_impuestos_en_pdf'] ?? false;
        if ($mostrarTotalesDocumento && self::terminoActivoFormulario($config, 'impuestos_activo') && $mostrarImpuestos !== false) {
            $terminos[] = $conIva
                ? sprintf(self::ENUNCIADO_IVA_INCLUIDO, (int) $ivaPct)
                : self::ENUNCIADO_IVA_NO_INCLUIDO;
        }

        // 4. tiempo entrega
        $tiempoEntrega = $data['term_cond_tiempo_entrega_dias'] ?? null;
        if (self::terminoActivoFormulario($config, 'tiempo_entrega_activo') && $tiempoEntrega !== null && (int) $tiempoEntrega > 0) {
            $terminos[] = sprintf(self::ENUNCIADO_TIEMPO_ENTREGA, (int) $tiempoEntrega);
        }

        // 5. inicio trabajos
        if (self::terminoActivoFormulario($config, 'inicio_trabajos_activo')) {
            self::appendInicioTrabajosEnunciados(
                $terminos,
                $config,
                (int) ($data['term_cond_inicio_trabajo'] ?? 1),
                isset($data['term_cond_inicio_trabajo_porcentaje']) ? (float) $data['term_cond_inicio_trabajo_porcentaje'] : null,
                isset($data['term_cond_inicio_trabajo_cantidad']) ? (float) $data['term_cond_inicio_trabajo_cantidad'] : null,
            );
        }

        // 6-9. cláusulas visibles
        $defaultVisibilidad = $visibilidadEstricta ? false : true;
        if (self::flagVisible($visibilidad, 'pago_contra_conformidad', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_PAGO_TOTAL_CONFORMIDAD;
        }
        $garantiaDias = (int) ($data['obs_garantia_dias'] ?? 0);
        if (self::terminoActivoFormulario($config, 'garantia_activo') && $garantiaDias > 0) {
            $duracion = self::formatearDuracion($garantiaDias);
            $terminos[] = sprintf(self::ENUNCIADO_GARANTIA, $duracion);
        }
        if (self::flagVisible($visibilidad, 'garantia_calidad', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_GARANTIA_CALIDAD;
        }
        if (self::flagVisible($visibilidad, 'correccion_defectos', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_CORRECCION_DEFECTOS;
        }
        if (self::flagVisible($visibilidad, 'incluye_materiales_insumos', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_INCLUYE_MATERIALES_INSUMOS;
        }
        if (self::flagVisible($visibilidad, 'incluye_traslados', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_INCLUYE_TRASLADOS;
        }
        if (self::flagVisible($visibilidad, 'incluye_viaticos', $defaultVisibilidad)) {
            $terminos[] = self::ENUNCIADO_INCLUYE_VIATICOS;
        }

        $textosLibres = is_array($data['term_cond_textos_libres'] ?? null) ? $data['term_cond_textos_libres'] : [];
        if ($textosLibres === []) {
            $textosLibres = self::legacyTextosLibresFromConfig($config, 'condicionantes_adicionales_');
        }
        foreach (array_slice($textosLibres, 0, 4) as $txtRaw) {
            $txt = trim((string) $txtRaw);
            if ($txt !== '') {
                $terminos[] = $txt;
            }
        }

        $alcances = is_array($data['validacion_alcances'] ?? null) ? $data['validacion_alcances'] : [];
        $defaultAlcance = $alcances !== [] ? false : true;
        if (self::flagVisible($alcances, 'incluye_todos_los_costos', $defaultAlcance)) {
            $validaciones[] = self::ENUNCIADO_ALCANCE_INCLUYE_TODOS_COSTOS;
        }
        if (self::flagVisible($alcances, 'sin_costos_adicionales_no_autorizados', $defaultAlcance)) {
            $validaciones[] = self::ENUNCIADO_ALCANCE_SIN_COSTOS_ADICIONALES;
        }
        if (self::flagVisible($alcances, 'adicionales_requieren_autorizacion_escrita', $defaultAlcance)) {
            $validaciones[] = self::ENUNCIADO_ALCANCE_ADICIONALES_AUTORIZACION;
        }

        self::appendObservacionesAdicionalesDesdeConfig($observaciones, $config);

        return [
            'terminos' => $terminos,
            'validaciones' => $validaciones,
            'observaciones' => $observaciones,
        ];
    }

    /**
     * Inicio de trabajos: autorización del presupuesto (por defecto) o anticipo recibido (% en configuración).
     *
     * @param  array<int, string>  $lista
     * @param  array<string, mixed>  $config
     */
    private static function appendInicioTrabajosEnunciados(
        array &$lista,
        array $config,
        int $inicioTrabajo,
        ?float $inicioTrabajoPorcentaje,
        ?float $inicioTrabajoCantidad,
    ): void {
        if (array_key_exists('inicio_trabajos_activo', $config) && $config['inicio_trabajos_activo'] === false) {
            return;
        }

        $modo = $config['inicio_trabajos_modo'] ?? null;
        if ($modo === 'autorizacion' || ($modo === null && $inicioTrabajo === 1)) {
            $lista[] = self::ENUNCIADO_INICIO_TRABAJOS_AUTORIZACION;

            return;
        }

        if ($modo === 'anticipo_porcentaje' || ($modo === 'anticipo' && ($inicioTrabajoPorcentaje ?? 0) > 0) || ($modo === null && ($inicioTrabajoPorcentaje ?? 0) > 0)) {
            $lista[] = sprintf(self::ENUNCIADO_INICIO_TRABAJOS_ANTICIPO_PORCENTAJE, (int) ($inicioTrabajoPorcentaje ?? 0));

            return;
        }

        if ($modo === 'anticipo_cantidad' || ($modo === null && ($inicioTrabajoCantidad ?? 0) > 0)) {
            $monto = number_format((float) ($inicioTrabajoCantidad ?? 0), 2, '.', ',');
            $lista[] = sprintf(self::ENUNCIADO_INICIO_TRABAJOS_ANTICIPO_CANTIDAD, $monto);

            return;
        }

        if ($modo === 'anticipo') {
            $pctLegacy = (int) ($config['inicio_trabajos_anticipo_pct'] ?? 0);
            if ($pctLegacy > 0) {
                $lista[] = sprintf(self::ENUNCIADO_INICIO_TRABAJOS_ANTICIPO_PORCENTAJE, $pctLegacy);
                return;
            }
        }

        $lista[] = self::ENUNCIADO_INICIO_TRABAJOS_ANTICIPO_PLACEHOLDER;
    }

    private static function buildEnunciadoMoneda(null|string $moneda): string
    {
        $codigo = strtoupper(trim((string) $moneda));
        if ($codigo === '') {
            $codigo = 'MXN';
        }

        return self::ENUNCIADOS_MONEDA[$codigo]
            ?? sprintf('Los precios están expresados en la moneda %s.', $codigo);
    }

    /**
     * En formularios nuevos, ausencia de bandera implica inactivo.
     *
     * @param  array<string, mixed>  $config
     */
    private static function terminoActivoFormulario(array $config, string $key): bool
    {
        return array_key_exists($key, $config) ? (bool) $config[$key] : false;
    }

    /**
     * En registros persistidos, si la bandera no existe se conserva el comportamiento legado.
     *
     * @param  array<string, mixed>  $config
     */
    private static function terminoActivoPersistido(array $config, string $key, bool $legacyDefault): bool
    {
        return array_key_exists($key, $config) ? (bool) $config[$key] : $legacyDefault;
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private static function flagVisible(array $flags, string $key, bool $default): bool
    {
        return array_key_exists($key, $flags) ? (bool) $flags[$key] : $default;
    }

    /**
     * @return array<int, string>
     * @param  array<string, mixed>  $config
     */
    private static function legacyTextosLibresFromConfig(array $config, string $prefix): array
    {
        $items = [];

        for ($i = 1; $i <= 4; $i++) {
            $txt = trim((string) ($config["{$prefix}{$i}"] ?? ''));
            if ($txt !== '') {
                $items[] = $txt;
            }
        }

        return $items;
    }

    /**
     * Textos libres de observaciones adicionales guardados en configuracion_condiciones.
     *
     * @param  array<int, string>  $observaciones
     * @param  array<string, mixed>  $config
     */
    private static function appendObservacionesAdicionalesDesdeConfig(array &$observaciones, array $config): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $txt = trim((string) ($config["observaciones_adicionales_{$i}"] ?? ''));
            if ($txt !== '') {
                $observaciones[] = $txt;
            }
        }
    }

    /**
     * Construye enunciados de observaciones desde un array.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function buildObservacionesEnunciadosFromArray(array $data): array
    {
        return self::buildEnunciadosClasificadosFromArray($data)['observaciones'];
    }

    /**
     * Boot del modelo.
     */
    protected static function booted(): void
    {
        static::creating(function (Presupuesto $presupuesto) {
            if (empty($presupuesto->uuid)) {
                $presupuesto->uuid = (string) Str::uuid();
            }
        });
    }

    // /**
    //  * Marca el presupuesto como visto y la notificación del usuario como leída.
    //  * Sobrescribe el trait para también buscar notificaciones por presupuesto_id,
    //  * ya que cada usuario del proveedor tiene su propia notificación.
    //  */
    // public function markRead(?User $user = null): void
    // {
    //     MarksAsNotified::markRead($user);

    //     // Marcar también cualquier notificación del usuario actual que referencie este presupuesto
    //     if ($user) {
    //         $notification = $user->unreadNotifications()
    //             ->where('data->presupuesto_id', $this->id)
    //             ->first();
    //         if ($notification) {
    //             $notification->markAsRead();
    //         }
    //     }
    // }

    /**
     * Relaciones para carga eager estándar.
     *
     * @return array<int, string>
     */
    public static function eagerLodable(): array
    {
        return [
            'proveedor',
            'empresaReceptora',
            'proveedorReceptor',
            'user',
            'conceptos',
            'anexos',
            'anexosPdf',
        ];
    }

    /**
     * Relación con proveedor emisor.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Tarjeta de contacto emisor seleccionada al guardar (opcional).
     */
    public function configEmisorPresupuesto(): BelongsTo
    {
        return $this->belongsTo(ConfigEmisorReceptorPresupuesto::class, 'config_emisor_presupuesto_id');
    }

    /**
     * Cliente de cartera del emisor cuando empresa_receptora_id es un id de {@see CarteraCliente}.
     */
    public function empresaReceptora(): BelongsTo
    {
        return $this->belongsTo(CarteraCliente::class, 'empresa_receptora_id');
    }

    /**
     * Proveedor del catálogo receptor (si aplica); mutuamente excluyente con cartera salvo datos legados.
     */
    public function proveedorReceptor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_receptor_id');
    }

    /**
     * URL pública del logo del proveedor receptor (catálogo). Solo para respuesta API; no se persiste en presupuesto.
     */
    public function empresaReceptoraLogoUrlParaApi(): ?string
    {
        if ((int) ($this->proveedor_receptor_id ?? 0) <= 0) {
            return null;
        }

        $prov = $this->relationLoaded('proveedorReceptor')
            ? $this->proveedorReceptor
            : $this->proveedorReceptor()->first(['logo']);

        if (! $prov || empty($prov->logo)) {
            return null;
        }

        if (filter_var($prov->logo, FILTER_VALIDATE_URL)) {
            return $prov->logo;
        }

        return Storage::disk('public')->url($prov->logo);
    }

    /**
     * Datos del bloque «Dirigido a» para PDF y API: si el receptor es proveedor de catálogo,
     * se completan desde {@see Proveedor} cuando faltan columnas en el presupuesto.
     *
     * @return array{nombre: ?string, puesto: ?string, empresa: ?string, alias_empresa: ?string, telefono: ?string, correo: ?string, direccion: ?string}
     */
    public function empresaReceptoraParaDocumento(): array
    {
        $prov = null;
        if ($this->proveedor_receptor_id) {
            $prov = $this->relationLoaded('proveedorReceptor')
                ? $this->proveedorReceptor
                : $this->proveedorReceptor()->first();
        }

        if ($prov) {
            return [
                'nombre' => self::primerTextoNoVacio($this->empresa_receptora_nombre, $prov->nombre_propietario),
                'puesto' => self::primerTextoNoVacio($this->empresa_receptora_puesto, $prov->contacto_cargo),
                'empresa' => self::primerTextoNoVacio($this->empresa_receptora_empresa, $prov->razon_social),
                'alias_empresa' => self::primerTextoNoVacio($this->empresa_receptora_alias, $prov->nombre_comercial),
                'telefono' => self::primerTextoNoVacio(
                    $this->empresa_receptora_telefono,
                    $prov->contacto_telefono,
                    $prov->telefono,
                    $prov->celular
                ),
                'correo' => self::primerTextoNoVacio($this->empresa_receptora_correo, $prov->contacto_correo, $prov->email),
                'direccion' => self::primerTextoNoVacio($this->empresa_receptora_direccion, $prov->direccion_empresa),
            ];
        }

        if ($this->empresa_receptora_id) {
            $this->loadMissing('empresaReceptora');
        }

        $cartera = $this->empresaReceptora;

        return [
            'nombre' => self::primerTextoNoVacio(
                $this->empresa_receptora_nombre,
                $cartera?->nombre
            ),
            'puesto' => self::primerTextoNoVacio(
                $this->empresa_receptora_puesto,
                $cartera?->puesto
            ),
            'empresa' => self::primerTextoNoVacio(
                $this->empresa_receptora_empresa,
                $cartera?->empresa
            ),
            'alias_empresa' => self::primerTextoNoVacio(
                $this->empresa_receptora_alias,
                $cartera?->alias_empresa
            ),
            'telefono' => self::primerTextoNoVacio(
                $this->empresa_receptora_telefono,
                $cartera?->telefono
            ),
            'correo' => self::primerTextoNoVacio(
                $this->empresa_receptora_correo,
                $cartera?->correo
            ),
            'direccion' => self::primerTextoNoVacio(
                $this->empresa_receptora_direccion,
                $cartera?->direccion
            ),
        ];
    }

    /**
     * @param  string|null  ...$candidatos
     */
    private static function primerTextoNoVacio(?string ...$candidatos): ?string
    {
        foreach ($candidatos as $c) {
            if ($c === null) {
                continue;
            }
            $t = trim((string) $c);
            if ($t !== '') {
                return $t;
            }
        }

        return null;
    }

    /**
     * Usuario que registró el presupuesto.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Conceptos del presupuesto.
     */
    public function conceptos(): HasMany
    {
        return $this->hasMany(PresupuestoConcepto::class);
    }

    /**
     * Anexos del presupuesto.
     */
    public function anexos(): HasMany
    {
        return $this->hasMany(PresupuestoAnexo::class)->orderBy('orden')->orderBy('id');
    }

    /**
     * Anexos PDF (se concatenan al final del PDF generado).
     */
    public function anexosPdf(): HasMany
    {
        return $this->hasMany(PresupuestoAnexoPdf::class)->orderBy('orden')->orderBy('id');
    }

    public function estadoLogs(): HasMany
    {
        return $this->hasMany(PresupuestoEstadoLog::class)
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    /**
     * Registra un cambio de estado. No se usa en alta de borrador.
     *
     * @param  \DateTimeInterface|string|null  $fecha
     */
    public function registrarCambioEstado(
        ?string $estadoAnterior = null,
        ?int $userId = null,
        $fecha = null,
        ?string $nota = null
    ): void {
        $estadoNuevo = (string) $this->estado;
        $estadoAnterior = $estadoAnterior ?? $this->getOriginal('estado');

        if ($estadoAnterior === $estadoNuevo) {
            return;
        }

        $momento = $fecha ?? now();
        $notaLimpia = $nota !== null ? trim($nota) : null;
        if ($notaLimpia === '') {
            $notaLimpia = null;
        }

        PresupuestoEstadoLog::query()->create([
            'presupuesto_id' => $this->id,
            'user_id' => $userId,
            'fecha' => $momento,
            'estado_anterior' => $estadoAnterior,
            'estado' => $estadoNuevo,
            'nota' => $notaLimpia,
        ]);
    }

    /**
     * Fecha del último log con el estado indicado.
     * Solo consulta en memoria si `estadoLogs` está eager-loaded (evita N+1 en listados).
     */
    public function fechaDeEstado(string $estado): ?\Illuminate\Support\Carbon
    {
        if (! $this->relationLoaded('estadoLogs')) {
            return null;
        }

        $log = $this->estadoLogs->firstWhere('estado', $estado);

        return $log?->fecha;
    }

    public function setPptoConfigAttribute(mixed $value): void
    {
        $sanitized = \App\Support\PresupuestoPdfDocumentConfig::sanitizePptoConfig($value);
        $this->attributes['ppto_config'] = empty($sanitized)
            ? null
            : json_encode($sanitized, JSON_UNESCAPED_UNICODE);
    }

    /**
     * HELPERS
     */


    /**
     * Calcula subtotal, IVA y total con base en conceptos y configuración del IVA.
     */
    public function calcularTotales(): void
    {
        $this->recalcularDesdeConceptos();
    }

    /**
     * Recalcula el subtotal a partir de conceptos y luego aplica IVA.
     */
    public function recalcularDesdeConceptos(): void
    {
        $subtotal = $this->relationLoaded('conceptos')
            ? $this->conceptos->sum(fn(PresupuestoConcepto $concepto) => (float) $concepto->precio_total)
            : (float) $this->conceptos()->sum('precio_total');

        $this->subtotal = round($subtotal, 2);
        $this->aplicarIva();
    }

    /**
     * Totales del documento: descuento sobre subtotal (antes de IVA), luego IVA y total.
     *
     * @return array{
     *     subtotal: float,
     *     porcentaje_descuento: int|null,
     *     cantidad_descuento: float|null,
     *     monto_descuento: float,
     *     base_antes_iva: float,
     *     iva_total: float,
     *     total: float,
     *     mostrar_descuento: bool
     * }
     */
    public static function calcularTotalesDocumento(
        float $subtotal,
        ?int $porcentajeDescuento,
        ?float $cantidadDescuento,
        bool $conIva,
        float $ivaPorcentaje
    ): array {
        $subtotalSeguro = max(0.0, round($subtotal, 2));
        $pct = $porcentajeDescuento !== null ? max(0, min(100, (int) $porcentajeDescuento)) : 0;
        $cantidad = $cantidadDescuento !== null ? round(max(0.0, (float) $cantidadDescuento), 2) : null;
        $montoDesdePorcentaje = $pct >= 1 ? round($subtotalSeguro * ($pct / 100), 2) : 0.0;

        $usarPorcentaje = $pct >= 1;
        $montoDescuento = $usarPorcentaje
            ? $montoDesdePorcentaje
            : min($subtotalSeguro, $cantidad ?? 0.0);
        $mostrarDescuento = $montoDescuento > 0;
        $cantidadNormalizada = $mostrarDescuento ? round($montoDescuento, 2) : null;
        $porcentajeNormalizado = $mostrarDescuento
            ? ($usarPorcentaje
                ? $pct
                : max(0, min(100, (int) round(($montoDescuento / max($subtotalSeguro, 0.01)) * 100))))
            : null;
        $baseAntesIva = round($subtotal - $montoDescuento, 2);
        $ivaTotal = $conIva ? round($baseAntesIva * ($ivaPorcentaje / 100), 2) : 0.0;
        $total = round($baseAntesIva + $ivaTotal, 2);

        return [
            'subtotal' => $subtotalSeguro,
            'porcentaje_descuento' => $porcentajeNormalizado,
            'cantidad_descuento' => $cantidadNormalizada,
            'monto_descuento' => $montoDescuento,
            'base_antes_iva' => $baseAntesIva,
            'iva_total' => $ivaTotal,
            'total' => $total,
            'mostrar_descuento' => $mostrarDescuento,
        ];
    }

    /**
     * Aplica IVA según configuración actual (`con_iva`, `iva_porcentaje` y `porcentaje_descuento`).
     */
    public function aplicarIva(): void
    {
        $totales = self::calcularTotalesDocumento(
            (float) $this->subtotal,
            $this->porcentaje_descuento !== null ? (int) $this->porcentaje_descuento : null,
            $this->cantidad_descuento !== null ? (float) $this->cantidad_descuento : null,
            (bool) $this->con_iva,
            (float) $this->iva_porcentaje
        );

        $this->porcentaje_descuento = $totales['porcentaje_descuento'];
        $this->cantidad_descuento = $totales['cantidad_descuento'];
        $this->iva_total = $totales['iva_total'];
        $this->total = $totales['total'];
    }

    /**
     * Genera un token público único para compartir el presupuesto.
     */
    public function generarTokenPublico(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->token_publico = $token;
        $this->save();

        return $token;
    }

    /**
     * Asegura que el presupuesto tenga un token público.
     */
    public function asegurarTokenPublico(): string
    {
        if ($this->token_publico) {
            return $this->token_publico;
        }

        return $this->generarTokenPublico();
    }

    /**
     * Genera un número de presupuesto consecutivo por proveedor.
     */
    public static function generarNumeroPresupuesto(int $proveedorId): string
    {
        $proveedor = Proveedor::query()->findOrFail($proveedorId);

        return $proveedor->obtenerFolioSiguientePresupuesto();
    }

    /**
     * Marca como vencidos los presupuestos enviados cuya fecha_vencimiento ya pasó.
     */
    public static function actualizarVencidos(): int
    {
        $presupuestos = self::query()
            ->where('estado', self::ESTADO_ENVIADO)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->get();

        foreach ($presupuestos as $presupuesto) {
            $estadoAnterior = $presupuesto->estado;
            $presupuesto->estado = self::ESTADO_VENCIDO;
            $presupuesto->save();
            $presupuesto->registrarCambioEstado($estadoAnterior);
        }

        return $presupuestos->count();
    }


    /**
     * SCOPES
     */


    /**
     * Filtra por UUID.
     */
    public function scopeByUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }

    /**
     * Filtra por proveedor.
     */
    public function scopeByProveedor($query, int $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }

    /**
     * Filtra por usuario.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filtra por rango de fechas de emisión.
     */
    public function scopeByFechaRango($query, ?string $desde, ?string $hasta)
    {
        return $query
            ->when($desde, fn($q) => $q->whereDate('fecha_emision', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('fecha_emision', '<=', $hasta));
    }

    /**
     * Presupuestos con IVA.
     */
    public function scopeConIva($query)
    {
        return $query->where('con_iva', true);
    }

    /**
     * Presupuestos sin IVA.
     */
    public function scopeSinIva($query)
    {
        return $query->where('con_iva', false);
    }

    /**
     * Restringe el query a los N presupuestos más recientes según created_at (y desempate por PK).
     * Debe aplicarse cuando ya están el resto de condiciones (proveedor, filtros, etc.).
     */
    public function scopeUltimasPresupuestos(Builder $query, int $n): Builder
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
            ->orderByDesc("{$table}.created_at")
            ->orderByDesc("{$table}.{$pk}")
            ->limit($n)
            ->pluck("{$table}.{$pk}");

        if ($ids->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn("{$table}.{$pk}", $ids);
    }

    /**
     * FILTERS
     */

    /**
     * Filtro por búsqueda general.
     * Busca en: numero_presupuesto, nombre_presupuesto, concepto_general, empresa_receptora_nombre, empresaReceptora.nombre
     */
    public function filterBySearch($query, string $value)
    {
        return $query->where(function ($query) use ($value) {
            $query
                ->where('numero_presupuesto', 'like', "%{$value}%")
                ->orWhere('nombre_presupuesto', 'like', "%{$value}%")
                ->orWhere('concepto_general', 'like', "%{$value}%")
                ->orWhere('empresa_receptora_nombre', 'like', "%{$value}%")
                ->orWhere('empresa_receptora_empresa', 'like', "%{$value}%")
                ->orWhereHas('empresaReceptora', function ($q) use ($value) {
                    $q->where('nombre', 'like', "%{$value}%")
                        ->orWhere('empresa', 'like', "%{$value}%");
                });
        });
    }

    /**
     * Filtro por UUID.
     */
    public function filterByUuid($query, string $value)
    {
        return $query->where('uuid', $value);
    }

    /**
     * Filtro por número de presupuesto.
     */
    public function filterByNumeroPresupuesto($query, string $value)
    {
        return $query->where('numero_presupuesto', 'like', "%{$value}%");
    }

    /**
     * Filtro por proveedor.
     */
    public function filterByProveedorId($query, $value)
    {
        return $query->whereIn('proveedor_id', explode(',', (string) $value));
    }

    /**
     * Filtro por proveedor receptor (presupuestos recibidos en el catálogo).
     */
    public function filterByProveedorReceptorId($query, $value)
    {
        return $query->whereIn('proveedor_receptor_id', explode(',', (string) $value));
    }

    /**
     * Filtro por empresa receptora (solo registros del sistema).
     */
    public function filterByEmpresaReceptoraId($query, $value)
    {
        return $query->whereIn('empresa_receptora_id', explode(',', (string) $value));
    }

    /**
     * Filtro por usuario.
     */
    public function filterByUserId($query, $value)
    {
        return $query->whereIn('user_id', explode(',', (string) $value));
    }

    /**
     * Filtro por fecha exacta de emisión.
     */
    public function filterByFechaEmision($query, string $value)
    {
        return $query->whereDate('created_at', $value);
    }

    /**
     * Filtro por fecha de emisión desde.
     */
    public function filterByFechaDesde($query, string $value)
    {
        return $query->whereDate('created_at', '>=', $value);
    }

    /**
     * Filtro por fecha de emisión hasta.
     */
    public function filterByFechaHasta($query, string $value)
    {
        return $query->whereDate('created_at', '<=', $value);
    }

    /**
     * Filtro por fecha de vencimiento desde.
     */
    public function filterByFechaVencimientoDesde($query, string $value)
    {
        return $query->whereDate('fecha_vencimiento', '>=', $value);
    }

    /**
     * Filtro por fecha de vencimiento hasta.
     */
    public function filterByFechaVencimientoHasta($query, string $value)
    {
        return $query->whereDate('fecha_vencimiento', '<=', $value);
    }

    /**
     * Filtro por indicador de IVA.
     */
    public function filterByConIva($query, $value)
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolValue === null) {
            return $query;
        }

        return $query->where('con_iva', $boolValue);
    }

    /**
     * Filtro por total exacto.
     */
    public function filterByTotal($query, $value)
    {
        return $query->where('total', $value);
    }

    /**
     * Filtro por estado del presupuesto.
     * Acepta valor único o varios separados por coma (ej: rechazado,vencido).
     */
    public function filterByEstado($query, $value)
    {
        if (empty($value)) {
            return $query;
        }
        $estados = array_map('trim', explode(',', (string) $value));
        $estados = array_filter($estados);

        return $estados === [] ? $query : $query->whereIn('estado', $estados);
    }

    /**
     * Filtro por segmento del listado.
     * observados: rechazado con motivo_rechazo y no visto (item_visto=false)
     * rechazados: el resto (rechazados sin motivo, vistos, o vencidos)
     */
    public function filterBySegmento($query, $value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $query;
        }

        $estadosRechazados = [self::ESTADO_RECHAZADO, self::ESTADO_RECHAZADO_CON_OBSERVACION];

        if ($value === 'observados') {
            return $query
                ->whereIn('estado', $estadosRechazados)
                ->whereNotNull('motivo_rechazo')
                ->whereRaw('TRIM(motivo_rechazo) != ?', [''])
                ->where(function ($q) {
                    $q->where('item_visto', false)->orWhereNull('item_visto');
                });
        }

        if ($value === 'rechazados') {
            return $query->where(function ($q) use ($estadosRechazados) {
                $q->whereIn('estado', array_merge($estadosRechazados, [self::ESTADO_VENCIDO]))
                    ->where(function ($sub) {
                        // Sin motivo O está visto
                        $sub->whereNull('motivo_rechazo')
                            ->orWhereRaw('TRIM(COALESCE(motivo_rechazo, "")) = ?', [''])
                            ->orWhere('item_visto', true);
                    });
            });
        }

        return $query;
    }

    /**
     * Filtro por item_visto (presupuesto visto/no visto).
     */
    public function filterByItemVisto($query, $value)
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolValue === null) {
            return $query;
        }

        return $query->where('item_visto', $boolValue);
    }

    public function filterByUltimasPresupuestos($query, $value)
    {
        return $query->ultimasPresupuestos((int) $value);
    }


    /**
     * HELPERS DE FORMATEO
     */
    private static function formatearDuracion(int $dias): string
    {
        $anios = intdiv($dias, 365);
        $resto = $dias % 365;

        $meses = intdiv($resto, 30);
        $diasRestantes = $resto % 30;

        $partes = [];

        if ($anios > 0) {
            $partes[] = $anios === 1 ? '1 año' : "{$anios} años";
        }

        if ($meses > 0) {
            $partes[] = $meses === 1 ? '1 mes' : "{$meses} meses";
        }

        if ($diasRestantes > 0) {
            $partes[] = $diasRestantes === 1 ? '1 día' : "{$diasRestantes} días";
        }

        if (empty($partes)) {
            return '0 días';
        }

        if (count($partes) === 1) {
            return $partes[0];
        }

        $ultimo = array_pop($partes);
        return implode(', ', $partes) . ' y ' . $ultimo;
    }
}
