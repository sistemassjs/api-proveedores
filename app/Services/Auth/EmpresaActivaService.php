<?php

namespace App\Services\Auth;

use App\Exceptions\Api\Auth\UnauthorizedProveedorAccessException;
use App\Http\Resources\Auth\UserAuthenticateResource;
use App\Http\Resources\ProveedorResource;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProveedor;
use App\Support\PublicStorageUrl;

/**
 * Empresa con la que opera la sesión y listado de membresías activas.
 */
class EmpresaActivaService
{
    /**
     * Empresa de la sesión: última elegida si sigue vigente, si no la principal, si no la primera activa.
     */
    public function resolver(User $user): ?Proveedor
    {
        $activas = $user->userProveedores()
            ->where('activo', true)
            ->with('proveedor')
            ->get();

        if ($activas->isEmpty()) {
            return null;
        }

        if ($user->ultima_empresa_id) {
            $elegida = $activas->first(
                fn (UserProveedor $row) => (int) $row->proveedor_id === (int) $user->ultima_empresa_id
            );
            if ($elegida?->proveedor) {
                return $elegida->proveedor;
            }
        }

        $principal = $activas->first(
            fn (UserProveedor $row) => $row->tipo_relacion === 'PRINCIPAL' && $row->proveedor
        );
        if ($principal?->proveedor) {
            return $principal->proveedor;
        }

        return $activas->first()?->proveedor;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(User $user): array
    {
        $rows = $user->userProveedores()
            ->where('activo', true)
            ->with(['proveedor', 'role'])
            ->get()
            ->sortBy(fn (UserProveedor $row) => $row->tipo_relacion === 'PRINCIPAL' ? 0 : 1)
            ->values();

        return $rows->map(function (UserProveedor $row) {
            $proveedor = $row->proveedor;
            if (! $proveedor) {
                return null;
            }

            return [
                'id' => $proveedor->id,
                'razon_social' => $proveedor->razon_social,
                'nombre_comercial' => $proveedor->nombre_comercial,
                'alias' => $proveedor->alias,
                'rfc' => $proveedor->rfc,
                'logo' => PublicStorageUrl::make($proveedor->logo),
                'tipo_relacion' => $row->tipo_relacion,
                'estado' => $row->estado,
                'rol' => $row->role ? [
                    'id' => $row->role->id,
                    'nombre' => $row->role->nombre,
                ] : null,
            ];
        })->filter()->values()->all();
    }

    /**
     * Deja en memoria el rol de la empresa activa para el resource de sesión.
     * No persiste users.role_id.
     */
    public function aplicarRol(User $user, ?Proveedor $proveedor): void
    {
        if (! $proveedor) {
            return;
        }

        $roleId = $user->userProveedores()
            ->where('proveedor_id', $proveedor->id)
            ->where('activo', true)
            ->value('role_id');

        $role = $roleId ? Role::query()->find($roleId) : null;
        if (! $role) {
            $user->loadMissing('role');
            $role = $user->role;
        }

        if ($role) {
            $user->setRelation('role', $role);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function payload(User $user, ?string $token = null, array $extra = []): array
    {
        $user->load(User::eagerLodable());
        $proveedor = $this->resolver($user);
        $this->aplicarRol($user, $proveedor);

        if ($proveedor) {
            $proveedor->loadMissing(Proveedor::eagerLodable());
        }

        return array_merge([
            'user' => new UserAuthenticateResource($user),
            'token' => $token,
            'proveedor' => $proveedor ? new ProveedorResource($proveedor) : null,
            'empresas' => $this->listar($user),
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    public function cambiar(User $user, int $proveedorId): array
    {
        $vigente = $user->userProveedores()
            ->where('proveedor_id', $proveedorId)
            ->where('activo', true)
            ->exists();

        if (! $vigente) {
            throw new UnauthorizedProveedorAccessException(
                'No tienes acceso activo a esa empresa.'
            );
        }

        $user->ultima_empresa_id = $proveedorId;
        $user->save();

        return $this->payload($user->fresh() ?? $user, null);
    }
}
