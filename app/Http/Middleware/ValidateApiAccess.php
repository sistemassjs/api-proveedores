<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\Auth\UnauthorizedProveedorAccessException;
use App\Support\UserCuentaEstado;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Middleware para validar el acceso a la API con rate limiting
 * basado en roles y contexto de proveedor.
 */
class ValidateApiAccess
{
    /**
     * Rate limits por rol (requests por minuto)
     */
    private const RATE_LIMITS = [
        'ADMINISTRADOR' => 1000,
        'GERENTE' => 500,
        'SUPERVISOR' => 300,
        'VENTAS' => 200,
        'AUXILIAR' => 100,
        'default' => 60,
    ];

    /**
     * Endpoints que requieren validación especial
     */
    private const SENSITIVE_ENDPOINTS = [
        'proveedores/*/users',
        'proveedores/*/productos/import',
        'auth/*',
        'admin/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     *
     * @throws UnauthorizedProveedorAccessException
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar autenticación
        if (! Auth::check()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Token de autenticación requerido.',
                'error_code' => 'AUTHENTICATION_REQUIRED',
            ], 401);
        }

        $user = Auth::user();
        $userId = $user->id;

        $cuentaCheck = UserCuentaEstado::assertCanAuthenticate($user);
        if (! $cuentaCheck['ok']) {
            return response()->json([
                'status' => 'ERROR',
                'code' => 403,
                'message' => $cuentaCheck['message'],
                'data' => null,
                'errors' => ['codigo' => $cuentaCheck['codigo']],
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }

        // Aplicar rate limiting
        $this->applyRateLimiting($request, $user);

        // Validar acceso a endpoints sensibles
        if ($this->isSensitiveEndpoint($request)) {
            $this->validateSensitiveAccess($request, $user);
        }

        // Registrar acceso para auditoría
        $this->logApiAccess($request, $user);

        return $next($request);
    }

    /**
     * Aplica rate limiting basado en el rol del usuario.
     *
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    private function applyRateLimiting(Request $request, $user): void
    {
        $roleName = $user->role ? $user->role->name : 'default';
        $maxAttempts = static::RATE_LIMITS[$roleName] ?? static::RATE_LIMITS['default'];

        $key = $this->getRateLimitKey($request, $user);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            // Log del rate limit alcanzado
            Log::warning('API Rate Limit Exceeded', [
                'user_id' => $user->id,
                'role' => $roleName,
                'ip' => $request->ip(),
                'endpoint' => $request->fullUrl(),
                'retry_after' => $retryAfter,
            ]);

            abort(429, "Demasiadas solicitudes. Inténtalo de nuevo en {$retryAfter} segundos.");
        }

        RateLimiter::hit($key, 60); // 60 segundos de ventana
    }

    /**
     * Genera la clave para rate limiting.
     */
    private function getRateLimitKey(Request $request, $user): string
    {
        $roleName = $user->role ? $user->role->name : 'default';

        // Combinar user ID, rol e IP para mayor granularidad
        return sprintf(
            'api_access:%s:%s:%s',
            $user->id,
            $roleName,
            $request->ip()
        );
    }

    /**
     * Verifica si el endpoint es sensible y requiere validación especial.
     */
    private function isSensitiveEndpoint(Request $request): bool
    {
        $path = $request->path();

        foreach (static::SENSITIVE_ENDPOINTS as $pattern) {
            // Convertir el patrón a regex
            $regex = str_replace('*', '.*', $pattern);

            if (preg_match("#^{$regex}$#", $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida el acceso a endpoints sensibles.
     *
     * @throws UnauthorizedProveedorAccessException
     */
    private function validateSensitiveAccess(Request $request, $user): void
    {
        $roleName = $user->role ? $user->role->name : null;
        $path = $request->path();

        // Validaciones específicas por endpoint
        if (str_contains($path, 'proveedores/') && str_contains($path, '/users')) {
            // Solo usuarios con permisos administrativos pueden gestionar usuarios
            if (! in_array($roleName, ['ADMINISTRADOR', 'GERENTE'])) {
                // Verificar si es usuario principal del proveedor
                $proveedorId = $this->extractProveedorIdFromPath($path);
                if ($proveedorId && ! $this->isMainUserOfProveedor($user->id, $proveedorId)) {
                    throw UnauthorizedProveedorAccessException::insufficientPermissions('gestión de usuarios');
                }
            }
        }

        if (str_contains($path, 'import')) {
            // Importaciones requieren permisos especiales
            if (! in_array($roleName, ['ADMINISTRADOR', 'GERENTE', 'SUPERVISOR'])) {
                throw UnauthorizedProveedorAccessException::insufficientPermissions('importación de datos');
            }
        }

        if (str_contains($path, 'admin/')) {
            // Área administrativa solo para administradores
            if ($roleName !== 'ADMINISTRADOR') {
                throw UnauthorizedProveedorAccessException::insufficientPermissions('acceso administrativo');
            }
        }
    }

    /**
     * Extrae el ID del proveedor de la ruta.
     */
    private function extractProveedorIdFromPath(string $path): ?int
    {
        if (preg_match('/proveedores\/(\d+)/', $path, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Verifica si el usuario es principal de un proveedor.
     */
    private function isMainUserOfProveedor(int $userId, int $proveedorId): bool
    {
        $cacheKey = "main_user_check_{$userId}_{$proveedorId}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $proveedorId) {
            return \App\Models\UserProveedor::where('user_id', $userId)
                ->where('proveedor_id', $proveedorId)
                ->where('tipo_relacion', 'PRINCIPAL')
                ->where('activo', true)
                ->exists();
        });
    }

    /**
     * Registra el acceso a la API para auditoría.
     */
    private function logApiAccess(Request $request, $user): void
    {
        // Solo registrar accesos a endpoints sensibles o con alta frecuencia
        if ($this->shouldLogAccess($request)) {
            $logData = [
                'user_id' => $user->id,
                'role' => $user->role ? $user->role->name : null,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ];

            // Log a archivo específico para análisis posterior
            // Log::channel('api_access')->info('API Access', $logData);
            Log::info('API Access', $logData);
        }
    }

    /**
     * Determina si se debe registrar el acceso.
     */
    private function shouldLogAccess(Request $request): bool
    {
        // Siempre registrar endpoints sensibles
        if ($this->isSensitiveEndpoint($request)) {
            return true;
        }

        // Para otros endpoints, registrar solo algunos métodos
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }
}
