<?php

namespace App\Http\Controllers;

use App\Http\Requests\Presupuesto\ProveedorStorePresupuestoCarteraClienteRequest;
use App\Http\Requests\Presupuesto\ProveedorUpdatePresupuestoCarteraClienteRequest;
use App\Http\Resources\Presupuesto\ProveedorPresupuestoCarteraClienteResource;
use App\Models\CarteraCliente;
use App\Models\Proveedor;
use App\Support\PresupuestoAnexoImagenOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProveedorPresupuestoCarteraClientesController extends Controller
{
    private bool $logEnabled = true;

    /**
     * Listado de cartera de clientes del proveedor.
     */
    public function index(Request $request, Proveedor $proveedor): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
        }

        $filters = $request->only(CarteraCliente::getFilters());
        $filters['proveedor_id'] = $proveedor->id;

        $sortBy = $request->input('sort_by', 'empresa');
        $order = $request->input('order', 'asc');
        $perPage = $request->input('per_page', 15);

        $originalPaginator = CarteraCliente::query()
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = ProveedorPresupuestoCarteraClienteResource::collection($originalPaginator)->resolve();

        return $this->paginated($originalPaginator->setCollection(collect($data)));
    }

    /**
     * Crear cliente en cartera.
     */
    public function store(
        ProveedorStorePresupuestoCarteraClienteRequest $request,
        Proveedor $proveedor
    ): JsonResponse {
        try {

            $user = $request->user();

            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
            }

            $validated = $request->validated();

            $logoPath = null;
            $base64 = $validated['logo_base64'] ?? null;
            if (is_string($base64) && trim($base64) !== '') {
                $logoPath = $this->guardarLogoBase64((int) $proveedor->id, $base64);
            }

            $cliente = CarteraCliente::create([
                'proveedor_id' => $proveedor->id,
                'nombre' => $validated['nombre'],
                'puesto' => $validated['puesto'] ?? null,
                'empresa' => $validated['empresa'],
                'alias_empresa' => $validated['alias_empresa'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'correo' => $validated['correo'] ?? null,
                'logo_path' => $logoPath,
            ]);

            $this->log('Cliente agregado a cartera', [
                'cliente_id' => $cliente->id,
                'proveedor_id' => $proveedor->id,
            ]);

            return $this->success(
                new ProveedorPresupuestoCarteraClienteResource($cliente),
                'Cliente agregado a cartera.',
                201
            );
        } catch (Throwable $e) {

            $this->log('Error al crear cliente de cartera', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible crear el cliente.', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar cliente de cartera.
     */
    public function show(
        Request $request,
        Proveedor $proveedor,
        CarteraCliente $carteraCliente
    ): JsonResponse {

        $user = $request->user();

        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
        }

        if ((int) $carteraCliente->proveedor_id !== (int) $proveedor->id) {
            return $this->error('El cliente no pertenece a este proveedor.', null, 403);
        }

        return $this->success(
            new ProveedorPresupuestoCarteraClienteResource($carteraCliente)
        );
    }

    /**
     * Actualizar cliente de cartera.
     */
    public function update(
        ProveedorUpdatePresupuestoCarteraClienteRequest $request,
        Proveedor $proveedor,
        CarteraCliente $carteraCliente
    ): JsonResponse {

        try {

            $user = $request->user();

            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
            }

            if ((int) $carteraCliente->proveedor_id !== (int) $proveedor->id) {
                return $this->error('El cliente no pertenece a este proveedor.', null, 403);
            }

            $validated = $request->validated();

            if (array_key_exists('activo', $validated) && ! array_key_exists('nombre', $validated)) {
                $carteraCliente->update([
                    'activo' => (bool) $validated['activo'],
                ]);

                $mensaje = $carteraCliente->activo
                    ? 'Cliente reactivado correctamente.'
                    : 'Cliente dado de baja correctamente.';

                $this->log($mensaje, [
                    'cliente_id' => $carteraCliente->id,
                    'activo' => $carteraCliente->activo,
                ]);

                return $this->success(
                    new ProveedorPresupuestoCarteraClienteResource($carteraCliente->fresh()),
                    $mensaje
                );
            }

            $logoPath = $carteraCliente->logo_path;

            $base64 = $validated['logo_base64'] ?? null;
            if (is_string($base64) && trim($base64) !== '') {
                $nuevoPath = $this->guardarLogoBase64((int) $proveedor->id, $base64);
                if ($nuevoPath !== null) {
                    $this->eliminarLogoSiExiste($logoPath);
                    $logoPath = $nuevoPath;
                }
            } elseif (! empty($validated['eliminar_logo'])) {
                $this->eliminarLogoSiExiste($logoPath);
                $logoPath = null;
            }

            $payload = [
                'nombre' => $validated['nombre'],
                'puesto' => $validated['puesto'] ?? null,
                'empresa' => $validated['empresa'],
                'alias_empresa' => $validated['alias_empresa'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'correo' => $validated['correo'] ?? null,
                'logo_path' => $logoPath,
            ];
            if (array_key_exists('activo', $validated)) {
                $payload['activo'] = (bool) $validated['activo'];
            }

            $carteraCliente->update($payload);

            $this->log('Cliente de cartera actualizado', [
                'cliente_id' => $carteraCliente->id,
            ]);

            return $this->success(
                new ProveedorPresupuestoCarteraClienteResource($carteraCliente->fresh()),
                'Cliente actualizado correctamente.'
            );
        } catch (Throwable $e) {

            $this->log('Error al actualizar cliente de cartera', [
                'cliente_id' => $carteraCliente->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible actualizar el cliente.', [$e->getMessage()], 500);
        }
    }

    /**
     * Dar de baja cliente de cartera (soft: activo = false).
     */
    public function destroy(
        Request $request,
        Proveedor $proveedor,
        CarteraCliente $carteraCliente
    ): JsonResponse {

        try {

            $user = $request->user();

            if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
                return $this->error('El usuario autenticado no tiene acceso al proveedor indicado.', null, 403);
            }

            if ((int) $carteraCliente->proveedor_id !== (int) $proveedor->id) {
                return $this->error('El cliente no pertenece a este proveedor.', null, 403);
            }

            $carteraCliente->update(['activo' => false]);

            $this->log('Cliente dado de baja de cartera', [
                'cliente_id' => $carteraCliente->id,
            ]);

            return $this->success(
                new ProveedorPresupuestoCarteraClienteResource($carteraCliente->fresh()),
                'Cliente dado de baja correctamente.'
            );
        } catch (Throwable $e) {

            $this->log('Error al dar de baja cliente de cartera', [
                'cliente_id' => $carteraCliente->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('No fue posible dar de baja el cliente.', [$e->getMessage()], 500);
        }
    }

    private function guardarLogoBase64(int $proveedorId, string $dataUri): ?string
    {
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $dataUri, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            return null;
        }

        $optimizado = PresupuestoAnexoImagenOptimizer::optimizarParaAlmacenamiento($binary);
        $extension = $optimizado['extension'] ?? 'jpg';

        $path = sprintf(
            'proveedores/%d/presupuestos/cartera-clientes/%s.%s',
            $proveedorId,
            Str::uuid()->toString(),
            $extension
        );

        Storage::disk('public')->put($path, $optimizado['binary']);

        return $path;
    }

    private function eliminarLogoSiExiste(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        $path = trim((string) $path);
        if (str_starts_with($path, 'data:image/')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function log($message, $data = []): void
    {
        if (! $this->logEnabled) {
            return;
        }

        Log::info($message, $data);
    }
}
