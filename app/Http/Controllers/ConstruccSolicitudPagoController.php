<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSolicitud;
use App\Enums\EstadoSP;
use App\Http\Requests\Construcc\SolicitudPagoAutorizarRequest;
use App\Http\Requests\Construcc\SolicitudPagoAutorizarParcialRequest;
use App\Http\Requests\Construcc\SolicitudPagoConfirmarPagoRequest;
use App\Http\Requests\Construcc\SolicitudPagoRechazarRequest;
use App\Http\Requests\Construcc\GenerarSolicitudPagoConstruccRequest;
use App\Http\Resources\Construcc\ConstruccSolicitudPagoResource;
use App\Http\Resources\Construcc\ConstruccGenerarSppResource;
use App\Models\SolicitudPago;
use App\Models\Proveedor;
use App\Models\CuentaBancaria;
use App\Enums\EstadoCuentaBancaria;
use App\Notifications\SolicitudPago\SolicitudPagoPagadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoRechazadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoRechazadaSinAutorizacionNotification;
use App\Notifications\ProveedorEmpresa\ProveedorAsociadoAEmpresaNotification;
use App\Services\InterApiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Http\Requests\Construcc\SolicitudPagoUpdateConprobantePagoRequest;
use App\Models\PagoSolicitudPago;
use App\Notifications\SolicitudPago\SolicitudPagoComprobanteActualizadoNotification;
use Carbon\Carbon;

class ConstruccSolicitudPagoController extends Controller
{
    use ApiResponse;

    protected $interApiService;

    public function __construct(InterApiService $interApiService)
    {
        $this->interApiService = $interApiService;
    }

    /**
     * IDs de roles de ConstruccApp
     */
    private const ROLES_CONSTRUCC_IDS = [
        0, // Administrador
        1, // Director General (DG)
        2, // Director Técnico (DT)
        3, // Director Administrativo (DA)
        4, // Superintendente (SI)
        5, // Programación y Control (PC)
        6, // Residente de Obra (RO)
    ];

    /**
     * Roles que pueden autorizar solicitudes de pago
     */
    private const ROLES_AUTORIZACION = ['DG', 'DT', 'PC', 'SI'];

    /**
     * Roles que pueden rechazar solicitudes
     */
    private const ROLES_RECHAZO = ['DG', 'DT', 'PC', 'SI', 'DA'];

    /**
     * Rol que puede confirmar pagos
     */
    private const ROL_PAGO = 'DA';

    /**
     * Listado paginado filtrado por empresa de construcción
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(SolicitudPago::getFilters());
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');
        $perPage = $request->input('per_page', 10000);

        $usuarioNivel = $request->input('usuario_nivel');
        $usuarioIdFiltro = $request->input('usuario_id');

        $query = SolicitudPago::query()
            ->with(array_merge(
                SolicitudPago::eagerLodable(),
                ['pagos']
            ))
            ->where('verificada', true)
            // ->whereHas('pagos') // 👈 CLAVE: elimina pagos vacíos
            ->filter($filters);

        if ((int) $usuarioNivel === 6) {
            $query->where('usuario_id', (int) $usuarioIdFiltro);
        }

        $query->orderBy($sortBy, $order);

        $paginator = $query->paginate($perPage);

        return $this->paginated(
            $paginator->setCollection(
                ConstruccSolicitudPagoResource::collection($paginator)->collection
            )
        );
    }

    /**
     * Listado paginado segmentado por estado de solicitud ( autorizada, pendientes (pendientes sin pagos), rechazadas (del ultimo mes), abonadas (pendiientes con cpagos),todas)
     * 
     * parametros: usuario_nivel, usuario_id, empresa_construcc_id
     * 
     * si el nivel es RO: 6, solo se listan las SPP del usuario
     * para las pagadas son las ultimas 20
     */
    public function pendientes(Request $request): JsonResponse
    {
        // $filters = $request->only(SolicitudPago::getFilters());

        $usuarioNivel = (int) $request->input('usuario_nivel');
        $usuarioIdFiltro = (int) $request->input('usuario_id');
        $empresaConstruccId = $request->input('empresa_construcc_id');

        // 🔹 Constantes de nivel (evita números mágicos)
        $NIVEL_RO = 6;

        // 🔹 Base query única
        $baseQuery = SolicitudPago::query()
            ->with(SolicitudPago::eagerLodable())
            ->where('verificada', true);
        // ->filter($filters);

        // 🔥 Filtro por nivel de usuario
        if ($usuarioNivel === $NIVEL_RO) {
            $baseQuery->where('usuario_id', $usuarioIdFiltro);
        }

        // 🔥 Filtro por empresa
        if (!empty($empresaConstruccId)) {
            $baseQuery->where('empresa_construcc_id', $empresaConstruccId);
        }

        // 🔥 Definición de segmentos
        $segmentDefs = [
            'autorizadas' => fn($q) =>
            $q->where('estado_solicitud', EstadoSP::AUTORIZADA->value),

            'pendientes' => fn($q) =>
            $q->where('estado_solicitud', EstadoSP::PENDIENTE->value)
                ->whereDoesntHave('pagos'),

            'pagadas' => fn($q) =>
            $q->where('estado_solicitud', EstadoSP::PAGADO->value)
                ->where('updated_at', '>=', now()->subMonth())
                ->latest('updated_at')
                ->limit(20),

            'abonadas' => fn($q) =>
            $q->where('estado_solicitud', EstadoSP::PENDIENTE->value)
                ->whereHas('pagos', function ($q) {
                    $q->whereIn('estado_pago', [
                        PagoSolicitudPago::ESTADO_APLICADO,
                        PagoSolicitudPago::ESTADO_PARCIAL,
                        PagoSolicitudPago::ESTADO_COMPLETADO,
                    ]);
                }),
        ];

        // 🔥 Ejecutar segmentos
        $data = [];
        foreach ($segmentDefs as $key => $callback) {
            $data[$key] = ConstruccSolicitudPagoResource::collection(
                $callback(clone $baseQuery)->get()
            );
        }

        return $this->success($data);
    }

    /**
     *  completar el metododo similar al index. Se carga solo dato sespecificos de las SPP y contadores 
     * 
     * Estados: 
     *  - Pendientes de autorizar -> verificada = true, estado_solicitud = pendiente
     *  - Autorizadas -> estado_solicitud = autorizada
     *  - Rechazadas -> estado_solicitud = rechazada
     *  - Pagadas -> estado_solicitud = pagado
     * 
     * Estas se tiene que contabilizar dependiendo el filtro indicado: mes, semana, dia
     *  
     * GET /api/construcc/solicitudes-pago/index-contadores → trae SPP de este mes
     * GET /api/construcc/solicitudes-pago/index-contadores?periodo=semana
     * GET /api/construcc/solicitudes-pago/index-contadores?periodo=dia
     * 
     * GET /api/construcc/solicitudes-pago/indexPorEstadoContadores?periodo=mes&estado_solicitud=pendiente&rol_id=3
     *  
     */
    public function indexPorEstadoContadores(Request $request): JsonResponse
    {
        $filters = $request->only(SolicitudPago::getFilters());
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');
        $perPage = $request->input('per_page', 10000);
        $rolUsuario = $request->input('rol_id', $request->input('rol_usuario'));
        $rolUsuario = is_numeric($rolUsuario) ? (int) $rolUsuario : null;
        $periodo = strtolower((string) $request->input('periodo', 'mes'));
        $periodo = in_array($periodo, ['dia', 'semana', 'mes'], true) ? $periodo : 'mes';

        $aplicarPeriodo = function ($query) use ($periodo) {
            if (! $periodo) {
                return $query;
            }

            $inicio = match ($periodo) {
                'dia' => Carbon::now()->startOfDay(),
                'semana' => Carbon::now()->startOfWeek(),
                'mes' => Carbon::now()->startOfMonth(),
            };

            return $query->whereBetween('created_at', [$inicio, Carbon::now()]);
        };

        $countFilters = array_diff_key($filters, array_flip(['verificada']));

        $baseConteo = SolicitudPago::query()
            ->when(! empty($countFilters), fn($q) => $q->filter($countFilters));

        $aplicarPeriodo($baseConteo);

        $segmentCounts = (clone $baseConteo)
            ->where('verificada', true)
            ->selectRaw('estado_solicitud, COUNT(*) as total')
            ->groupBy('estado_solicitud')
            ->pluck('total', 'estado_solicitud')
            ->toArray();

        $pendienteEstado = $rolUsuario === 3
            ? EstadoSP::AUTORIZADA->value
            : EstadoSP::PENDIENTE->value;

        $conteo = [
            'por_validar' => (clone $baseConteo)->where('verificada', false)->count(),
            'pendiente' => (int) ($segmentCounts[$pendienteEstado] ?? 0),
            'autorizadas' => (int) ($segmentCounts[EstadoSP::AUTORIZADA->value] ?? 0),
            'rechazadas' => (int) ($segmentCounts[EstadoSP::RECHAZADA->value] ?? 0),
            'pagadas' => (int) ($segmentCounts[EstadoSP::PAGADO->value] ?? 0),
            'sin_factura' => (clone $baseConteo)
                ->whereNull('folio_factura')
                ->whereNotNull('ruta_archivo_factura_xml')
                ->count(),
        ];

        $query = SolicitudPago::query()
            ->select([
                'id',
                'numero_folio_solicitud',
                'folio_factura',
                'estado_solicitud',
                'monto_total',
                'empresa_construcc_id',
                'monto_abonado',
                'saldo_pendiente',
                'verificada',
                'proveedor_id',
                'empresa_construcc_id',
                'usuario_id',
                'usuario_nombre',
                'created_at',
            ])
            ->where('verificada', true)
            ->filter($filters)
            ->orderBy($sortBy, $order);

        $aplicarPeriodo($query);

        // Aquí debería limitar por la empresa del usuario ConstruccApp
        // if ($request->user()->empresa_construcc_id) {
        //     $query->where('empresa_construcc_id', $request->user()->empresa_construcc_id);
        // }

        $paginator = $query->paginate($perPage);

        return $this->paginated(
            $paginator,
            'Datos paginados.',
            200,
            [
                'conteo' => $conteo,
                'periodo' => $periodo,
                'estado_solicitud' => $filters['estado_solicitud'] ?? null,
            ]
        );
    }

    /**
     * Mostrar detalle de una solicitud
     */
    public function show(SolicitudPago $solicitudPago): JsonResponse
    {
        // Cargar relaciones estándar
        $solicitudPago->load(SolicitudPago::eagerLodable());

        if (! $solicitudPago->folio_factura && $solicitudPago->ruta_archivo_factura_xml) {

            // Obtener ruta física real del XML
            $rutaXmlFisica = Storage::disk('private')
                ->path($solicitudPago->ruta_archivo_factura_xml);

            // Pasar la ruta real al extractor
            $datosXml = $this->extraerDatosXML($rutaXmlFisica);

            if ($datosXml && isset($datosXml['folio'])) {
                $solicitudPago->folio_factura = $datosXml['folio'];
                $solicitudPago->save();
            }
        }

        // Si la SP no tiene cuentas bancarias asociadas, buscar las cuentas del proveedor
        if ($solicitudPago->cuentasBancarias->isEmpty()) {
            $proveedor = $solicitudPago->proveedor;

            $cuentasProveedor = $proveedor->cuentasBancarias
                ->where('estatus', 'activa')
                ->sortByDesc('preferida');

            if ($cuentasProveedor->isNotEmpty()) {
                $cuentaAMostrar = $cuentasProveedor->first();
                $solicitudPago->setRelation('cuentasBancarias', collect([$cuentaAMostrar]));
            }
        }

        return $this->success(
            new ConstruccSolicitudPagoResource($solicitudPago)
        );
    }

    /**
     * Listado segmentado de solicitudes NO verificadas
     */
    public function indexNoVerificadas(Request $request): JsonResponse
    {
        $usuarioId = (int) $request->input('usuario_id');
        $usuarioNivel = (int) $request->input('usuario_nivel');
        $empresaId = $request->input('empresa_construcc_id');

        $NIVEL_RO = 6;

        /**
         * 🔹 BASE QUERY
         */
        $baseQuery = SolicitudPago::query()
            ->with(SolicitudPago::eagerLodable())
            ->where('verificada', false);

        // 🔥 Filtro por empresa
        if (!empty($empresaId)) {
            $baseQuery->where('empresa_construcc_id', $empresaId);
        }

        /**
         * 🔥 SEGMENTOS
         */
        $segmentDefs = [

            // 🔹 Mis SP (solo del usuario)
            'mis_sp' => fn($q) =>
            $q->where('usuario_id', $usuarioId),

            // 🔹 SP por validar (de otros usuarios)
            'por_validar' => fn($q) =>
            $q->when($usuarioNivel !== $NIVEL_RO, function ($q) use ($usuarioId) {
                $q->where('usuario_id', '!=', $usuarioId);
            }),

            // 🔹 Rechazadas (últimas 10)
            'rechazadas' => fn($q) =>
            $q->where('estado_solicitud', EstadoSP::RECHAZADA->value)
                ->latest('fecha_rechazo')
                ->limit(10),
        ];

        /**
         * 🔥 EJECUCIÓN
         */
        $data = [];

        foreach ($segmentDefs as $key => $callback) {
            $data[$key] = ConstruccSolicitudPagoResource::collection(
                $callback(clone $baseQuery)->get()
            );
        }

        return $this->success($data);
    }


