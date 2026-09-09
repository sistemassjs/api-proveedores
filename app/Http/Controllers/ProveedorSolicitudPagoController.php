<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSP;
use App\Http\Requests\SolicitudPago\CrearSolicitudPagoRequest;
use App\Http\Requests\SolicitudPago\CrearSolicitudPagoSinFacturaRequest;
use App\Http\Resources\Proveedor\ProveedorPagoResource;
use App\Http\Resources\SolicitudPago\SolicitudPagoResource;
use App\Models\EmpresaConstrucc;
use App\Models\PagoSPP;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\SolicitudPago;
use App\Notifications\Presupuesto\PresupuestoRecibidoClienteProveedorNotification;
use App\Services\InterApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProveedorSolicitudPagoController extends Controller
{
    protected $interApiService;

    public function __construct(InterApiService $interApiService)
    {
        $this->interApiService = $interApiService;
    }

    /**
     * Listado con paginación, filtrando por proveedor
     */
    public function index(Request $request, Proveedor $proveedor): JsonResponse
    {
        $filters = $request->only(SolicitudPago::getFilters());
        $filters['proveedor_id'] = $proveedor->id;
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');
        $perPage = $request->input('per_page', 10);

        // Los contadores por pestaña deben reflejar totales sin el límite «últimas N» ni el filtro de estado activo
        $countFilters = array_diff_key($filters, array_flip(['estado_solicitud', 'ultimas_spp']));
        $segmentCounts = SolicitudPago::query()
            ->where('proveedor_id', $proveedor->id)
            ->when(! empty($countFilters), fn($q) => $q->filter($countFilters))
            ->selectRaw('estado_solicitud, COUNT(*) as total')
            ->groupBy('estado_solicitud')
            ->pluck('total', 'estado_solicitud')
            ->toArray();

        $segmentCountsFormatted = [
            'pendiente' => (int) ($segmentCounts[EstadoSP::PENDIENTE->value] ?? 0),
            'rechazada' => (int) ($segmentCounts[EstadoSP::RECHAZADA->value] ?? 0),
            'autorizada' => (int) ($segmentCounts[EstadoSP::AUTORIZADA->value] ?? 0),
            'pagado' => (int) ($segmentCounts[EstadoSP::PAGADO->value] ?? 0),
            'sin_factura' => (int) (clone $baseQuery)->where('tiene_factura', false)->count(),
        ];

        $unreadSegmentCountsFormatted = [
            'pendiente' => (int) (clone $baseQuery)
                ->where('estado_solicitud', EstadoSP::PENDIENTE->value)
                ->where(function ($q) {
                    $q->where('item_visto', false)->orWhereNull('item_visto');
                })
                ->count(),
            'rechazada' => (int) (clone $baseQuery)
                ->where('estado_solicitud', EstadoSP::RECHAZADA->value)
                ->where(function ($q) {
                    $q->where('item_visto', false)->orWhereNull('item_visto');
                })
                ->count(),
            'autorizada' => (int) (clone $baseQuery)
                ->where('estado_solicitud', EstadoSP::AUTORIZADA->value)
                ->where(function ($q) {
                    $q->where('item_visto', false)->orWhereNull('item_visto');
                })
                ->count(),
            'pagado' => (int) (clone $baseQuery)
                ->where('estado_solicitud', EstadoSP::PAGADO->value)
                ->where(function ($q) {
                    $q->where('item_visto', false)->orWhereNull('item_visto');
                })
                ->count(),
            'sin_factura' => (int) (clone $baseQuery)
                ->where('tiene_factura', false)
                ->where(function ($q) {
                    $q->where('item_visto', false)->orWhereNull('item_visto');
                })
                ->count(),
        ];

        if ($hasUltimas) {
            $ids = (clone $baseQuery)->pluck('id');
            $listQuery = SolicitudPago::query()
                ->with(SolicitudPago::eagerLodable())
                ->whereIn('id', $ids);

            $statusFilters = array_intersect_key($filters, array_flip(['estado_solicitud', 'tiene_factura']));
            if (! empty($statusFilters)) {
                $listQuery->filter($statusFilters);
            }

            $query = $listQuery->orderBy($sortBy, $order)->paginate($perPage);
        } else {
            $query = SolicitudPago::query()
                ->with(SolicitudPago::eagerLodable())
                ->filter($filters)
                ->orderBy($sortBy, $order)
                ->paginate($perPage);
        }

        $data = SolicitudPagoResource::collection($query)->resolve();

        return $this->paginated(
            $query->setCollection(collect($data)),
            'Datos paginados.',
            200,
            [
                'segment_counts' => $segmentCountsFormatted,
                'unread_segment_counts' => $unreadSegmentCountsFormatted,
            ]
        );
    }

    /**
     * Listado sin paginación, filtrando por proveedor
     */
    public function uindex(Request $request, Proveedor $proveedor): JsonResponse
    {
        $filters = $request->only(SolicitudPago::getFilters());
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');

        $query = SolicitudPago::query()
            ->with(['proveedor', 'empresaConstrucc', 'cuentasBancarias'])
            ->where('proveedor_id', $proveedor->id)
            ->filter($filters);

        // 📌 Aplicar filtro default última semana
        // $query = $this->aplicarFiltroUltimaSemana($query, $request);

        $items = $query
            ->orderBy($sortBy, $order)
            ->get();

        return $this->success(
            SolicitudPagoResource::collection($items)
        );
    }

    /**
     * Crear nueva solicitud
     */
    public function store(CrearSolicitudPagoRequest $request, Proveedor $proveedor): JsonResponse
    {
        $validated = $request->validated();

        $facturaPdf = $request->file('factura_pdf');
        $facturaXml = $request->file('factura_xml');
        $cotizacionFile = $request->file('cotizacion');

        if (! $facturaPdf || ! $facturaXml) {
            return response()->json([
                'success' => false,
                'message' => 'Los archivos PDF y XML son obligatorios.',
            ], 422);
        }

        $rutaPdf = $facturaPdf->store('facturas/pdf', 'private');
        $rutaXml = $facturaXml->store('facturas/xml', 'private');

        // Extraer datos del XML
        $datosXml = $this->extraerDatosXML($facturaXml);

        // Combinar serie y folio para formar el folio_factura
        $serie = $datosXml['serie'] ?? '';
        $folio = $datosXml['folio'] ?? '';
        $folioFactura = trim($serie . ($serie && $folio ? '-' : '') . $folio) ?: null;

        // Procesar archivo de cotización si existe
        $rutaCotizacion = null;
        if ($cotizacionFile) {
            $rutaCotizacion = $cotizacionFile->store('cotizaciones', 'private');
        }

        $numeroFolio = SolicitudPago::generarNumeroFolio($proveedor);
        $empresaConstructId = $validated['empresa_construcc_id'] ?? null;

        // Datos del usuario de Construcc que genera la SP
        $usuarioId = $validated['usuario_id'] ?? $validated['usuario_construcc_id'] ?? null;
        $empresaConstrucc = $proveedor->empresasConstrucc()->where('empresa_construcc.id', $empresaConstructId)->firstOrFail();
        $folio_consecutivo_construcc = $empresaConstrucc->obtenerFolioSiguienteSP();

        $usuarioNombre = $validated['usuario_nombre'] ?? $validated['residente'] ?? null;
        $cotizacion_id = $validated['cotizacion_id'] ?? null;
        $montoTotal = $validated['monto_total'];

        $solicitud = SolicitudPago::create([
            'proveedor_id' => $proveedor->id,
            'usuario_creador_id' => $request->user()->id ?? null,
            'numero_folio_solicitud' => $numeroFolio,
            'folio_factura' => $folioFactura,
            'datos_factura_xml' => $datosXml,
            'descripcion_concepto' => $validated['descripcion_concepto'] ?? '',
            'observaciones' => $validated['observaciones'] ?? null,
            'ruta_archivo_factura_pdf' => $rutaPdf,
            'ruta_archivo_factura_xml' => $rutaXml,
            'ruta_archivo_cotizacion' => $rutaCotizacion,
            // 
            'folio_sp_consecutivo' => $folio_consecutivo_construcc,
            'empresa_construcc_id' => $empresaConstructId,
            'usuario_id' => $usuarioId,
            'usuario_nombre' => $usuarioNombre,
            'cotizacion_id' => $cotizacion_id,
            'estado_solicitud' => 'pendiente',
            'fecha_registro_pendiente' => now(),
            'monto_total' => $montoTotal,
            'saldo_pendiente' => $montoTotal,
            'monto_abonado' => 0,
            'pago_completo' => false,
            'tiene_factura' => true,
        ]);

        $solicitud->addNotification();

        $this->interApiService->notifyNewSolicitudCompra($solicitud);

        // Sincronizar cuentas bancarias si se enviaron
        if (array_key_exists('cuentas_bancarias', $validated) && is_array($validated['cuentas_bancarias'])) {
            $solicitud->sincronizarCuentasBancarias($validated['cuentas_bancarias']);
        }


        return $this->success(
            new SolicitudPagoResource($solicitud->load(['proveedor', 'empresaConstrucc', 'cuentasBancarias'])),
            'Solicitud de pago creada correctamente.',
            201
        );
    }

    /**
     * Crear nueva solicitud
     */
    public function storeSinFactura(CrearSolicitudPagoSinFacturaRequest $request, Proveedor $proveedor): JsonResponse
    {
        $validated = $request->validated();

        $cotizacionFile = $request->file('cotizacion');
        $rutaCotizacion = $cotizacionFile->store('cotizaciones', 'private');

        $numeroFolio = SolicitudPago::generarNumeroFolio($proveedor);
        $empresaConstructId = $validated['empresa_construcc_id'] ?? null;

        // Datos del usuario de Construcc que genera la SP
        $usuarioId = $validated['usuario_id'] ?? $validated['usuario_construcc_id'] ?? null;
        $empresaConstrucc = $proveedor->empresasConstrucc()->where('empresa_construcc.id', $empresaConstructId)->firstOrFail();
        $folio_consecutivo_construcc = $empresaConstrucc->obtenerFolioSiguienteSP();

        $usuarioNombre = $validated['usuario_nombre'] ?? $validated['residente'] ?? null;
        $cotizacion_id = $validated['cotizacion_id'] ?? null;
        $montoTotal = $validated['monto_total'];

        $solicitud = SolicitudPago::create([
            'proveedor_id' => $proveedor->id,
            'numero_folio_solicitud' => $numeroFolio,
            'folio_factura' => '',
            'descripcion_concepto' => $validated['descripcion_concepto'] ?? '',
            'observaciones' => $validated['observaciones'] ?? null,
            'ruta_archivo_factura_pdf' => null,
            'ruta_archivo_factura_xml' => null,
            'ruta_archivo_cotizacion' => $rutaCotizacion,

            // 
            'folio_sp_consecutivo' => $folio_consecutivo_construcc,
            'empresa_construcc_id' => $empresaConstructId,
            'usuario_id' => $usuarioId,
            'usuario_nombre' => $usuarioNombre,
            'cotizacion_id' => $cotizacion_id,
            'estado_solicitud' => 'pendiente',
            'fecha_registro_pendiente' => now(),
            'monto_total' => $montoTotal,
            'saldo_pendiente' => $montoTotal,
            'monto_abonado' => 0,
            'pago_completo' => false,
            'tiene_factura' => false,
        ]);

        $this->interApiService->notifyNewSolicitudCompra($solicitud);

        /**
         * notificacion apra los contadores aqui
         */
        $solicitud->addNotification();


        // Sincronizar cuentas bancarias si se enviaron
        if (array_key_exists('cuentas_bancarias', $validated) && is_array($validated['cuentas_bancarias'])) {
            $solicitud->sincronizarCuentasBancarias($validated['cuentas_bancarias']);
        }


        return $this->success(
            new SolicitudPagoResource($solicitud->load(['proveedor', 'empresaConstrucc', 'cuentasBancarias'])),
            'Solicitud de pago creada correctamente.',
            201
        );
    }

    /**
     * Mostrar detalle
     */
    public function show(Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a este proveedor', 403);
        }

        if (
            $solicitudPago->estado_solicitud === 'rechazada' &&
            ! $solicitudPago->visto_rechazada
        ) {
            $solicitudPago->update([
                'visto_rechazada' => true,
            ]);
        }

        // 🔹 Marcar notificación como leída (integración con Laravel Notifications)
        $solicitudPago->markRead(auth()->user());


        // Cargar relaciones estándar + pagos parciales asociados
        $solicitudPago->load([
            ...SolicitudPago::eagerLodable(),
            'pagos' => function ($query) {
                $query->with([
                    'empresaConstrucc',
                    'proveedor',
                ])->orderByPivot('fecha_aplicacion', 'desc');
            },
        ]);

        // Fallback cuentas bancarias
        if ($solicitudPago->cuentasBancarias->isEmpty()) {
            $cuentasProveedor = $proveedor->cuentasBancarias
                ->where('estatus', 'activa')
                ->sortByDesc('preferida');

            if ($cuentasProveedor->isNotEmpty()) {
                $solicitudPago->setRelation(
                    'cuentasBancarias',
                    collect([$cuentasProveedor->first()])
                );
            }
        }

        $solicitudPagoData = (new SolicitudPagoResource($solicitudPago))->resolve();
        $datosFacturacionEsperados = $this->resolverDatosFacturacionParaSolicitud($solicitudPago);

        if (!empty($datosFacturacionEsperados)) {
            $solicitudPagoData['datos_facturacion'] = array_merge(
                $solicitudPagoData['datos_facturacion'] ?? [],
                $datosFacturacionEsperados
            );
        }
        $solicitudPagoData['pagos'] = ProveedorPagoResource::collection($solicitudPago->pagos)->resolve();

        return $this->success($solicitudPagoData);
    }

    /**
     * Resuelve la especificación de facturación esperada para mostrarla en el detalle de la SPP.
     * Replica las reglas usadas al subir factura XML.
     */
    private function resolverDatosFacturacionParaSolicitud(SolicitudPago $solicitudPago): array
    {
        $datosFacturacion = [];

        if ($solicitudPago->datos_facturacion_id) {

            $interResponse = $this->interApiService->obtenerDatosFacturacionEmpresa($solicitudPago->datos_facturacion_id);
            if (!$interResponse['success']) {
                Log::warning('InterAPI Razón Social falló', [
                    'sp_id' => $solicitudPago->id,
                    'response' => $interResponse
                ]);
            }
            $data = $interResponse['data']['data'] ?? [];

            $facturacionDefault = $data['facturacion_default'] ?? [];
            $razonSocial = $data['razon_social'] ?? [];
            $regimenFiscal      = $data['regimen_fiscal_default'] ?? null;

            $datosFacturacion = [
                'uso_cfdi'       => $facturacionDefault['uso_cfdi'] ?? null,
                'forma_pago'     => $facturacionDefault['forma_pago'] ?? null,
                'metodo_pago'    => $facturacionDefault['metodo_pago'] ?? null,
                'codigo_postal'  => $facturacionDefault['codigo_postal'] ?? null,
                'regimen_fiscal' => $regimenFiscal,
                'rfc'            => $razonSocial['rfc'] ?? null,
                'total'          => $solicitudPago->monto_total ?? null,
                'moneda'         => $solicitudPago->moneda ?? 'MXN',
            ];
        }

        if ($solicitudPago->razon_social_id && !$solicitudPago->datos_facturacion_id) {

            if (!$solicitudPago->uso || !$solicitudPago->mp || !$solicitudPago->fp) {
                Log::warning(
                    'La solicitud de pago no tiene la especificación mínima de facturación.',
                    [
                        'uso_cfdi'    => $solicitudPago->uso,
                        'metodo_pago' => $solicitudPago->mp,
                        'forma_pago'  => $solicitudPago->fp,
                    ]
                );
            }

            $interResponse = $this->interApiService
                ->obtenerDatosFacturacionRazonSocial($solicitudPago->razon_social_id);

            if (!$interResponse['success']) {
                Log::warning('InterAPI Razón Social falló', [
                    'sp_id' => $solicitudPago->id,
                    'response' => $interResponse
                ]);
            }

            $data = $interResponse['data'] ?? [];

            $cp = $data['cp'] ?? null;
            $regimenFiscal = $data['regimen_fiscal_default'] ?? $solicitudPago->rf;
            $rfc = $data['rfc'] ?? null;
            $razonSocial = $data['razon_social'] ?? null;

            $datosFacturacion = [
                'uso_cfdi'       => $solicitudPago->uso,
                'metodo_pago'    => $solicitudPago->mp,
                'forma_pago'     => $solicitudPago->fp,
                'codigo_postal'  => $cp,
                'regimen_fiscal' => $regimenFiscal,
                'rfc'            => $rfc,
                'total'          => $solicitudPago->monto_total ?? null,
                'moneda'         => $solicitudPago->moneda ?? 'MXN',
            ];
        }

        return  $datosFacturacion;
    }


    /**
     * Actualizar solicitud de pago
     */
    public function update(Request $request, Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a este proveedor', 403);
        }

        // Validar que se puede actualizar según el estado
        if (! in_array($solicitudPago->estado_solicitud, ['pendiente', 'procesando'])) {
            return $this->error('No se puede actualizar una solicitud en este estado', 422);
        }

        $solicitudPago->update($request->only([
            'descripcion_concepto',
            'observaciones',
            'usuario_id',
            'usuario_nombre',
            'monto_total',
        ]));

        // Si se actualiza el monto, recalcular saldos
        if ($request->has('monto_total')) {
            $solicitudPago->update([
                'saldo_pendiente' => $request->monto_total - $solicitudPago->monto_abonado,
            ]);
        }

        return $this->success(
            new SolicitudPagoResource($solicitudPago->load(SolicitudPago::eagerLodable())),
            'Solicitud de pago actualizada correctamente.'
        );
    }

    /**
     * Actualizar cuentas bancarias de la solicitud de pago
     */
    public function actualizarCuentasBancarias(Request $request, Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        $request->validate([
            'cuentas_bancarias' => 'required|array',
            'cuentas_bancarias.*.cuenta_bancaria_id' => 'required|integer|exists:cuentas_bancarias,id',
            'cuentas_bancarias.*.datos_especificos' => 'nullable|array',
            'cuentas_bancarias.*.datos_especificos.alias' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.banco_clave' => 'nullable|string|max:10',
            'cuentas_bancarias.*.datos_especificos.banco_nombre' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.cuenta' => 'nullable|string|max:20',
            'cuentas_bancarias.*.datos_especificos.clabe' => 'nullable|string|max:20',
            'cuentas_bancarias.*.datos_especificos.tarjeta' => 'nullable|string|max:20',
            'cuentas_bancarias.*.datos_especificos.titular_cuenta' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.referencia' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.estatus' => 'nullable|integer|min:0|max:2',
            'cuentas_bancarias.*.datos_especificos.sucursal' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.swift' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.preferida' => 'nullable|boolean',
        ]);

        // Sincronizar las cuentas bancarias
        $solicitudPago->sincronizarCuentasBancarias($request->cuentas_bancarias);

        return $this->success(
            new SolicitudPagoResource($solicitudPago->load(['proveedor', 'empresaConstrucc', 'cuentasBancarias'])),
            'Cuentas bancarias actualizadas correctamente.'
        );
    }

    /**
     * Subir comprobante (solo guarda el archivo, no cambia estado)
     */
    public function subirComprobantePago(Request $request, Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        $request->validate([
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('comprobante');
        $path = $file->store('comprobantes', 'private');

        $solicitudPago->update([
            'ruta_archivo_comprobante_pago' => $path,
            'fecha_con_comprobante' => now(),
        ]);

        $solicitudPago->enviarCorreoComprobantePagoAProveedor($path);

        return $this->success(
            new SolicitudPagoResource($solicitudPago->load(['proveedor', 'empresaConstrucc', 'cuentasBancarias'])),
            'Comprobante de pago subido correctamente.'
        );
    }

    /**
     * Descargar factura PDF
     */
    public function descargarFacturaPdf(Proveedor $proveedor, SolicitudPago $solicitudPago)
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        if (! $solicitudPago->ruta_archivo_factura_pdf || ! Storage::disk('private')->exists($solicitudPago->ruta_archivo_factura_pdf)) {
            return $this->success(['archivo_no_disponible' => true], 'Factura PDF no disponible', 200);
        }

        return response()->download(
            Storage::disk('private')->path($solicitudPago->ruta_archivo_factura_pdf)
        );
    }

    /**
     * Descargar factura XML
     */
    public function descargarFacturaXml(Proveedor $proveedor, SolicitudPago $solicitudPago)
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        if (! $solicitudPago->ruta_archivo_factura_xml || ! Storage::disk('private')->exists($solicitudPago->ruta_archivo_factura_xml)) {
            return $this->success(['archivo_no_disponible' => true], 'Factura XML no disponible', 200);
        }

        return response()->download(
            Storage::disk('private')->path($solicitudPago->ruta_archivo_factura_xml)
        );
    }

    /**
     * Descargar comprobante de pago
     */
    public function descargarComprobantePago(Proveedor $proveedor, SolicitudPago $solicitudPago)
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        if (! $solicitudPago->ruta_archivo_comprobante_pago || ! Storage::disk('private')->exists($solicitudPago->ruta_archivo_comprobante_pago)) {
            return $this->success(['archivo_no_disponible' => true], 'Comprobante no disponible', 200);
        }

        return response()->download(
            Storage::disk('private')->path($solicitudPago->ruta_archivo_comprobante_pago)
        );
    }

    /**
     * Descargar comprobante de un Pago SPP (pago parcial)
     */
    public function descargarComprobantePagoParcial(Proveedor $proveedor, PagoSPP $pago)
    {
        if ($pago->proveedor_id !== $proveedor->id) {
            return $this->error('El pago no pertenece a la empresa en GestionPlus', 403);
        }

        if (! $pago->comprobante_pago || ! Storage::disk('private')->exists($pago->comprobante_pago)) {
            return $this->success(['archivo_no_disponible' => true], 'Comprobante no disponible', 200);
        }

        return response()->download(
            Storage::disk('private')->path($pago->comprobante_pago)
        );
    }

    /**
     * Descargar cotización
     */
    public function descargarCotizacion(Proveedor $proveedor, SolicitudPago $solicitudPago)
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        if (! $solicitudPago->ruta_archivo_cotizacion || ! Storage::disk('private')->exists($solicitudPago->ruta_archivo_cotizacion)) {
            return $this->success(['archivo_no_disponible' => true], 'Cotización no disponible', 200);
        }

        return response()->download(
            Storage::disk('private')->path($solicitudPago->ruta_archivo_cotizacion)
        );
    }

    /**
     * Autorizar
     */
    public function autorizar(Request $request, Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        $solicitudPago->update([
            'estado_solicitud' => 'autorizada',
            'fecha_aprobado' => now(),
        ]);

        return $this->success(
            new SolicitudPagoResource($solicitudPago->load(['proveedor', 'empresaConstrucc', 'cuentasBancarias'])),
            'Solicitud autorizada correctamente.'
        );
    }

    /**
     * Rechazar
     */
    public function rechazar(Request $request, Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        $request->validate([
            'motivo_rechazo' => 'required|string|max:500',
        ]);

        $solicitudPago->update([
            'estado_solicitud' => 'rechazada',
            'fecha_rechazado' => now(),
            'motivo_rechazo' => $request->motivo_rechazo,
        ]);

        return $this->success(
            new SolicitudPagoResource($solicitudPago->load(['proveedor', 'empresaConstrucc', 'cuentasBancarias'])),
            'Solicitud rechazada correctamente.'
        );
    }

    /**
     * Confirmar pago de solicitud (desde el proveedor)
     */
    public function confirmarPagoSP(Request $request, Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a este proveedor', 403);
        }

        if ($solicitudPago->estado_solicitud !== 'autorizada') {
            return $this->error('Solo se pueden confirmar solicitudes autorizadas', 422);
        }

        $solicitudPago->update([
            'estado_solicitud' => 'pagada',
            'fecha_confirmacion_pago' => now(),
            'pago_completo' => true,
        ]);

        return $this->success(
            new SolicitudPagoResource($solicitudPago->load(SolicitudPago::eagerLodable())),
            'Pago confirmado correctamente.'
        );
    }

    /**
     * Cambiar estado a procesando
     */
    public function procesando(Request $request, Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a la empresa en GestionPlus', 403);
        }

        if ($solicitudPago->estado_solicitud !== 'pendiente') {
            return $this->error('Solo se pueden marcar como procesando las solicitudes pendientes', 422);
        }

        $solicitudPago->update([
            'estado_solicitud' => 'procesando',
            'fecha_procesando' => now(),
        ]);

        return $this->success(
            new SolicitudPagoResource($solicitudPago->load(SolicitudPago::eagerLodable())),
            'Solicitud marcada como procesando.'
        );
    }

    /**
     * Obtener histórico de OC y SP del proveedor
     */
    public function historico(Request $request, Proveedor $proveedor): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        // Fecha por defecto: última semana (pero si el cliente envía fecha_desde/fecha_hasta se usan)
        // $fechaDesde = $request->input('fecha_desde', now()->subDays(7)->startOfDay());
        // $fechaHasta = $request->input('fecha_hasta', now()->endOfDay());

        // Base query
        $query = SolicitudPago::where('proveedor_id', $proveedor->id)
            ->with(['empresaConstrucc', 'ordenesCompra'])
            // ->whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->orderBy('created_at', 'desc');

        // Excluir las solicitudes RECHAZADAS que ya fueron marcadas como vistas
        // (Queremos mantener: registros que NO sean rechazados OR si lo son, que NO estén vistos)
        $query->where(function ($q) {
            $q->where('estado_solicitud', '<>', 'rechazada')
                ->orWhere('visto_rechazada', false);
        });

        $solicitudes = $query->paginate($perPage);

        // Mapear datos con información de OC vinculada
        $data = $solicitudes->getCollection()->map(function ($sp) {
            $ordenCompra = $sp->ordenesCompra->first() ?? null;

            return [
                'solicitud_pago' => [
                    'id' => $sp->id,
                    'numero_folio_solicitud' => $sp->numero_folio_solicitud,
                    'monto_total' => $sp->monto_total,
                    'estado_solicitud' => $sp->estado_solicitud,
                    'fecha_creacion' => $sp->created_at,
                    'descripcion_concepto' => $sp->descripcion_concepto,
                    'usuario_id' => $sp->usuario_id,
                    'usuario_nombre' => $sp->usuario_nombre,
                    'origen_oc' => $sp->origen_oc ?? false,
                    'visto_rechazada' => $sp->visto_rechazada ?? false,
                ],
                'orden_compra' => $ordenCompra ? [
                    'id' => $ordenCompra->id,
                    'numero_orden' => $ordenCompra->numero_orden,
                    'importe_total' => $ordenCompra->importe_total,
                    'estado' => $ordenCompra->estado,
                    'fecha_orden' => $ordenCompra->fecha_orden,
                    'monto_asociado' => $ordenCompra->pivot->monto_asociado ?? 0,
                    'fecha_vinculacion' => $ordenCompra->pivot->fecha_vinculacion ?? null,
                ] : null,
                'empresa' => $sp->empresaConstrucc ? [
                    'id' => $sp->empresaConstrucc->id,
                    'nombre' => $sp->empresaConstrucc->nombre,
                    'rfc' => $sp->empresaConstrucc->rfc,
                ] : null,
                'timeline' => $this->getTimelineSP($sp),
            ];
        });

        // Reemplazamos la colección paginada por la nueva colección mapeada
        return $this->paginated($solicitudes->setCollection($data));
    }

    /**
     * Generar timeline de estados de una SP
     */
    private function getTimelineSP(SolicitudPago $sp): array
    {
        $timeline = [];

        if ($sp->fecha_registro_pendiente) {
            $timeline[] = [
                'estado' => EstadoSP::PENDIENTE->value,
                'fecha' => $sp->fecha_registro_pendiente,
                'descripcion' => 'Solicitud creada',
            ];
        }

        if ($sp->fecha_procesando) {
            $timeline[] = [
                'estado' => 'procesando',
                'fecha' => $sp->fecha_procesando,
                'descripcion' => 'En proceso de revisión',
            ];
        }

        if ($sp->fecha_aprobado) {
            $timeline[] = [
                'estado' => EstadoSP::AUTORIZADA->value,
                'fecha' => $sp->fecha_aprobado,
                'descripcion' => 'Solicitud autorizada',
            ];
        }

        if ($sp->fecha_rechazado) {
            $timeline[] = [
                'estado' => EstadoSP::RECHAZADA->value,
                'fecha' => $sp->fecha_rechazado,
                'descripcion' => 'Solicitud rechazada: ' . ($sp->motivo_rechazo ?? 'Sin motivo especificado'),
            ];
        }

        if ($sp->fecha_confirmacion_pago) {
            $timeline[] = [
                'estado' => EstadoSP::PAGADO->value,
                'fecha' => $sp->fecha_confirmacion_pago,
                'descripcion' => 'Pago confirmado',
            ];
        }

        return $timeline;
    }

    /**
     * Obtener conteo de solicitudes por estado
     */
    public function conteoPorEstado(Request $request, Proveedor $proveedor): JsonResponse
    {
        $filters = $request->only(SolicitudPago::getFilters());
        unset($filters['ultimas_spp'], $filters['estado_solicitud']);

        // Base query con filtros opcionales (sin limitar a últimas N ni a un solo estado)
        $baseQuery = SolicitudPago::query()
            ->where('proveedor_id', $proveedor->id);

        if (! empty($filters)) {
            $baseQuery->filter($filters);
        }

        // Conteos por estado (usando STRINGS - el campo estado_solicitud NO usa el enum)
        $conteos = [
            'total' => (clone $baseQuery)->count(),
            'pendientes' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PENDIENTE->value)->count(),
            // => (clone $baseQuery)->where('estado_solicitud', 'pendiente')->count(),
            'autorizadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::AUTORIZADA->value)->count(),
            // => (clone $baseQuery)->where('estado_solicitud', 'autorizada')->count(),
            'rechazadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::RECHAZADA->value)->count(),
            // => (clone $baseQuery)->where('estado_solicitud', 'rechazada')->count(),
            'pagadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PAGADO->value)->count(),
            // => (clone $baseQuery)->where('estado_solicitud', 'pagada')->count(),
            'sin_factura' => (clone $baseQuery)->where('tiene_factura', false)->count(),
        ];

        return $this->success($conteos, 'Conteo por estado obtenido correctamente');
    }

    /**
     * Eliminar
     */
    public function destroy(Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('Solicitud no pertenece a este proveedor', 403);
        }

        $solicitudPago->delete();

        return $this->success(null, 'Solicitud de pago eliminada correctamente.');
    }

    /**
     * Empresas de construcción
     */
    public function empresasConstructoras(Request $request): JsonResponse
    {
        $search = $request->input('search', '');
        $limit = $request->input('limit', 20);

        $query = EmpresaConstrucc::activo();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                    ->orWhere('razon_social', 'LIKE', "%{$search}%")
                    ->orWhere('rfc', 'LIKE', "%{$search}%");
            });
        }

        $empresas = $query->limit($limit)->get();

        return $this->success(
            $empresas->map(function ($empresa) {
                return [
                    'id' => $empresa->id,
                    'nombre' => $empresa->nombre,
                    'rfc' => $empresa->rfc,
                    'razon_social' => $empresa->razon_social,
                    'representante_legal' => $empresa->representante_legal,
                ];
            })
        );
    }

    /**
     * Métricas del dashboard de SP
     * Retorna conteo de solicitudes por estado
     */
    public function getDashboardMetrics(Request $request, Proveedor $proveedor): JsonResponse
    {
        // Obtener TODOS los filtros disponibles del modelo
        $filters = $request->only(SolicitudPago::getFilters());

        // // Filtro por defecto: última semana (si el cliente no envía fechas)
        $fechaDesde = $request->input('fecha_registro_pendiente_desde', now()->subDays(15)->startOfDay());
        $fechaHasta = $request->input('fecha_registro_pendiente_hasta', now()->endOfDay());

        // Base query
        $baseQuery = SolicitudPago::query()
            ->where('proveedor_id', $proveedor->id)
            // ->whereBetween('created_at', [$fechaDesde, $fechaHasta]);
            ->where('created_at', '>=', now()->subDays(15));

        // Aplicar filtros del sistema si existen
        if (!empty($filters)) {
            $baseQuery->filter($filters);
        }

        // filtr 15 dias atras - solo presupuestos no vistos (item_visto = false)
        $presupuestosCount = Presupuesto::where('proveedor_id', $proveedor->id)
            ->where('created_at', '>=', now()->subDays(15))
            ->where('item_visto', false)
            ->count();

        // Presupuestos recibidos (otro proveedor te cotiza): sin usar item_visto;
        // conteo = notificaciones DB no leídas PresupuestoRecibidoClienteProveedor cuyo presupuesto
        // pertenece a este proveedor como receptor (misma ventana de 15 días que el listado).
        $presupuestosRecibidosIds = Presupuesto::query()
            ->where('proveedor_receptor_id', $proveedor->id)
            ->where('created_at', '>=', now()->subDays(15))
            ->pluck('id');

        $presupuestosRecibidosNoLeidos = 0;
        if ($presupuestosRecibidosIds->isNotEmpty() && $request->user()) {
            $presupuestosRecibidosNoLeidos = $request->user()
                ->unreadNotifications()
                ->where('type', PresupuestoRecibidoClienteProveedorNotification::class)
                ->whereIn('data->presupuesto_id', $presupuestosRecibidosIds->all())
                ->count();
        }
        // Rechazadas NO vistas
        // $rechazadasNoVistas = (clone $baseQuery)
        //     ->where('estado_solicitud', EstadoSP::RECHAZADA->value)
        //     ->where('visto_rechazada', false);

        // Conteos por estado (RESPETANDO lo que ya retornas)
        $conteos = [
            'total_sp' => (clone $baseQuery)->count(),
            'sp_pendientes' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PENDIENTE->value)->where('item_visto', false)->count(),
            'sp_autorizadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::AUTORIZADA->value)->where('item_visto', false)->count(),
            'sp_en_proceso' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PENDIENTE->value)->where('item_visto', false)->count(),
            'sp_rechazadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::RECHAZADA->value)->where('item_visto', false)->count(),
            'sp_pagadas' => (clone $baseQuery)->where('estado_solicitud', EstadoSP::PAGADO->value)->where('item_visto', false)->count(),
            'sp_sin_factura' => (clone $baseQuery)->where('tiene_factura', false)->where('item_visto', false)->count(),
            'presupuestos' => $presupuestosCount,
            'presupuestos_recibidos' => $presupuestosRecibidosNoLeidos,
        ];

        return $this->success($conteos, 'Métricas del dashboard obtenidas correctamente');
    }


    /**
     * Filtrara 1 semana atras 
     */
    private function aplicarFiltroUltimaSemana($query, Request $request)
    {
        $fechaDesde = $request->input('fecha_desde', now()->subDays(7)->startOfDay());
        $fechaHasta = $request->input('fecha_hasta', now()->endOfDay());

        return $query->whereBetween('created_at', [$fechaDesde, $fechaHasta]);
    }

    /**
     * Extraer datos del XML de la factura
     */
    private function extraerDatosXML($archivoXml): array
    {
        try {
            $contenidoXml = file_get_contents($archivoXml->getRealPath());
            $xml = simplexml_load_string($contenidoXml, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                Log::warning('No se pudo parsear el XML de la factura');
                return [];
            }

            $namespaces = $xml->getNamespaces(true);
            $cfdiNamespace = $namespaces['cfdi'] ?? $namespaces[''] ?? null;
            $cfdiRoot = $cfdiNamespace ? $xml->children($cfdiNamespace) : $xml;
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

            if (isset($cfdiRoot->Emisor)) {
                $emisor = $cfdiRoot->Emisor->attributes();
                $datos['emisor'] = [
                    'rfc' => (string) ($emisor->Rfc ?? ''),
                    'nombre' => (string) ($emisor->Nombre ?? ''),
                    'regimen_fiscal' => (string) ($emisor->RegimenFiscal ?? ''),
                ];
            }

            if (isset($cfdiRoot->Receptor)) {
                $receptor = $cfdiRoot->Receptor->attributes();
                $datos['receptor'] = [
                    'rfc' => (string) ($receptor->Rfc ?? ''),
                    'nombre' => (string) ($receptor->Nombre ?? ''),
                    'uso_cfdi' => (string) ($receptor->UsoCFDI ?? ''),
                    'domicilio_fiscal_receptor' => (string) ($receptor->DomicilioFiscalReceptor ?? ''),
                    'regimen_fiscal_receptor' => (string) ($receptor->RegimenFiscalReceptor ?? ''),
                ];

                // Claves planas usadas por validarEspecificacionFactura
                $datos['uso_cfdi'] = (string) ($receptor->UsoCFDI ?? '');
                $datos['regimen_fiscal_receptor'] = (string) ($receptor->RegimenFiscalReceptor ?? '');
                $datos['codigo_postal_receptor'] = (string) ($receptor->DomicilioFiscalReceptor ?? '');
                $datos['rfc_receptor'] = (string) ($receptor->Rfc ?? '');
            }

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
     * Subir archivos de factura (PDF y XML) a una Solicitud de Pago existente
     */
    public function uploadFacturaPdfXml(
        Request $request,
        Proveedor $proveedor,
        SolicitudPago $solicitudPago
    ): JsonResponse {

        $request->validate([
            'factura_pdf' => 'required|file|mimes:pdf|max:10240',
            'factura_xml' => 'required|file|mimes:xml|max:5120',
        ]);

        $facturaPdf = $request->file('factura_pdf');
        $facturaXml = $request->file('factura_xml');

        $rutaPdf = $facturaPdf->store('facturas/pdf', 'private');
        $rutaXml = $facturaXml->store('facturas/xml', 'private');

        $datosXml = $this->extraerDatosXML($facturaXml);

        // 🔎 Validación de especificación (InterAPI)
        // $validacion = $this->validarEspecificacionFacturaInterApi($solicitudPago, $datosXml);
        // if ($validacion) {
        //     return $validacion;
        // }

        $serie = $datosXml['serie'] ?? '';
        $folio = $datosXml['folio'] ?? '';
        $folioFactura = trim($serie . ($serie && $folio ? '-' : '') . $folio) ?: null;

        $solicitudPago->update([
            'folio_factura' => $folioFactura,
            'datos_factura_xml' => $datosXml,
            'ruta_archivo_factura_pdf' => $rutaPdf,
            'ruta_archivo_factura_xml' => $rutaXml,
            'tiene_factura' => true,
        ]);

        $solicitudPago->enviarCorreoFacturaAEmpresaConstrucc($rutaPdf, $rutaXml);

        return $this->success(
            new SolicitudPagoResource(
                $solicitudPago->load(SolicitudPago::eagerLodable())
            ),
            'Factura cargada correctamente.',
            201
        );
    }


    /**
     * Subir únicamente el PDF de la factura
     */
    public function uploadFacturaPdf(Request $request, Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        $request->validate([
            'factura_pdf' => 'required|file|mimes:pdf|max:10240',
        ]);

        $facturaPdf = $request->file('factura_pdf');

        $rutaPdf = $facturaPdf->store('facturas/pdf', 'private');

        $solicitudPago->update([
            'ruta_archivo_factura_pdf' => $rutaPdf,
            'tiene_factura' => true,
        ]);

        /**
         * TODO: aqio se dispara la ntofiocacion  
         *  event(new FacturaAsociadaASolicitudPago($solicitudPago, 'xml'));
         */
        $this->solicitudTieneFacturaCompleta($solicitudPago);


        return $this->success(
            new SolicitudPagoResource(
                $solicitudPago->load(SolicitudPago::eagerLodable())
            ),
            'Factura PDF cargada correctamente.',
            201
        );
    }

    /**
     * Subir únicamente el XML de la factura
     */
    public function uploadFacturaXml(
        Request $request,
        Proveedor $proveedor,
        SolicitudPago $solicitudPago
    ): JsonResponse {

        Log::info('🟢 upload Factura XML', ['sp' => $solicitudPago->id]);

        if ($solicitudPago->proveedor_id !== $proveedor->id) {
            return $this->error('La solicitud no pertenece al proveedor.', 403);
        }

        $request->validate([
            'factura_xml' => [
                'required',
                'file',
                'mimetypes:text/xml,application/xml',
                'max:5120',
            ],
        ]);

        $facturaXml = $request->file('factura_xml');
        $datosXml = $this->extraerDatosXML($facturaXml);

        if (empty($datosXml)) {
            return $this->error('El archivo XML no es un CFDI válido.', 422);
        }

        Log::info('🧪 Factura parseada', ['xml' => $datosXml]);

        $datosFacturacion = null;

        if ($solicitudPago->datos_facturacion_id) {

            $interResponse = $this->interApiService->obtenerDatosFacturacionEmpresa($solicitudPago->datos_facturacion_id);
            if (!$interResponse['success']) {
                return $this->error(
                    'No fue posible obtener los datos de facturación desde Construcc.',
                    $interResponse['error'] ?? null,
                    500
                );
            }
            $data = $interResponse['data']['data'] ?? [];

            $facturacionDefault = $data['facturacion_default'] ?? [];
            $razonSocial = $data['razon_social'] ?? [];
            $regimenFiscal      = $data['regimen_fiscal_default'] ?? null;

            $datosFacturacion = [
                'uso_cfdi'       => $facturacionDefault['uso_cfdi'] ?? null,
                'forma_pago'     => $facturacionDefault['forma_pago'] ?? null,
                'metodo_pago'    => $facturacionDefault['metodo_pago'] ?? null,
                'codigo_postal'  => $facturacionDefault['codigo_postal'] ?? null,
                'regimen_fiscal' => $regimenFiscal,
                'rfc'            => $razonSocial['rfc'] ?? null,
                'total'          => $solicitudPago->monto_total ?? null,
                'moneda'         => $solicitudPago->moneda ?? 'MXN',
            ];
        }

        if ($solicitudPago->razon_social_id && !$solicitudPago->datos_facturacion_id) {

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

            $interResponse = $this->interApiService
                ->obtenerDatosFacturacionRazonSocial($solicitudPago->razon_social_id);

            if (!$interResponse['success']) {
                return $this->error(
                    'No fue posible obtener la razón social desde Construcc.',
                    $interResponse['error'] ?? null,
                    500
                );
            }

            $data = $interResponse['data'] ?? [];

            $cp = $data['cp'] ?? null;
            $regimenFiscal = $data['regimen_fiscal_default'] ?? $solicitudPago->rf;
            $rfc = $data['rfc'] ?? null;
            $razonSocial = $data['razon_social'] ?? null;

            $datosFacturacion = [
                'uso_cfdi'       => $solicitudPago->uso,
                'metodo_pago'    => $solicitudPago->mp,
                'forma_pago'     => $solicitudPago->fp,
                'codigo_postal'  => $cp,
                'regimen_fiscal' => $regimenFiscal,
                'rfc'            => $rfc,
                'total'          => $solicitudPago->monto_total ?? null,
                'moneda'         => $solicitudPago->moneda ?? 'MXN',
            ];
        }

        if ($datosFacturacion) {
            Log::info('🧾 Comparativa Facturación InterAPI vs CFDI XML', [
                'sp_id' => $solicitudPago->id,
                'interapi' => $datosFacturacion,
                'xml' => $datosXml
            ]);

            $errores = $solicitudPago->validarEspecificacionFactura($datosXml, $datosFacturacion);

            if (!empty($errores)) {
                return $this->error(
                    'La factura no cumple con la especificación requerida.',
                    $errores,
                    422
                );
            }
        }

        $serie = $datosXml['serie'] ?? '';
        $folio = $datosXml['folio'] ?? '';
        $folioFactura = trim($serie . ($serie && $folio ? '-' : '') . $folio) ?: null;
        $rutaXml = $facturaXml->store('facturas/xml', 'private');

        $solicitudPago->update([
            'folio_factura'           => $folioFactura,
            'datos_factura_xml'      => $datosXml,
            'ruta_archivo_factura_xml' => $rutaXml,
        ]);

        $this->solicitudTieneFacturaCompleta($solicitudPago);

        return $this->success(
            new SolicitudPagoResource(
                $solicitudPago->load(SolicitudPago::eagerLodable())
            ),
            'Factura XML cargada correctamente.',
            201
        );
    }



    /**
     * Verifica si la Solicitud de Pago ya tiene PDF y XML
     * y envía la notificación SOLO una vez.
     */
    private function solicitudTieneFacturaCompleta(SolicitudPago $solicitudPago): bool
    {
        $tienePdf = !empty($solicitudPago->ruta_archivo_factura_pdf);
        $tieneXml = !empty($solicitudPago->ruta_archivo_factura_xml);

        if (! $tienePdf || ! $tieneXml) {
            return false;
        }

        $solicitudPago->enviarCorreoFacturaAEmpresaConstrucc($solicitudPago->ruta_archivo_factura_pdf, $solicitudPago->ruta_archivo_factura_xml);

        $solicitudPago->update(['tiene_factura' => true,]);

        return true;
    }
}
