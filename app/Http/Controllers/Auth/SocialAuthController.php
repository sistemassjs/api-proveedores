<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $socialAuth
    ) {}

    /**
     * Inicia OAuth (redirect a Google u otro provider habilitado).
     */
    public function redirect(string $provider): Response|RedirectResponse
    {
        $provider = strtolower($provider);

        if (! $this->socialAuth->isProviderEnabled($provider)) {
            return redirect()->away(
                $this->socialAuth->frontendCallbackUrl([
                    'error' => 'provider_no_soportado',
                    'message' => 'Proveedor de autenticación no disponible.',
                ])
            );
        }

        return Socialite::driver($provider)
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Callback OAuth → Sanctum token → redirect al PWA.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $provider = strtolower($provider);

        if (! $this->socialAuth->isProviderEnabled($provider)) {
            return redirect()->away(
                $this->socialAuth->frontendCallbackUrl([
                    'error' => 'provider_no_soportado',
                    'message' => 'Proveedor de autenticación no disponible.',
                ])
            );
        }

        if ($request->filled('error')) {
            return redirect()->away(
                $this->socialAuth->frontendCallbackUrl([
                    'error' => (string) $request->query('error'),
                    'message' => 'Inicio de sesión con ' . $provider . ' cancelado o rechazado.',
                ])
            );
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $auth = $this->socialAuth->resolveAuthenticatedUser($provider, $socialUser);
            $fragment = $this->socialAuth->successFragment(
                $auth['token'],
                $auth['pending_registro']
            );

            return redirect()->away(
                $this->socialAuth->frontendCallbackUrl([], $fragment)
            );
        } catch (\Throwable $e) {
            Log::error('Error en callback OAuth', [
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return redirect()->away(
                $this->socialAuth->frontendCallbackUrl([
                    'error' => 'oauth_fallido',
                    'message' => $e->getMessage() ?: 'No se pudo completar el inicio de sesión social.',
                ])
            );
        }
    }
}