    /**
     * Listado segmentado de solicitudes NO verificadas
     */
    public function indexNoVerificadasParaRO(Request $request): JsonResponse
    {
        $usuarioId = (int) $request->input('usuario_id');
        $empresaId = $request->input('empresa_construcc_id');

        /**
         * 🔹 BASE QUERY
         */
        $baseQuery = SolicitudPago::query()
            ->with(SolicitudPago::eagerLodable())
            ->where('empresa_construcc_id', $empresaId)
            ->where('usuario_id', $usuarioId);

        /**
         * 🔥 SEGMENTOS
         */
        $segmentDefs = [

            'por_validar' => fn($q) =>
            $q->where('verificada', false)
                ->where('estado_solicitud', EstadoSP::PENDIENTE->value),

            'validadas' => fn($q) =>
            $q->where('verificada', true)
                ->where('estado_solicitud', EstadoSP::PENDIENTE->value),

            'rechazadas' => fn($q) =>
            $q->where('estado_solicitud', EstadoSP::RECHAZADA->value)
                ->latest('fecha_rechazo')
                ->limit(10),
        ];

        /**
         * 🔥 EJECUCIÓN
         */
        $data = [];

        foreach ($segmentDefs as $key => $callback) {
            $data[$key] = ConstruccSolicitudPagoResource::collection(
                $callback(clone $baseQuery)->get()
            );
        }

        return $this->success($data);
    }

    /**
     * Listado segmentado de solicitudes NO verificadas
     */
    public function indexNoVerificadasParaDirectores(Request $request): JsonResponse
    {
        $usuarioId = (int) $request->input('usuario_id');
        $empresaId = $request->input('empresa_construcc_id');

        /**
         * 🔹 BASE QUERY
         */
        $baseQuery = SolicitudPago::query()
            ->with(SolicitudPago::eagerLodable())
            ->where('empresa_construcc_id', $empresaId);

        /**
         * 🔥 SEGMENTOS
         */
        $segmentDefs = [

            // 🔹 SP del usuario
            'mis_sp' => fn($q) =>
            $q
                ->where('usuario_id', $usuarioId)
                ->where('verificada', false)
                ->where('estado_solicitud', EstadoSP::PENDIENTE->value),

            // 🔹 SP de la empresa sin validar
            'por_validar' => fn($q) =>
            $q
                ->where('usuario_id', '!=', $usuarioId)
                ->where('verificada', false)
                ->where('estado_solicitud', EstadoSP::PENDIENTE->value),

            // 🔹 Rechazadas de la empresa
            'rechazadas' => fn($q) =>
            $q
                ->where('estado_solicitud', EstadoSP::RECHAZADA->value)
                ->latest('fecha_rechazo')
                ->limit(10),
        ];

        /**
         * 🔥 EJECUCIÓN
         */
        $data = [];

        foreach ($segmentDefs as $key => $callback) {
            $data[$key] = ConstruccSolicitudPagoResource::collection(
                $callback(clone $baseQuery)->get()
            );
        }

        return $this->success($data);
    }



