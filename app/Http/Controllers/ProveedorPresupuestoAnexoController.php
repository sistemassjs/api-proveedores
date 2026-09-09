<?php

namespace App\Http\Controllers;

use App\Http\Requests\Presupuesto\StorePresupuestoAnexoBulkRequest;
use App\Http\Requests\Presupuesto\StorePresupuestoAnexoRequest;
use App\Http\Requests\Presupuesto\UpdatePresupuestoAnexoRequest;
use App\Http\Resources\Presupuesto\PresupuestoAnexoResource;
use App\Models\Presupuesto;
use App\Models\PresupuestoAnexo;
use App\Models\Proveedor;
use App\Support\PresupuestoAnexoImagenOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProveedorPresupuestoAnexoController extends Controller
{
    public function index(Request $request, Proveedor $proveedor, Presupuesto $presupuesto): JsonResponse
    {
        $access = $this->validateAccess($request, $proveedor, $presupuesto);
        if ($access !== null) {
            return $access;
        }

        $anexos = $presupuesto->anexos()
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return $this->success(PresupuestoAnexoResource::collection($anexos), 'Operación exitosa.');
    }

    public function storeBulk(
        StorePresupuestoAnexoBulkRequest $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto);
        if ($access !== null) {
            return $access;
        }

        try {
            $items = $request->validated()['anexos'];
            $baseOrden = (int) $presupuesto->anexos()->max('orden');

            $creados = DB::transaction(function () use ($items, $proveedor, $presupuesto, $baseOrden) {
                $result = [];
                foreach ($items as $index => $item) {
                    $archivo = $this->guardarImagenBase64($proveedor, $presupuesto, $item['archivo_base64']);
                    $orden = isset($item['orden'])
                        ? (int) $item['orden']
                        : ($baseOrden + $index + 1);

                    $result[] = PresupuestoAnexo::create([
                        'presupuesto_id' => (int) $presupuesto->id,
                        'titulo' => $this->normalizarTituloAnexo($item['titulo'] ?? null),
                        'descripcion' => $item['descripcion'] ?? null,
                        'precio' => $item['precio'] ?? null,
                        'orden' => $orden,
                        'archivo_path' => $archivo['path'],
                        'archivo_width' => $archivo['width'] ?: null,
                        'archivo_height' => $archivo['height'] ?: null,
                        'archivo_aspect_ratio' => $archivo['aspect_ratio'] ?: null,
                    ])->fresh(PresupuestoAnexo::eagerLodable());
                }

                return $result;
            });

            return $this->success(
                PresupuestoAnexoResource::collection(collect($creados)),
                'Anexos creados correctamente.',
                201
            );
        } catch (Throwable $e) {
            return $this->error('No fue posible crear los anexos.', [$e->getMessage()], 500);
        }
    }

    public function store(
        StorePresupuestoAnexoRequest $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto);
        if ($access !== null) {
            return $access;
        }

        try {
            $validated = $request->validated();

            $archivo = $this->guardarImagenBase64($proveedor, $presupuesto, $validated['archivo_base64']);

            $anexo = PresupuestoAnexo::create([
                'presupuesto_id' => (int) $presupuesto->id,
                'titulo' => $this->normalizarTituloAnexo($validated['titulo'] ?? null),
                'descripcion' => $validated['descripcion'] ?? null,
                'precio' => $validated['precio'] ?? null,
                'orden' => isset($validated['orden'])
                    ? (int) $validated['orden']
                    : ((int) $presupuesto->anexos()->max('orden') + 1),
                'archivo_path' => $archivo['path'],
                'archivo_width' => $archivo['width'] ?: null,
                'archivo_height' => $archivo['height'] ?: null,
                'archivo_aspect_ratio' => $archivo['aspect_ratio'] ?: null,
            ])->fresh(PresupuestoAnexo::eagerLodable());

            return $this->success(
                new PresupuestoAnexoResource($anexo),
                'Anexo creado correctamente.',
                201
            );
        } catch (Throwable $e) {
            return $this->error('No fue posible crear el anexo.', [$e->getMessage()], 500);
        }
    }

    public function show(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoAnexo $anexo
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $anexo);
        if ($access !== null) {
            return $access;
        }

        return $this->success(new PresupuestoAnexoResource($anexo), 'Operación exitosa.');
    }

    public function update(
        UpdatePresupuestoAnexoRequest $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoAnexo $anexo
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $anexo);
        if ($access !== null) {
            return $access;
        }

        try {
            $validated = $request->validated();
            $payload = [
                'titulo' => $this->normalizarTituloAnexo($validated['titulo'] ?? null),
                'descripcion' => $validated['descripcion'] ?? null,
                'precio' => $validated['precio'] ?? null,
                'orden' => isset($validated['orden']) ? (int) $validated['orden'] : (int) $anexo->orden,
            ];

            if (array_key_exists('archivo_base64', $validated) && ! empty($validated['archivo_base64'])) {
                if ($anexo->archivo_path && Storage::disk('public')->exists($anexo->archivo_path)) {
                    Storage::disk('public')->delete($anexo->archivo_path);
                }
                $archivo = $this->guardarImagenBase64(
                    $proveedor,
                    $presupuesto,
                    $validated['archivo_base64']
                );
                $payload['archivo_path'] = $archivo['path'];
                $payload['archivo_width'] = $archivo['width'] ?: null;
                $payload['archivo_height'] = $archivo['height'] ?: null;
                $payload['archivo_aspect_ratio'] = $archivo['aspect_ratio'] ?: null;
            }

            $anexo->update($payload);

            return $this->success(
                new PresupuestoAnexoResource($anexo->fresh(PresupuestoAnexo::eagerLodable())),
                'Anexo actualizado correctamente.'
            );
        } catch (Throwable $e) {
            return $this->error('No fue posible actualizar el anexo.', [$e->getMessage()], 500);
        }
    }

    public function destroy(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        PresupuestoAnexo $anexo
    ): JsonResponse {
        $access = $this->validateAccess($request, $proveedor, $presupuesto, $anexo);
        if ($access !== null) {
            return $access;
        }

        try {
            if ($anexo->archivo_path && Storage::disk('public')->exists($anexo->archivo_path)) {
                Storage::disk('public')->delete($anexo->archivo_path);
            }
            $anexo->delete();

            return $this->success(null, 'Anexo eliminado correctamente.');
        } catch (Throwable $e) {
            return $this->error('No fue posible eliminar el anexo.', [$e->getMessage()], 500);
        }
    }

    private function normalizarTituloAnexo(mixed $titulo): string
    {
        if ($titulo === null) {
            return '';
        }

        return trim((string) $titulo);
    }

    private function validateAccess(
        Request $request,
        Proveedor $proveedor,
        Presupuesto $presupuesto,
        ?PresupuestoAnexo $anexo = null
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
        }

        if ((int) $presupuesto->proveedor_id !== (int) $proveedor->id) {
            return $this->error('El presupuesto no pertenece al proveedor indicado.', null, 403);
        }

        if ($anexo && (int) $anexo->presupuesto_id !== (int) $presupuesto->id) {
            return $this->error('El anexo no pertenece al presupuesto indicado.', null, 403);
        }

        return null;
    }

    /**
     * @return array{path: string, width: int, height: int, aspect_ratio: float}
     */
    private function guardarImagenBase64(Proveedor $proveedor, Presupuesto $presupuesto, string $dataUri): array
    {
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $dataUri, $matches)) {
            throw new \InvalidArgumentException('La imagen del anexo no es válida.');
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            throw new \InvalidArgumentException('La imagen del anexo no es válida.');
        }

        $optimizado = PresupuestoAnexoImagenOptimizer::optimizarParaAlmacenamiento($binary);
        $extension = $optimizado['extension'] ?? 'jpg';

        $path = sprintf(
            'proveedores/%d/presupuestos/%d/anexos/%s.%s',
            (int) $proveedor->id,
            (int) $presupuesto->id,
            Str::uuid()->toString(),
            $extension
        );

        Storage::disk('public')->put($path, $optimizado['binary']);

        return [
            'path' => $path,
            'width' => (int) ($optimizado['width'] ?? 0),
            'height' => (int) ($optimizado['height'] ?? 0),
            'aspect_ratio' => (float) ($optimizado['aspect_ratio'] ?? 0),
        ];
    }
}
