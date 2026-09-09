<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\RoleResource;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAuthenticateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'telefono_codigo_pais' => $this->telefono_codigo_pais,
            'telefono' => $this->telefono,
            'foto_perfil_url' => PublicStorageUrl::make($this->foto_perfil_url),
            'role' => new RoleResource($this->whenLoaded('role')),
            'estado' => $this->status,
            'solicitar_correo' => $this->solicitarCorreo(),
            'cambiar_pass_default' => $this->cambiar_pass_default,
            'email_verificado' => ! is_null($this->email_verified_at),
            'email_verified_at' => $this->email_verified_at,
            'oauth_providers' => $this->resolveOauthProviders(),
            'auth_google' => in_array('google', $this->resolveOauthProviders(), true),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveOauthProviders(): array
    {
        if ($this->relationLoaded('oauthAccounts')) {
            return $this->oauthAccounts->pluck('provider')->unique()->values()->all();
        }

        return $this->oauthAccounts()->pluck('provider')->unique()->values()->all();
    }
}
