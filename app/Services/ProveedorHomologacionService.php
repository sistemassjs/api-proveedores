<?php

namespace App\Services;

use App\Models\CuentaBancaria;
use App\Models\Notificacion;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\SolicitudPago;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\UserProveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para homologación de proveedores duplicados
 * Maneja la lógica compleja de reasignación de usuarios y actualización de relaciones
 */
class ProveedorHomologacionService
{
    /**
     * Homologar proveedores duplicados consolidando usuarios y relaciones
     *
     * @param array $proveedorIds IDs de los proveedores a homologar
     * @param bool $eliminarProveedoresOrigen Si se deben eliminar los proveedores origen
     * @return array Resultado de la homologación
     */
    public function homologarProveedores(array $proveedorIds, bool $eliminarProveedoresOrigen = true): array
    {
        return DB::transaction(function () use ($proveedorIds, $eliminarProveedoresOrigen) {
            // Obtener proveedores ordenados por fecha de creación (el más antiguo primero)
            $proveedores = Proveedor::whereIn('id', $proveedorIds)
                ->with(['users', 'userProveedores'])
                ->orderBy('created_at', 'asc')
                ->get();

            if ($proveedores->count() < 2) {
                throw new \Exception('Se requieren al menos 2 proveedores para homologar');
            }

            // Proveedor destino (el más antiguo)
            $proveedorDestino = $proveedores->first();
            $proveedoresOrigen = $proveedores->slice(1);

            $resultado = [
                'proveedor_destino_id' => $proveedorDestino->id,
                'proveedores_origen_ids' => $proveedoresOrigen->pluck('id')->toArray(),
                'usuarios_reasignados' => 0,
                'relaciones_actualizadas' => [],
                'notificaciones_generadas' => 0,
                'proveedores_eliminados' => 0,
            ];

            // Usuario principal del proveedor destino
            $usuarioPrincipalDestino = $proveedorDestino->usuarioPrincipal();

            // Roles disponibles para asignar (en orden de prioridad)
            $rolesDisponibles = $this->obtenerRolesParaAsignacion();

            // Procesar cada proveedor origen
            foreach ($proveedoresOrigen as $proveedorOrigen) {
                // Reasignar usuarios
                $usuariosReasignados = $this->reasignarUsuariosDeProveedor(
                    $proveedorOrigen,
                    $proveedorDestino,
                    $rolesDisponibles
                );

                $resultado['usuarios_reasignados'] += $usuariosReasignados;

                // Actualizar todas las relaciones
                $relacionesActualizadas = $this->actualizarRelacionesProveedor(
                    $proveedorOrigen->id,
                    $proveedorDestino->id
                );

                foreach ($relacionesActualizadas as $modelo => $cantidad) {
                    if (!isset($resultado['relaciones_actualizadas'][$modelo])) {
                        $resultado['relaciones_actualizadas'][$modelo] = 0;
                    }
                    $resultado['relaciones_actualizadas'][$modelo] += $cantidad;
                }

                // Generar notificaciones para el usuario principal (comentado - tabla no existe)
                // if ($usuarioPrincipalDestino && $usuariosReasignados > 0) {
                //     $this->generarNotificacionHomologacion(
                //         $proveedorDestino,
                //         $proveedorOrigen,
                //         $usuarioPrincipalDestino,
                //         $usuariosReasignados
                //     );
                //     $resultado['notificaciones_generadas']++;
                // }
            }

            // Eliminar o dar de baja proveedores origen
            if ($eliminarProveedoresOrigen) {
                foreach ($proveedoresOrigen as $proveedorOrigen) {
                    // Verificar que no tenga relaciones pendientes críticas
                    if ($this->puedeEliminarProveedor($proveedorOrigen->id)) {
                        $proveedorOrigen->delete();
                        $resultado['proveedores_eliminados']++;
                    } else {
                        // Si no puede eliminarse, darlo de baja
                        $proveedorOrigen->update(['estatus' => 'inactivo']);
                    }
                }
            }

            Log::info('Homologación de proveedores completada', $resultado);

            return $resultado;
        });
    }

