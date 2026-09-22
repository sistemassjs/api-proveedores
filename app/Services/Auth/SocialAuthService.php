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
use App\Support\ClientApp;
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
     * State OAuth firmado (app de origen para volver a la PWA correcta).
     *
     * @param  array{app?: string}  $payload
     */
    public function encodeOAuthState(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $body, $this->oauthStateKey());

        return $body.'.'.$sig;
    }

    /**
     * @return array{app: ?string, n?: string}
     */
    public function decodeOAuthState(?string $state, bool $allowDefault = true): array
    {
        $default = [
            'app' => $allowDefault ? ClientApp::normalize(null) : null,
        ];

        if ($state === null || $state === '') {
            return $default;
        }

        $state = str_replace(' ', '+', $state);

        $parts = explode('.', $state, 2);
        if (count($parts) !== 2) {
            return $default;
        }

        [$body, $sig] = $parts;
        $expected = hash_hmac('sha256', $body, $this->oauthStateKey());
        if (! hash_equals($expected, $sig)) {
            return $default;
        }

        $pad = 4 - (strlen($body) % 4);
        if ($pad < 4) {
            $body .= str_repeat('=', $pad);
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if ($json === false) {
            return $default;
        }

        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $default;
        }

        if (! is_array($data) || ! isset($data['app'])) {
            return $default;
        }

        $out = [
            'app' => ClientApp::normalize($data['app']),
        ];
        if (! empty($data['n']) && is_string($data['n'])) {
            $out['n'] = $data['n'];
        }

        return $out;
    }

    /**
     * @param  bool  $allowDefault  Si false y el state es inválido → null (para fallback).
     */
    public function appKeyFromOAuthState(?string $state, bool $allowDefault = true): ?string
    {
        return $this->decodeOAuthState($state, $allowDefault)['app'] ?? null;
    }

    private function oauthStateKey(): string
    {
        return (string) config('app.key', 'oauth-state');
    }

    /**
     * Arma URL de callback front (base ya resuelta por app).
     *
     * @param  array<string, scalar>  $query
     */
    public function buildFrontendCallback(
        string $callbackBase,
        array $query = [],
        ?string $fragment = null
    ): string {
        $url = rtrim($callbackBase, '/');
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }
        if ($fragment) {
            $url .= '#'.ltrim($fragment, '#');
        }

        return $url;
    }

    /**
     * URL de callback en la PWA indicada (gestion|nexprov).
     */
    public function frontendCallbackUrl(
        array $query = [],
        ?string $fragment = null,
        ?string $appKey = null
    ): string {
        $base = ClientApp::frontendPathFor($appKey, 'auth/callback');

        return $this->buildFrontendCallback($base, $query, $fragment);
    }

    /**
     * Resuelve el usuario local desde el perfil OAuth: cuenta vinculada,
     * email existente (auto-vínculo) o alta GERENTE + proveedor stub.
     * Otorga acceso a la app cliente indicada (gestion|nexprov).
     *
     * @return array{
     *     user: User,
     *     token: string,
     *     proveedor: mixed,
     *     pending_registro: bool
     * }
     */
    public function resolveAuthenticatedUser(
        string $provider,
        SocialiteUser $socialUser,
        ?string $appKey = null
    ): array {
        $provider = strtolower($provider);
        $providerId = (string) $socialUser->getId();
        $email = strtolower(trim((string) $socialUser->getEmail()));
        $appKey = ClientApp::normalize($appKey);

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

        // Google valida posesión del email → otorga la app que inició el OAuth.
        $user->grantClientApp($appKey);

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

    /**
     * Fragmento seguro para el PWA (token no viaja en Referer de query).
     */
    public function successFragment(string $token, bool $pendingRegistro): string
    {
        $parts = [
            'token='.rawurlencode($token),
            'pending_registro='.($pendingRegistro ? '1' : '0'),
        ];

        return implode('&', $parts);
    }
}
