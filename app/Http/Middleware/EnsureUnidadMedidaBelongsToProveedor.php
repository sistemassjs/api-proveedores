<?php

namespace App\Http\Middleware;

use App\Models\UnidadMedida;
use Closure;
use Illuminate\Http\Request;

/**
 * Resuelve la unidad de medida de la ruta (catálogo global).
 * Ya no valida pertenencia a proveedor.
 */
class EnsureUnidadMedidaBelongsToProveedor
{
    public function handle(Request $request, Closure $next)
    {
        $unidadMedida = $request->route('unidad');

        if ($unidadMedida !== null && is_numeric($unidadMedida)) {
            $request->attributes->set(
                'unidad',
                UnidadMedida::findOrFail($unidadMedida)
            );
        }

        return $next($request);
    }
}
