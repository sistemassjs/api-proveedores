<?php

namespace App\Exceptions;

use App\Services\AuditService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
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
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso no encontrado',
                    'error_code' => 'RESOURCE_NOT_FOUND',
                ], 404);
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $e->validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint no encontrado',
                    'error_code' => 'ENDPOINT_NOT_FOUND',
                ], 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método HTTP no permitido para este endpoint',
                    'error_code' => 'METHOD_NOT_ALLOWED',
                ], 405);
            }

            // Error genérico del servidor
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
}
