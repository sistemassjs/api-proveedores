<?php

namespace App\Services\Proveedor;

use App\Enums\EstadoUsuario;
use App\Enums\UserRoleEnumerate;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\UserProveedor;
use App\Services\Auth\ProveedorAccessService;
use Illuminate\Support\Facades\DB;

class ProveedorRestriccionUsuariosService
{
    public const ESTATUS_RESTRICTIVOS = ['bloqueado', 'suspendido'];

    public function __construct(
        private ProveedorAccessService $proveedorAccessService
    ) {}

    public function esEstatusRestrictivo(?string $estatus): bool
    {
        return $estatus !== null && in_array($estatus, self::ESTATUS_RESTRICTIVOS, true);
    }

    /**
     * @return array{usuarios_afectados: int, mensaje_confirmacion: string}
     */
    public function previewImpacto(Proveedor $proveedor, string $estatusDestino): array
    {
        $n = $this->contarUsuariosAfectables($proveedor);
        $etiqueta = $estatusDestino === 'bloqueado' ? 'bloqueada' : 'suspendida';

        $mensaje = $n === 0
            ? "¿Marcar la empresa como {$etiqueta}? No hay usuarios vinculados activos (excl. administradores globales)."
            : sprintf(
                '¿Marcar la empresa como %s? Se desactivará el vínculo de %d usuario(s) con esta empresa, se cerrarán sus sesiones activas',
                $etiqueta,
                $n
            ).' y, si no tienen otra empresa activa, su cuenta quedará '.$estatusDestino.'.';

        return [
            'usuarios_afectados' => $n,
            'mensaje_confirmacion' => $mensaje,
        ];
    }

    /**
     * Aplica restricción al pasar la empresa a bloqueado/suspendido.
     *
     * @return array<string, mixed>|null Resumen de la operación o null si no aplica.
     */
    public function aplicarTrasCambioEstatus(Proveedor $proveedor, ?string $estatusAnterior): ?array
    {
        $nuevo = $proveedor->estatus;

        if (! $this->esEstatusRestrictivo($nuevo)) {
            return null;
        }

        if ($estatusAnterior === $nuevo) {
            return null;
        }

        return $this->aplicarRestriccion($proveedor, $nuevo);
    }

    public function contarUsuariosAfectables(Proveedor $proveedor): int
    {
        return $this->queryPivotsActivosNoAdmin($proveedor->id)->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function aplicarRestriccion(Proveedor $proveedor, string $estatusProveedor): array
    {
        $estadoUsuario = $this->mapEstatusProveedorAUsuario($estatusProveedor);

        $pivots = $this->queryPivotsActivosNoAdmin($proveedor->id)
            ->with(['user.role'])
            ->get();

        $usuariosProcesados = 0;
        $cuentasRestringidas = 0;
        $soloVinculo = 0;
        $tokensRevocados = 0;

        DB::transaction(function () use (
            $pivots,
            $proveedor,
            $estadoUsuario,
            &$usuariosProcesados,
            &$cuentasRestringidas,
            &$soloVinculo,
            &$tokensRevocados
        ) {
            foreach ($pivots as $pivot) {
                /** @var UserProveedor $pivot */
                $user = $pivot->user;
                if (! $user || $this->esAdministradorGlobal($user)) {
                    continue;
                }

                $pivot->update([
                    'activo' => false,
                    'fecha_desasignacion' => now(),
                    'estado' => $estadoUsuario,
                ]);

                $otrosActivos = UserProveedor::query()
                    ->where('user_id', $user->id)
                    ->where('proveedor_id', '!=', $proveedor->id)
                    ->where('activo', true)
                    ->count();

                $deleted = $user->tokens()->delete();
                $tokensRevocados += (int) $deleted;

                if ($otrosActivos === 0) {
                    $user->update(['status' => $estadoUsuario]);
                    $cuentasRestringidas++;
                } else {
                    $soloVinculo++;
                }

                $this->proveedorAccessService->clearUserAccessCache($user->id);
                $usuariosProcesados++;
            }

            $this->proveedorAccessService->clearProveedorAccessCache($proveedor->id);
        });

        $mensaje = sprintf(
            'Empresa actualizada. %d usuario(s) afectado(s): %d con cuenta %s y %d solo desvinculado(s) de esta empresa. Sesiones cerradas.',
            $usuariosProcesados,
            $cuentasRestringidas,
            $estatusProveedor,
            $soloVinculo
        );

        return [
            'usuarios_procesados' => $usuariosProcesados,
            'cuentas_restringidas' => $cuentasRestringidas,
            'solo_desvinculados' => $soloVinculo,
            'tokens_revocados' => $tokensRevocados,
            'mensaje' => $mensaje,
        ];
    }

    private function mapEstatusProveedorAUsuario(string $estatusProveedor): string
    {
        return match ($estatusProveedor) {
            'bloqueado' => EstadoUsuario::BLOQUEADO->value,
            'suspendido' => EstadoUsuario::SUSPENDIDO->value,
            default => EstadoUsuario::SUSPENDIDO->value,
        };
    }

    private function queryPivotsActivosNoAdmin(int $proveedorId)
    {
        return UserProveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->where('activo', true)
            ->whereHas('user', function ($q) {
                $q->whereHas('role', function ($roleQ) {
                    $roleQ->where('nombre', '!=', UserRoleEnumerate::ADMINISTRADOR->value);
                });
            });
    }

    private function esAdministradorGlobal(User $user): bool
    {
        return $user->isAdmin();
    }
}
