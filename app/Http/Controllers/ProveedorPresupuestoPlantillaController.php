<?php

namespace App\Http\Controllers;

use App\Http\Requests\Presupuesto\StorePresupuestoPlantillaRequest;
use App\Http\Requests\Presupuesto\UpdatePresupuestoPlantillaRequest;
use App\Http\Resources\Presupuesto\PresupuestoPlantillaResource;
use App\Http\Resources\Presupuesto\PresupuestoResource;
use App\Models\Presupuesto;
use App\Models\PresupuestoPlantilla;
use App\Models\PresupuestoPlantillaConcepto;
use App\Models\Proveedor;
use App\Services\Presupuesto\PresupuestoPlantillaAplicarService;
use App\Services\Presupuesto\PresupuestoPlantillaDesdePresupuestoService;
use App\Support\PresupuestoAnexoImagenOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * CRUD e aplicar de plantillas de presupuesto.
 * Aislado del controller del documento Presupuesto.
 */
class ProveedorPresupuestoPlantillaController extends Controller
{
    private bool $logEnabled = true;

    public function __construct(
        private readonly PresupuestoPlantillaAplicarService $aplicarService,
        private readonly PresupuestoPlantillaDesdePresupuestoService $desdePresupuestoService
    ) {}

    public function index(Request $request, Proveedor $proveedor): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso a la empresa en GestionPlus.', null, 403);
        }

        $filters = $request->only(PresupuestoPlantilla::getFilters());
        $filters['proveedor_id'] = $proveedor->id;

        $sortBy = $request->input('sort_by', 'nombre');
        $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->input('per_page', 20);
        $allowedSort = ['nombre', 'created_at', 'updated_at', 'id', 'activo'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'nombre';
        }

        $paginator = PresupuestoPlantilla::query()
            ->with(['conceptos', 'anexos', 'anexosPdf'])
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate(max(1, min($perPage, 100)));

        $data = PresupuestoPlantillaResource::collection($paginator)->resolve();

        return $this->paginated($paginator->setCollection(collect($data)), 'Plantillas de presupuesto.');
    }

    public function store(StorePresupuestoPlantillaRequest $request, Proveedor $proveedor): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso a la empresa en GestionPlus.', null, 403);
            }

            $validated = $request->validated();
            $plantilla = DB::transaction(function () use ($validated, $proveedor, $user) {
                $payload = collect($validated)->except(['conceptos'])->toArray();
                $payload['proveedor_id'] = (int) $proveedor->id;
                $payload['user_id'] = (int) $user->id;
                $payload['activo'] = array_key_exists('activo', $payload)
                    ? (bool) $payload['activo']
                    : true;
                $payload['term_cond_moneda'] = $payload['term_cond_moneda'] ?? 'MXN';
                $payload['con_iva'] = $payload['con_iva'] ?? true;
                $payload['iva_porcentaje'] = $payload['iva_porcentaje'] ?? 16;
                $payload['config_mostrar_totales'] = $payload['config_mostrar_totales'] ?? true;

                $plantilla = PresupuestoPlantilla::create($payload);
                $this->sincronizarConceptos($plantilla, $validated['conceptos'] ?? []);

                return $plantilla->fresh(PresupuestoPlantilla::eagerLodable());
            });

            $this->log('Plantilla de presupuesto creada', [
                'plantilla_id' => $plantilla->id,
                'proveedor_id' => $proveedor->id,
            ]);

            return $this->success(
                new PresupuestoPlantillaResource($plantilla),
                'Plantilla creada correctamente.',
                201
            );
        } catch (Throwable $e) {
            $this->log('Error al crear plantilla de presupuesto', ['error' => $e->getMessage()]);

            return $this->error('No fue posible crear la plantilla.', [$e->getMessage()], 500);
        }
    }

    public function show(Request $request, Proveedor $proveedor, PresupuestoPlantilla $plantilla): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso a la empresa en GestionPlus.', null, 403);
        }
        if ((int) $plantilla->proveedor_id !== (int) $proveedor->id) {
            return $this->error('La plantilla no pertenece a esta empresa.', null, 403);
        }

        $plantilla->load(PresupuestoPlantilla::eagerLodable());

        return $this->success(new PresupuestoPlantillaResource($plantilla), 'Plantilla de presupuesto.');
    }

    public function update(
        UpdatePresupuestoPlantillaRequest $request,
        Proveedor $proveedor,
        PresupuestoPlantilla $plantilla
    ): JsonResponse {
        try {
            $user = $request->user();
            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso a la empresa en GestionPlus.', null, 403);
            }
            if ((int) $plantilla->proveedor_id !== (int) $proveedor->id) {
                return $this->error('La plantilla no pertenece a esta empresa.', null, 403);
            }

            $validated = $request->validated();
            $plantilla = DB::transaction(function () use ($validated, $plantilla) {
                $payload = collect($validated)->except(['conceptos'])->toArray();
                $plantilla->update($payload);
                if (array_key_exists('conceptos', $validated)) {
                    $this->sincronizarConceptos($plantilla, $validated['conceptos'] ?? []);
                }

                return $plantilla->fresh(PresupuestoPlantilla::eagerLodable());
            });

            $this->log('Plantilla de presupuesto actualizada', ['plantilla_id' => $plantilla->id]);

            return $this->success(
                new PresupuestoPlantillaResource($plantilla),
                'Plantilla actualizada correctamente.'
            );
        } catch (Throwable $e) {
            $this->log('Error al actualizar plantilla', [
                'plantilla_id' => $plantilla->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible actualizar la plantilla.', [$e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Proveedor $proveedor, PresupuestoPlantilla $plantilla): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso a la empresa en GestionPlus.', null, 403);
            }
            if ((int) $plantilla->proveedor_id !== (int) $proveedor->id) {
                return $this->error('La plantilla no pertenece a esta empresa.', null, 403);
            }

            $id = $plantilla->id;
            $plantilla->delete();

            $this->log('Plantilla de presupuesto eliminada', ['plantilla_id' => $id]);

            return $this->success(null, 'Plantilla eliminada correctamente.');
        } catch (Throwable $e) {
            return $this->error('No fue posible eliminar la plantilla.', [$e->getMessage()], 500);
        }
    }

    /**
     * Crea un presupuesto borrador desde la plantilla (sin receptor).
     */
    public function aplicar(Request $request, Proveedor $proveedor, PresupuestoPlantilla $plantilla): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso a la empresa en GestionPlus.', null, 403);
            }
            if ((int) $plantilla->proveedor_id !== (int) $proveedor->id) {
                return $this->error('La plantilla no pertenece a esta empresa.', null, 403);
            }
            if (! $plantilla->activo) {
                return $this->error('La plantilla está inactiva y no se puede usar.', null, 422);
            }

            $presupuesto = $this->aplicarService->aplicar($plantilla, $user);

            $this->log('Plantilla aplicada a presupuesto', [
                'plantilla_id' => $plantilla->id,
                'presupuesto_id' => $presupuesto->id,
            ]);

            return $this->success(
                new PresupuestoResource($presupuesto),
                'Presupuesto creado desde la plantilla.',
                201
            );
        } catch (Throwable $e) {
            $this->log('Error al aplicar plantilla', [
                'plantilla_id' => $plantilla->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible crear el presupuesto desde la plantilla.', [$e->getMessage()], 500);
        }
    }

    /**
     * Aplica la plantilla sobre un PPTO existente (sin crear otro ni tocar receptor/fecha/descripción).
     */
    public function aplicarSobre(
        Request $request,
        Proveedor $proveedor,
        PresupuestoPlantilla $plantilla,
        Presupuesto $presupuesto
    ): JsonResponse {
        try {
            $user = $request->user();
            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso a la empresa en GestionPlus.', null, 403);
            }
            if ((int) $plantilla->proveedor_id !== (int) $proveedor->id) {
                return $this->error('La plantilla no pertenece a esta empresa.', null, 403);
            }
            if ((int) $presupuesto->proveedor_id !== (int) $proveedor->id) {
                return $this->error('El presupuesto no pertenece a esta empresa.', null, 403);
            }
            if (! $plantilla->activo) {
                return $this->error('La plantilla está inactiva y no se puede usar.', null, 422);
            }
            if (! $this->puedeEditarPresupuestoParaAplicarPlantilla($presupuesto)) {
                return $this->error(
                    'No se puede modificar este presupuesto en GestionPlus. Solo se editan borradores o presupuestos con observaciones del cliente.',
                    ['estado_actual' => $presupuesto->estado],
                    422
                );
            }

            $actualizado = $this->aplicarService->aplicarSobre($plantilla, $presupuesto);

            $this->log('Plantilla aplicada sobre presupuesto existente', [
                'plantilla_id' => $plantilla->id,
                'presupuesto_id' => $presupuesto->id,
            ]);

            return $this->success(
                new PresupuestoResource($actualizado),
                'Plantilla aplicada al presupuesto.'
            );
        } catch (Throwable $e) {
            $this->log('Error al aplicar plantilla sobre presupuesto', [
                'plantilla_id' => $plantilla->id,
                'presupuesto_id' => $presupuesto->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible aplicar la plantilla al presupuesto.', [$e->getMessage()], 500);
        }
    }

    /**
     * Crea una plantilla a partir de un presupuesto existente (sin receptor).
     */
    public function desdePresupuesto(Request $request, Proveedor $proveedor, Presupuesto $presupuesto): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso a la empresa en GestionPlus.', null, 403);
            }
            if ((int) $presupuesto->proveedor_id !== (int) $proveedor->id) {
                return $this->error('El presupuesto no pertenece a esta empresa.', null, 403);
            }

            $validated = $request->validate([
                'nombre' => ['required', 'string', 'max:120'],
                'descripcion' => ['nullable', 'string', 'max:500'],
                'mantener_anexos_imagen' => ['sometimes', 'boolean'],
                'mantener_anexos_pdf' => ['sometimes', 'boolean'],
                'mantener_tarjeta' => ['sometimes', 'boolean'],
                'mantener_tema' => ['sometimes', 'boolean'],
            ], [
                'nombre.required' => 'El nombre de la plantilla es obligatorio.',
                'nombre.max' => 'El nombre de la plantilla no puede superar 120 caracteres.',
            ]);

            $plantilla = $this->desdePresupuestoService->crear(
                $presupuesto,
                $user,
                trim((string) $validated['nombre']),
                isset($validated['descripcion']) ? trim((string) $validated['descripcion']) : null,
                [
                    'mantener_anexos_imagen' => $this->boolFromRequest($request, 'mantener_anexos_imagen', true),
                    'mantener_anexos_pdf' => $this->boolFromRequest($request, 'mantener_anexos_pdf', true),
                    'mantener_tarjeta' => $this->boolFromRequest($request, 'mantener_tarjeta', true),
                    'mantener_tema' => $this->boolFromRequest($request, 'mantener_tema', true),
                ]
            );

            $this->log('Plantilla creada desde presupuesto', [
                'plantilla_id' => $plantilla->id,
                'presupuesto_id' => $presupuesto->id,
            ]);

            return $this->success(
                new PresupuestoPlantillaResource($plantilla),
                'Plantilla creada desde el presupuesto.',
                201
            );
        } catch (Throwable $e) {
            $this->log('Error al crear plantilla desde presupuesto', [
                'presupuesto_id' => $presupuesto->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible crear la plantilla desde el presupuesto.', [$e->getMessage()], 500);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $conceptos
     */
    private function sincronizarConceptos(PresupuestoPlantilla $plantilla, array $conceptos): void
    {
        $plantilla->conceptos()->delete();
        $numero = 1;
        foreach ($conceptos as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $tipo = $fila['tipo'] ?? PresupuestoPlantillaConcepto::TIPO_CONCEPTO;
            $esParrafo = $tipo === PresupuestoPlantillaConcepto::TIPO_PARRAFO;
            $imagenPath = null;
            $base64 = $fila['imagen_base64'] ?? null;
            if (! $esParrafo && is_string($base64) && trim($base64) !== '') {
                $imagenPath = $this->guardarImagenConcepto((int) $plantilla->proveedor_id, (int) $plantilla->id, $base64);
            } elseif (! $esParrafo && ! empty($fila['imagen_path'])) {
                $imagenPath = (string) $fila['imagen_path'];
            }

            PresupuestoPlantillaConcepto::create([
                'presupuesto_plantilla_id' => $plantilla->id,
                'numero' => $numero++,
                'tipo' => $tipo,
                'descripcion' => (string) ($fila['descripcion'] ?? ''),
                'cantidad' => $esParrafo ? 0 : (float) ($fila['cantidad'] ?? 1),
                'unidad' => $esParrafo ? '' : (string) ($fila['unidad'] ?? 'pieza'),
                'precio_unitario' => $esParrafo ? 0 : (float) ($fila['precio_unitario'] ?? 0),
                'imagen_path' => $imagenPath,
            ]);
        }
    }

    private function guardarImagenConcepto(int $proveedorId, int $plantillaId, string $base64): ?string
    {
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/', $base64, $m)) {
            return null;
        }
        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            return null;
        }
        $optimized = PresupuestoAnexoImagenOptimizer::optimizarParaAlmacenamiento($binary);
        $path = sprintf(
            'presupuesto-plantillas/%d/%d/conceptos/%s.%s',
            $proveedorId,
            $plantillaId,
            Str::uuid()->toString(),
            $optimized['extension'] ?? 'jpg'
        );
        Storage::disk('public')->put($path, $optimized['binary'] ?? $binary);

        return $path;
    }

    private function boolFromRequest(Request $request, string $key, bool $default): bool
    {
        $value = filter_var(
            $request->input($key, $default),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        return $value === null ? $default : $value;
    }

    /**
     * Borrador o rechazo con motivo (misma regla que update de PPTO).
     */
    private function puedeEditarPresupuestoParaAplicarPlantilla(Presupuesto $presupuesto): bool
    {
        if ($presupuesto->estado === Presupuesto::ESTADO_BORRADOR) {
            return true;
        }

        if (in_array($presupuesto->estado, [Presupuesto::ESTADO_RECHAZADO, Presupuesto::ESTADO_RECHAZADO_CON_OBSERVACION], true)) {
            return $presupuesto->motivo_rechazo && trim((string) $presupuesto->motivo_rechazo) !== '';
        }

        return false;
    }

    private function log(string $message, array $context = []): void
    {
        if (! $this->logEnabled) {
            return;
        }
        \Illuminate\Support\Facades\Log::info($message, $context);
    }
}
