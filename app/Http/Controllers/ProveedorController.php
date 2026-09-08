<?php

namespace App\Http\Controllers;

use App\Exceptions\Api\Crud\ResourceNotFoundException;
use App\Http\Requests\Proveedor\ProveedorStoreRequest;
use App\Http\Requests\Proveedor\ProveedorUpdateConstanciaFiscalRequest;
use App\Http\Requests\Proveedor\ProveedorUpdateLogoRequest;
use App\Http\Requests\Proveedor\ProveedorUpdateRequest;
use App\Http\Resources\Admin\AdminProveedorAcordeonResource;
use App\Http\Resources\ProveedorResource;
use App\Http\Resources\ProveedorValidacionPerfilCompletoResource;
use App\Http\Resources\UserResource;
use App\Models\Proveedor;
use App\Models\User;
// use App\Services\ConstanciaFiscalService;
use App\Services\Proveedor\ConstanciaFiscalHybridService;
use App\Services\Proveedor\ProveedorPerfilCompletadoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProveedorController extends Controller
{
    public function __construct(
        private readonly ProveedorPerfilCompletadoService $perfilCompletadoService,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/proveedor/logo",
     *     summary="Actualizar logo del proveedor",
     *     tags={"Proveedores"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="logo", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Logo actualizado"),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     * Actualiza el logo del proveedor principal del usuario autenticado.
     *
     * - Elimina el logo anterior del proveedor (si existe).
     * - Elimina la foto de perfil anterior del usuario (si existe).
     * - Guarda y asigna el nuevo logo.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws ResourceNotFoundException Si el proveedor no existe.
     */
    public function updateLogo(ProveedorUpdateLogoRequest $request, Proveedor $proveedor)
    {
        $user = $request->user();
        $proveedor = $user->proveedorPrincipal();

        if (! $proveedor) {
            throw new ResourceNotFoundException('Proveedor no encontrado.');
        }

        try {
            // Eliminar logo anterior si existe físicamente
            if ($proveedor->logo && Storage::disk('public')->exists($proveedor->logo)) {
                Storage::disk('public')->delete($proveedor->logo);
            }

            // Subir nuevo logo
            $file = $request->file('logo');

            // Formato: {id:6 dígitos}_{randStr:6 dígitos}.extension
            $idPadded = str_pad($proveedor->id, 6, '0', STR_PAD_LEFT);
            $randomStr = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $extension = $file->getClientOriginalExtension();
            $filename = "{$idPadded}_{$randomStr}.{$extension}";

            // Almacenar en carpeta específica logos_empresas
            $path = $file->storeAs('logos_empresas', $filename, 'public');

            // Actualizar proveedor con el path relativo
            $proveedor->update(['logo' => $path]);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar logo del proveedor', [
                'proveedor_id' => $proveedor->id,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error(
                'No se pudo actualizar el logo en este momento. Intenta nuevamente.',
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Recargar modelos con relaciones
        $proveedor->refresh()->load(Proveedor::eagerLodable());
        $user->refresh()->load(\App\Models\User::eagerLodable());

        // Respuesta JSON con recursos actualizados
        return $this->success([
            'proveedor' => new ProveedorResource($proveedor),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Obtiene el proveedor principal asociado a un usuario por ID.
     *
     * @param  int  $id  ID del usuario
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws ResourceNotFoundException Si no se encuentra el usuario o proveedor.
     */
    public function getProveedorByUserId(Request $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            throw new ResourceNotFoundException('Usuario no encontrado.');
        }
        $proveedor = $user->proveedorPrincipal();
        if (! $proveedor) {
            throw new ResourceNotFoundException('Empresa no registrada en GestionPlus.');
        }

        return $this->success(new ProveedorResource($proveedor->load(Proveedor::eagerLodable())));
    }

    /**
     * @OA\Get(
     *     path="/api/proveedores",
     *     summary="Listar proveedores",
     *     tags={"Proveedores"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="nombre_comercial", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="rfc", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="estatus", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(type="string", default="nombre_comercial")),
     *     @OA\Parameter(name="order", in="query", required=false, @OA\Schema(type="string", enum={"asc", "desc"}, default="asc")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de proveedores",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Proveedor")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     * Lista los proveedores con filtros, ordenamiento y paginación.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $request->only(Proveedor::getFilters());
        $sortBy = $request->input('sort_by', 'nombre_comercial');
        $order = $request->input('order', 'asc');
        $perPage = $request->input('per_page', 10);

        $originalPaginator = Proveedor::with(Proveedor::eagerLodable())
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = ProveedorResource::collection($originalPaginator)->resolve();

        return $this->paginated($originalPaginator->setCollection(collect($data)));
    }

    /**
     * @OA\Get(
     *     path="/api/proveedores/{id}",
     *     summary="Obtener proveedor por ID",
     *     tags={"Proveedores"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Datos del proveedor", @OA\JsonContent(ref="#/components/schemas/Proveedor")),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     * Muestra los datos de un proveedor específico.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Proveedor $proveedor)
    {
        $proveedor->loadMissing(Proveedor::eagerLodable());

        return $this->success(new ProveedorResource($proveedor));
    }

    /**
     * @OA\Put(
     *     path="/api/proveedores/{id}",
     *     summary="Actualizar proveedor",
     *     tags={"Proveedores"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nombre_comercial", type="string"),
     *             @OA\Property(property="razon_social", type="string"),
     *             @OA\Property(property="rfc", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="telefono", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Proveedor actualizado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     * Actualiza la información de un proveedor.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProveedorUpdateRequest $request, Proveedor $proveedor)
    {
        $validated = $request->validated();
        // El flag de pruebas solo lo gestiona admin (AdminProveedorController).
        unset($validated['es_cuenta_de_pruebas']);

        $regimenes = $validated['regimenes_fiscales'] ?? null;
        unset($validated['regimenes_fiscales']);

        $proveedor->update($validated);

        if (is_array($regimenes)) {
            $this->syncRegimenesFiscales($proveedor, $regimenes);
        }

        $proveedor = $proveedor->fresh(Proveedor::eagerLodable());
        $this->perfilCompletadoService->sincronizarBandera($proveedor);

        return $this->success(new ProveedorResource($proveedor), 'Empresa actualizada con éxito.', 200);
    }

    /**
     * Reemplaza los regímenes fiscales 1:N y espeja el principal en columnas legacy.
     *
     * @param  array<int, array<string, mixed>>  $regimenes
     */
    private function syncRegimenesFiscales(Proveedor $proveedor, array $regimenes): void
    {
        $normalizados = [];
        foreach ($regimenes as $item) {
            $clave = trim((string) ($item['clave'] ?? ''));
            $nombre = trim((string) ($item['nombre'] ?? ''));
            if ($nombre === '' || strcasecmp($nombre, 'Seleccione una opción') === 0) {
                continue;
            }
            // Conservar clave '000' solo si no hay clave SAT; no descartar el régimen.
            $normalizados[] = [
                'clave' => $clave === '' ? '000' : $clave,
                'nombre' => $nombre,
                'fecha_alta' => $this->normalizeFechaMexicana($item['fecha_alta'] ?? null),
                'fecha_fin' => $this->normalizeFechaMexicana($item['fecha_fin'] ?? null),
                'es_principal' => (bool) ($item['es_principal'] ?? false),
                'origen' => $item['origen'] ?? 'manual',
            ];
        }

        // Deduplicar por clave+nombre (última gana). Permite varios con clave desconocida.
        $byKey = [];
        foreach ($normalizados as $row) {
            $byKey[$row['clave'].'|'.mb_strtolower($row['nombre'])] = $row;
        }
        $normalizados = array_values($byKey);

        if (count($normalizados) === 0) {
            $proveedor->regimenesFiscales()->delete();
            $proveedor->update([
                'regimen_fiscal_clave' => null,
                'regimen_fiscal_nombre' => null,
            ]);

            return;
        }

        $hasPrincipal = collect($normalizados)->contains(fn ($r) => ! empty($r['es_principal']));
        if (! $hasPrincipal) {
            $normalizados[0]['es_principal'] = true;
        } else {
            // Solo uno principal
            $seen = false;
            foreach ($normalizados as $i => $row) {
                if ($row['es_principal']) {
                    if ($seen) {
                        $normalizados[$i]['es_principal'] = false;
                    }
                    $seen = true;
                }
            }
        }

        $proveedor->regimenesFiscales()->delete();
        foreach ($normalizados as $row) {
            $proveedor->regimenesFiscales()->create($row);
        }

        $principal = collect($normalizados)->firstWhere('es_principal', true) ?? $normalizados[0];
        $proveedor->update([
            'regimen_fiscal_clave' => ($principal['clave'] === '' || $principal['clave'] === '000')
                ? null
                : $principal['clave'],
            'regimen_fiscal_nombre' => $principal['nombre'],
        ]);
    }

    /**
     * Normaliza fechas SAT (dd/mm/yyyy) a Y-m-d para columnas date.
     */
    private function normalizeFechaMexicana(mixed $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }
        $raw = trim((string) $fecha);
        if ($raw === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $raw);
                if ($dt instanceof Carbon) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Throwable) {
                // probar siguiente formato
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/proveedores/{id}",
     *     summary="Dar de baja proveedor (eliminación lógica)",
     *     tags={"Proveedores"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Proveedor dado de baja"),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     * Marca un proveedor como baja (eliminación lógica).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws ResourceNotFoundException Si no se encuentra el proveedor.
     */
    public function destroy($id)
    {
        $proveedor = Proveedor::find($id);
        if (! $proveedor) {
            throw new ResourceNotFoundException('Empresa no registrada en GestionPlus.');
        }
        $proveedor->update([['estatus' => 'baja']]);

        return $this->success(null, 204);
    }

    /**
     * Obtiene los proveedores con sus categorías raíz, subcategorías y conteo de productos.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function proveedoresConCategoriasConSubcatCountProductos(Request $request)
    {
        $proveedores = Proveedor::with([
            'categorias' => function ($query) {
                $query->whereNull('parent_id') // solo categorías raíz
                    ->with([
                        'children' => function ($subquery) {
                            $subquery->withCount('productos');
                        },
                    ])
                    ->withCount('productos');
            },
        ])
            ->withCount('productos') // total de productos por proveedor
            ->get();

        return $this->success(
            AdminProveedorAcordeonResource::collection($proveedores),
            'Listado de empresas con sus categorías, subcategorías y contador de productos.'
        );
    }

    /**
     * Subir o actualizar la constancia fiscal del proveedor.
     */
    public function updateConstanciaFiscal(
        ProveedorUpdateConstanciaFiscalRequest $request,
        Proveedor $proveedor,
        ConstanciaFiscalHybridService $constanciaFiscalService
    ) {
        $validated = $request->validated();
        $user = $request->user();

        // Verificar acceso
        if ($user->proveedorPrincipal()?->id !== $proveedor->id) {
            return response()->json([
                'message' => 'No tienes permisos para subir esta constancia fiscal.',
            ], Response::HTTP_FORBIDDEN);
        }

        $file = $request->file('constancia_fiscal');

        // Borrar constancia anterior si existe
        if ($proveedor->constancia_fiscal && Storage::disk('public')->exists($proveedor->constancia_fiscal)) {
            Storage::disk('public')->delete($proveedor->constancia_fiscal);
        }

        // Guardar nueva constancia en public
        // ruta de almacenamiento: constancias/constancia_{proveedor_id:6 dijitos}_{timestamp}.pdf
        $idFormateado = str_pad($proveedor->id, 6, '0', STR_PAD_LEFT);
        $filename = 'constancia_' . $idFormateado . '_' . time() . '.pdf';
        $path = $file->storeAs('constancias', $filename, 'public');

        $proveedor->update(['constancia_fiscal' => $path]);

        // Extraer datos fiscales del QR de la constancia
        $datosFiscales = null;
        $datosExtraccion = [
            'exito' => false,
            'mensaje' => '',
            'datos' => null,
        ];

        try {
            $fullPath = Storage::disk('public')->path($path);
            Log::info('Intentando extraer datos fiscales de: ' . $fullPath);

            $datosFiscales = $constanciaFiscalService->extraerDatos($fullPath);
            Log::info('Datos fiscales extraídos:', ['datos' => $datosFiscales]);

            if ($datosFiscales) {

                // La constancia puede traer varios regímenes.
                // Para compatibilidad con campos legacy (singulares), usar el mejor candidato:
                // 1) Primer régimen con clave
                // 2) Si ninguno tiene clave, primer régimen con nombre
                if (!empty($datosFiscales['regimenes']) && is_array($datosFiscales['regimenes'])) {
                    $regimenes = array_values(array_filter(
                        $datosFiscales['regimenes'],
                        fn($r) => is_array($r) && (!empty($r['nombre']) || !empty($r['clave']))
                    ));

                    $regimenSeleccionado = null;
                    foreach ($regimenes as $regimen) {
                        if (!empty($regimen['clave'])) {
                            $regimenSeleccionado = $regimen;
                            break;
                        }
                    }

                    if (!$regimenSeleccionado && !empty($regimenes)) {
                        $regimenSeleccionado = $regimenes[0];
                    }

                    $datosFiscales['regimen_fiscal_nombre'] = $regimenSeleccionado['nombre'] ?? null;
                    $datosFiscales['regimen_fiscal_clave'] = $regimenSeleccionado['clave'] ?? null;

                    // Persistir todos los regímenes detectados (1:N)
                    $payloadRegimenes = [];
                    foreach ($regimenes as $idx => $regimen) {
                        $clave = trim((string) ($regimen['clave'] ?? ''));
                        $nombre = trim((string) ($regimen['nombre'] ?? ''));
                        if ($clave === '' && $nombre === '') {
                            continue;
                        }
                        $payloadRegimenes[] = [
                            'clave' => $clave !== '' ? $clave : '000',
                            'nombre' => $nombre !== '' ? $nombre : 'Régimen fiscal',
                            'fecha_alta' => $regimen['fecha_alta'] ?? null,
                            'es_principal' => $idx === 0,
                            'origen' => 'constancia',
                        ];
                    }
                    if (! empty($payloadRegimenes)) {
                        try {
                            $this->syncRegimenesFiscales($proveedor, $payloadRegimenes);
                        } catch (\Throwable $syncError) {
                            Log::warning('No se pudieron persistir regímenes de la constancia', [
                                'proveedor_id' => $proveedor->id,
                                'error' => $syncError->getMessage(),
                            ]);
                        }
                    }
                }

                // Mapear campos para la tabla de proveedores
                $datosFiscales['calle'] = $datosFiscales['nombre_vialidad'] ?? null;
                $datosFiscales['ciudad'] = $datosFiscales['municipio_delegacion'] ?? null;
                $datosFiscales['estado'] = $datosFiscales['entidad_federativa'] ?? null;
                $datosFiscales['pais'] = 'México';

                $datosExtraccion['exito'] = true;
                $datosExtraccion['mensaje'] = 'Datos fiscales extraídos exitosamente';
                $datosExtraccion['datos'] = $datosFiscales;
            } else {
                $datosExtraccion['mensaje'] = 'No se pudieron extraer los datos fiscales del PDF';
                Log::warning('No se extrajeron datos fiscales válidos');
            }
        } catch (\Exception $e) {
            $datosExtraccion['mensaje'] = 'Error al procesar el PDF: ' . $e->getMessage();
            Log::error('Error al extraer datos fiscales: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }

        return $this->success([
            'proveedor' => new ProveedorResource(
                tap(
                    $proveedor->fresh(Proveedor::eagerLodable()),
                    fn (Proveedor $fresh) => $this->perfilCompletadoService->sincronizarBandera($fresh)
                )
            ),
            'extraccion_datos' => $datosExtraccion['datos'],
            'extraccion_meta' => [
                'exito' => $datosExtraccion['exito'],
                'mensaje' => $datosExtraccion['mensaje'] ?: (
                    $datosExtraccion['exito']
                    ? 'Datos fiscales extraídos correctamente desde la constancia fiscal.'
                    : 'No se pudieron extraer datos de la constancia fiscal. Puedes completarlos manualmente.'
                ),
            ],
        ], 'Constancia fiscal actualizada con éxito.', 200);
    }

    /**
     * Vista previa o descarga de la constancia fiscal.
     */
    public function previewConstanciaFiscal(Request $request, Proveedor $proveedor)
    {
        $user = $request->user();

        // Validar acceso
        if ($user->proveedorPrincipal()?->id !== $proveedor->id) {
            return response()->json([
                'message' => 'No tienes permisos para acceder a esta constancia fiscal.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $proveedor->constancia_fiscal || ! Storage::disk('public')->exists($proveedor->constancia_fiscal)) {
            return response()->json([
                'message' => 'La constancia fiscal no está disponible en GestionPlus.',
            ], Response::HTTP_NOT_FOUND);
        }

        $path = Storage::disk('public')->path($proveedor->constancia_fiscal);

        // Mostrar inline en navegador (preview)
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="constancia_fiscal.pdf"',
        ]);
    }

    /**
     * Valida si el proveedor puede generar una Solicitud de Pago (SP).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function puedeGenerarSP(Request $request, Proveedor $proveedor)
    {
        $this->perfilCompletadoService->sincronizarBandera($proveedor);
        $responseData = $this->perfilCompletadoService->evaluarPuedeGenerarSP($proveedor->fresh());

        return $this->success(new ProveedorValidacionPerfilCompletoResource($responseData));
    }

    /**
     * Valida si el perfil del proveedor está completado.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function validarPerfilCompletado(Request $request, Proveedor $proveedor)
    {
        $evaluacion = $this->perfilCompletadoService->evaluar($proveedor);
        $this->perfilCompletadoService->sincronizarBandera($proveedor);

        return $this->success([
            'fiscales' => $evaluacion['fiscales'],
            'bancarios' => $evaluacion['bancarios'],
            'generales' => $evaluacion['generales'],
            'perfil_empresa_completado' => $evaluacion['perfil_empresa_completado'],
        ]);
    }


    /**
     * Verificar rfc existe en la tabla proveedores
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificarRfcExistente(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'rfc' => ['required', 'string'],
        ]);

        // Verificar si el correo existe en la tabla users
        $existe = Proveedor::where('rfc', $request->rfc)->where('id', '!=', $proveedor->id)->exists();

        return $this->success([
            'existe' => $existe,
            'rfc' => $request->rfc,
        ], $existe ? 'El RFC ya está registrado en GestionPlus.' : 'El RFC está disponible en GestionPlus.', 200);
    }

    /**
     * Verificar rfc existe en la tabla proveedores
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificarRfcExistenteExcluyendoProveedor(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'rfc' => ['required', 'string'],
        ]);

        // Verificar si el correo existe en la tabla users
        $existe = Proveedor::where('rfc', $request->rfc)->where('id', '!=', $proveedor->id)->exists();

        return $this->success([
            'existe' => $existe,
            'rfc' => $request->rfc,
        ], $existe ? 'El RFC ya está registrado en GestionPlus.' : 'El RFC está disponible en GestionPlus.', 200);
    }

    public function verificarRazonSocialExistenteExcluyendoProveedor(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'razon_social' => ['required', 'string'],
        ]);

        // Verificar si el correo existe en la tabla users
        $existe = Proveedor::where('razon_social', $request->razon_social)->where('id', '!=', $proveedor->id)->exists();

        return $this->success([
            'existe' => $existe,
            'razon_social' => $request->razon_social,
        ], $existe ? 'La razón social ya está registrada en GestionPlus.' : 'La razón social está disponible en GestionPlus.', 200);
    }


    /**
     * Verificar telefono existe en la tabla users
     * Excluyendo el proveedor especificado
     * @param Request $request
     * @param Proveedor $proveedor
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificarTelefonoExistenteExcluyendoProveedor(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'telefono' => ['required', 'string'],
        ]);

        // Verificar si el telefono existe en la tabla users
        $existe = User::where('telefono', $request->telefono)->where('id', '!=', $proveedor->id)->exists();

        return $this->success([
            'existe' => $existe,
            'telefono' => $request->telefono,
        ], $existe ? 'El teléfono ya está registrado en GestionPlus.' : 'El teléfono está disponible en GestionPlus.', 200);
    }
}
