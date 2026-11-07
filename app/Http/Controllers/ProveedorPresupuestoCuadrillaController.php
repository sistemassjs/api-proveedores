<?php

namespace App\Http\Controllers;

use App\Http\Requests\Presupuesto\StorePresupuestoCuadrillaRequest;
use App\Http\Requests\Presupuesto\UpdatePresupuestoCuadrillaRequest;
use App\Http\Resources\Presupuesto\PresupuestoCuadrillaResource;
use App\Models\Presupuesto;
use App\Models\PresupuestoCuadrilla;
use App\Models\Proveedor;
use App\Services\Presupuesto\PresupuestoMatrizCalculoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProveedorPresupuestoCuadrillaController extends Controller
{
    public function __construct(
        private PresupuestoMatrizCalculoService $matrizCalculo
    ) {}

    public function index(Request $request, Proveedor $proveedor, Presupuesto $presupuesto): JsonResponse
    {
        $access = $this->validateAccess($request, $proveedor, $presupuesto);
        if ($access !== null) {
            return $access;
        }

        $cuadrillas = $presupuesto->cuadrillas()->orderBy('orden')->orderBy('id')->get();

        return $this->success(
            PresupuestoCuadrillaResource::collection($cuadrillas),
            'Operación exitosa.'
        );
    }

    public function store(
        StorePresupuestoCuadrillaRequest $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto);
        if ($access !== null) {
            return $access;
        }

        try {
            $validated = $request->validated();
            $cuadrilla = PresupuestoCuadrilla::create([
                'presupuesto_id' => (int) $presupuesto->id,
                'orden' => isset($validated['orden'])
                    ? (int) $validated['orden']
                    : ((int) $presupuesto->cuadrillas()->max('orden') + 1),
                'clave' => $validated['clave'] ?? null,
                'nombre' => $validated['nombre'],
                'integrantes' => $validated['integrantes'] ?? null,
                'unidad' => $validated['unidad'] ?? 'jor',
                'costo_unitario' => $validated['costo_unitario'],
            ]);

            return $this->success(
                new PresupuestoCuadrillaResource($cuadrilla),
                'Cuadrilla agregada al presupuesto.',
                201
            );
        } catch (Throwable $e) {
            return $this->error('No fue posible crear la cuadrilla.', [$e->getMessage()], 500);
        }
    }

    public function show(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoCuadrilla $cuadrilla
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $cuadrilla);
        if ($access !== null) {
            return $access;
        }

        return $this->success(new PresupuestoCuadrillaResource($cuadrilla), 'Operación exitosa.');
    }

    public function update(
        UpdatePresupuestoCuadrillaRequest $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoCuadrilla $cuadrilla
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $cuadrilla);
        if ($access !== null) {
            return $access;
        }

        try {
            $validated = $request->validated();
            $costoAnterior = (float) $cuadrilla->costo_unitario;
            $unidadAnterior = (string) $cuadrilla->unidad;
            $nombreAnterior = (string) $cuadrilla->nombre;
            $integrantesAnterior = (string) ($cuadrilla->integrantes ?? '');
            $claveAnterior = (string) ($cuadrilla->clave ?? '');

            $cuadrilla->fill($validated);
            $cuadrilla->save();

            $cambioRelevante = abs($costoAnterior - (float) $cuadrilla->costo_unitario) >= 0.0001
                || $unidadAnterior !== (string) $cuadrilla->unidad
                || $nombreAnterior !== (string) $cuadrilla->nombre
                || $integrantesAnterior !== (string) ($cuadrilla->integrantes ?? '')
                || $claveAnterior !== (string) ($cuadrilla->clave ?? '');

            if ($cambioRelevante) {
                DB::transaction(function () use ($presupuesto, $cuadrilla) {
                    $this->matrizCalculo->propagarCostoCuadrillaEnPresupuesto(
                        $presupuesto->fresh(['conceptos.componentes']) ?? $presupuesto,
                        (int) $cuadrilla->id
                    );
                });
            }

            return $this->success(
                new PresupuestoCuadrillaResource($cuadrilla->fresh()),
                'Cuadrilla actualizada.'
            );
        } catch (Throwable $e) {
            return $this->error('No fue posible actualizar la cuadrilla.', [$e->getMessage()], 500);
        }
    }

    public function destroy(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoCuadrilla $cuadrilla
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $cuadrilla);
        if ($access !== null) {
            return $access;
        }

        try {
            $cuadrilla->delete();

            return $this->success(null, 'Cuadrilla eliminada.');
        } catch (Throwable $e) {
            return $this->error('No fue posible eliminar la cuadrilla.', [$e->getMessage()], 500);
        }
    }

    private function validateAccess(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        ?PresupuestoCuadrilla $cuadrilla = null
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
        }

        if ((int) $presupuesto->proveedor_id !== (int) $proveedor->id) {
            return $this->error('El presupuesto no pertenece al proveedor indicado.', null, 403);
        }

        if ($cuadrilla && (int) $cuadrilla->presupuesto_id !== (int) $presupuesto->id) {
            return $this->error('La cuadrilla no pertenece al presupuesto indicado.', null, 403);
        }

        return null;
    }
}