    /**
     * Reasignar usuarios de un proveedor origen a un proveedor destino
     */
    protected function reasignarUsuariosDeProveedor(
        Proveedor $proveedorOrigen,
        Proveedor $proveedorDestino,
        array $rolesDisponibles
    ): int {
        $usuariosReasignados = 0;
        $usuariosDestino = $proveedorDestino->users->pluck('id')->toArray();

        // Obtener usuarios del proveedor origen
        $userProveedores = UserProveedor::where('proveedor_id', $proveedorOrigen->id)
            ->with('user')
            ->get();

        foreach ($userProveedores as $index => $userProveedor) {
            $userId = $userProveedor->user_id;

            // Si el usuario ya existe en el proveedor destino, solo desactivar relación anterior
            if (in_array($userId, $usuariosDestino)) {
                $userProveedor->update([
                    'activo' => false,
                    'fecha_desasignacion' => now(),
                    'observaciones' => "Desactivado por homologación de proveedor {$proveedorOrigen->id} a {$proveedorDestino->id}",
                ]);
                continue;
            }

            // Determinar el rol a asignar
            $nuevoRol = $this->determinarRolParaUsuario($index, $rolesDisponibles);

            // Actualizar el rol del usuario si es necesario
            if ($nuevoRol && $userProveedor->user->role_id !== $nuevoRol['id']) {
                $userProveedor->user->update(['role_id' => $nuevoRol['id']]);
            }

            // Actualizar la relación pivot para apuntar al proveedor destino
            $userProveedor->update([
                'proveedor_id' => $proveedorDestino->id,
                'tipo_relacion' => $index === 0 ? 'PRINCIPAL' : 'SECUNDARIO',
                'fecha_asignacion' => now(),
                'observaciones' => "Reasignado desde proveedor {$proveedorOrigen->id} por homologación",
            ]);

            $usuariosReasignados++;
        }

        return $usuariosReasignados;
    }

    /**
     * Actualizar todas las relaciones de un proveedor origen a un proveedor destino
     */
    protected function actualizarRelacionesProveedor(int $proveedorOrigenId, int $proveedorDestinoId): array
    {
        $actualizaciones = [];

        // Solicitudes de pago (comentado - tabla puede no tener relación directa)
        // $actualizaciones['solicitudes_pago'] = SolicitudPago::where('proveedor_id', $proveedorOrigenId)
        //     ->update(['proveedor_id' => $proveedorDestinoId]);

        // Órdenes de compra (comentado - tabla puede no tener relación directa)
        // $actualizaciones['ordenes_compra'] = OrdenCompra::where('proveedor_id', $proveedorOrigenId)
        //     ->update(['proveedor_id' => $proveedorDestinoId]);

        // Cuentas bancarias
        $actualizaciones['cuentas_bancarias'] = CuentaBancaria::where('proveedor_id', $proveedorOrigenId)
            ->update(['proveedor_id' => $proveedorDestinoId]);

        // Sucursales
        $actualizaciones['sucursales'] = Sucursal::where('proveedor_id', $proveedorOrigenId)
            ->update(['proveedor_id' => $proveedorDestinoId]);

        // Productos
        $actualizaciones['productos'] = Producto::where('proveedor_id', $proveedorOrigenId)
            ->update(['proveedor_id' => $proveedorDestinoId]);

        // Notificaciones (comentado - tabla no existe actualmente)
        // $actualizaciones['notificaciones'] = Notificacion::where('proveedor_id', $proveedorOrigenId)
        //     ->update(['proveedor_id' => $proveedorDestinoId]);

        // Relaciones en otras tablas si existen
        $actualizaciones['categorias'] = DB::table('categorias')
            ->where('proveedor_id', $proveedorOrigenId)
            ->update(['proveedor_id' => $proveedorDestinoId]);

        $actualizaciones['marcas'] = DB::table('marcas')
            ->where('proveedor_id', $proveedorOrigenId)
            ->update(['proveedor_id' => $proveedorDestinoId]);

        $actualizaciones['unidades_medida'] = 0; // catálogo global: no se reasignan por proveedor

        return $actualizaciones;
    }

