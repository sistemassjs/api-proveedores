<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateProveedorRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $proveedor = $request->route('proveedor');
        $proveedorId = is_object($proveedor) ? ($proveedor->id ?? null) : $proveedor;
        $roleNombre = (is_numeric($proveedorId) && method_exists($user, 'nombreRolEnProveedor'))
            ? $user->nombreRolEnProveedor((int) $proveedorId)
            : $user->role?->nombre;

        if ($roleNombre === 'ADMINISTRADOR') {
            return $next($request);
        }

        if (! in_array($roleNombre, $roles, true)) {
            return response()->json(['error' => 'No tienes permisos suficientes'], 403);
        }

        return $next($request);
    }
}