    /**
     * Marcar una solicitud de pago como verificada
     * Solo el usuario construcción correspondiente puede marcar su SP como verificada
     * Envía los datos de la SP y el usuario al servidor inter API
     * 
     * Para niveles directivos (1-DG, 2-DT, 3-DA, 5-PC) también marca como autorizada para ese rol
     */
    public function marcarComoVerificada(Request $request, SolicitudPago $solicitudPago): JsonResponse
    {
        // Log::info('SP-marcarComoVerificada: ', [
        //     'solicitud_pago_id' => $solicitudPago->id,
        //     'payload' => $request->all(),
        // ]);

        // Validación
        $validated = $request->validate([
            'usuario_id' => ['required', 'integer'],
            'nivel_id' => ['required', 'integer'],
            'empresa_id' => ['required'],
            'obra_id' => ['nullable', 'integer'],
            'tipo' => ['nullable', 'string'],
            'tipo_id' => ['nullable', 'integer'],
            'notas' => ['nullable', 'string'],
            'utilizara' => ['nullable', 'string'],
            'equipo' => ['nullable', 'string'],
            'equipo_id' => ['nullable', 'integer'],
            //  
            'monto_autorizado' => ['nullable', 'numeric'],
            'notas_autorizacion' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            $updateData = [
                'verificada' => true,
            ];

            // Si se proporciona un monto autorizado, se actualiza el monto autorizado y se registra el usuario que autorizó.
            if ($request->has('monto_autorizado')) {
                $updateData['ro_monto'] = $request->monto_autorizado;
                $updateData['ro_usuario_id'] = $request->usuario_id;
                $updateData['ro_validacion_fecha'] = now();
                $updateData['ro_motivo'] = $request->notas_autorizacion;
            }

            foreach (['tipo', 'tipo_id', 'obra_id', 'notas', 'utilizara', 'equipo', 'equipo_id'] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }

            // Niveles que al validar pasan la SP a autorizada.
            $nivelToRol = [0 => 'dg', 1 => 'dg', 2 => 'dt', 3 => 'da', 4 => 'si', 5 => 'pc',];

            if (array_key_exists($request->nivel_id, $nivelToRol)) {
                $rolField = $nivelToRol[$request->nivel_id];
                $fechaField = "{$rolField}_fecha";

                // SI (nivel 4) solo verifica, no autoriza
                if ($request->nivel_id !== 4) {
                    $updateData[$rolField] = EstadoSolicitud::AUTORIZADA->value;
                    $updateData[$fechaField] = now();
                    $updateData['estado_solicitud'] = EstadoSP::AUTORIZADA->value;
                }
            }


            $solicitudPago->update($updateData);

            $result = $this->interApiService->spNotifyByValidator(
                $solicitudPago->id,
                $solicitudPago->numero_folio_solicitud,
                $request->empresa_id,
                $request->usuario_id,
            );

            /**
             * TODO: Notioficar si la PS no tiene fgactura
             */

            if ($result['success']) {
                Log::info('✅ InterAPI respondió correctamente', ['solicitud_pago_id' => $solicitudPago->id, 'response' => $result,]);
            } else {
                Log::warning('⚠️ InterAPI respondió con error', ['solicitud_pago_id' => $solicitudPago->id, 'response' => $result,]);
            }

            DB::commit();

            $mensaje = 'Solicitud de pago marcada como verificada correctamente.';
            if (array_key_exists($request->nivel_id, $nivelToRol)) {
                $mensaje .= ' Autorizada por ' . strtoupper($nivelToRol[$request->nivel_id]) . '.';
            }

            return $this->success(
                new ConstruccSolicitudPagoResource(
                    $solicitudPago->fresh()->load(SolicitudPago::eagerLodable())
                ),
                $mensaje
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('❌ Error crítico al verificar SP', [
                'solicitud_pago_id' => $solicitudPago->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->error(
                'Error al procesar la verificación de la solicitud.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }



    /**
     * Marcar una solicitud de pago como rechazada durante verificación
     * Cambia el estado a RECHAZADA y marca verificada como false
     * Esto evita que la SP se liste para los directivos
     */
    public function marcarComoRechazada(Request $request, SolicitudPago $solicitudPago): JsonResponse
    {
        // Log::info('✅ Antes de validate: ', [
        //     'solicitud_pago_id' => $solicitudPago->id,
        //     'folio' => $solicitudPago->numero_folio_solicitud,
        //     'proveedor_id' => $solicitudPago->proveedor->id,
        // ]);

        // Validar que se proporcione un motivo de rechazo
        $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ]);

        // Actualizar el estado de la SP
        $solicitudPago->update([
            'estado_solicitud' => EstadoSP::RECHAZADA->value,
            'verificada' => false,
            'motivo_rechazo' => $request->motivo_rechazo,
            'fecha_rechazo' => now(),
        ]);

        // Enviar notificación al proveedor
        try {
            $proveedor = $solicitudPago->proveedor;
            $usuarioPrincipal = $proveedor->usuarioPrincipal();

            if ($usuarioPrincipal) {
                // Contar notificaciones antes de enviar
                $countBefore = $usuarioPrincipal->notifications()->count();

                // Enviar notificación
                $usuarioPrincipal->notify(new SolicitudPagoRechazadaSinAutorizacionNotification(
                    $solicitudPago->numero_folio_solicitud,
                    $solicitudPago->id,
                    $proveedor->id,
                    $request->motivo_rechazo,
                    $usuarioPrincipal->id,
                    $solicitudPago->verificada ? 1 : 0
                ));

                // Obtener la notificación recién creada (debe ser la única nueva)
                $notificationId = $usuarioPrincipal->notifications()
                    ->skip($countBefore)
                    ->take(1)
                    ->value('id');

                // Guardar notification_id en la SP
                if ($notificationId) {
                    $solicitudPago->update(['notification_id' => $notificationId]);
                }

                // Log::info('✅ Notificación de SP Rechazada Sin Autorización enviada', [
                //     'solicitud_pago_id' => $solicitudPago->id,
                //     'folio' => $solicitudPago->numero_folio_solicitud,
                //     'proveedor_id' => $proveedor->id,
                //     'usuario_id' => $usuarioPrincipal->id,
                // ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error al enviar notificación de SP Rechazada Sin Autorización', [
                'solicitud_pago_id' => $solicitudPago->id,
                'error' => $e->getMessage(),
            ]);
            // No fallar la operación si la notificación falla
        }

        return $this->success(
            new ConstruccSolicitudPagoResource($solicitudPago->fresh()->load(SolicitudPago::eagerLodable())),
            'Solicitud de pago rechazada correctamente durante verificación.'
        );
    }

    /**
     * Autorizar una solicitud de pago por rol específico
     * Roles: DG, DT, PC, SI
     *
     * Reglas:
     * - Solo se puede autorizar si la SP está PENDIENTE
     * - DG puede autorizar directamente (pasa a estado compuesto)
     * - DT, PC, SI autorizan individualmente
     * - Si todos los roles [DG, DT, PC, SI] autorizan, pasa a AUTORIZADA
     */
    public function autorizar(SolicitudPagoAutorizarRequest $request, SolicitudPago $solicitudPago): JsonResponse
    {
        $data = $request->validated();
        $rol = strtoupper(trim($data['rol']));

        // Mapeo explícito de roles
        $rolMap = [
            'DG' => 'dg',
            'DT' => 'dt',
            'PC' => 'pc',
            'SI' => 'si',
        ];

        if (! isset($rolMap[$rol])) {
            return $this->error('Rol no válido.', null, 400);
        }

        /**
         * No permite autorizar a mas de un director
         */
        // // 1️⃣ Solo si está PENDIENTE
        // if ($solicitudPago->estado_solicitud !== EstadoSP::PENDIENTE->value) {
        //     return $this->error(
        //         'Solo se pueden autorizar solicitudes en estado PENDIENTE.',
        //         null,
        //         400
        //     );
        // }

        $rolField = $rolMap[$rol];
        $fechaField = "{$rolField}_fecha";

        // 2️⃣ Evitar doble autorización
        if ($solicitudPago->$rolField === EstadoSolicitud::AUTORIZADA->value) {
            return $this->error('Este rol ya autorizó esta solicitud.', null, 400);
        }

        // 3️⃣ Autorizar rol + cambiar estado general
        $solicitudPago->update([
            $rolField => EstadoSolicitud::AUTORIZADA->value,
            $fechaField => now(),
            'estado_solicitud' => EstadoSP::AUTORIZADA->value,
        ]);


        $this->interApiService->spAutorizarNotify($solicitudPago);

        return $this->success(
            new ConstruccSolicitudPagoResource(
                $solicitudPago->fresh()->load(SolicitudPago::eagerLodable())
            ),
            "Solicitud autorizada correctamente por {$rol}."
        );
    }

    /**
     * Autorizar un monto parcial de una solicitud de pago
     * 
     * Este endpoint permite autorizar un monto menor al total de la solicitud.
     * Casos de uso: Flujo de caja limitado, pagos escalonados, aprobación por etapas.
     * 
     * Reglas:
     * - monto_autorizado debe ser > 0
     * - monto_autorizado debe ser <= monto_total
     * - notas_autorizacion es obligatorio (mínimo 10 caracteres)
     * - El usuario debe tener permiso para autorizar (DG, DT, PC, SI)
     * - Marca el nivel correspondiente como autorizado
     * - La solicitud pasa a estado AUTORIZADA
     * 
     * @param SolicitudPagoAutorizarParcialRequest $request
     * @param SolicitudPago $solicitudPago
     * @return JsonResponse
     */
    public function autorizarParcial(SolicitudPagoAutorizarParcialRequest $request, SolicitudPago $solicitudPago): JsonResponse
    {
        $data = $request->validated();
        $rol = strtoupper(trim($data['rol']));
        $montoAutorizado = $data['monto_autorizado'];
        $notasAutorizacion = $data['notas_autorizacion'];
        $usuarioId = $data['usuario_id'];
        $usuarioNombre = $data['usuario_nombre'];

        // Mapeo explícito de roles
        $rolMap = [
            'DG' => 'dg',
            'DT' => 'dt',
            'PC' => 'pc',
            'DA' => 'da',
        ];

        if (! isset($rolMap[$rol])) {
            return $this->error('Rol no válido.', null, 400);
        }

        // Validar que el monto autorizado no exceda el monto total
        if ($montoAutorizado > $solicitudPago->monto_total) {
            return $this->error(
                'El monto autorizado no puede ser mayor al monto total de la solicitud.',
                [
                    'monto_total' => $solicitudPago->monto_total,
                    'monto_autorizado' => $montoAutorizado
                ],
                400
            );
        }

        $rolField = $rolMap[$rol];
        $fechaField = "{$rolField}_fecha";

        // Evitar doble autorización
        if ($solicitudPago->$rolField === EstadoSolicitud::AUTORIZADA->value) {
            return $this->error('Este rol ya autorizó esta solicitud.', null, 400);
        }

        // Autorizar rol + registrar monto parcial + cambiar estado general
        $solicitudPago->update([
            // Marcar nivel como autorizado
            $rolField => EstadoSolicitud::AUTORIZADA->value,
            $fechaField => now(),
            'estado_solicitud' => EstadoSP::AUTORIZADA->value,

            // Registrar datos de autorización parcial
            'monto_autorizado' => $montoAutorizado,
            'usuario_autorizo_parcial_id' => $usuarioId,
            'usuario_autorizo_parcial_nombre' => $usuarioNombre,
            'motivo_autorizacion_parcial' => $notasAutorizacion,
            'fecha_autorizacion_parcial' => now(),
        ]);

        // Log::info('✅ Solicitud autorizada con monto parcial', [
        //     'solicitud_pago_id' => $solicitudPago->id,
        //     'folio' => $solicitudPago->numero_folio_solicitud,
        //     'rol' => $rol,
        //     'monto_total' => $solicitudPago->monto_total,
        //     'monto_autorizado' => $montoAutorizado,
        //     'usuario_id' => $usuarioId,
        //     'usuario_nombre' => $usuarioNombre,
        // ]);

        $this->interApiService->spAutorizarNotify($solicitudPago);

        return $this->success(
            new ConstruccSolicitudPagoResource(
                $solicitudPago->fresh()->load(SolicitudPago::eagerLodable())
            ),
            "Solicitud autorizada con monto parcial de $" . number_format($montoAutorizado, 2) . " por {$rol}."
        );
    }

    /**
     * Rechazar una solicitud de pago por rol específico
     *
     * Reglas:x
     * - La SP se puede rechazar eb cualquier momento antes de PAGADO por los roles: DG, DT, PC, SI, DA
     * - DG puede rechazar en cualquier momento antes de PAGADO
     */
    public function rechazar(SolicitudPagoRechazarRequest $request, SolicitudPago $solicitudPago): JsonResponse
    {
        $data = $request->validated();

        $rol = strtoupper($data['rol']);
        $estadoActual = $solicitudPago->estado_solicitud;

        if ($estadoActual === EstadoSP::RECHAZADA->value) {
            return $this->success($solicitudPago, 'La solicitud ya está rechazada.');
        }

        // Validaciones según el rol
        $rolesPermitidos = ['DG', 'DT', 'PC', 'SI', 'DA'];

        if (!in_array($rol, $rolesPermitidos, true)) {
            return $this->error('Rol no autorizado para rechazar la solicitud.', null, 403);
        }

        // Ningún rol puede rechazar si ya está PAGADO
        if ($estadoActual === EstadoSP::PAGADO->value) {
            return $this->error(
                'No se pueden rechazar solicitudes en estado PAGADO.',
                null,
                400
            );
        }

        // Actualizar estado y registrar quién rechazó
        $rolField = strtolower($rol === 'PC' ? 'pc' : $rol);
        $fechaField = $rolField . '_fecha';

        $solicitudPago->update([
            'estado_solicitud' => EstadoSP::RECHAZADA->value,
            'motivo_rechazo' => $data['motivo_rechazo'],
            'fecha_rechazo' => now(),
            $rolField => EstadoSolicitud::RECHAZADA->value,
            $fechaField => now(),
        ]);

        // Enviar notificación al proveedor
        try {
            $proveedor = $solicitudPago->proveedor;
            $usuarioPrincipal = $proveedor->usuarioPrincipal();

            if ($usuarioPrincipal) {
                // Contar notificaciones antes de enviar
                $countBefore = $usuarioPrincipal->notifications()->count();

                // Enviar notificación
                $usuarioPrincipal->notify(new SolicitudPagoRechazadaNotification(
                    $solicitudPago->numero_folio_solicitud,
                    $solicitudPago->id,
                    $proveedor->id,
                    $data['motivo_rechazo'],
                    $usuarioPrincipal->id
                ));

                // Obtener la notificación recién creada (debe ser la única nueva)
                $notificationId = $usuarioPrincipal->notifications()
                    ->skip($countBefore)
                    ->take(1)
                    ->value('id');

                // Guardar notification_id en la SP
                if ($notificationId) {
                    $solicitudPago->update(['notification_id' => $notificationId]);
                }

                // Log::info('✅ Notificación de SP Rechazada enviada', [
                //     'solicitud_pago_id' => $solicitudPago->id,
                //     'folio' => $solicitudPago->numero_folio_solicitud,
                //     'proveedor_id' => $proveedor->id,
                //     'usuario_id' => $usuarioPrincipal->id,
                // ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error al enviar notificación de SP Rechazada', [
                'solicitud_pago_id' => $solicitudPago->id,
                'error' => $e->getMessage(),
            ]);
            // No fallar la operación si la notificación falla
        }

        return $this->success(
            new ConstruccSolicitudPagoResource($solicitudPago->fresh()->load(SolicitudPago::eagerLodable())),
            "Solicitud rechazada correctamente por {$rol}."
        );
    }

    /**
     * Confirmar pago de una solicitud (completo o parcial)
     * Solo DA puede confirmar pagos y debe subir comprobante
     * Solo es posible si la SP cumple con las reglas de autorización:
     *  - DG aprobó (tiene fuerza mayor) O al menos uno de DT/PC/SI aprobó
     *  - No debe tener roles rechazados
     *  - Debe estar en estado AUTORIZADA o PAGADO (pagos parciales)
     *  - No debe estar completamente pagada
     */
    public function confirmarPago(SolicitudPagoConfirmarPagoRequest $request, SolicitudPago $solicitudPago): JsonResponse
    {
        // Log::info('🟢 PAGO-SP Carga util de la peticion: ', [
        //     'rol' => $request->rol,
        //     'monto_pagado' => $request->monto_pagado,
        //     'comprobante' => $request->comprobante,
        //     'observaciones' => $request->observaciones,
        //     'cuenta_bancaria_empresa_construcc_id' => $request->cuenta_bancaria_empresa_construcc_id,
        //     // 
        //     'fecha_comprobante_pago' => $request->fecha,
        //     'fecha_pago' =>  now(),
        //     'hora' => $request->hora,
        //     'nombre_beneficiario' => $request->nombre_beneficiario,
        //     'clave_rastreo' => $request->clave_rastreo,
        //     'banco' => $request->banco,
        // ]);

        // Log::info('🟢 PAGO-SP: Solicitud de confirmación de pago recibida', [
        //     'solicitud_pago_id' => $solicitudPago->id,
        //     'folio' => $solicitudPago->numero_folio_solicitud,
        //     'estado_actual' => $solicitudPago->estado_solicitud,
        //     'usuario_id' => $solicitudPago->usuario_id,
        //     'empresa_id' => $solicitudPago->empresa_construcc_id,
        // ]);

        /**
         * Campos de la peticion
         *  - rol
         *  - monto_pagado
         *  - comprobante
         *  - observaciones
         *  - cuenta_bancaria_empresa_construcc_id
         */
        $data = $request->validated();

        // 2. Verificar que no tenga roles rechazados
        if ($solicitudPago->estado_solicitud === EstadoSP::RECHAZADA->value) {
            return $this->error(
                'No se puede confirmar el pago porque uno o más roles han rechazado la solicitud.',
                null,
                400
            );
        }

        // 3. Verificar que esté en estado válido (AUTORIZADA o PAGADO con pagos parciales)
        if (! in_array(
            $solicitudPago->estado_solicitud,
            [
                // EstadoSP::PENDIENTE->value,
                // EstadoSP::PAGADO->value,
                EstadoSP::AUTORIZADA->value
            ]
        )) {
            return $this->error(
                'Solo se pueden confirmar pagos de solicitudes AUTORIZADAS.',
                null,
                400
            );
        }

        // Guardar comprobante
        $path = $request->file('comprobante')->store('comprobantes', 'private');

        //  Falta realizar revision del tema.
        $estadoFinal = EstadoSP::PAGADO->value;
        $estadoDA = EstadoSolicitud::PAGADO->value;


        // $fechaPago = Carbon::createFromFormat(
        //     'Y-m-d H:i:s',
        //     trim($request->fecha . ' ' . $request->hora)
        // );

        // Log::info(
        //     '🟢 PAGO-SP: SP actualizada',
        //     [
        //         'cuenta_bancaria_empresa_construcc_id' => $request->cuenta_bancaria_empresa_construcc_id,
        //         'estado_solicitud' => $estadoFinal,
        //         'ruta_archivo_comprobante_pago' => $path,
        //         'notas_abono' => $request->observaciones,
        //         'monto_pagado' => $request->monto_pagado,
        //         'da' => $estadoDA,
        //         'da_fecha' => now(),
        //         // datos comprobante
        //         'fecha_comprobante_pago' => $request->fecha_hora_pago,
        //         'fecha_pago' =>  now(),
        //         'nombre_beneficiario_pago' => $request->nombre_beneficiario,
        //         'clave_rastreo_pago' => $request->clave_rastreo,
        //         'banco_pago' => $request->banco,
        //     ]
        // );

        // Actualizar solicitud
        $solicitudPago->update([
            'cuenta_bancaria_empresa_construcc_id' => $request->cuenta_bancaria_empresa_construcc_id,
            'estado_solicitud' => $estadoFinal,
            'ruta_archivo_comprobante_pago' => $path,
            'fecha_pago' =>  now(),
            'notas_abono' => $request->observaciones,
            'monto_pagado' => $request->monto_pagado,
            'da' => $estadoDA,
            'da_fecha' => now(),

            // datos comprobante
            'fecha_comprobante_pago' => $request->fecha_hora_pago,
            'nombre_beneficiario_pago' => $request->nombre_beneficiario,
            'clave_rastreo_pago' => $request->clave_rastreo,
            'banco_pago' => $request->banco,

        ]);

        // Enviar notificación al proveedor
        try {
            $proveedor = $solicitudPago->proveedor;
            $usuarioPrincipal = $proveedor->usuarioPrincipal();

            if ($usuarioPrincipal) {
                $usuarioPrincipal->notify(new SolicitudPagoPagadaNotification(
                    $solicitudPago->numero_folio_solicitud,
                    $solicitudPago->id,
                    $proveedor->id,
                    $usuarioPrincipal->id
                ));

                // Notificacion de SP Pagada
                $data = [
                    'sp_id' => $solicitudPago->id,
                    'sp_folio' => $solicitudPago->numero_folio_solicitud,
                    'company_id' => $solicitudPago->empresa_construcc_id,
                    'folio_factura' => $solicitudPago->folio_factura,
                    'proveedor' => $proveedor->nombre_comercial,
                    'monto' => $solicitudPago->monto_total,
                    'fecha_pago' => $solicitudPago->fecha_pago,
                    'user_id' => $solicitudPago->usuario_id,
                ];

                // Log::info('🟢 PAGO-SP: Notificación de SP Pagada - Datos preparados', $data);

                $result = $this->interApiService->spPagoNotify($data);

                if ($result['success']) {
                    Log::info('✅ InterAPI respondió correctamente', ['solicitud_pago_id' => $solicitudPago->id, 'response' => $result,]);
                } else {
                    Log::warning('⚠️ InterAPI respondió con error', ['solicitud_pago_id' => $solicitudPago->id, 'response' => $result,]);
                }

                // Log::info('✅ Notificación de SP Pagada enviada', [
                //     'solicitud_pago_id' => $solicitudPago->id,
                //     'folio' => $solicitudPago->numero_folio_solicitud,
                //     'proveedor_id' => $proveedor->id,
                //     'usuario_id' => $usuarioPrincipal->id,
                //     'usuario_idcuenta_bancaria_empresa_construcc_id' => $solicitudPago->cuenta_bancaria_empresa_construcc_id,
                //     // 'monto' => $montoAbono,
                //     // 'pago_completo' => $pagoCompleto,
                // ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error al enviar notificación de SP Pagada', [
                'solicitud_pago_id' => $solicitudPago->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success(
            new ConstruccSolicitudPagoResource($solicitudPago->fresh()->load(SolicitudPago::eagerLodable())),
            'Pago completado correctamente. La solicitud ha sido pagada en su totalidad.'
        );
    }

    /**
     * Descargar comprobante de pago
     */
    public function descargarComprobante(SolicitudPago $solicitudPago)
    {
        if (
            ! $solicitudPago->ruta_archivo_comprobante_pago ||
            ! Storage::disk('private')->exists($solicitudPago->ruta_archivo_comprobante_pago)
        ) {
            return $this->error('Comprobante no disponible', null, 404);
        }

        return response()->download(
            Storage::disk('private')->path($solicitudPago->ruta_archivo_comprobante_pago)
        );
    }

    /**
     * Descargar factura PDF
     */
    public function descargarFacturaPdf(SolicitudPago $solicitudPago)
    {
        if (
            ! $solicitudPago->ruta_archivo_factura_pdf ||
            ! Storage::disk('private')->exists($solicitudPago->ruta_archivo_factura_pdf)
        ) {
            return $this->error('Factura PDF no disponible', null, 404);
        }

        return response()->download(
            Storage::disk('private')->path($solicitudPago->ruta_archivo_factura_pdf)
        );
    }

    /**
     * Descargar factura XML
     */
    public function descargarFacturaXml(SolicitudPago $solicitudPago)
    {
        if (
            ! $solicitudPago->ruta_archivo_factura_xml ||
            ! Storage::disk('private')->exists($solicitudPago->ruta_archivo_factura_xml)
        ) {
            return $this->error('Factura XML no disponible', null, 404);
        }

        return response()->download(
            Storage::disk('private')->path($solicitudPago->ruta_archivo_factura_xml)
        );
    }

    /**
     * Descargar cotización
     */
    public function descargarCotizacion(SolicitudPago $solicitudPago)
    {
        if (
            ! $solicitudPago->ruta_archivo_cotizacion ||
            ! Storage::disk('private')->exists($solicitudPago->ruta_archivo_cotizacion)
        ) {
            return $this->error('Cotización no disponible', null, 404);
        }

        return response()->download(
            Storage::disk('private')->path($solicitudPago->ruta_archivo_cotizacion)
        );
    }

    /**
     * Listar solicitudes pendientes para un rol específico
     * Muestra solo las SP que necesitan acción de ese rol
     */
    public function listarPorRol(Request $request): JsonResponse
    {
        $request->validate([
            'rol' => ['required', 'string', Rule::in(['DG', 'DT', 'PC', 'SI', 'DA', 'RO'])],
        ]);

        $rol = strtoupper($request->rol);
        $rolField = strtolower($rol === 'PC' ? 'pc' : $rol);

        $filters = $request->only(SolicitudPago::getFilters());
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');
        $perPage = $request->input('per_page', 10);

        $query = SolicitudPago::query()->with(SolicitudPago::eagerLodable());

        // Filtrar según el rol
        if ($rol === 'DA') {
            // DA ve las solicitudes AUTORIZADAS para confirmar pago
            // O las que ya tienen pagos parciales (estado PAGADO pero no pago_completo)
            $query->where('estado_solicitud', '!=', EstadoSP::AUTORIZADA->value)
                ->where(function ($q) {
                    $q->where('dg', EstadoSolicitud::AUTORIZADA->value)
                        ->orWhere('dt', EstadoSolicitud::AUTORIZADA->value)
                        ->orWhere('pc', EstadoSolicitud::AUTORIZADA->value)
                        ->orWhere('si', EstadoSolicitud::AUTORIZADA->value);
                })
                ->where('pago_completo', false);
        } elseif ($rol === 'RO') {
            $query->where(function ($q) {
                $q->where('estado_solicitud', EstadoSP::PENDIENTE->value);
                //  TODO : Filtar las que peretenencen a ese RO
            });
        } elseif ($rol === 'DG') {
            // Si es DG, mostrar todas las pendientes independientemente de otros roles
            // Si es DT, PC, SI - no mostrar las que ya autorizó DG (para evitar duplicados)
            $query->where(function ($q) {
                $q->where('dg', '!=', EstadoSolicitud::AUTORIZADA->value)
                    ->orWhereNull('dg')
                    ->orWhere('dg', EstadoSolicitud::PENDIENTE->value);
            });
        } elseif ($rol === 'DT') {
            // Si es DG, mostrar todas las pendientes independientemente de otros roles
            // Si es DT, PC, SI - no mostrar las que ya autorizó DG (para evitar duplicados)
            $query->where(function ($q) {
                $q->where('dt', '!=', EstadoSolicitud::AUTORIZADA->value)
                    ->orWhereNull('dt')
                    ->orWhere('dt', EstadoSolicitud::PENDIENTE->value);
            });
        } elseif ($rol === 'PC') {
            // Si es DG, mostrar todas las pendientes independientemente de otros roles
            // Si es DT, PC, SI - no mostrar las que ya autorizó DG (para evitar duplicados)
            $query->where(function ($q) {
                $q->where('pc', '!=', EstadoSolicitud::AUTORIZADA->value)
                    ->orWhereNull('pc')
                    ->orWhere('pc', EstadoSolicitud::PENDIENTE->value);
            });
        } elseif ($rol === 'SI') {
            // Si es DG, mostrar todas las pendientes independientemente de otros roles
            // Si es DT, PC, SI - no mostrar las que ya autorizó DG (para evitar duplicados)
            $query->where(function ($q) {
                $q->where('si', '!=', EstadoSolicitud::AUTORIZADA->value)
                    ->orWhereNull('si')
                    ->orWhere('si', EstadoSolicitud::PENDIENTE->value);
            });
        }

        $query->filter($filters)->orderBy($sortBy, $order);
        $paginator = $query->paginate($perPage);

        return $this->paginated(
            $paginator->setCollection(
                ConstruccSolicitudPagoResource::collection($paginator)->collection
            ),
            "Solicitudes para rol {$rol}"
        );
    }

    /**
     * Listar solicitudes por estado específico
     */
    public function listarPorEstado(Request $request): JsonResponse
    {
        $request->validate([
            'estado' => ['required', 'string', Rule::in(['PENDIENTE', 'AUTORIZADA', 'RECHAZADA', 'PAGADO'])],
        ]);

        $estado = strtoupper($request->estado);
        $estadoEnum = EstadoSP::from(strtolower($estado));

        $filters = $request->only(SolicitudPago::getFilters());
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');
        $perPage = $request->input('per_page', 10);

        $query = SolicitudPago::query()
            ->with(SolicitudPago::eagerLodable())
            ->where('estado_solicitud', $estadoEnum->value)
            ->filter($filters)
            ->orderBy($sortBy, $order);

        $paginator = $query->paginate($perPage);

        return $this->paginated(
            $paginator->setCollection(
                ConstruccSolicitudPagoResource::collection($paginator)->collection
            ),
            "Solicitudes en estado {$estado}"
        );
    }

    /**
     * Dashboard con estadísticas por rol
     */
    public function estadisticasPorRol(Request $request): JsonResponse
    {
        $request->validate([
            'rol' => ['nullable', 'string', Rule::in(['DG', 'DT', 'PC', 'SI', 'DA'])],
        ]);

        $rol = $request->rol ? strtoupper($request->rol) : null;

        $stats = [
            'pendientes' => 0,
            'autorizadas' => 0,
            'rechazadas' => 0,
            'pagadas_completas' => 0,
            'con_pagos_parciales' => 0,
            'monto_total_pendiente' => 0,
            'monto_total_autorizado' => 0,
            'monto_total_pagado' => 0,
        ];

        if ($rol) {
            $rolField = strtolower($rol === 'PC' ? 'pc' : $rol);

            if ($rol === 'DA') {
                $stats['pendientes'] = SolicitudPago::where('estado_solicitud', EstadoSP::AUTORIZADA->value)->count();
                $stats['con_pagos_parciales'] = SolicitudPago::where('estado_solicitud', EstadoSP::PAGADO->value)
                    ->where('pago_completo', false)->count();
            } else {
                $stats['pendientes'] = SolicitudPago::where('estado_solicitud', EstadoSP::PENDIENTE->value)
                    ->where(function ($q) use ($rolField) {
                        $q->where($rolField, EstadoSolicitud::PENDIENTE->value)
                            ->orWhereNull($rolField);
                    })->count();
            }
        }

        // Estadísticas generales
        $stats['pendientes'] = SolicitudPago::where('estado_solicitud', EstadoSP::PENDIENTE->value)->count();
        $stats['autorizadas'] = SolicitudPago::where('estado_solicitud', EstadoSP::AUTORIZADA->value)->count();
        $stats['rechazadas'] = SolicitudPago::where('estado_solicitud', EstadoSP::RECHAZADA->value)->count();
        $stats['pagadas_completas'] = SolicitudPago::where('estado_solicitud', EstadoSP::PAGADO->value)
            ->where('pago_completo', true)->count();
        $stats['con_pagos_parciales'] = SolicitudPago::where('estado_solicitud', EstadoSP::PAGADO->value)
            ->where('pago_completo', false)->count();

        // Montos
        $stats['monto_total_pendiente'] = SolicitudPago::where('estado_solicitud', EstadoSP::PENDIENTE->value)
            ->sum('monto_total');
        $stats['monto_total_autorizado'] = SolicitudPago::where('estado_solicitud', EstadoSP::AUTORIZADA->value)
            ->sum('saldo_pendiente');
        $stats['monto_total_pagado'] = SolicitudPago::sum('monto_abonado');

        return $this->success($stats, 'Estadísticas de solicitudes de pago');
    }

    /**
     * Verificar si todos los roles han autorizado la solicitud
     */
    private function verificarAutorizacionCompleta(SolicitudPago $solicitudPago): bool
    {
        return $solicitudPago->dg === EstadoSolicitud::AUTORIZADA->value &&
            $solicitudPago->dt === EstadoSolicitud::AUTORIZADA->value &&
            $solicitudPago->pc === EstadoSolicitud::AUTORIZADA->value &&
            $solicitudPago->si === EstadoSolicitud::AUTORIZADA->value;
    }

    private function verificarAutorizacionDeAlmenosUno(SolicitudPago $solicitudPago): bool
    {
        $autorizado = EstadoSolicitud::AUTORIZADA->value;

        // DG tiene fuerza mayor - si DG autoriza, es suficiente
        if ($solicitudPago->dg === $autorizado) {
            return true;
        }

        // Al menos uno de DT, PC o SI debe haber autorizado
        return $solicitudPago->dt === $autorizado ||
            $solicitudPago->pc === $autorizado ||
            $solicitudPago->si === $autorizado;
    }

    /**
     * Listar proveedores asociados a una empresa constructora
     * Opcionalmente filtra por usuario_construcc_id mediante parámetro GET
     */
    public function proveedoresPorEmpresa(Request $request, $empresaId): JsonResponse
    {
        $usuarioConstruccId = $request->input('usuario_construcc_id');

        $proveedores = \App\Models\Proveedor::query()

            // 🔥 Mantienes tu exclusión
            // ->where(function ($q) {
            //     $q->where('tipo_alta', '!=', 2)
            //         ->orWhereNull('tipo_alta');
            // })

            // 🔥 AQUÍ está la magia: mismo patrón que index
            ->where(function ($q) use ($empresaId, $usuarioConstruccId) {

                // 1. Proveedores asociados/enlazados
                $q->whereHas('empresasConstrucc', function ($rel) use ($empresaId, $usuarioConstruccId) {
                    $rel->where('empresa_construcc_id', $empresaId);

                    if ($usuarioConstruccId) {
                        $rel->where('usuario_construcc_id', $usuarioConstruccId);
                    }
                });

                // 2. Proveedores dados de alta por la empresa
                $q->orWhere(function ($alta) use ($empresaId, $usuarioConstruccId) {
                    $alta->where('empresa_construcc_alta', $empresaId);

                    if ($usuarioConstruccId) {
                        $alta->where('user_construcc_alta', $usuarioConstruccId);
                    }
                });
            })

            ->select('id', 'nombre_comercial', 'razon_social', 'rfc')
            ->orderBy('nombre_comercial')
            ->get();

        if ($proveedores->isEmpty()) {
            return $this->error('No se encontraron proveedores asociados a esta empresa.', null, 200);
        }

        return $this->success($proveedores, 'Proveedores asociados a la empresa constructora.');
    }

    /**
     * Listar proveedores NO asociados a una empresa constructora
     * Opcionalmente filtra por usuario_construcc_id mediante parámetro GET
     * Si se proporciona usuario_construcc_id, muestra proveedores que NO están asociados a esa combinación empresa-usuario
     */
    public function proveedoresNoAsociadosPorEmpresa(Request $request, $empresaId): JsonResponse
    {
        $usuarioConstruccId = $request->input('usuario_construcc_id');

        $proveedores = \App\Models\Proveedor::query()
            ->whereDoesntHave('empresasConstrucc', function ($q) use ($empresaId, $usuarioConstruccId) {
                $q->where('empresa_construcc_id', $empresaId);

                // Filtrar por usuario si se proporciona el parámetro
                if ($usuarioConstruccId) {
                    $q->where('usuario_construcc_id', $usuarioConstruccId);
                }
            })
            ->select('id', 'nombre_comercial', 'razon_social', 'rfc')
            ->orderBy('nombre_comercial')
            ->get();

        if ($proveedores->isEmpty()) {
            return $this->error('No se encontraron proveedores disponibles para asociar a esta empresa.', null, 200);
        }

        return $this->success($proveedores, 'Proveedores disponibles para asociar a la empresa constructora.');
    }

    /**
     * Asociar un proveedor a una empresa constructora con usuario
     * Siempre crea un nuevo registro, permitiendo múltiples usuarios por relación
     */
    public function asociarProveedorAEmpresa(Request $request, $empresaId): JsonResponse
    {
        $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'usuario_construcc_id' => ['required', 'integer'],
            'usuario_construcc_nombre' => ['required', 'string', 'max:255'],
        ]);

        $proveedorId = $request->input('proveedor_id');
        $usuarioConstruccId = $request->input('usuario_construcc_id');
        $usuarioConstruccNombre = $request->input('usuario_construcc_nombre');

        // Verificar que la empresa constructora exista
        $empresa = \App\Models\EmpresaConstrucc::find($empresaId);
        if (! $empresa) {
            return $this->error('La empresa constructora no existe.', null, 404);
        }

        // Verificar que el proveedor exista
        $proveedor = \App\Models\Proveedor::find($proveedorId);
        if (! $proveedor) {
            return $this->error('El proveedor no existe.', null, 404);
        }

        // Verificar si ya existe la asociación con ESTE usuario específico
        $existeConUsuario = DB::table('empresa_construcc_proveedor')
            ->where('empresa_construcc_id', $empresaId)
            ->where('proveedor_id', $proveedorId)
            ->where('usuario_construcc_id', $usuarioConstruccId)
            ->exists();

        if ($existeConUsuario) {
            return $this->error('Este usuario ya tiene registrada una invitación para este proveedor.', null, 400);
        }

        // Crear nueva asociación con el usuario
        $proveedor->empresasConstrucc()->attach($empresaId, [
            'usuario_construcc_id' => $usuarioConstruccId,
            'usuario_construcc_nombre' => $usuarioConstruccNombre,
        ]);

        // Enviar notificación al proveedor
        try {
            $proveedor->notify(new ProveedorAsociadoAEmpresaNotification(
                $proveedor->id,
                $proveedor->nombre_comercial ?? $proveedor->razon_social,
                $empresa->id,
                $empresa->nombre,
                $empresa->rfc,
                $usuarioConstruccId,
                $usuarioConstruccNombre
            ));
        } catch (\Exception $e) {
            Log::error('Error al enviar notificación de asociación empresa-proveedor', [
                'proveedor_id' => $proveedor->id,
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success(
            [
                'proveedor_id' => $proveedorId,
                'empresa_id' => $empresaId,
                'usuario_construcc_id' => $usuarioConstruccId,
                'usuario_construcc_nombre' => $usuarioConstruccNombre,
            ],
            'Proveedor asociado exitosamente por el usuario.'
        );
    }

    /**
     * Buscar empresas constructoras
     */
    public function empresasConstructoras(Request $request): JsonResponse
    {
        $buscar = $request->input('search', '');
        $limit = min($request->input('limit', 20), 50); // Máximo 50 resultados

        $query = \App\Models\EmpresaConstrucc::query()
            ->select('id', 'nombre', 'rfc', 'razon_social');

        if ($buscar) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('rfc', 'like', "%{$buscar}%")
                    ->orWhere('razon_social', 'like', "%{$buscar}%");
            });
        }

        $empresas = $query->orderBy('nombre')
            ->limit($limit)
            ->get();

        if ($empresas->isEmpty()) {
            return $this->error('No se encontraron empresas constructoras.', null, 200);
        }

        return $this->success($empresas, 'Empresas constructoras encontradas.');
    }

    /**
     * Obtener estadísticas generales
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $stats = [
            'total_solicitudes' => SolicitudPago::count(),
            'pendientes' => SolicitudPago::where('estado_solicitud', EstadoSP::PENDIENTE->value)->count(),
            'autorizadas' => SolicitudPago::where('estado_solicitud', EstadoSP::AUTORIZADA->value)->count(),
            'rechazadas' => SolicitudPago::where('estado_solicitud', EstadoSP::RECHAZADA->value)->count(),
            'pagadas' => SolicitudPago::where('estado_solicitud', EstadoSP::PAGADO->value)->count(),
            'monto_total' => SolicitudPago::sum('monto_total'),
            'monto_pagado' => SolicitudPago::sum('monto_abonado'),
            'monto_pendiente' => SolicitudPago::sum('saldo_pendiente'),
        ];

        return $this->success($stats, 'Estadísticas generales de solicitudes de pago.');
    }

    /**
     * Métricas del dashboard de SP (verificadas)
     * Retorna conteo de solicitudes por estado filtrando por usuario, empresa, proveedor y fechas
     */
    public function dashboardSpMetricasVerificadas(Request $request): JsonResponse
    {
        $filters = $request->only([
            'fecha_registro_pendiente_desde',
            'fecha_registro_pendiente_hasta',
        ]);

        $usuarioId = $request->input('usuario_id');
        // Alias para compatibilidad: empresa_id o empresa_construcc_id
        $empresaId = $request->input('empresa_id', $request->input('empresa_construcc_id'));
        $proveedorId = $request->input('proveedor_id');

        // Base query solo con SP verificadas
        $baseQuery = SolicitudPago::query()
            ->where('verificada', true);

        // Filtros obligatorios/condicionales
        if ($usuarioId !== null && $usuarioId !== '') {
            $baseQuery->where('usuario_id', $usuarioId); // coincidencia exacta
        }

        if ($empresaId !== null && $empresaId !== '') {
            $baseQuery->where('empresa_construcc_id', $empresaId);
        }

        if ($proveedorId !== null && $proveedorId !== '') {
            $baseQuery->where('proveedor_id', $proveedorId);
        }

        // Filtros de fechas (reutilizando el scope filter del modelo)
        if (! empty($filters)) {
            $baseQuery->filter($filters);
        }

        // Conteos por estado
        $conteos = [
            'total_sp' => (clone $baseQuery)->count(),
            'sp_pendientes' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PENDIENTE->value)->count(),
            'sp_autorizadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::AUTORIZADA->value)->count(),
            'sp_rechazadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::RECHAZADA->value)->count(),
            'sp_pagadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PAGADO->value)->count(),
        ];

        return $this->success($conteos, 'Métricas de solicitudes de pago verificadas obtenidas correctamente');
    }

    /**
     * Métricas del dashboard de SP (no verificadas)
     * Retorna conteo de solicitudes por estado filtrando por usuario, empresa, proveedor y fechas
     * Solo considera SP no verificadas.
     */
    public function dashboardSpMetricasNoVerificadas(Request $request): JsonResponse
    {
        $filters = $request->only([
            'fecha_registro_pendiente_desde',
            'fecha_registro_pendiente_hasta',
        ]);

        $usuarioId = $request->input('usuario_id');
        // Alias para compatibilidad: empresa_id o empresa_construcc_id
        $empresaId = $request->input('empresa_id', $request->input('empresa_construcc_id'));
        $proveedorId = $request->input('proveedor_id');

        // Base query solo con SP no verificadas
        $baseQuery = SolicitudPago::query()
            ->where('verificada', false);

        // Filtros obligatorios/condicionales
        if ($usuarioId !== null && $usuarioId !== '') {
            $baseQuery->where('usuario_id', $usuarioId); // coincidencia exacta
        }

        if ($empresaId !== null && $empresaId !== '') {
            $baseQuery->where('empresa_construcc_id', $empresaId);
        }

        if ($proveedorId !== null && $proveedorId !== '') {
            $baseQuery->where('proveedor_id', $proveedorId);
        }

        // Filtros de fechas (reutilizando el scope filter del modelo)
        if (! empty($filters)) {
            $baseQuery->filter($filters);
        }

        // Conteos por estado
        $conteos = [
            'total_sp' => (clone $baseQuery)->count(),
            'sp_pendientes' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PENDIENTE->value)->count(),
            'sp_autorizadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::AUTORIZADA->value)->count(),
            'sp_rechazadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::RECHAZADA->value)->count(),
            'sp_pagadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PAGADO->value)->count(),
        ];

        return $this->success($conteos, 'Métricas de solicitudes de pago no verificadas obtenidas correctamente');
    }


    /**
     * Conteo de solicitudes pendientes de autorizar
     * Validada = 1 y recibe como parámetro: estado - pendiente|autorizada
     */
    public function spPendienteAutorizar(Request $request): JsonResponse
    {
        // Reconectar bases de datos
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::purge('mysql5');
        DB::reconnect('mysql5');

        $request->validate([
            'estado' => ['required', 'string', Rule::in(['pendiente', 'autorizada', 'PENDIENTE', 'AUTORIZADA'])],
            'empresa_construcc_id' => ['nullable', 'integer'],
        ]);

        $estado = $request->input('estado');
        $filters = $request->only(SolicitudPago::getFilters());

        $query = SolicitudPago::on('mysql5')
            ->where('verificada', true)
            ->where('empresa_construcc_id', $request->input('empresa_construcc_id'))
            ->filter($filters);

        // Filtrar por estado si se proporciona
        if ($estado) {
            $estadoEnum = EstadoSP::from(strtolower($estado));
            $query->where('estado_solicitud', $estadoEnum->value);
        } else {
            // Por defecto, contar pendientes y autorizadas
            $query->whereIn('estado_solicitud', [
                EstadoSP::PENDIENTE->value,
                EstadoSP::AUTORIZADA->value
            ]);
        }

        $conteo = $query->count();

        return $this->success(
            ['conteo' => $conteo],
            'Conteo de solicitudes pendientes de autorizar obtenido correctamente'
        );
    }


    /**
     * Conteo de solicitudes por validar
     * Validada = 0 y recibe parámetro usuario_id: entero no null
     */
    public function spPorValidar(Request $request): JsonResponse
    {
        // Reconectar bases de datos
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::purge('mysql5');
        DB::reconnect('mysql5');

        // // 👉 Log de entrada al método
        // Log::info('📥 Ingresando a spPorValidar', [
        //     'route' => $request->path(),
        //     'ip'    => $request->ip(),
        //     'params' => $request->all(),
        //     'headers' => [
        //         'X-API-KEY' => $request->header('X-API-KEY') ? '(recibida)' : '(no enviada)',
        //         'Authorization' => $request->header('Authorization') ? '(recibido)' : '(no enviado)',
        //     ],
        // ]);

        // 👉 Validación
        try {
            $request->validate([
                'usuario_id' => ['required', 'integer'],
                'empresa_construcc_id' => ['required', 'integer'],

            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            Log::warning('⚠️ Error de validación en spPorValidar', [
                'errors' => $e->errors(),
                'params' => $request->all(),
            ]);

            throw $e;
        }

        $usuarioId = $request->input('usuario_id');
        $filters = $request->only(SolicitudPago::getFilters());

        // Log::info('🔍 Parámetros procesados', [
        //     'usuario_id' => $usuarioId,
        //     'filters'    => $filters,
        // ]);

        // 👉 Construcción de la query
        $query = SolicitudPago::on('mysql5')
            ->where('verificada', false)
            ->where('usuario_id', $usuarioId)
            ->where('estado_solicitud', EstadoSP::PENDIENTE->value)
            ->filter($filters);

        // 👉 Log de SQL generado
        // Log::debug('🧩 Query generada en spPorValidar', [
        //     'sql' => $query->toSql(),
        //     'bindings' => $query->getBindings(),
        // ]);

        // 👉 Conteo ejecutado
        $conteo = $query->count();

        // Log::info('📤 Respuesta spPorValidar', [
        //     'conteo' => $conteo,
        //     'usuario_id' => $usuarioId,
        // ]);

        return $this->success(
            ['conteo' => $conteo],
            'Conteo de solicitudes por validar obtenido correctamente'
        );
    }


    /**
     * Conteo de solicitudes por validar filtrado por empresasConstructoras
     * Validada = 0 y recibe parámetro usuario_id: entero no null
     */
    public function spPorValidarOtros(Request $request): JsonResponse
    {
        // Reconectar bases de datos
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::purge('mysql5');
        DB::reconnect('mysql5');

        // 👉 Validación
        try {
            $request->validate([
                'usuario_id' => ['required', 'integer'],
                'empresa_construcc_id' => ['required', 'integer'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('⚠️ Error de validación en spPorValidar', ['errors' => $e->errors(), 'params' => $request->all()]);
            throw $e;
        }

        $usuarioId = $request->input('usuario_id');
        $empresaConstruccId = $request->input('empresa_construcc_id');
        // $filters = $request->only(SolicitudPago::getFilters());

        // 75 --- 14

        // 👉 Construcción de la query
        $query = SolicitudPago::on('mysql5')
            ->where('verificada', false)
            ->where('usuario_id', '!=', $usuarioId) // excluye el usuario
            ->where('empresa_construcc_id', $empresaConstruccId)
            ->where('estado_solicitud', EstadoSP::PENDIENTE->value);
        // ->filter($filters);


        // 👉 Conteo ejecutado
        $conteo = $query->count();

        // Log::info('📤 Respuesta spPorValidar', [
        //     'conteo' => $conteo,
        //     'usuario_id' => $usuarioId,
        // ]);

        return $this->success(
            ['conteo' => $conteo],
            'Conteo de solicitudes por validar, filtradas para la empresa.'
        );
    }

    /**
     * Conteo de SP sin factura, filtrado por empresa y validada.
     * ruta: /api/construcc/solicitudes-pago/sp-sin-factura
     */
    public function spSinFactura(Request $request): JsonResponse
    {
        // Reconectar bases de datos
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::purge('mysql5');
        DB::reconnect('mysql5');

        $request->validate([
            'empresa_construcc_id' => ['nullable', 'integer'],
        ]);

        $filters = $request->only(SolicitudPago::getFilters());

        $query = SolicitudPago::on('mysql5')
            ->where('verificada', true)
            ->whereIn('estado_solicitud', [EstadoSP::AUTORIZADA->value, EstadoSP::PAGADO->value])
            ->where('empresa_construcc_id', $request->input('empresa_construcc_id'))
            ->filter($filters);

        $conteo = $query->count();

        return $this->success(
            ['conteo' => $conteo],
            'Conteo de solicitudes de pago sin factura obtenido correctamente.'
        );
    }


    /**
     * Generar nueva solicitud de pago desde construcción
     * Este endpoint crea un proveedor nuevo, registra su cuenta bancaria y genera la SPP
     * 
     * Flujo:
     * 1. Validar datos del proveedor (RFC, email, teléfono, celular)
     * 2. Crear proveedor con tipo_alta = 2 (UserConstrucc)
     * 3. Registrar cuenta bancaria (primera cuenta se marca como preferida)
     * 3.1. Asociar proveedor a empresa de construcción
     * 4. Almacenar archivos (factura PDF, XML, cotización)
     * 5. Crear solicitud de pago según nivel del usuario:
     *    - Directores (DG, DT, DA, PC, Admin): verificada=true, estado=autorizada
     *    - Otros (SI, RO): verificada=false, estado=pendiente
     * 6. Sincronizar cuenta bancaria con SP mediante tabla pivote
     * 7. Notificar a directores si fue auto-aprobada (TODO: implementar)
     */
    public function generarSolicitudPagoConstrucc(GenerarSolicitudPagoConstruccRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            // ============================================
            // PASO 1: Validar y buscar proveedor existente
            // ============================================
            $proveedorExistente = Proveedor::where('rfc', $validated['proveedor_rfc'])
                ->orWhere('email', $validated['proveedor_email'])
                ->orWhere('telefono', $validated['proveedor_telefono'])
                ->when(isset($validated['proveedor_celular']), function ($query) use ($validated) {
                    return $query->orWhere('telefono', $validated['proveedor_celular']);
                })
                ->first();

            if ($proveedorExistente) {
                DB::rollBack();
                return $this->error(
                    'El proveedor ya existe en el sistema.',
                    [
                        'proveedor_id' => $proveedorExistente->id,
                        'razon_social' => $proveedorExistente->razon_social,
                        'rfc' => $proveedorExistente->rfc,
                        'email' => $proveedorExistente->email,
                        'telefono' => $proveedorExistente->telefono,
                    ],
                    409
                );
            }

            // ============================================
            // PASO 2: Crear proveedor con tipo_alta = 2 (UserConstrucc)
            // ============================================
            $proveedor = Proveedor::create([
                'rfc' => strtoupper($validated['proveedor_rfc']),
                'razon_social' => $validated['proveedor_razon_social'],
                'nombre_comercial' => $validated['proveedor_nombre_comercial'],
                'email' => $validated['proveedor_email'],
                'telefono' => $validated['proveedor_telefono'],
                'estatus' => 'activo',
                'is_proveedor_sp' => true,
                'is_proveedor_catalogo' => false,
                'tipo_alta' => 2, // 1: Proveedor, 2: UserConstrucc
                'perfil_empresa_completo' => false,
            ]);

            // Log::info('✅ Proveedor creado desde construcción', [
            //     'proveedor_id' => $proveedor->id,
            //     'rfc' => $proveedor->rfc,
            //     'razon_social' => $proveedor->razon_social,
            // ]);

            // ============================================
            // PASO 3: Registrar cuenta bancaria
            // ============================================
            // 🔹 Verificar si es la primera cuenta bancaria del proveedor
            $esPrimeraCuenta = !$proveedor->cuentasBancarias()->exists();

            $cuentaBancaria = CuentaBancaria::create([
                'proveedor_id' => $proveedor->id,
                'alias' => $validated['cuenta_bancaria_alias'],
                'banco_clave' => $validated['cuenta_bancaria_banco_clave'],
                'banco_nombre' => $validated['cuenta_bancaria_banco_nombre'],
                'cuenta' => $validated['cuenta_bancaria_cuenta'] ?? null,
                'clabe' => $validated['cuenta_bancaria_clabe'] ?? null,
                'tarjeta' => $validated['cuenta_bancaria_tarjeta'] ?? null,
                'titular_cuenta' => $validated['cuenta_bancaria_titular_cuenta'],
                'referencia' => $validated['cuenta_bancaria_referencia'] ?? null,
                'estatus' => EstadoCuentaBancaria::ACTIVA,
                'sucursal' => $validated['cuenta_bancaria_sucursal'] ?? null,
                'swift' => $validated['cuenta_bancaria_swift'] ?? null,
                'preferida' => $esPrimeraCuenta,
            ]);

            // Log::info('✅ Cuenta bancaria registrada', [
            //     'cuenta_bancaria_id' => $cuentaBancaria->id,
            //     'proveedor_id' => $proveedor->id,
            //     'alias' => $cuentaBancaria->alias,
            //     'preferida' => $cuentaBancaria->preferida,
            // ]);

            // ============================================
            // PASO 3.1: Asociar proveedor a empresa de construcción (si aplica)
            // ============================================
            $empresaConstructId = $validated['empresa_construcc_id'] ?? null;
            $usuarioId = $validated['usuario_id'] ?? null;
            $usuarioNombre = $validated['usuario_nombre'] ?? null;

            if ($empresaConstructId && $usuarioId) {
                $proveedor->empresasConstrucc()->attach($empresaConstructId, [
                    'usuario_construcc_id' => $usuarioId,
                    'usuario_construcc_nombre' => $usuarioNombre,
                ]);

                // Log::info('✅ Proveedor asociado a empresa de construcción', [
                //     'proveedor_id' => $proveedor->id,
                //     'empresa_construcc_id' => $empresaConstructId,
                //     'usuario_id' => $usuarioId,
                // ]);
            }

            // ============================================
            // PASO 4: Almacenar archivos
            // ============================================
            $facturaPdf = $request->file('factura_pdf');
            $facturaXml = $request->file('factura_xml');
            $cotizacionFile = $request->file('cotizacion');

            if (!$facturaPdf || !$facturaXml) {
                DB::rollBack();
                return $this->error('Los archivos PDF y XML son obligatorios.', null, 422);
            }

            $rutaPdf = $facturaPdf->store('facturas/pdf', 'private');
            $rutaXml = $facturaXml->store('facturas/xml', 'private');

            // Extraer datos del XML
            $datosXml = $this->extraerDatosXML($facturaXml->getRealPath());

            // Combinar serie y folio para formar el folio_factura
            $serie = $datosXml['serie'] ?? '';
            $folio = $datosXml['folio'] ?? '';
            $folioFactura = trim($serie . ($serie && $folio ? '-' : '') . $folio) ?: null;

            // Procesar archivo de cotización si existe
            $rutaCotizacion = null;
            if ($cotizacionFile) {
                $rutaCotizacion = $cotizacionFile->store('cotizaciones', 'private');
            }

            // Log::info('✅ Archivos almacenados', [
            //     'factura_pdf' => $rutaPdf,
            //     'factura_xml' => $rutaXml,
            //     'cotizacion' => $rutaCotizacion,
            //     'folio_factura' => $folioFactura,
            // ]);

            // ============================================
            // PASO 5: Crear solicitud de pago
            // ============================================
            $numeroFolio = SolicitudPago::generarNumeroFolio($proveedor);
            $montoTotal = $validated['monto_total'];

            // Obtener folio consecutivo si hay empresa (ya asociada en paso 3.1)
            $folio_consecutivo_construcc = null;
            if ($empresaConstructId) {
                $empresaConstrucc = \App\Models\EmpresaConstrucc::find($empresaConstructId);

                if ($empresaConstrucc) {
                    $folio_consecutivo_construcc = $empresaConstrucc->obtenerFolioSiguienteSP();
                }
            }

            // Determinar estado inicial según el nivel del usuario
            // 0: Admin, 1: DG, 2: DT, 3: DA, 5: PC - Auto-aprueban
            // 4: SI, 6: RO, 7 - Requieren aprobación
            $nivelId = $validated['nivel_id'] ?? null;
            $nivelesDirectores = [0, 1, 2, 3, 5]; // Admin, DG, DT, DA, PC

            $esDirector = $nivelId !== null && in_array($nivelId, $nivelesDirectores);

            // Mapeo de nivel a campo de rol
            $nivelToRol = [
                0 => 'dg', // Admin se trata como DG
                1 => 'dg', // Director General
                2 => 'dt', // Director Técnico
                3 => 'da', // Director Administrativo
                5 => 'pc', // Programación y Control
            ];

            // Datos base de la SP
            $datosSP = [
                'proveedor_id' => $proveedor->id,
                'numero_folio_solicitud' => $numeroFolio,
                'folio_factura' => $folioFactura,
                'datos_factura_xml' => $datosXml,
                'descripcion_concepto' => $validated['descripcion_concepto'],
                'observaciones' => $validated['observaciones'] ?? null,
                'ruta_archivo_factura_pdf' => $rutaPdf,
                'ruta_archivo_factura_xml' => $rutaXml,
                'ruta_archivo_cotizacion' => $rutaCotizacion,
                'folio_sp_consecutivo' => $folio_consecutivo_construcc,
                'empresa_construcc_id' => $empresaConstructId,
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $usuarioNombre,
                'monto_total' => $montoTotal,
                'saldo_pendiente' => $montoTotal,
                'monto_abonado' => 0,
                'pago_completo' => false,
                'tiene_factura' => true,

                // Campos adicionales de la solicitud
                'obra_id' => $validated['obra_id'] ?? null,
                'tipo' => $validated['tipo'] ?? null,
                'tipo_id' => $validated['tipo_id'] ?? null,
                'notas' => $validated['notas'] ?? null,
                'utilizara' => $validated['utilizara'] ?? null,
                'equipo' => $validated['equipo'] ?? null,
                'equipo_id' => $validated['equipo_id'] ?? null,
            ];

            if ($esDirector) {
                // Director: Auto-aprueba (verificada = true, autorizada)
                $rolField = $nivelToRol[$nivelId];
                $fechaField = "{$rolField}_fecha";

                $datosSP['verificada'] = true;
                $datosSP['estado_solicitud'] = EstadoSP::AUTORIZADA->value;
                $datosSP['fecha_registro_pendiente'] = now();
                $datosSP['fecha_aprobado'] = now();
                $datosSP[$rolField] = EstadoSolicitud::AUTORIZADA->value;
                $datosSP[$fechaField] = now();
            } else {
                // Residente/otro: Requiere validación y aprobación
                $datosSP['verificada'] = true;
                $datosSP['estado_solicitud'] = EstadoSP::PENDIENTE->value;
                $datosSP['fecha_registro_pendiente'] = now();
            }

            $solicitud = SolicitudPago::create($datosSP);

            // Log::info('✅ Solicitud de pago creada', [
            //     'solicitud_pago_id' => $solicitud->id,
            //     'numero_folio' => $solicitud->numero_folio_solicitud,
            //     'proveedor_id' => $proveedor->id,
            //     'monto_total' => $montoTotal,
            //     'verificada' => $solicitud->verificada,
            //     'estado_solicitud' => $solicitud->estado_solicitud,
            //     'auto_aprobada_por_director' => $esDirector,
            // ]);

            // ============================================
            // NOTIFICACIÓN: Si es director, notificar a otros directores
            // ============================================
            if ($esDirector) {
                // TODO: Implementar notificación a directores (DG, DT, DA, PC)
                // cuando un director crea y auto-aprueba una SP
                // 
                // Datos para la notificación:
                // - solicitud_pago_id: $solicitud->id
                // - numero_folio: $solicitud->numero_folio_solicitud
                // - empresa_construcc_id: $empresaConstructId
                // - usuario_que_creo_id: $usuarioId
                // - usuario_que_creo_nombre: $usuarioNombre
                // - nivel_id: $nivelId (rol del director que creó)
                // - monto_total: $montoTotal
                // - proveedor: $proveedor->nombre_comercial
                // 
                // Ejemplo de uso:
                // $this->interApiService->notifyDirectoresSPAutoAprobada($solicitud, $nivelId);

                // Log::info('📧 [PENDIENTE] Notificar a directores sobre SP auto-aprobada', [
                //     'solicitud_pago_id' => $solicitud->id,
                //     'creada_por_nivel' => $nivelId,
                //     'empresa_construcc_id' => $empresaConstructId,
                // ]);
            }

            // ============================================
            // PASO 6: Sincronizar cuenta bancaria con la SP
            // ============================================
            $solicitud->sincronizarCuentasBancarias([
                [
                    'cuenta_bancaria_id' => $cuentaBancaria->id,
                    'datos_especificos' => [
                        'alias' => $cuentaBancaria->alias,
                        'banco_clave' => $cuentaBancaria->banco_clave,
                        'banco_nombre' => $cuentaBancaria->banco_nombre,
                        'cuenta' => $cuentaBancaria->cuenta,
                        'clabe' => $cuentaBancaria->clabe,
                        'tarjeta' => $cuentaBancaria->tarjeta,
                        'titular_cuenta' => $cuentaBancaria->titular_cuenta,
                        'referencia' => $cuentaBancaria->referencia,
                        'sucursal' => $cuentaBancaria->sucursal,
                        'swift' => $cuentaBancaria->swift,
                        'preferida' => true,
                    ],
                ],
            ]);

            // Log::info('✅ Cuenta bancaria sincronizada con SP', [
            //     'solicitud_pago_id' => $solicitud->id,
            //     'cuenta_bancaria_id' => $cuentaBancaria->id,
            // ]);

            // Agregar notificación
            $solicitud->addNotification();

            // Notificar a través de InterAPI
            $this->interApiService->notifyNewSolicitudCompra($solicitud);

            DB::commit();

            return $this->success(
                new ConstruccGenerarSppResource(
                    $solicitud->load(['proveedor', 'empresaConstrucc', 'cuentasBancarias'])
                ),
                'Solicitud de pago generada correctamente desde construcción.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error al generar SPP desde construcción', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Error al generar la solicitud de pago. Por favor, intente nuevamente.',
                [
                    'error' => $e->getMessage(),
                    'linea' => $e->getLine(),
                ],
                500
            );
        }
    }

    /**
     * METODOS PRIVADOS: solo para uso interno del controlador
     * EXTRAER DATOS DEL XML DE LA FACTURA --> REVISAR LA FUNCION TAMBIEN SE USA EN pROVEEWDORsOLICITUDcOMPRAcONTROLLER.PHP
     */
    private function extraerDatosXML($archivoXml): array
    {
        try {
            // ✅ Validar que el archivo exista y sea legible
            if (! $archivoXml || ! file_exists($archivoXml) || ! is_readable($archivoXml)) {
                Log::warning('⚠️ Archivo XML no existe o no es legible', [
                    'ruta' => $archivoXml,
                ]);
                return [];
            }

            // Leer contenido del XML
            $contenidoXml = file_get_contents($archivoXml);

            if ($contenidoXml === false) {
                Log::warning('⚠️ No se pudo leer el contenido del XML', [
                    'ruta' => $archivoXml,
                ]);
                return [];
            }

            // Parsear XML
            $xml = simplexml_load_string($contenidoXml, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                Log::warning('⚠️ No se pudo parsear el XML de la factura', [
                    'ruta' => $archivoXml,
                ]);
                return [];
            }

            $namespaces = $xml->getNamespaces(true);
            $cfdiNamespace = $namespaces['cfdi'] ?? $namespaces[''] ?? null;
            $cfdiRoot = $cfdiNamespace ? $xml->children($cfdiNamespace) : $xml;

            // Extraer datos principales del comprobante
            $atributos = $xml->attributes();

            $datos = [
                'version' => (string) ($atributos->Version ?? ''),
                'serie' => (string) ($atributos->Serie ?? ''),
                'folio' => (string) ($atributos->Folio ?? ''),
                'fecha' => (string) ($atributos->Fecha ?? ''),
                'sello' => (string) ($atributos->Sello ?? ''),
                'no_certificado' => (string) ($atributos->NoCertificado ?? ''),
                'certificado' => (string) ($atributos->Certificado ?? ''),
                'subtotal' => (string) ($atributos->SubTotal ?? ''),
                'total' => (string) ($atributos->Total ?? ''),
                'moneda' => (string) ($atributos->Moneda ?? 'MXN'),
                'tipo_comprobante' => (string) ($atributos->TipoDeComprobante ?? ''),
                'metodo_pago' => (string) ($atributos->MetodoPago ?? ''),
                'forma_pago' => (string) ($atributos->FormaPago ?? ''),
                'lugar_expedicion' => (string) ($atributos->LugarExpedicion ?? ''),
            ];

            // Extraer emisor
            if (isset($cfdiRoot->Emisor)) {
                $emisor = $cfdiRoot->Emisor->attributes();
                $datos['emisor'] = [
                    'rfc' => (string) ($emisor->Rfc ?? ''),
                    'nombre' => (string) ($emisor->Nombre ?? ''),
                    'regimen_fiscal' => (string) ($emisor->RegimenFiscal ?? ''),
                ];
            }

            // Extraer receptor
            if (isset($cfdiRoot->Receptor)) {
                $receptor = $cfdiRoot->Receptor->attributes();
                $datos['receptor'] = [
                    'rfc' => (string) ($receptor->Rfc ?? ''),
                    'nombre' => (string) ($receptor->Nombre ?? ''),
                    'uso_cfdi' => (string) ($receptor->UsoCFDI ?? ''),
                    'domicilio_fiscal_receptor' => (string) ($receptor->DomicilioFiscalReceptor ?? ''),
                    'regimen_fiscal_receptor' => (string) ($receptor->RegimenFiscalReceptor ?? ''),
                ];

                // Claves planas usadas por SolicitudPago::validarEspecificacionFactura
                $datos['uso_cfdi'] = (string) ($receptor->UsoCFDI ?? '');
                $datos['regimen_fiscal_receptor'] = (string) ($receptor->RegimenFiscalReceptor ?? '');
                $datos['codigo_postal_receptor'] = (string) ($receptor->DomicilioFiscalReceptor ?? '');
                $datos['rfc_receptor'] = (string) ($receptor->Rfc ?? '');
            }

            // Extraer conceptos
            $conceptos = [];
            if (isset($cfdiRoot->Conceptos)) {
                foreach ($cfdiRoot->Conceptos->Concepto as $concepto) {
                    $conceptoAttr = $concepto->attributes();
                    $conceptos[] = [
                        'clave_prod_serv' => (string) ($conceptoAttr->ClaveProdServ ?? ''),
                        'cantidad' => (string) ($conceptoAttr->Cantidad ?? ''),
                        'clave_unidad' => (string) ($conceptoAttr->ClaveUnidad ?? ''),
                        'unidad' => (string) ($conceptoAttr->Unidad ?? ''),
                        'descripcion' => (string) ($conceptoAttr->Descripcion ?? ''),
                        'valor_unitario' => (string) ($conceptoAttr->ValorUnitario ?? ''),
                        'importe' => (string) ($conceptoAttr->Importe ?? ''),
                    ];
                }
            }
            $datos['conceptos'] = $conceptos;

            // Extraer UUID del timbre fiscal
            if (isset($cfdiRoot->Complemento)) {
                $complemento = $cfdiRoot->Complemento;
                $timbreNode = null;

                foreach ($complemento->getNamespaces(true) as $namespace) {
                    foreach ($complemento->children($namespace) as $child) {
                        if ($child->getName() === 'TimbreFiscalDigital') {
                            $timbreNode = $child;
                            break 2;
                        }
                    }
                }

                if ($timbreNode) {
                    $timbre = $timbreNode->attributes();
                    $datos['timbre_fiscal'] = [
                        'uuid' => (string) ($timbre->UUID ?? ''),
                        'fecha_timbrado' => (string) ($timbre->FechaTimbrado ?? ''),
                        'rfc_prov_certif' => (string) ($timbre->RfcProvCertif ?? ''),
                        'sello_cfd' => (string) ($timbre->SelloCFD ?? ''),
                        'no_certificado_sat' => (string) ($timbre->NoCertificadoSAT ?? ''),
                        'sello_sat' => (string) ($timbre->SelloSAT ?? ''),
                    ];
                }
            }

            return $datos;
        } catch (\Exception $e) {
            Log::error('Error al extraer datos del XML', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Actualiza el comprobante de pago de una Solicitud de Pago.
     *
     * Este método permite reemplazar el comprobante de pago de una solicitud
     * que ya se encuentra en estado PAGADO. El comprobante anterior será eliminado
     * del almacenamiento y se guardará el nuevo archivo.
     *
     * Reglas de negocio:
     * - Solo el rol DA (Dirección Administrativa) puede realizar esta acción.
     * - La Solicitud de Pago debe estar en estado PAGADO.
     * - El archivo de comprobante es obligatorio.
     * - Se elimina el comprobante anterior antes de guardar el nuevo.
     * - Se notifica la actualización del comprobante al proveedor.
     *
     * Flujo del proceso:
     * 1. Valida que el rol del usuario sea DA.
     * 2. Verifica que la solicitud esté en estado PAGADO.
     * 3. Elimina el comprobante de pago anterior si existe.
     * 4. Guarda el nuevo comprobante en almacenamiento privado.
     * 5. Actualiza los datos relacionados al comprobante.
     * 6. Notifica la actualización a la API de Construcciones (InterAPI).
     *
     * @param  SolicitudPagoUpdateConprobantePagoRequest  $request
     * @param  SolicitudPago  $solicitudPago
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function actualizarComprobantePago(SolicitudPagoUpdateConprobantePagoRequest $request, SolicitudPago $solicitudPago): JsonResponse
    {
        // Log::info('🟢 PAGO-SP Carga util de la peticion: ', [
        //     'rol' => $request->rol,
        //     'comprobante' => $request->comprobante,
        //     'observaciones' => $request->observaciones,
        //     'fecha' => $request->fecha,
        //     'hora' => $request->hora,
        //     'nombre_beneficiario' => $request->nombre_beneficiario,
        //     'clave_rastreo' => $request->clave_rastreo,
        //     'banco' => $request->banco,

        // ]);

        /** 🔒 Validar rol */
        if ($request->rol !== 'DA') {
            return $this->error(
                'Solo el rol DA puede actualizar el comprobante de pago.',
                null,
                403
            );
        }

        /** 🔒 Validar estado */
        if ($solicitudPago->estado_solicitud !== EstadoSP::PAGADO->value) {
            return $this->error(
                'La solicitud de pago debe estar en estado PAGADO.',
                null,
                422
            );
        }

        /** 🧹 Eliminar comprobante anterior */
        if (
            $solicitudPago->ruta_archivo_comprobante_pago &&
            Storage::disk('private')->exists($solicitudPago->ruta_archivo_comprobante_pago)
        ) {
            Storage::disk('private')->delete(
                $solicitudPago->ruta_archivo_comprobante_pago
            );
        }

        /** 📂 Guardar nuevo comprobante */
        $path = $request->file('comprobante')
            ->store('comprobantes', 'private');

        // $fechaPago = Carbon::createFromFormat(
        //     'Y-m-d H:i:s',
        //     trim($request->fecha . ' ' . $request->hora)
        // );

        // Log::info(
        //     '🟢 PAGO-SP: SP actualizada',
        //     [
        //         'ruta_archivo_comprobante_pago' => $path,
        //         'notas_abono' => $request->observaciones,
        //         'fecha_comprobante_pago' => $request->fecha_hora_pago,
        //         'fecha_pago' =>  now(),

        //         'nombre_beneficiario_pago' => $request->nombre_beneficiario,
        //         'clave_rastreo_pago' => $request->clave_rastreo,
        //         'banco_pago' => $request->banco,
        //     ]
        // );

        /** 📝 Actualizar SOLO datos del comprobante */
        $solicitudPago->update([
            'ruta_archivo_comprobante_pago' => $path,
            'notas_abono' => $request->observaciones,
            'fecha_pago' =>  now(),
            'fecha_comprobante_pago' => $request->fecha_hora_pago,
            'nombre_beneficiario_pago' => $request->nombre_beneficiario,
            'clave_rastreo_pago' => $request->clave_rastreo,
            'banco_pago' => $request->banco,
        ]);

        /** 📡 Notificar vía InterAPI */
        /**
         * NOTIFICAR AL PROVEEDOR
         */
        // Enviar notificación al proveedor por actualización de comprobante
        try {
            $proveedor = $solicitudPago->proveedor;
            $usuarioPrincipal = $proveedor->usuarioPrincipal();

            if ($usuarioPrincipal) {

                /** 🔔 Notificación interna (Laravel Notifications) */
                $usuarioPrincipal->notify(
                    new SolicitudPagoComprobanteActualizadoNotification(
                        $solicitudPago->numero_folio_solicitud,
                        $solicitudPago->id,
                        $proveedor->id,
                        $usuarioPrincipal->id
                    )
                );

                // Log::info('🟢 SP-COMPROBANTE: Notificación local enviada', [
                //     'solicitud_pago_id' => $solicitudPago->id,
                //     'folio' => $solicitudPago->numero_folio_solicitud,
                //     'proveedor_id' => $proveedor->id,
                //     'usuario_id' => $usuarioPrincipal->id,
                // ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error al enviar notificación de comprobante actualizado', [
                'solicitud_pago_id' => $solicitudPago->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success(
            new ConstruccSolicitudPagoResource(
                $solicitudPago->fresh()->load(
                    SolicitudPago::eagerLodable()
                )
            ),
            'Comprobante de pago actualizado correctamente.'
        );
    }


    /**
     * GESTION DE FACTURA
     */
    public function uploadFacturaPdfXml(Request $request, SolicitudPago $solicitudPago): JsonResponse
    {
        $request->validate([
            'factura_pdf' => 'required|file|mimes:pdf|max:10240',
            'factura_xml' => 'required|file|mimes:xml|max:5120',
            'usuario_construcc_subio_factura_id' => 'required|integer',
            'usuario_construcc_subio_factura_rol' => 'required|string|max:50',
        ]);

        // TODO: validar empresa_construcc_id del request contra $solicitudPago->empresa_construcc_id

        $facturaPdf = $request->file('factura_pdf');
        $facturaXml = $request->file('factura_xml');
        $datosXml = $this->extraerDatosXML($facturaXml->getRealPath());
        if (empty($datosXml)) {
            return $this->error('El archivo XML no es un CFDI válido.', null, 422);
        }

        $serie = $datosXml['serie'] ?? '';
        $folio = $datosXml['folio'] ?? '';
        $folioFactura = trim($serie . ($serie && $folio ? '-' : '') . $folio) ?: null;


        // 🔎 VALIDACIÓN DE ESPECIFICACIÓN DE FACTURA (SPP sin factura / con especificación)
        /**
         * TODO: Ajustar esta seccion
         * Reglas:
         *  - si datos_facturacion_id viene definido de ahi se tomaran los datos de facturacion
         *  - si razon_social_id viene datos_facturacion_id sera null y usp, mp, fp son obligatorios y rf podra ser null y si tiene valor sustituye los datos de razon_social_id['razon_social'] unicamente los demas quedan igual.
         * 
         * Interapi Tiene los metodos correspondientes:
         * datos_facturacion_id: obtenerDatosFacturacionEmpresa
         * razon_social_id: obtenerRazonSocial
         */
        // 🔎 VALIDACIÓN DE ESPECIFICACIÓN DE FACTURA (SPP sin factura / con especificación)
        if ($solicitudPago->datos_facturacion_id) {

            $interResponse = $this->interApiService->obtenerDatosFacturacionEmpresa(
                $solicitudPago->datos_facturacion_id
            );

            if (!$interResponse['success']) {
                return $this->error(
                    'No fue posible obtener los datos de facturación desde Construcc.',
                    $interResponse['error'] ?? null,
                    500
                );
            }

            $data = $interResponse['data'] ?? [];

            $facturacionDefault = $data['facturacion_default'] ?? [];
            $regimenFiscal      = $data['regimen_fiscal_default'] ?? null;

            $datosFacturacion = [
                'uso_cfdi'       => $facturacionDefault['uso_cfdi'] ?? null,
                'forma_pago'     => $facturacionDefault['forma_pago'] ?? null,
                'metodo_pago'    => $facturacionDefault['metodo_pago'] ?? null,
                'codigo_postal'  => $facturacionDefault['codigo_postal'] ?? null,
                'regimen_fiscal' => $regimenFiscal,
                'rfc'            => $data['rfc'] ?? null,
                'total'          => $solicitudPago->monto_total ?? null,
                'moneda'         => $solicitudPago->moneda ?? 'MXN',
            ];

            $errores = $solicitudPago->validarEspecificacionFactura($datosXml, $datosFacturacion);

            if (!empty($errores)) {
                return $this->error(
                    'La factura no cumple con la especificación requerida.',
                    $errores,
                    422
                );
            }
        } elseif ($solicitudPago->razon_social_id) {

            if (!$solicitudPago->uso || !$solicitudPago->mp || !$solicitudPago->fp) {
                return $this->error(
                    'La solicitud de pago no tiene la especificación mínima de facturación.',
                    [
                        'uso_cfdi'    => $solicitudPago->uso,
                        'metodo_pago' => $solicitudPago->mp,
                        'forma_pago'  => $solicitudPago->fp,
                    ],
                    422
                );
            }

            $interResponse = $this->interApiService->obtenerDatosFacturacionRazonSocial(
                $solicitudPago->razon_social_id
            );

            if (!$interResponse['success']) {
                return $this->error(
                    'No fue posible obtener la razón social desde Construcc.',
                    $interResponse['error'] ?? null,
                    500
                );
            }

            $razonSocial = $interResponse['data'] ?? [];

            $datosFacturacion = [
                'uso_cfdi'       => $solicitudPago->uso,
                'metodo_pago'    => $solicitudPago->mp,
                'forma_pago'     => $solicitudPago->fp,
                'codigo_postal'  => $razonSocial['codigo_postal'] ?? null,
                'regimen_fiscal' => $solicitudPago->rf, // puede ser null
                'rfc'            => $razonSocial['rfc'] ?? null,
                'total'          => $solicitudPago->monto_total ?? null,
                'moneda'         => $solicitudPago->moneda ?? 'MXN',
            ];

            $errores = $solicitudPago->validarEspecificacionFactura($datosXml, $datosFacturacion);

            if (!empty($errores)) {
                return $this->error(
                    'La factura no cumple con la especificación requerida.',
                    $errores,
                    422
                );
            }
        }

        $rutaPdf = $facturaPdf->store('facturas/pdf', 'private');
        $rutaXml = $facturaXml->store('facturas/xml', 'private');

        $solicitudPago->update([
            'folio_factura' => $folioFactura,
            'datos_factura_xml' => $datosXml,
            'ruta_archivo_factura_pdf' => $rutaPdf,
            'ruta_archivo_factura_xml' => $rutaXml,
            'tiene_factura' => true,
            'fecha_subida_factura_pdf' => now(),
            'fecha_subida_factura_xml' => now(),
            'usuario_construcc_subio_factura_id' => $request->usuario_construcc_subio_factura_id,
            'usuario_construcc_subio_factura_rol' => $request->usuario_construcc_subio_factura_rol,
        ]);

        $solicitudPago->enviarCorreoFacturaAEmpresaConstrucc($rutaPdf, $rutaXml);

        Log::info('Factura: Antes Notificación a InterAPI');

        $response = $this->interApiService->sPPFacturaNotifyDA(
            $solicitudPago->id,
            $solicitudPago->numero_folio_solicitud,
            $solicitudPago->empresa_construcc_id,
        );

        Log::info('Factura: Notificación a InterAPI', [
            'solicitud_pago_id' => $solicitudPago->id,
            'numero_folio' => $solicitudPago->numero_folio_solicitud,
            'empresa_construcc_id' => $solicitudPago->empresa_construcc_id,
            'inter_api_response' => $response,
        ]);
        return $this->success(
            new ConstruccSolicitudPagoResource(
                $solicitudPago->load(SolicitudPago::eagerLodable())
            ),
            'Factura cargada correctamente.',
            201
        );
    }




    /**
     * contadores 
     */
    public function metricas(Request $request): JsonResponse
    {
        // ✅ Validación
        $request->validate([
            'empresa_construcc_id' => ['required', 'integer'],
            'usuario_id' => ['required', 'integer'],
            'usuario_rol' => ['required', 'integer'],
        ]);

        $empresaId = $request->empresa_construcc_id;
        $usuarioId = $request->usuario_id;
        $rol = $request->usuario_rol;

        /**
         * 🔹 BASE QUERY
         */
        $baseQuery = SolicitudPago::on('mysql5')
            ->where('empresa_construcc_id', $empresaId);

        // 🔹 control por rol
        if ($rol == 6) {
            $baseQuery->where('usuario_id', $usuarioId);
        }

        /**
         * 🔥 CONTEOS (ORM)
         */

        $pendienteAutorizar = (clone $baseQuery)
            ->where('verificada', true)
            ->where('estado_solicitud', EstadoSP::PENDIENTE->value)
            ->whereDoesntHave('pagos')
            ->count();

        $autorizadas = (clone $baseQuery)
            ->where('verificada', true)
            ->where('estado_solicitud', EstadoSP::AUTORIZADA->value)
            ->count();

        $porValidar = (clone $baseQuery)
            ->where('verificada', false)
            ->where('usuario_id', $usuarioId)
            ->where('estado_solicitud', EstadoSP::PENDIENTE->value)
            ->count();

        $porValidarOtros = (clone $baseQuery)
            ->when($rol != 6, function ($q) use ($usuarioId) {
                $q->where('usuario_id', '!=', $usuarioId);
            })
            ->where('verificada', false)
            ->where('estado_solicitud', EstadoSP::PENDIENTE->value)
            ->count();

        $sinFactura = (clone $baseQuery)
            ->where('verificada', true)
            ->whereIn('estado_solicitud', [
                EstadoSP::AUTORIZADA->value,
                EstadoSP::PAGADO->value
            ])
            ->where('tiene_factura', false)
            ->count();

        return $this->success([
            'pendiente_autorizar' => $pendienteAutorizar,
            'autorizadas' => $autorizadas,
            'por_validar' => $porValidar,
            'por_validar_otros' => $porValidarOtros,
            'sin_factura' => $sinFactura,
        ], 'Conteos obtenidos correctamente');
    }
}
