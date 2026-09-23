<?php

namespace App\Exceptions;

use App\Services\AuditService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Log de errores con AuditService
            AuditService::logError(
                $e->getMessage(),
                get_class($e),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'user_id' => Auth::check() ? Auth::id() : null,
                ]
            );
        });
    }

    public function render($request, Throwable $e)
    {
        // Respuestas personalizadas para API
        if ($request->expectsJson()) {
            if ($e instanceof AuthenticationException) {
                $this->logApiException($e, 'UNAUTHENTICATED', 401);

                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }

            if ($e instanceof ModelNotFoundException) {
                $this->logApiException($e, 'RESOURCE_NOT_FOUND', 404);

                return response()->json([
                    'success' => false,
                    'message' => 'Recurso no encontrado',
                    'error_code' => 'RESOURCE_NOT_FOUND',
                ], 404);
            }

            if ($e instanceof ValidationException) {
                Log::warning('API Validation Error', $this->apiExceptionContext($e, 'VALIDATION_ERROR', 422) + [
                    'errors' => $e->validator->errors()->toArray(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $e->validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            if ($e instanceof NotFoundHttpException) {
                $this->logApiException($e, 'ENDPOINT_NOT_FOUND', 404);

                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint no encontrado',
                    'error_code' => 'ENDPOINT_NOT_FOUND',
                ], 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                $this->logApiException($e, 'METHOD_NOT_ALLOWED', 405, [
                    'allowed_methods' => $e->getHeaders()['Allow'] ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Método HTTP no permitido para este endpoint',
                    'error_code' => 'METHOD_NOT_ALLOWED',
                ], 405);
            }

            // Error genérico del servidor (también HttpException no tipados arriba)
            $this->logApiException($e, 'INTERNAL_SERVER_ERROR', 500);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Error interno del servidor'
                    : $e->getMessage(),
                'error_code' => 'INTERNAL_SERVER_ERROR',
            ], 500);
        }

        return parent::render($request, $e);
    }

    /**
     * Fuerza log en laravel.log aunque la excepción esté en dontReport (ej. 405).
     */
    private function logApiException(Throwable $e, string $errorCode, int $status, array $extra = []): void
    {
        $level = $status >= 500 ? 'error' : 'warning';

        Log::{$level}('API Exception', $this->apiExceptionContext($e, $errorCode, $status) + $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function apiExceptionContext(Throwable $e, string $errorCode, int $status): array
    {
        return [
            'error_code' => $errorCode,
            'http_status' => $status,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'route' => request()->route()?->getName() ?? request()->path(),
            'user_id' => Auth::check() ? Auth::id() : null,
            'ip' => request()->ip(),
            'x_client_app' => request()->header('X-Client-App'),
            'trace' => collect(explode("\n", $e->getTraceAsString()))->take(15)->implode("\n"),
        ];
    }
}
