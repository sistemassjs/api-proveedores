<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\SolicitudPago;
use App\Models\PagoSPP;
use App\Support\PublicStorageUrl;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Controlador para gestionar las SPP de un proveedor y sus pagos parciales.
 * Enfocado en la vista desde el proveedor hacia sus solicitudes de pago.
 */
class ConstruccSPPSolicitudPagoController extends Controller
{
    use ApiResponse;
    /**
     * Lista todas las SPP de un proveedor específico.
     * 
     * GET /api/construcc/proveedor/{id_proveedor}/spp
     * 
     * @param int $proveedorId
     * @param Request $request
     * @return JsonResponse
     */
    public function index($proveedorId, Request $request): JsonResponse
    {
        try {
            $proveedor = Proveedor::findOrFail($proveedorId);
            
            $perPage = $request->get('per_page', 20);
            $filters = $request->except(['page', 'per_page']);
            
            $query = SolicitudPago::where('proveedor_id', $proveedorId)
                ->with([
                    'proveedor',
                    'empresaConstrucc',
                    'pagos' => function ($query) {
                        $query->withPivot(['monto_aplicado', 'estado_pago', 'notas', 'fecha_aplicacion']);
                    }
                ])
                ->filter($filters)
                ->orderBy('created_at', 'desc');

            $solicitudes = $query->paginate($perPage);

            return $this->success([
                'solicitudes' => $solicitudes->items(),
                'pagination' => [
                    'total' => $solicitudes->total(),
                    'per_page' => $solicitudes->perPage(),
                    'current_page' => $solicitudes->currentPage(),
                    'last_page' => $solicitudes->lastPage(),
                ],
                'proveedor' => [
                    'id' => $proveedor->id,
                    'nombre_comercial' => $proveedor->nombre_comercial,
                    'rfc' => $proveedor->rfc,
                ],
            ], 'Solicitudes de pago obtenidas exitosamente.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Proveedor no encontrado al listar SPP', [
                'proveedor_id' => $proveedorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'No se encontró el proveedor solicitado.',
                null,
                404
            );
        } catch (\Exception $e) {
            Log::error('Error al listar SPP del proveedor', [
                'proveedor_id' => $proveedorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'No se pudieron obtener las solicitudes de pago. Por favor, intente nuevamente.',
                null,
                500
            );
        }
    }

    /**
     * Muestra una SPP específica de un proveedor con todos sus pagos parciales.
     * 
     * GET /api/construcc/proveedor/{id_proveedor}/spp/{id_spp}
     * 
     * @param int $proveedorId
     * @param int $sppId
     * @return JsonResponse
     */
    public function show($proveedorId, $sppId): JsonResponse
    {
        try {
            $proveedor = Proveedor::findOrFail($proveedorId);
            
            $solicitud = SolicitudPago::where('id', $sppId)
                ->where('proveedor_id', $proveedorId)
                ->with([
                    'proveedor',
                    'empresaConstrucc',
                    'cuentasBancarias',
                    'pagos' => function ($query) {
                        $query->withPivot([
                            'monto_aplicado',
                            'estado_pago',
                            'notas',
                            'fecha_aplicacion'
                        ])->orderBy('pago_solicitud_pago.fecha_aplicacion', 'desc');
                    }
                ])
                ->firstOrFail();

            // Calcular resumen de pagos
            $totalPagado = $solicitud->pagos->sum('pivot.monto_aplicado');
            $cantidadPagos = $solicitud->pagos->count();
            
            return $this->success([
                'solicitud' => $solicitud,
                'resumen_pagos' => [
                    'total_pagado' => (float) $totalPagado,
                    'cantidad_pagos' => $cantidadPagos,
                    'monto_total' => (float) $solicitud->monto_total,
                    'saldo_pendiente' => (float) $solicitud->saldo_pendiente,
                    'pago_completo' => (bool) $solicitud->pago_completo,
                ],
            ], 'Solicitud de pago obtenida exitosamente.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Solicitud de pago no encontrada', [
                'proveedor_id' => $proveedorId,
                'spp_id' => $sppId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'La solicitud de pago no existe o no pertenece a este proveedor.',
                null,
                404
            );
        } catch (\Exception $e) {
            Log::error('Error al obtener SPP del proveedor', [
                'proveedor_id' => $proveedorId,
                'spp_id' => $sppId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'No se pudo obtener el detalle de la solicitud de pago. Por favor, intente nuevamente.',
                null,
                500
            );
        }
    }

    /**
     * Lista todos los pagos parciales de una SPP específica.
     * 
     * GET /api/construcc/proveedor/{id_proveedor}/spp/{id_spp}/pagos
     * 
     * @param int $proveedorId
     * @param int $sppId
     * @return JsonResponse
     */
    public function pagos($proveedorId, $sppId): JsonResponse
    {
        try {
            $proveedor = Proveedor::findOrFail($proveedorId);
            
            $solicitud = SolicitudPago::where('id', $sppId)
                ->where('proveedor_id', $proveedorId)
                ->firstOrFail();

            $pagos = $solicitud->pagos()
                ->withPivot([
                    'monto_aplicado',
                    'estado_pago',
                    'notas',
                    'fecha_aplicacion'
                ])
                ->orderBy('pago_solicitud_pago.fecha_aplicacion', 'desc')
                ->get();

            return $this->success([
                'pagos' => $pagos,
                'solicitud_pago' => [
                    'id' => $solicitud->id,
                    'numero_folio_solicitud' => $solicitud->numero_folio_solicitud,
                    'monto_total' => (float) $solicitud->monto_total,
                    'monto_abonado' => (float) $solicitud->monto_abonado,
                    'saldo_pendiente' => (float) $solicitud->saldo_pendiente,
                    'pago_completo' => (bool) $solicitud->pago_completo,
                ],
            ], 'Pagos obtenidos exitosamente.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Solicitud de pago no encontrada al listar pagos', [
                'proveedor_id' => $proveedorId,
                'spp_id' => $sppId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'La solicitud de pago no existe o no pertenece a este proveedor.',
                null,
                404
            );
        } catch (\Exception $e) {
            Log::error('Error al listar pagos de SPP', [
                'proveedor_id' => $proveedorId,
                'spp_id' => $sppId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'No se pudieron obtener los pagos. Por favor, intente nuevamente.',
                null,
                500
            );
        }
    }

    /**
     * Muestra un pago específico de una SPP.
     * 
     * GET /api/construcc/proveedor/{id_proveedor}/spp/{id_spp}/pagos/{id_pago}
     * 
     * @param int $proveedorId
     * @param int $sppId
     * @param int $pagoId
     * @return JsonResponse
     */
    public function showPago($proveedorId, $sppId, $pagoId): JsonResponse
    {
        try {
            $proveedor = Proveedor::findOrFail($proveedorId);
            
            $solicitud = SolicitudPago::where('id', $sppId)
                ->where('proveedor_id', $proveedorId)
                ->firstOrFail();

            // Buscar el pago y verificar que esté asociado a esta SPP
            $pago = PagoSPP::where('id', $pagoId)
                ->whereHas('solicitudesPago', function ($query) use ($sppId) {
                    $query->where('solicitud_pago_id', $sppId);
                })
                ->with(['empresaConstrucc'])
                ->firstOrFail();

            // Obtener datos del pivot
            $pivotData = DB::connection('mysql5')
                ->table('pago_solicitud_pago')
                ->where('pago_spp_id', $pagoId)
                ->where('solicitud_pago_id', $sppId)
                ->first();

            return $this->success([
                'pago' => $pago,
                'relacion' => [
                    'monto_aplicado' => (float) $pivotData->monto_aplicado,
                    'estado_pago' => $pivotData->estado_pago,
                    'notas' => $pivotData->notas,
                    'fecha_aplicacion' => $pivotData->fecha_aplicacion,
                ],
                'solicitud_pago' => [
                    'id' => $solicitud->id,
                    'numero_folio_solicitud' => $solicitud->numero_folio_solicitud,
                    'monto_total' => (float) $solicitud->monto_total,
                    'monto_abonado' => (float) $solicitud->monto_abonado,
                    'saldo_pendiente' => (float) $solicitud->saldo_pendiente,
                ],
            ], 'Detalle del pago obtenido exitosamente.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Pago no encontrado o no asociado a la SPP', [
                'proveedor_id' => $proveedorId,
                'spp_id' => $sppId,
                'pago_id' => $pagoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'El pago no existe o no está asociado a esta solicitud de pago.',
                null,
                404
            );
        } catch (\Exception $e) {
            Log::error('Error al obtener pago de SPP', [
                'proveedor_id' => $proveedorId,
                'spp_id' => $sppId,
                'pago_id' => $pagoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'No se pudo obtener el detalle del pago. Por favor, intente nuevamente.',
                null,
                500
            );
        }
    }

    /**
     * Sube o actualiza el comprobante de pago para un pago específico.
     * 
     * POST /api/construcc/proveedor/{id_proveedor}/spp/{id_spp}/pagos/{id_pago}/subir-comprobante
     * 
     * @param Request $request
     * @param int $proveedorId
     * @param int $sppId
     * @param int $pagoId
     * @return JsonResponse
     */
    public function subirComprobante(Request $request, $proveedorId, $sppId, $pagoId): JsonResponse
    {
        try {
            $proveedor = Proveedor::findOrFail($proveedorId);
            
            $solicitud = SolicitudPago::where('id', $sppId)
                ->where('proveedor_id', $proveedorId)
                ->firstOrFail();

            $pago = PagoSPP::where('id', $pagoId)
                ->whereHas('solicitudesPago', function ($query) use ($sppId) {
                    $query->where('solicitud_pago_id', $sppId);
                })
                ->firstOrFail();

            // Validación
            $validated = $request->validate([
                'comprobante_pago' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ], [
                'comprobante_pago.required' => 'El comprobante de pago es obligatorio.',
                'comprobante_pago.file' => 'El comprobante debe ser un archivo válido.',
                'comprobante_pago.mimes' => 'El comprobante debe ser PDF, JPG o PNG.',
                'comprobante_pago.max' => 'El comprobante no debe superar los 10MB.',
            ]);

            DB::beginTransaction();

            // Eliminar el comprobante anterior si existe
            if ($pago->comprobante_pago && Storage::disk('public')->exists($pago->comprobante_pago)) {
                Storage::disk('public')->delete($pago->comprobante_pago);
            }

            // Guardar el nuevo comprobante
            $comprobantePath = $request->file('comprobante_pago')->store(
                'comprobantes_pago',
                'public'
            );

            $pago->update([
                'comprobante_pago' => $comprobantePath,
            ]);

            DB::commit();

            return $this->success([
                'comprobante_pago' => $comprobantePath,
                'comprobante_pago_url' => PublicStorageUrl::make($comprobantePath),
            ], 'Comprobante de pago guardado exitosamente.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Pago o SPP no encontrado al subir comprobante', [
                'proveedor_id' => $proveedorId,
                'spp_id' => $sppId,
                'pago_id' => $pagoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'El pago o la solicitud de pago no existe.',
                null,
                404
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->error(
                'Error de validación en el archivo.',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al subir comprobante de pago', [
                'proveedor_id' => $proveedorId,
                'spp_id' => $sppId,
                'pago_id' => $pagoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'No se pudo guardar el comprobante. Por favor, intente nuevamente.',
                null,
                500
            );
        }
    }

    /**
     * Registra un pago y lo aplica a una o varias SPP de un proveedor.
     * Este endpoint permite crear un pago con comprobante y asociarlo a múltiples SPP.
     * 
     * POST /api/construcc/proveedor/{id_proveedor}/pagos
     * 
     * @param Request $request
     * @param int $proveedorId
     * @return JsonResponse
     */
    public function registrarPago(Request $request, $proveedorId): JsonResponse
    {
        try {
            $proveedor = Proveedor::findOrFail($proveedorId);

            // Validación
            $validated = $request->validate([
                // Datos del pago
                'comprobante_pago' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'fecha_pago' => 'required|date',
                'referencia_pago' => 'nullable|string|max:100',
                
                // Datos bancarios del pago (origen)
                'banco_pago' => 'nullable|string|max:100',
                'cuenta_origen' => 'nullable|string|max:50',
                'tipo_cuenta_origen' => 'nullable|string|max:50',
                'clabe_interbancaria_origen' => 'nullable|string|max:18',
                
                // Datos bancarios del proveedor (destino)
                'banco_destino' => 'nullable|string|max:100',
                'cuenta_destino' => 'nullable|string|max:50',
                'tipo_cuenta_destino' => 'nullable|string|max:50',
                'clabe_interbancaria_destino' => 'nullable|string|max:18',
                'titular_cuenta_destino' => 'nullable|string|max:255',
                
                // Montos
                'monto_total' => 'required|numeric|min:0.01',
                
                // Metadatos
                'observaciones' => 'nullable|string',
                'usuario_registro_id' => 'nullable|integer',
                'usuario_registro_nombre' => 'nullable|string|max:255',
                'empresa_construcc_id' => 'nullable|integer',
                
                // SPPs a las que se aplica (obligatorio)
                'solicitudes_pago' => 'required|array|min:1',
                'solicitudes_pago.*.solicitud_pago_id' => [
                    'required',
                    'integer',
                    Rule::exists('solicitudes_pago', 'id')->where('proveedor_id', $proveedorId),
                ],
                'solicitudes_pago.*.monto_aplicado' => 'required|numeric|min:0.01',
                'solicitudes_pago.*.estado_pago' => [
                    'required',
                    Rule::in(['aplicado', 'pendiente', 'rechazado', 'parcial', 'completado'])
                ],
                'solicitudes_pago.*.notas' => 'nullable|string',
            ], [
                'comprobante_pago.required' => 'El comprobante de pago es obligatorio.',
                'comprobante_pago.mimes' => 'El comprobante debe ser PDF, JPG o PNG.',
                'comprobante_pago.max' => 'El comprobante no debe superar los 10MB.',
                'fecha_pago.required' => 'La fecha de pago es obligatoria.',
                'referencia_pago.required' => 'La referencia de pago es obligatoria.',
                'monto_total.required' => 'El monto total es obligatorio.',
                'monto_total.min' => 'El monto debe ser mayor a cero.',
                'solicitudes_pago.required' => 'Debe especificar al menos una solicitud de pago.',
                'solicitudes_pago.*.solicitud_pago_id.exists' => 'Una o más solicitudes de pago no existen o no pertenecen a este proveedor.',
                'solicitudes_pago.*.monto_aplicado.required' => 'El monto aplicado es obligatorio para cada solicitud.',
                'solicitudes_pago.*.estado_pago.in' => 'El estado del pago no es válido.',
            ]);

            DB::beginTransaction();

            // Guardar el comprobante de pago
            $comprobantePath = $request->file('comprobante_pago')->store(
                'comprobantes_pago',
                'public'
            );

            // Crear el pago
            $pago = PagoSPP::create([
                'comprobante_pago' => $comprobantePath,
                'fecha_pago' => $validated['fecha_pago'],
                'fecha_registro' => now(),
                'referencia_pago' => $validated['referencia_pago'],
                'banco_pago' => $validated['banco_pago'] ?? null,
                'cuenta_origen' => $validated['cuenta_origen'] ?? null,
                'tipo_cuenta_origen' => $validated['tipo_cuenta_origen'] ?? null,
                'clabe_interbancaria_origen' => $validated['clabe_interbancaria_origen'] ?? null,
                'banco_destino' => $validated['banco_destino'] ?? null,
                'cuenta_destino' => $validated['cuenta_destino'] ?? null,
                'tipo_cuenta_destino' => $validated['tipo_cuenta_destino'] ?? null,
                'clabe_interbancaria_destino' => $validated['clabe_interbancaria_destino'] ?? null,
                'titular_cuenta_destino' => $validated['titular_cuenta_destino'] ?? null,
                'monto_total' => $validated['monto_total'],
                'observaciones' => $validated['observaciones'] ?? null,
                'usuario_registro_id' => $validated['usuario_registro_id'] ?? null,
                'usuario_registro_nombre' => $validated['usuario_registro_nombre'] ?? null,
                'empresa_construcc_id' => $validated['empresa_construcc_id'] ?? null,
            ]);

            // Verificar que el monto total aplicado no exceda el monto del pago
            $montoTotalAplicado = collect($validated['solicitudes_pago'])->sum('monto_aplicado');
            if ($montoTotalAplicado > $validated['monto_total']) {
                throw new \Exception('El monto total aplicado a las solicitudes excede el monto del pago registrado.');
            }

            // Aplicar el pago a las solicitudes de pago del proveedor
            foreach ($validated['solicitudes_pago'] as $solicitudData) {
                $solicitudPago = SolicitudPago::where('id', $solicitudData['solicitud_pago_id'])
                    ->where('proveedor_id', $proveedorId)
                    ->firstOrFail();
                
                // Adjuntar la relación con los datos del pivot
                $pago->solicitudesPago()->attach($solicitudPago->id, [
                    'monto_aplicado' => $solicitudData['monto_aplicado'],
                    'estado_pago' => $solicitudData['estado_pago'],
                    'notas' => $solicitudData['notas'] ?? null,
                    'fecha_aplicacion' => now(),
                ]);

                // Actualizar los saldos de la solicitud de pago si el estado es aplicado
                if ($solicitudData['estado_pago'] === 'aplicado' || $solicitudData['estado_pago'] === 'completado') {
                    $solicitudPago->actualizarSaldos($solicitudData['monto_aplicado']);
                }
            }

            DB::commit();

            // Cargar las relaciones para la respuesta
            $pago->load(['solicitudesPago', 'empresaConstrucc']);

            Log::info('Pago registrado exitosamente', [
                'proveedor_id' => $proveedorId,
                'pago_id' => $pago->id,
                'monto_total' => $pago->monto_total,
                'cantidad_spp' => count($validated['solicitudes_pago']),
            ]);

            return $this->success(
                $pago,
                'Pago registrado y aplicado exitosamente.',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Proveedor o solicitud de pago no encontrada al registrar pago', [
                'proveedor_id' => $proveedorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'El proveedor o alguna solicitud de pago no existe.',
                null,
                404
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->error(
                'Error de validación en los datos del pago.',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al registrar pago del proveedor', [
                'proveedor_id' => $proveedorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'No se pudo registrar el pago. ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
