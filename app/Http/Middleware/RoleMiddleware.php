<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\Auth\UnauthorizedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Control de acceso basado en roles.
     * En rutas con {proveedor}, el rol es el de esa membresía.
     *
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new UnauthorizedException('No hay un usuario autenticado.');
        }

        $userRole = $this->roleNombre($request, $user);
        $permitidos = array_map('strtolower', $roles);

        if ($userRole === null || ! in_array(strtolower($userRole), $permitidos, true)) {
            throw new UnauthorizedException(
                'El usuario no tiene el rol requerido. Rol actual: '.($userRole ?? 'desconocido').'. Roles permitidos: '.implode(', ', $roles).'.'
            );
        }

        return $next($request);
    }

    private function roleNombre(Request $request, $user): ?string
    {
        $proveedor = $request->route('proveedor');
        $proveedorId = is_object($proveedor) ? ($proveedor->id ?? null) : $proveedor;

        if (is_numeric($proveedorId) && (int) $proveedorId > 0 && method_exists($user, 'nombreRolEnProveedor')) {
            $nombre = $user->nombreRolEnProveedor((int) $proveedorId);
            if (is_string($nombre) && $nombre !== '') {
                return $nombre;
            }
        }

        return $user->role?->nombre;
    }
}