    /**
     * Generar notificación para el usuario principal del proveedor destino
     * COMENTADO: Tabla de notificaciones no existe actualmente
     */
    // protected function generarNotificacionHomologacion(
    //     Proveedor $proveedorDestino,
    //     Proveedor $proveedorOrigen,
    //     User $usuarioPrincipal,
    //     int $usuariosReasignados
    // ): void {
    //     $notificacion = Notificacion::create([
    //         'tipo' => 'homologacion_proveedor',
    //         'proveedor_id' => $proveedorDestino->id,
    //         'titulo' => 'Usuarios reasignados a tu empresa',
    //         'mensaje' => "Se han reasignado {$usuariosReasignados} usuario(s) desde '{$proveedorOrigen->razon_social}' a tu empresa por homologación de proveedores duplicados.",
    //         'data' => json_encode([
    //             'proveedor_destino_id' => $proveedorDestino->id,
    //             'proveedor_origen_id' => $proveedorOrigen->id,
    //             'usuarios_reasignados' => $usuariosReasignados,
    //             'action' => 'ver_usuarios',
    //             'url' => "/admin/proveedores/{$proveedorDestino->id}/usuarios",
    //         ]),
    //         'leida' => false,
    //     ]);
    //
    //     Log::info('Notificación de homologación generada', [
    //         'notificacion_id' => $notificacion->id,
    //         'proveedor_destino_id' => $proveedorDestino->id,
    //         'usuario_id' => $usuarioPrincipal->id,
    //     ]);
    // }

    /**
     * Obtener roles disponibles para asignación en orden de prioridad
     */
    protected function obtenerRolesParaAsignacion(): array
    {
        return DB::table('roles')
            ->whereIn('nombre', ['ADMINISTRADOR', 'SUPERVISOR', 'VENTAS', 'AUXILIAR'])
            ->orderByRaw("FIELD(nombre, 'ADMINISTRADOR', 'SUPERVISOR', 'VENTAS', 'AUXILIAR')")
            ->get()
            ->toArray();
    }

    /**
     * Determinar el rol a asignar a un usuario según su posición
     */
    protected function determinarRolParaUsuario(int $index, array $rolesDisponibles): ?array
    {
        if (empty($rolesDisponibles)) {
            return null;
        }

        // El primer usuario (principal) mantiene su rol o recibe ADMINISTRADOR
        if ($index === 0) {
            return (array) $rolesDisponibles[0] ?? null;
        }

        // El segundo usuario recibe SUPERVISOR o VENTAS
        if ($index === 1) {
            return (array) ($rolesDisponibles[1] ?? $rolesDisponibles[2] ?? null);
        }

        // Los demás reciben AUXILIAR
        return (array) end($rolesDisponibles);
    }

    /**
     * Verificar si un proveedor puede ser eliminado
     */
    protected function puedeEliminarProveedor(int $proveedorId): bool
    {
        // Verificar que no tenga usuarios activos
        $tieneUsuariosActivos = UserProveedor::where('proveedor_id', $proveedorId)
            ->where('activo', true)
            ->exists();

        if ($tieneUsuariosActivos) {
            return false;
        }

        // Verificar que todas las relaciones hayan sido migradas
        // $tieneSolicitudesPago = SolicitudPago::where('proveedor_id', $proveedorId)->exists();
        // $tieneOrdenesCompra = OrdenCompra::where('proveedor_id', $proveedorId)->exists();
        $tieneCuentasBancarias = CuentaBancaria::where('proveedor_id', $proveedorId)->exists();
        $tieneProductos = Producto::where('proveedor_id', $proveedorId)->exists();

        return !($tieneCuentasBancarias || $tieneProductos);
    }

