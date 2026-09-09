<?php

namespace App\Http\Resources;

use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="UserResource",
 *     type="object",
 *     title="UserResource",
 *     properties={
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Juan Pérez"),
 *         @OA\Property(property="email", type="string", example="juan@example.com"),
 *         @OA\Property(property="foto_perfil_url", type="string", nullable=true),
 *         @OA\Property(property="role_id", type="integer", example=2),
 *         @OA\Property(property="status", type="string", example="activo"),
 *         @OA\Property(property="created_at", type="string", example="2023-01-01 00:00:00"),
 *         @OA\Property(property="updated_at", type="string", example="2023-01-01 00:00:00")
 *     }
 * )
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->pivot ?? null;

        $proveedor = $this->proveedorAsignado();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'telefono_codigo_pais' => $this->telefono_codigo_pais,
            'foto_perfil_url' => $this->resolveFotoPerfilUrl(),
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'role_id' => $this->whenLoaded('role', fn () => $this->role_id),
            'status' => $this->status,
            'estado' => $this->status,
            'es_cuenta_de_pruebas' => (bool) $this->es_cuenta_de_pruebas,
            'role' => $this->whenLoaded('role', fn () => new RoleResource($this->role)),
            'oauth_providers' => $this->resolveOauthProviders(),
            'auth_google' => in_array('google', $this->resolveOauthProviders(), true),
            'proveedor' => $proveedor === null ? null : [
                'id' => $proveedor->id,
                'nombre_comercial' => $proveedor->nombre_comercial,
                'razon_social' => $proveedor->razon_social,
                'rfc' => $proveedor->rfc,
                'email' => $proveedor->email,
                'telefono' => $proveedor->telefono,
                'telefono_codigo_pais' => $proveedor->telefono_codigo_pais,
                'direccion_empresa' => $proveedor->direccion_empresa,
                'es_cuenta_de_pruebas' => (bool) ($proveedor->es_cuenta_de_pruebas ?? false),
                'logo' => $proveedor->logo
                    ? Storage::disk('public')->url($proveedor->logo)
                    : null,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'extra_data' => $pivot ? [
                'tipo_relacion' => $pivot->tipo_relacion,
                'activo' => (bool) $pivot->activo,
                'estado' => $pivot->estado ?? 'registrado',
                'fecha_asignacion' => optional($pivot->fecha_asignacion)->format('Y-m-d H:i:s'),
                'fecha_desasignacion' => optional($pivot->fecha_desasignacion)->format('Y-m-d H:i:s'),
                'observaciones' => $pivot->observaciones,
            ] : null,
        ];
    }

    protected function resolveFotoPerfilUrl(): ?string
    {
        $path = $this->foto_perfil_url;
        if (! $path) {
            return null;
        }

        if (preg_match('/^https?:\/\//', $path)) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    /**
     * Proveedor principal o secundario activo para vistas de administración (listado/detalle).
     */
    protected function proveedorAsignado(): ?Proveedor
    {
        /** @var User $user */
        $user = $this->resource;

        if ($user->relationLoaded('proveedores')) {
            if ($user->proveedores->isEmpty()) {
                return null;
            }

            $candidatos = $user->proveedores->filter(function (Proveedor $prov) {
                $pivot = $prov->pivot;

                return $pivot
                    && $pivot->activo
                    && in_array($pivot->tipo_relacion, ['PRINCIPAL', 'SECUNDARIO'], true);
            });

            $principal = $candidatos->first(fn (Proveedor $p) => $p->pivot->tipo_relacion === 'PRINCIPAL');

            return $principal ?? $candidatos->first();
        }

        return $user->proveedorPrincipal();
    }

    /**
     * @return list<string>
     */
    protected function resolveOauthProviders(): array
    {
        if ($this->relationLoaded('oauthAccounts')) {
            return $this->oauthAccounts->pluck('provider')->unique()->values()->all();
        }

        return $this->oauthAccounts()->pluck('provider')->unique()->values()->all();
    }
}
