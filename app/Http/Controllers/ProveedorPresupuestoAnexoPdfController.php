<?php

namespace App\Http\Controllers;

use App\Http\Requests\Presupuesto\StorePresupuestoAnexoPdfRequest;
use App\Http\Requests\Presupuesto\UpdatePresupuestoAnexoPdfRequest;
use App\Http\Resources\Presupuesto\PresupuestoAnexoPdfResource;
use App\Models\Presupuesto;
use App\Models\PresupuestoAnexoPdf;
use App\Models\Proveedor;
use App\Support\PresupuestoAnexoPdfStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProveedorPresupuestoAnexoPdfController extends Controller
{
    public function index(Request $request, Proveedor $proveedor, Presupuesto $presupuesto): JsonResponse
    {
        $access = $this->validateAccess($request, $proveedor, $presupuesto);
        if ($access !== null) {
            return $access;
        }

        $anexos = $presupuesto->anexosPdf()
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return $this->success(PresupuestoAnexoPdfResource::collection($anexos), 'Operación exitosa.');
    }

    public function store(
        StorePresupuestoAnexoPdfRequest $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto);
        if ($access !== null) {
            return $access;
        }

        try {
            $validated = $request->validated();
            $archivo = PresupuestoAnexoPdfStorage::guardarPdfBase64(
                $proveedor,
                $presupuesto,
                $validated['archivo_base64']
            );

            $anexo = PresupuestoAnexoPdf::create([
                'presupuesto_id' => (int) $presupuesto->id,
                'titulo' => $this->normalizarTitulo($validated['titulo'] ?? null),
                'orden' => isset($validated['orden'])
                    ? (int) $validated['orden']
                    : ((int) $presupuesto->anexosPdf()->max('orden') + 1),
                'archivo_path' => $archivo['path'],
                'paginas' => $archivo['paginas'],
                'mostrar_estampado' => $this->booleanDesdeValidated($validated, 'mostrar_estampado', true),
                'mostrar_numero_pagina' => $this->booleanDesdeValidated($validated, 'mostrar_numero_pagina', true),
                'mostrar_datos_presupuesto' => $this->booleanDesdeValidated($validated, 'mostrar_datos_presupuesto', true),
            ])->fresh(PresupuestoAnexoPdf::eagerLodable());

            return $this->success(
                new PresupuestoAnexoPdfResource($anexo),
                'Anexo PDF creado correctamente.',
                201
            );
        } catch (Throwable $e) {
            return $this->error('No fue posible crear el anexo PDF.', [$e->getMessage()], 500);
        }
    }

    public function show(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoAnexoPdf $anexoPdf
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $anexoPdf);
        if ($access !== null) {
            return $access;
        }

        return $this->success(new PresupuestoAnexoPdfResource($anexoPdf), 'Operación exitosa.');
    }

    public function update(
        UpdatePresupuestoAnexoPdfRequest $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoAnexoPdf $anexoPdf
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $anexoPdf);
        if ($access !== null) {
            return $access;
        }

        try {
            $validated = $request->validated();
            $payload = [
                'titulo' => $this->normalizarTitulo($validated['titulo'] ?? null),
                'orden' => isset($validated['orden']) ? (int) $validated['orden'] : (int) $anexoPdf->orden,
            ];

            if (array_key_exists('archivo_base64', $validated) && ! empty($validated['archivo_base64'])) {
                if ($anexoPdf->archivo_path && Storage::disk('public')->exists($anexoPdf->archivo_path)) {
                    Storage::disk('public')->delete($anexoPdf->archivo_path);
                }
                $archivo = PresupuestoAnexoPdfStorage::guardarPdfBase64(
                    $proveedor,
                    $presupuesto,
                    $validated['archivo_base64']
                );
                $payload['archivo_path'] = $archivo['path'];
                $payload['paginas'] = $archivo['paginas'];
            }

            foreach (['mostrar_estampado', 'mostrar_numero_pagina', 'mostrar_datos_presupuesto'] as $flag) {
                if (array_key_exists($flag, $validated)) {
                    $payload[$flag] = $this->booleanDesdeValidated($validated, $flag, true);
                }
            }

            $anexoPdf->update($payload);

            return $this->success(
                new PresupuestoAnexoPdfResource($anexoPdf->fresh(PresupuestoAnexoPdf::eagerLodable())),
                'Anexo PDF actualizado correctamente.'
            );
        } catch (Throwable $e) {
            return $this->error('No fue posible actualizar el anexo PDF.', [$e->getMessage()], 500);
        }
    }

    public function destroy(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoAnexoPdf $anexoPdf
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $anexoPdf);
        if ($access !== null) {
            return $access;
        }

        try {
            if ($anexoPdf->archivo_path && Storage::disk('public')->exists($anexoPdf->archivo_path)) {
                Storage::disk('public')->delete($anexoPdf->archivo_path);
            }
            $anexoPdf->delete();

            return $this->success(null, 'Anexo PDF eliminado correctamente.');
        } catch (Throwable $e) {
            return $this->error('No fue posible eliminar el anexo PDF.', [$e->getMessage()], 500);
        }
    }

    private function normalizarTitulo(mixed $titulo): string
    {
        if ($titulo === null) {
            return '';
        }

        return trim((string) $titulo);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function booleanDesdeValidated(array $validated, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $validated)) {
            return $default;
        }

        return filter_var($validated[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private function validateAccess(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        ?PresupuestoAnexoPdf $anexoPdf = null
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
        }

        if ((int) $presupuesto->proveedor_id !== (int) $proveedor->id) {
            return $this->error('El presupuesto no pertenece al proveedor indicado.', null, 403);
        }

        if ($anexoPdf && (int) $anexoPdf->presupuesto_id !== (int) $presupuesto->id) {
            return $this->error('El anexo PDF no pertenece al presupuesto indicado.', null, 403);
        }

        return null;
    }
}