    /**
     * Previsualizar la homologación sin ejecutarla
     */
    public function previsualizarHomologacion(array $proveedorIds): array
    {
        // Obtener proveedores con sus usuarios y relaciones
        $proveedores = Proveedor::whereIn('id', $proveedorIds)
            ->with(['users.role'])
            ->withCount([
                'users',
                'cuentasBancarias',
                'productos',
                'sucursales',
                'categorias',
                'marcas',
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        $unidadesGlobales = DB::table('unidad_medidas')->count();

        if ($proveedores->count() < 2) {
            throw new \Exception('Se requieren al menos 2 proveedores para homologar');
        }

        $proveedorDestino = $proveedores->first();
        $proveedoresAEliminar = $proveedores->slice(1);

        // Obtener roles disponibles
        $rolesDisponibles = $this->obtenerRolesParaAsignacion();

        return [
            'proveedor_destino' => [
                'id' => $proveedorDestino->id,
                'razon_social' => $proveedorDestino->razon_social,
                'nombre_comercial' => $proveedorDestino->nombre_comercial,
                'rfc' => $proveedorDestino->rfc,
                'created_at' => $proveedorDestino->created_at,
                'recursos' => [
                    'usuarios' => $proveedorDestino->users_count,
                    'cuentas_bancarias' => $proveedorDestino->cuentas_bancarias_count,
                    'productos' => $proveedorDestino->productos_count,
                    'sucursales' => $proveedorDestino->sucursales_count,
                    'categorias' => $proveedorDestino->categorias_count,
                    'marcas' => $proveedorDestino->marcas_count,
                    'unidades_medida' => $unidadesGlobales,
                ],
            ],
            'proveedores_a_eliminar' => $proveedoresAEliminar->pluck('id')->toArray(),
            'proveedores_detalle' => $proveedoresAEliminar->map(fn($p) => [
                'id' => $p->id,
                'razon_social' => $p->razon_social,
                'nombre_comercial' => $p->nombre_comercial,
                'rfc' => $p->rfc,
                'created_at' => $p->created_at,
                'recursos' => [
                    'usuarios' => $p->users_count,
                    'cuentas_bancarias' => $p->cuentas_bancarias_count,
                    'productos' => $p->productos_count,
                    'sucursales' => $p->sucursales_count,
                    'categorias' => $p->categorias_count,
                    'marcas' => $p->marcas_count,
                    'unidades_medida' => 0,
                ],
                'puede_eliminar' => $this->puedeEliminarProveedor($p->id),
            ]),
            'usuarios' => $proveedores->flatMap(function ($proveedor) use ($proveedorDestino, $rolesDisponibles) {
                return $proveedor->users->map(function ($user, $index) use ($proveedor, $proveedorDestino, $rolesDisponibles) {
                    // Determinar rol sugerido
                    $rolSugerido = $this->determinarRolParaUsuario($index, $rolesDisponibles);

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ? $user->role->nombre : null,
                        'role_id' => $user->role_id,
                        'is_main' => $user->pivot->tipo_relacion === 'PRINCIPAL',
                        'proveedor_actual_id' => $proveedor->id,
                        'proveedor_actual' => $proveedor->razon_social,
                        'sera_reasignado' => $proveedor->id !== $proveedorDestino->id,
                        'proveedor_destino_id' => $proveedorDestino->id,
                        'rol_sugerido' => $rolSugerido ? $rolSugerido['nombre'] : null,
                        'rol_sugerido_id' => $rolSugerido ? $rolSugerido['id'] : null,
                    ];
                });
            }),
            'roles_disponibles' => collect($rolesDisponibles)->map(fn($rol) => [
                'id' => $rol->id,
                'nombre' => $rol->nombre,
            ])->toArray(),
            'totales' => [
                'total_usuarios' => $proveedores->sum('users_count'),
                'usuarios_a_reasignar' => $proveedoresAEliminar->sum('users_count'),
                'cuentas_bancarias' => $proveedoresAEliminar->sum('cuentas_bancarias_count'),
                'productos' => $proveedoresAEliminar->sum('productos_count'),
                'sucursales' => $proveedoresAEliminar->sum('sucursales_count'),
                'categorias' => $proveedoresAEliminar->sum('categorias_count'),
                'marcas' => $proveedoresAEliminar->sum('marcas_count'),
                'unidades_medida' => 0,
            ],
        ];
    }
}
