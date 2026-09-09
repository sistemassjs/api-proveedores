<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCuentaBancaria;
use App\Enums\EstadoUsuario;
use App\Http\Requests\Construcc\ConstruccProveedorStoreRequest;
use App\Http\Requests\Construcc\ConstruccProveedorUpdateRequest;
use App\Http\Resources\Construcc\ConstruccProveedorDetalleResource;
use App\Http\Resources\Construcc\ConstruccProveedorExistenteResource;
use App\Models\CuentaBancaria;
use App\Models\Proveedor;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para gestionar proveedores registrados por usuarios de construcción (tipo_alta = 2).
 * Proporciona funcionalidades para listar, ver detalles, crear, actualizar y eliminar proveedores.
 * Los proveedores gestionados aquí son específicos para el módulo de construcción y tienen
 * características particulares en comparación con otros tipos de proveedores.
 * Rutas principales:
 *  - GET /api/construcc/proveedores
 *  - GET /api/construcc/proveedores/{proveedor}
 *  - POST /api/construcc/proveedores
 *  - PUT /api/construcc/proveedores/{proveedor}
 *  - DELETE /api/construcc/proveedores/{proveedor} 
 */
class ConstruccProveedorController extends Controller
{
    use ApiResponse;

    /**
     * Lista proveedores con tipo_alta = 2 (registrados por usuarios construcciรณn)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $fields = Proveedor::getFilters();
            $filters = $request->only($fields);
            $empresaId = $request->integer('empresa_id');
            $usuarioId = $request->integer('usuario_id');

            $sortBy = $request->input('sort_by', 'nombre_comercial');
            $order = $request->input('order', 'asc');
            $perPage = $request->input('per_page', 1000);

            $query = Proveedor::query()

                ->when($empresaId, function ($q) use ($empresaId, $usuarioId) {
                    $q->where(function ($sub) use ($empresaId, $usuarioId) {

                        // 🔥 1. Proveedores asociados/enlazados
                        $sub->whereHas('empresasConstrucc', function ($rel) use ($empresaId, $usuarioId) {
                            $rel->where('empresa_construcc_id', $empresaId);

                            if ($usuarioId) {
                                $rel->where('usuario_construcc_id', $usuarioId);
                            }
                        });

                        // 🔥 2. Proveedores dados de alta por la empresa
                        $sub->orWhere(function ($alta) use ($empresaId, $usuarioId) {
                            $alta->where('empresa_construcc_alta', $empresaId);

                            if ($usuarioId) {
                                $alta->where('user_construcc_alta', $usuarioId);
                            }
                        });
                    });
                })

                ->with(['cuentasBancarias', 'empresasConstrucc'])
                ->withCount('solicitudesPago')
                ->filter($filters)
                ->orderBy($sortBy, $order);

            $originalPaginator = $query->paginate($perPage);

            $data = ConstruccProveedorDetalleResource::collection($originalPaginator)->resolve();

            return $this->paginated($originalPaginator->setCollection(collect($data)));
        } catch (\Exception $e) {
            Log::error('Error al listar proveedores construcción: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Error al obtener el listado de proveedores', null, 500);
        }
    }

    /**
     * Obtiene el detalle de un proveedor especรญfico con tipo_alta = 2
     */
    public function show(Proveedor $proveedor): JsonResponse
    {
        try {
            // Validar que sea un proveedor de construcciรณn
            if ($proveedor->tipo_alta !== 2) {
                return $this->error(
                    'Proveedor no encontrado o no corresponde a tipo_alta = 2.',
                    null,
                    404
                );
            }

            // Cargar relaciones
            $proveedor->load(['cuentasBancarias', 'empresasConstrucc']);

            // Agregar estadรญsticas de solicitudes de pago
            $estadisticas = [
                'total_solicitudes_pago' => $proveedor->solicitudesPago()->count(),
                'total_sp_pendientes' => $proveedor->solicitudesPago()->where('estado_solicitud', 'pendiente')->count(),
                'total_sp_autorizadas' => $proveedor->solicitudesPago()->where('estado_solicitud', 'autorizada')->count(),
                'total_sp_pagadas' => $proveedor->solicitudesPago()->where('estado_solicitud', 'pagada')->count(),
                'monto_total_solicitado' => $proveedor->solicitudesPago()->sum('monto_total'),
                'monto_total_pagado' => $proveedor->solicitudesPago()->where('pago_completo', true)->sum('monto_total'),
            ];

            $proveedor->estadisticas = $estadisticas;

            return $this->success(
                new ConstruccProveedorDetalleResource($proveedor),
                'Operaciรณn exitosa.'
            );
        } catch (\Exception $e) {
            Log::error('Error al obtener detalle del proveedor: ' . $e->getMessage(), [
                'proveedor_id' => $proveedor->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Error al obtener el detalle del proveedor',
                null,
                500
            );
        }
    }

    /**
     * Aplica filtro por empresa construcción al query (opcional).
     */
    private function aplicarFiltroEmpresa($query, ?int $empresaId): void
    {
        if (!$empresaId) {
            return;
        }
        $query->where(function ($q) use ($empresaId) {
            $q->whereHas('empresasConstrucc', fn($rel) => $rel->where('empresa_construcc_id', $empresaId))
                ->orWhere('empresa_construcc_alta', $empresaId);
        });
    }

    /**
     * Búsqueda por RFC. GET ?rfc=XXX&empresa_id=ID (opcional)
     */
    public function buscarPorRfc(Request $request): JsonResponse
    {
        $rfc = $request->query('rfc');
        if (empty($rfc)) {
            return response()->json(['success' => false, 'message' => 'El RFC es obligatorio.', 'data' => null], 422);
        }
        $query = Proveedor::query();
        $this->aplicarFiltroEmpresa($query, $request->filled('empresa_id') ? $request->integer('empresa_id') : null);
        $proveedor = $query->where('rfc', strtoupper($rfc))->first();
        return response()->json([
            'success' => true,
            'message' => $proveedor ? 'Proveedor encontrado' : 'No existe',
            'data' => ConstruccProveedorExistenteResource::toBusquedaArray($proveedor),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Búsqueda por email. GET ?email=XXX&empresa_id=ID (opcional)
     */
    public function buscarPorEmail(Request $request): JsonResponse
    {
        $email = $request->query('email');
        if (empty($email)) {
            return response()->json(['success' => false, 'message' => 'El email es obligatorio.', 'data' => null], 422);
        }
        $query = Proveedor::query();
        $this->aplicarFiltroEmpresa($query, $request->filled('empresa_id') ? $request->integer('empresa_id') : null);
        $proveedor = $query->where('email', $email)->first();
        return response()->json([
            'success' => true,
            'message' => $proveedor ? 'Proveedor encontrado' : 'No existe',
            'data' => ConstruccProveedorExistenteResource::toBusquedaArray($proveedor),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Búsqueda por razón social. GET ?razon_social=XXX&empresa_id=ID (opcional)
     */
    public function buscarPorRazonSocial(Request $request): JsonResponse
    {
        $razonSocial = $request->query('razon_social');
        if (empty($razonSocial)) {
            return response()->json(['success' => false, 'message' => 'La razón social es obligatoria.', 'data' => null], 422);
        }
        $query = Proveedor::query();
        $this->aplicarFiltroEmpresa($query, $request->filled('empresa_id') ? $request->integer('empresa_id') : null);
        $proveedor = $query->where('razon_social', $razonSocial)->first();
        return response()->json([
            'success' => true,
            'message' => $proveedor ? 'Proveedor encontrado' : 'No existe',
            'data' => ConstruccProveedorExistenteResource::toBusquedaArray($proveedor),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Búsqueda por teléfono. GET ?telefono=XXX&empresa_id=ID (opcional)
     */
    public function buscarPorTelefono(Request $request): JsonResponse
    {
        $telefono = $request->query('telefono');
        if (empty($telefono)) {
            return response()->json(['success' => false, 'message' => 'El teléfono es obligatorio.', 'data' => null], 422);
        }
        $query = Proveedor::query();
        $this->aplicarFiltroEmpresa($query, $request->filled('empresa_id') ? $request->integer('empresa_id') : null);
        $proveedor = $query->where('telefono', $telefono)->first();
        return response()->json([
            'success' => true,
            'message' => $proveedor ? 'Proveedor encontrado' : 'No existe',
            'data' => ConstruccProveedorExistenteResource::toBusquedaArray($proveedor),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Crea un nuevo proveedor con tipo_alta = 2 y su cuenta bancaria inicial
     */
    public function store(ConstruccProveedorStoreRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            // PASO 1: Crear proveedor
            $proveedor = Proveedor::create([
                'razon_social' => $data['razon_social'],
                'nombre_comercial' => $data['nombre_comercial'] ?? $data['razon_social'],
                'rfc' => isset($data['rfc']) ? strtoupper($data['rfc']) : null,
                'email' => $data['email'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'celular' => $data['celular'] ?? null,
                'tipo_alta' => 2,
                'is_proveedor_sp' => true,
                'is_proveedor_catalogo' => false,
                'perfil_empresa_completo' => false,
                'user_construcc_alta' => $data['usuario_id'],
                'empresa_construcc_alta' => $data['empresa_construcc_id'],
                'estatus' => EstadoUsuario::REGISTRADO->value,
            ]);

            $cuenta = null;

            // ✅ PASO 2: Crear cuenta SOLO si viene (misma forma que alta: $data['cuenta'] con tipo_cuenta + campo_dependiente)
            if (!empty($data['cuenta']) && is_array($data['cuenta'])) {
                $c = $data['cuenta'];
                $tipo = $c['tipo_cuenta'] ?? 'cuenta';
                $valor = $c['campo_dependiente'] ?? null;

                $cuentaAttrs = array_merge(
                    array_intersect_key($c, array_flip([
                        'alias',
                        'banco_clave',
                        'banco_nombre',
                        'titular_cuenta',
                        'referencia',
                        'sucursal',
                        'swift',
                    ])),
                    [
                        'cuenta' => $tipo === 'cuenta' ? $valor : null,
                        'clabe' => $tipo === 'clabe' ? $valor : null,
                        'tarjeta' => $tipo === 'tarjeta' ? $valor : null,
                        'preferida' => true,
                    ]
                );

                $cuenta = $proveedor->cuentasBancarias()->create($cuentaAttrs);
            }

            // PASO 3: Relación con empresa
            $proveedor->empresasConstrucc()->attach($data['empresa_construcc_id'], [
                'usuario_construcc_id' => $data['usuario_id'],
                'usuario_construcc_nombre' => $data['usuario_nombre'],
            ]);

            DB::commit();

            return $this->success(
                [
                    'proveedor' => [
                        'id' => $proveedor->id,
                        'nombre_comercial' => $proveedor->nombre_comercial,
                        'razon_social' => $proveedor->razon_social,
                        'rfc' => $proveedor->rfc,
                        'email' => $proveedor->email,
                        'telefono' => $proveedor->telefono,
                        'estatus' => $proveedor->estatus,
                        'tipo_alta' => $proveedor->tipo_alta,
                        'created_at' => $proveedor->created_at->format('Y-m-d H:i:s'),
                    ],
                    'cuenta_bancaria' => $cuenta ? [
                        'id' => $cuenta->id,
                        'alias' => $cuenta->alias,
                        'banco_clave' => $cuenta->banco_clave,
                        'banco_nombre' => $cuenta->banco_nombre,
                        'cuenta' => $cuenta->cuenta,
                        'clabe' => $cuenta->clabe,
                        'tarjeta' => $cuenta->tarjeta,
                        'titular_cuenta' => $cuenta->titular_cuenta,
                        'referencia' => $cuenta->referencia,
                        'sucursal' => $cuenta->sucursal,
                        'swift' => $cuenta->swift,
                        'preferida' => $cuenta->preferida,
                    ] : null,
                ],
                $cuenta
                    ? 'Proveedor creado exitosamente con cuenta bancaria.'
                    : 'Proveedor creado exitosamente sin cuenta bancaria.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al crear proveedor construcción: ' . $e->getMessage(), [
                'data' => $request->except(['cuenta']),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Error al crear el proveedor',
                null,
                500
            );
        }
    }

    /**
     * Actualiza un proveedor con tipo_alta = 2
     * Solo pueden actualizar: Directores (DG, DT, DA, PC) o el usuario que lo registrรณ
     */
    public function update(
        ConstruccProveedorUpdateRequest $request,
        Proveedor $proveedor
    ): JsonResponse {

        DB::beginTransaction();

        try {

            Log::info('[construcc.proveedor.update] Payload', [
                'proveedor_id' => $proveedor->id,
                'payload' => $request->validated()
            ]);

            Log::info('[construcc.proveedor.update] Toda la peticion', [
                'proveedor_id' => $proveedor->id,
                'request' => $request->all()
            ]);

            // 🔒 Validar tipo
            if ($proveedor->tipo_alta !== 2) {
                return $this->error('Solo se pueden actualizar proveedores tipo construcción.', null, 403);
            }
            // 
            // 🔒 Validar permisos
            $pivot = $proveedor->empresasConstrucc()->first();
            $usuarioCreadorId = $pivot?->pivot->usuario_construcc_id;

            if ($request->usuario_id != $usuarioCreadorId) {
                return $this->error('No tiene permisos para actualizar este proveedor.', null, 403);
            }

            // =========================
            // 🧠 ACTUALIZAR PROVEEDOR
            // =========================
            $data = $request->only([
                'razon_social',
                'rfc',
                'nombre_comercial',
                'email',
                'telefono',
                'celular',
            ]);

            if (isset($data['rfc'])) {
                $data['rfc'] = strtoupper($data['rfc']);
            }

            $proveedor->update($data);

            // =========================
            // 🏦 CUENTA BANCARIA (OBJETO)
            // =========================
            if ($request->filled('cuentas_bancarias')) {

                $c = $request->cuentas_bancarias;

                // 🚫 Evitar guardar basura (todo vacío)
                $hayDatos = collect($c)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();

                if ($hayDatos) {

                    $tipo = $c['tipo_cuenta'] ?? 'cuenta';
                    $valor = $c['campo_dependiente'] ?? '';

                    $dataCuenta = [
                        'alias' => $c['alias'] ?? '',
                        'banco_clave' => $c['banco_clave'] ?? '',
                        'banco_nombre' => $c['banco_nombre'] ?? '',
                        'titular_cuenta' => $c['titular_cuenta'] ?? '',
                        'referencia' => $c['referencia'] ?? '',
                        'sucursal' => $c['sucursal'] ?? '',
                        'swift' => $c['swift'] ?? '',

                        'tipo_cuenta' => $tipo,
                        'campo_dependiente' => $valor,

                        // 🔥 SIN NULLS
                        'cuenta' => $tipo === 'cuenta' ? $valor : '',
                        'clabe' => $tipo === 'clabe' ? $valor : '',
                        'tarjeta' => $tipo === 'tarjeta' ? $valor : '',

                        'preferida' => $c['preferida'] ?? true,
                    ];

                    if (!empty($c['id'])) {
                        // 🔄 UPDATE
                        $cuenta = $proveedor->cuentasBancarias()
                            ->where('id', $c['id'])
                            ->first();

                        if ($cuenta) {
                            $cuenta->update($dataCuenta);
                        }
                    } else {
                        // 🧹 OPCIONAL: borrar anteriores si solo quieres UNA cuenta
                        $proveedor->cuentasBancarias()->delete();

                        // ➕ CREATE
                        $proveedor->cuentasBancarias()->create($dataCuenta);
                    }
                }
            }

            DB::commit();

            $proveedor->load('cuentasBancarias');

            return $this->success(
                [
                    'proveedor' => $proveedor,
                    'cuentas_bancarias' => $proveedor->cuentasBancarias
                ],
                'Proveedor actualizado correctamente'
            );
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('[construcc.proveedor.update] error', [
                'proveedor_id' => $proveedor->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Error al actualizar el proveedor', null, 500);
        }
    }

    /**
     * Marca un proveedor como dado de baja (soft delete)
     * Solo pueden eliminar: Directores (DG, DT, DA, PC) o el usuario que lo registrรณ
     */
    public function destroy(Proveedor $proveedor, Request $request): JsonResponse
    {
        try {
            // Validar que sea un proveedor de construcciรณn
            if ($proveedor->tipo_alta !== 2) {
                return $this->error(
                    'Solo se pueden eliminar proveedores registrados por usuarios construcciรณn (tipo_alta = 2).',
                    null,
                    403
                );
            }

            // Validar datos de autorizaciรณn
            $request->validate([
                'usuario_id' => 'required|integer',
                'nivel_id' => 'required|integer|min:0|max:7',
                'motivo_baja' => 'nullable|string|max:500',
            ]);

            // Validar autorizaciรณn
            $nivelesDirectores = [1, 2, 3, 5]; // DG, DT, DA, PC
            $esDirector = in_array($request->nivel_id, $nivelesDirectores);

            // Obtener el usuario que registrรณ el proveedor desde la tabla pivot
            $pivotData = $proveedor->empresasConstrucc()->first();
            $usuarioCreadorId = $pivotData ? $pivotData->pivot->usuario_construcc_id : null;
            $esCreador = $request->usuario_id == $usuarioCreadorId;

            if (!$esDirector && !$esCreador) {
                return $this->error(
                    'No tiene permisos para eliminar este proveedor. Solo directores (DG, DT, DA, PC) o el usuario que lo registrรณ pueden eliminarlo.',
                    null,
                    403
                );
            }

            // Cambiar estatus a inactivo (soft delete)
            $proveedor->estatus  = EstadoUsuario::SUSPENDIDO->value;
            $proveedor->notas = $request->motivo_baja ?? 'Proveedor dado de baja';
            $proveedor->save();

            return $this->success(
                [
                    'id' => $proveedor->id,
                    'razon_social' => $proveedor->razon_social,
                    'estatus' => $proveedor->estatus,
                    'fecha_baja' => $proveedor->updated_at->format('Y-m-d H:i:s'),
                    'motivo_baja' => $request->motivo_baja,
                ],
                'Proveedor dado de baja exitosamente.'
            );
        } catch (\Exception $e) {
            Log::error('Error al eliminar proveedor construcciรณn: ' . $e->getMessage(), [
                'proveedor_id' => $proveedor->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Error al dar de baja el proveedor',
                null,
                500
            );
        }
    }



    /**
     * PROVEDORES TIPO ALTA 1 LISTADOS 
     */

    /** 
     * Estos métodos son para obtener listados de proveedores (registrados por otros medios, no construcción)
     */
    public function allProveedoresConSpp(Request $request, int $empresaId): JsonResponse
    {
        $estado = $request->input('estatus'); // 'rechazada', 'pendiente', 'autorizada', 'pagada'
        $proveedores = Proveedor::query()
            ->select(['id', 'nombre_comercial', 'razon_social'])
            // ->whereNull('tipo_alta')
            ->whereHas('solicitudesPago', function ($q) use ($empresaId, $estado) {
                $q->where('empresa_construcc_id', $empresaId);
                if ($estado) {
                    $q
                        ->where('estado_solicitud', $estado)
                        ->where('verificada', true)
                    ;
                }
            })
            ->withCount([
                'solicitudesPago as solicitudes_pago_count' => function ($q) use ($empresaId, $estado) {
                    $q->where('empresa_construcc_id', $empresaId);
                    if ($estado) {
                        $q->where('estado_solicitud', $estado)
                            ->where('verificada', true);
                    }
                }
            ])
            ->get()
            ->map(function ($proveedor) {
                $nombre = $proveedor->razon_social ?: $proveedor->nombre_comercial;
                $proveedor->nombre_comercial = $proveedor->razon_social = $nombre . ' (' . $proveedor->solicitudes_pago_count . ' SPP)';
                return $proveedor;
            });

        return $this->success($proveedores);
    }

    /**
     * Estos métodos son para obtener listados de proveedores (registrados por otros medios, no construcción) que tengan solicitudes de pago rechazadas o autorizadas
     */
    public function allProveedores(Request $request, int $empresaId): JsonResponse
    {
        $proveedores = Proveedor::query()
            ->select(['id', 'nombre_comercial', 'razon_social'])
            // ->whereNull('tipo_alta')
            ->withCount([
                'solicitudesPago as solicitudes_pago_count' => function ($q) use ($empresaId) {
                    $q->where('empresa_construcc_id', $empresaId);
                }
            ])
            ->get()
            ->map(function ($proveedor) {
                $nombre = $proveedor->razon_social ?: $proveedor->nombre_comercial;

                $proveedor->nombre_comercial =
                    $proveedor->razon_social = $nombre . ' (' . $proveedor->solicitudes_pago_count . ' SPP)';

                return $proveedor;
            });

        return $this->success($proveedores);
    }
}
