<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialAuthService;
use App\Support\ClientApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    private const CACHE_PREFIX = 'oauth.client_app.';

    public function __construct(
        private readonly SocialAuthService $socialAuth
    ) {}

    /**
     * Inicia OAuth. Query `app` se guarda en cache + state (nonce) para volver a la PWA correcta.
     */
    public function redirect(Request $request, string $provider): Response|RedirectResponse
    {
        $provider = strtolower($provider);
        $appKey = ClientApp::normalize($request->query('app'));
        $callbackBase = ClientApp::frontendPathFor($appKey, 'auth/callback');

        if (! $this->socialAuth->isProviderEnabled($provider)) {
            return redirect()->away(
                $this->socialAuth->buildFrontendCallback($callbackBase, [
                    'error' => 'provider_no_soportado',
                    'message' => 'Proveedor de autenticación no disponible.',
                ])
            );
        }

        $nonce = bin2hex(random_bytes(24));
        Cache::put(self::CACHE_PREFIX.$nonce, [
            'app' => $appKey,
            'callback' => $callbackBase,
        ], now()->addMinutes(20));

        // State firmado de respaldo (si el cache se pierde) + nonce para lookup.
        $state = $this->socialAuth->encodeOAuthState([
            'app' => $appKey,
            'n' => $nonce,
        ]);

        Log::info('OAuth redirect', [
            'provider' => $provider,
            'app' => $appKey,
            'callback' => $callbackBase,
        ]);

        return Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Callback OAuth → token → redirect a la PWA registrada en cache/state.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $provider = strtolower($provider);
        [$appKey, $callbackBase] = $this->resolveAppAndCallback($request);

        if (! $this->socialAuth->isProviderEnabled($provider)) {
            return redirect()->away(
                $this->socialAuth->buildFrontendCallback($callbackBase, [
                    'error' => 'provider_no_soportado',
                    'message' => 'Proveedor de autenticación no disponible.',
                ])
            );
        }

        if ($request->filled('error')) {
            return redirect()->away(
                $this->socialAuth->buildFrontendCallback($callbackBase, [
                    'error' => (string) $request->query('error'),
                    'message' => 'Inicio de sesión con '.$provider.' cancelado o rechazado.',
                ])
            );
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $auth = $this->socialAuth->resolveAuthenticatedUser($provider, $socialUser, $appKey);
            $fragment = $this->socialAuth->successFragment(
                $auth['token'],
                $auth['pending_registro']
            );

            $target = $this->socialAuth->buildFrontendCallback($callbackBase, [], $fragment);

            Log::info('OAuth callback OK', [
                'provider' => $provider,
                'app' => $appKey,
                'target' => $callbackBase,
            ]);

            return redirect()->away($target);
        } catch (\Throwable $e) {
            Log::error('Error en callback OAuth', [
                'provider' => $provider,
                'app' => $appKey,
                'message' => $e->getMessage(),
            ]);

            return redirect()->away(
                $this->socialAuth->buildFrontendCallback($callbackBase, [
                    'error' => 'oauth_fallido',
                    'message' => $e->getMessage() ?: 'No se pudo completar el inicio de sesión social.',
                ])
            );
        }
    }

    /**
     * @return array{0: string, 1: string} [appKey, callbackBaseUrl]
     */
    private function resolveAppAndCallback(Request $request): array
    {
        $state = $request->query('state');
        $decoded = $this->socialAuth->decodeOAuthState(
            is_string($state) ? $state : null,
            false
        );

        $nonce = is_array($decoded) ? ($decoded['n'] ?? null) : null;
        if (is_string($nonce) && $nonce !== '') {
            $cached = Cache::pull(self::CACHE_PREFIX.$nonce);
            if (is_array($cached) && ! empty($cached['callback'])) {
                return [
                    ClientApp::normalize($cached['app'] ?? null),
                    rtrim((string) $cached['callback'], '/'),
                ];
            }
        }

        $appKey = is_array($decoded) && ! empty($decoded['app'])
            ? ClientApp::normalize($decoded['app'])
            : ClientApp::normalize(null);

        return [
            $appKey,
            ClientApp::frontendPathFor($appKey, 'auth/callback'),
        ];
    }
}
