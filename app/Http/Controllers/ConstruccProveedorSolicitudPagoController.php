<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSP;
use App\Enums\EstadoSolicitud;
use App\Http\Requests\Construcc\ConstruccProveedorGenerarSppRequest;
use App\Http\Resources\Construcc\ConstruccProveedorSppResource;
use App\Models\CuentaBancaria;
use App\Models\Proveedor;
use App\Models\SolicitudPago;
use App\Services\InterApiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConstruccProveedorSolicitudPagoController extends Controller
{
    use ApiResponse;

    protected $interApiService;

    public function __construct(InterApiService $interApiService)
    {
        $this->interApiService = $interApiService;
    }

    /**
     * Generar Solicitud de Pago con un proveedor existente (tipo_alta = 2)
     *
     * Flujo:
     * 1. Validar que el proveedor sea tipo_alta = 2
     * 2. Validar que la cuenta bancaria pertenezca al proveedor
     * 3. Almacenar archivos (factura_pdf, factura_xml, cotizacion)
     * 4. Extraer datos del XML
     * 5. Crear SolicitudPago con estado según nivel_id
     * 6. Sincronizar cuenta bancaria con SP
     * 7. Agregar notificación y llamar InterApiService
     */
    public function store(ConstruccProveedorGenerarSppRequest $request, Proveedor $proveedor): JsonResponse
    {

        Log::info('✅ Iniciando inserción de SPP desde appConstrucc.');

        try {
            $validated = $request->validated();

            Log::info('🧪 VALIDATED DATA', [
                'keys' => array_keys($validated),
                'data' => $validated,
            ]);

            // ============================================
            // PASO 1: Validar que el proveedor sea tipo_alta = 2
            // ============================================
            // if ($proveedor->tipo_alta !== 2) {
            //     return $this->error(
            //         'Solo se pueden generar SPP con proveedores registrados por usuarios construcción (tipo_alta = 2).',
            //         [
            //             'proveedor_id' => $proveedor->id,
            //             'tipo_alta_actual' => $proveedor->tipo_alta,
            //         ],
            //         422
            //     );
            // }

            // ============================================
            // PASO 2: Validar que la cuenta bancaria pertenezca al proveedor
            // ============================================
            $cuentaBancariaId = $validated['cuenta_bancaria_id'] ?? null;

            if ($cuentaBancariaId) {
                $cuentaBancaria = CuentaBancaria::find($cuentaBancariaId);

                if (!$cuentaBancaria || $cuentaBancaria->proveedor_id !== $proveedor->id) {
                    return $this->error(
                        'La cuenta bancaria seleccionada no pertenece a este proveedor.',
                        [
                            'cuenta_bancaria_id' => $cuentaBancariaId,
                            'proveedor_id' => $proveedor->id,
                        ],
                        422
                    );
                }
            }
            // ============================================
            // PASO 3: Almacenar archivos
            // ============================================
            $facturaPdf = $request->file('factura_pdf');
            $facturaXml = $request->file('factura_xml');
            $cotizacionFile = $request->file('cotizacion');

            // Validación ya hecha en FormRequest:
            // - Si hay cotización → factura opcional
            // - Si NO hay cotización → factura obligatoria

            $rutaPdf = null;
            $rutaXml = null;
            $datosXml = [];
            $folioFactura = null;

            // Procesar factura si existe
            if ($facturaPdf && $facturaXml) {
                $rutaPdf = $facturaPdf->store('facturas/pdf', 'private');
                $rutaXml = $facturaXml->store('facturas/xml', 'private');

                // Extraer datos del XML
                $datosXml = $this->extraerDatosXML($facturaXml->getRealPath());

                // Combinar serie y folio para formar el folio_factura
                $serie = $datosXml['serie'] ?? '';
                $folio = $datosXml['folio'] ?? '';
                $folioFactura = trim($serie . ($serie && $folio ? '-' : '') . $folio) ?: null;
            }

            // Procesar archivo de cotización si existe
            $rutaCotizacion = null;
            if ($cotizacionFile) {
                $rutaCotizacion = $cotizacionFile->store('cotizaciones', 'private');
            }

            Log::info('✅ Archivos almacenados para SPP con proveedor existente', [
                'proveedor_id' => $proveedor->id,
                'factura_pdf' => $rutaPdf,
                'factura_xml' => $rutaXml,
                'cotizacion' => $rutaCotizacion,
                'folio_factura' => $folioFactura,
                'tiene_factura' => $rutaPdf !== null,
            ]);

            // ============================================
            // PASO 4: Crear solicitud de pago
            // ============================================
            $numeroFolio = SolicitudPago::generarNumeroFolio($proveedor);
            $montoTotal = $validated['monto_total'];

            // Obtener folio consecutivo de empresa construcción
            $empresaConstructId = $validated['empresa_construcc_id'];
            $usuarioId = $validated['usuario_id'];
            $usuarioNombre = $validated['usuario_nombre'];

            $folio_consecutivo_construcc = null;
            if ($empresaConstructId) {
                $empresaConstrucc = \App\Models\EmpresaConstrucc::find($empresaConstructId);

                if ($empresaConstrucc) {
                    $folio_consecutivo_construcc = $empresaConstrucc->obtenerFolioSiguienteSP();
                }
            }

            // Determinar estado inicial según el nivel del usuario
            $nivelId = $validated['nivel_id'];

            // Niveles que aprueban: DG, DT, PC
            $nivelesAprobadores = [0, 1, 2, 3, 5]; // Admin, DG, DT, DA, PC
            // 4=SI, 6=RO, 7 → PENDIENTE (requieren aprobación)

            // // Director Administrativo: va directo a pago
            // $esDA = $nivelId === 3;

            // Director que auto-aprueba: DG, DT, PC
            $esDirectorAprobador = in_array($nivelId, $nivelesAprobadores);

            // Mapeo de nivel a campo de rol
            $nivelToRol = [
                0 => 'dg', // Admin (se trata como residente, requiere aprobación)
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
                'tiene_factura' => $rutaPdf !== null && $rutaXml !== null, // true si tiene factura, false si solo tiene cotización

                // Campos adicionales de la solicitud
                'obra_id' => $validated['obra_id'] ?? null,
                'tipo' => $validated['tipo'] ?? null,
                'tipo_id' => $validated['tipo_id'] ?? null,
                'notas' => $validated['notas'] ?? null,
                'utilizara' => $validated['utilizara'] ?? null,
                'equipo' => $validated['equipo'] ?? null,
                'equipo_id' => $validated['equipo_id'] ?? null,
            ];

            if ($esDirectorAprobador) {
                // Directores DG, DT, PC: Auto-aprueban (verificada = true, autorizada)
                $rolField = $nivelToRol[$nivelId];
                $fechaField = "{$rolField}_fecha";

                $datosSP['verificada'] = true;
                $datosSP['estado_solicitud'] = EstadoSP::AUTORIZADA->value;
                $datosSP['fecha_registro_pendiente'] = now();
                $datosSP['fecha_aprobado'] = now();
                $datosSP[$rolField] = EstadoSolicitud::AUTORIZADA->value;
                $datosSP[$fechaField] = now();
            } else {
                // Residentes, Superintendentes, Admin, otros: Requiere validación y aprobación
                $datosSP['verificada'] = true;
                $datosSP['estado_solicitud'] = EstadoSP::PENDIENTE->value;
                $datosSP['fecha_registro_pendiente'] = now();
            }

            $montoParcial = $validated['monto_parcial'] ?? null;
            $motivoParcial = $validated['motivo_parcial'] ?? null;

            if (!is_null($montoParcial) && (float) $montoParcial > 0 && !is_null($motivoParcial)) {
                $datosSP['monto_autorizado'] = $montoParcial;
                $datosSP['usuario_autorizo_parcial_id'] = $usuarioId;
                $datosSP['usuario_autorizo_parcial_nombre'] = $usuarioNombre;
                $datosSP['fecha_autorizacion_parcial'] = now();
                $datosSP['motivo_autorizacion_parcial'] = $motivoParcial;

                $datosSP['validacion_usuario_id'] = $usuarioId;
                $datosSP['validacion_usuario_nombre'] = $usuarioNombre;
                $datosSP['validacion_fecha'] = now();
                $datosSP['validacion_monto'] = $montoParcial;
                $datosSP['validacion_motivo'] = $motivoParcial;
            }

            DB::beginTransaction();

            $solicitud = SolicitudPago::create($datosSP);

            Log::info('✅ Solicitud de pago creada con proveedor existente', [
                'solicitud_pago_id' => $solicitud->id,
                'numero_folio' => $solicitud->numero_folio_solicitud,
                'proveedor_id' => $proveedor->id,
                'monto_total' => $montoTotal,
                'verificada' => $solicitud->verificada,
                'estado_solicitud' => $solicitud->estado_solicitud,
                'nivel_id' => $nivelId,
                'auto_aprobada_por_director' => $esDirectorAprobador,
                'tiene_factura' => $solicitud->tiene_factura,
                'solo_cotizacion' => !$solicitud->tiene_factura && $rutaCotizacion !== null,
                // 'directo_a_pago_por_da' => $esDA,
            ]);

            // ============================================
            // NOTIFICACIÓN: Si es director aprobador o DA, notificar a otros directores
            // ============================================
            if ($esDirectorAprobador) {
                // TODO: Implementar notificación a directores (DG, DT, DA, PC)
                // cuando un director crea y auto-aprueba una SP o DA la envía directo a pago
                // 
                // Datos para la notificación:
                // - solicitud_pago_id: $solicitud->id
                // - numero_folio: $solicitud->numero_folio_solicitud
                // - empresa_construcc_id: $empresaConstructId
                // - usuario_que_creo_id: $usuarioId
                // - usuario_que_creo_nombre: $usuarioNombre
                // - nivel_id: $nivelId (rol del director que creó)
                // - estado_final: $solicitud->estado_solicitud
                // - monto_total: $montoTotal
                // - proveedor: $proveedor->nombre_comercial
                // 
                // Llamar al servicio de notificaciones inter-API cuando esté implementado:
                // $this->interApiService->notifyDirectoresSpAutoaprobada($solicitud, $nivelId, $empresaConstructId);

                Log::info('📬 TODO: Enviar notificación a otros directores sobre SP ', [
                    'solicitud_pago_id' => $solicitud->id,
                    'director_nivel_id' => $nivelId,
                    'rol_autoaprobacion' => $nivelToRol[$nivelId] ?? 'desconocido',
                    'estado_final' => $solicitud->estado_solicitud,
                ]);
            }

            // ============================================
            // PASO 5: Sincronizar cuenta bancaria con solicitud de pago
            // ============================================
            // $solicitud->sincronizarCuentasBancarias([$cuentaBancaria->id]);
            // Log::info('✅ Cuenta bancaria sincronizada con solicitud de pago', [
            //     'solicitud_pago_id' => $solicitud->id,
            //     'cuenta_bancaria_id' => $cuentaBancaria->id,
            // ]);

            DB::commit();

            // Notificar al sistema de compras sobre la nueva SP
            try {
                $this->interApiService->notifyNewSolicitudCompra($solicitud, $proveedor);
                Log::info('✅ Notificación enviada a InterAPI', [
                    'solicitud_pago_id' => $solicitud->id,
                ]);
            } catch (\Exception $e) {
                Log::warning('⚠️ Error al notificar a InterAPI (no crítico)', [
                    'solicitud_pago_id' => $solicitud->id,
                    'error' => $e->getMessage(),
                ]);
                // No fallar la operación si la notificación externa falla
            }

            // Cargar relaciones para el resource
            $solicitud->load(['proveedor', 'empresaConstrucc']);

            return $this->success(
                new ConstruccProveedorSppResource($solicitud),
                'Solicitud de pago generada exitosamente.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error al generar SPP con proveedor existente: ' . $e->getMessage(), [
                'proveedor_id' => $proveedor->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Error al generar la solicitud de pago',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Extraer datos del archivo XML de factura
     *
     * @param string $rutaXml Ruta absoluta del archivo XML
     * @return array Datos extraídos del XML
     */
    private function extraerDatosXML(string $rutaXml): array
    {
        try {
            $contenidoXml = file_get_contents($rutaXml);
            $xml = simplexml_load_string($contenidoXml);

            if (!$xml) {
                Log::warning('⚠️ No se pudo parsear el XML de factura');
                return [];
            }

            // Registrar namespaces del XML
            $namespaces = $xml->getNamespaces(true);
            $cfdi = $xml->children($namespaces['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4');

            return [
                'version' => (string) $cfdi['Version'] ?? null,
                'serie' => (string) $cfdi['Serie'] ?? null,
                'folio' => (string) $cfdi['Folio'] ?? null,
                'fecha' => (string) $cfdi['Fecha'] ?? null,
                'subtotal' => (float) $cfdi['SubTotal'] ?? 0,
                'descuento' => (float) $cfdi['Descuento'] ?? 0,
                'total' => (float) $cfdi['Total'] ?? 0,
                'moneda' => (string) $cfdi['Moneda'] ?? 'MXN',
                'tipo_cambio' => (float) $cfdi['TipoCambio'] ?? 1,
                'metodo_pago' => (string) $cfdi['MetodoPago'] ?? null,
                'forma_pago' => (string) $cfdi['FormaPago'] ?? null,
                'tipo_comprobante' => (string) $cfdi['TipoDeComprobante'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('❌ Error al extraer datos del XML: ' . $e->getMessage(), [
                'ruta_xml' => $rutaXml,
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
