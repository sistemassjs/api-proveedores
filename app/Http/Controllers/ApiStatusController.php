<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Estado / saludo público de la API.
 * Para publicar una release: subir VERSION y desplegar; verificar en GET /api/status.
 */
class ApiStatusController extends Controller
{
    /** Versión de la API (actualizar a mano en cada release). */
    public const VERSION = '1.0.3';

    public function __invoke(): JsonResponse
    {
        return $this->success([
            'name' => config('app.name'),
            'service' => 'api-proveedores',
            'version' => self::VERSION,
        ], 'API operativa.');
    }
}
