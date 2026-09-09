<?php

namespace App\Services\Auth;

use App\Enums\EstadoUsuario;
use App\Enums\UserRoleEnumerate;
use App\Http\Resources\Auth\UserAuthenticateResource;
use App\Http\Resources\ProveedorResource;
use App\Models\OauthAccount;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\User;
use App\Support\UserCuentaEstado;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SocialAuthService
{
    /**
     * Providers habilitados (ampliar cuando se agreguen drivers).
     *
     * @return list<string>
     */
    public function enabledProviders(): array
    {
        return config('services.oauth.providers', ['google']);
    }

    public function isProviderEnabled(string $provider): bool
    {
        return in_array(strtolower($provider), $this->enabledProviders(), true);
    }

    /**
     * Resuelve el usuario local desde el perfil OAuth: cuenta vinculada,
     * email existente (auto-vínculo) o alta GERENTE + proveedor stub.
     *
     * @return array{
     *     user: User,
     *     token: string,
     *     proveedor: mixed,
     *     pending_registro: bool
     * }
     */
    public function resolveAuthenticatedUser(string $provider, SocialiteUser $socialUser): array
    {
        $provider = strtolower($provider);
        $providerId = (string) $socialUser->getId();
        $email = strtolower(trim((string) $socialUser->getEmail()));

        if ($email === '') {
            throw new \RuntimeException('El proveedor no devolvió un correo electrónico.');
        }

        // Misma conexión que User (evita lock wait FK entre mysql / mysql5).
        $connection = (new User)->getConnectionName() ?: config('database.default');

        $account = OauthAccount::on($connection)
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($account) {
            $user = User::query()->findOrFail($account->user_id);
            $this->syncAccountAvatar($account, $socialUser->getAvatar());
        } else {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $user) {
                $user = $this->createGerenteWithStubProveedor($socialUser, $email, $connection);
            }

            OauthAccount::on($connection)->firstOrCreate(
                [
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ],
                [
                    'user_id' => $user->id,
                    'avatar' => $socialUser->getAvatar(),
                ]
            );

            if ($user->foto_perfil_url === null && $socialUser->getAvatar()) {
                $user->foto_perfil_url = $socialUser->getAvatar();
                $user->save();
            }
        }

        $cuentaCheck = UserCuentaEstado::assertCanAuthenticate($user);
        if (! $cuentaCheck['ok']) {
            throw new \RuntimeException($cuentaCheck['message'] ?? 'No puedes iniciar sesión.');
        }

        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
            $user->save();
        }

        $user->load(User::eagerLodable());
        $token = $user->createToken('OAuth Token')->plainTextToken;
        $proveedor = $user->proveedores()->count() > 0
            ? $user->proveedorPrincipal()
            : null;

        $pendingRegistro = $proveedor === null
            || ! (bool) $proveedor->perfil_empresa_completo
            || $proveedor->registro_completado_at === null;

        return [
            'user' => $user,
            'token' => $token,
            'proveedor' => $proveedor,
            'pending_registro' => $pendingRegistro,
        ];
    }

    /**
     * Payload de sesión alineado a login (ApiResponse data).
     *
     * @param  array{user: User, token: string, proveedor: mixed, pending_registro: bool}  $auth
     * @return array<string, mixed>
     */
    public function sessionPayload(array $auth): array
    {
        return [
            'user' => new UserAuthenticateResource($auth['user']),
            'token' => $auth['token'],
            'proveedor' => $auth['proveedor']
                ? new ProveedorResource($auth['proveedor'])
                : null,
            'pending_registro' => $auth['pending_registro'],
        ];
    }

    /**
     * Alta social: GERENTE + proveedor mínimo (completar en Mi Empresa).
     * Sin sucursal aquí: Sucursal es BaseModel/mysql5 y bloquea FK si el
     * proveedor se acaba de crear en otra conexión/transacción.
     */
    private function createGerenteWithStubProveedor(
        SocialiteUser $socialUser,
        string $email,
        ?string $connection = null
    ): User {
        $connection = $connection ?: ((new User)->getConnectionName() ?: config('database.default'));

        $role = Role::on($connection)
            ->where('nombre', UserRoleEnumerate::GERENTE->value)
            ->firstOrFail();

        $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname() ?: 'Usuario'));
        if ($name === '') {
            $name = 'Usuario';
        }

        $razonSocial = mb_substr(mb_strtoupper($name), 0, 191);

        $user = User::on($connection)->create([
            'name' => $name,
            'email' => $email,
            'password' => null,
            'role_id' => $role->id,
            'status' => EstadoUsuario::REGISTRADO->value,
            'email_verified_at' => now(),
            'foto_perfil_url' => $socialUser->getAvatar(),
            'cambiar_pass_default' => false,
        ]);

        $proveedor = Proveedor::on($connection)->create([
            'razon_social' => $razonSocial,
            'nombre_comercial' => $name,
            'nombre_propietario' => $name,
            'nombre_de_quien_registra' => $name,
            'email' => $email,
            'contacto_nombre' => $name,
            'contacto_correo' => $email,
            'estatus' => EstadoUsuario::REGISTRADO->value,
            'perfil_empresa_completo' => false,
            'is_proveedor_sp' => true,
            'is_proveedor_catalogo' => false,
            'tipo_alta' => 1,
            'tipos_empresa_id' => 1,
            'descripcion_giro_empresa' => 'Pendiente de completar',
            'direccion_empresa' => 'Pendiente de completar',
            'notas' => 'Alta vía OAuth (Google). Completar datos en Mi Empresa.',
        ]);

        $user->setConnection($connection);
        $user->proveedores()->attach($proveedor->id, [
            'tipo_relacion' => 'PRINCIPAL',
            'activo' => true,
            'fecha_asignacion' => now(),
            'observaciones' => 'Usuario principal (alta OAuth)',
        ]);

        return $user->fresh(User::eagerLodable());
    }

    private function syncAccountAvatar(OauthAccount $account, ?string $avatar): void
    {
        if ($avatar && $account->avatar !== $avatar) {
            $account->avatar = $avatar;
            $account->save();
        }
    }

    public function frontendCallbackUrl(array $query = [], ?string $fragment = null): string
    {
        $base = rtrim((string) config('services.oauth.frontend_callback'), '/');
        if ($base === '') {
            $base = rtrim((string) config('services.frontend.url'), '/') . '/auth/callback';
        }

        $url = $base;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        if ($fragment) {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }

    /**
     * Fragmento seguro para el PWA (token no viaja en Referer de query).
     */
    public function successFragment(string $token, bool $pendingRegistro): string
    {
        $parts = [
            'token=' . rawurlencode($token),
            'pending_registro=' . ($pendingRegistro ? '1' : '0'),
        ];

        return implode('&', $parts);
    }
}
