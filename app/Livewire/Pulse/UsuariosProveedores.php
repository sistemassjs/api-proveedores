<?php

namespace App\Livewire\Pulse;

use App\Models\Proveedor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class UsuariosProveedores extends Card
{
    public function render(): Renderable
    {
        [[$usuariosPorDia, $usuariosTotal, $proveedoresTotal], $time, $runAt] = $this->remember(function () {
            $usuariosPorDia = User::query()
                ->paraMetricasPlataforma()
                ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                ->whereNotNull('created_at')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('fecha')
                ->get()
                ->keyBy('fecha');

            $serieCompleta = Collection::times(14, function (int $index) use ($usuariosPorDia) {
                $fecha = CarbonImmutable::today()->subDays(13 - $index)->toDateString();
                $fila = $usuariosPorDia->get($fecha);

                return (object) [
                    'fecha' => $fecha,
                    'total' => (int) ($fila->total ?? 0),
                ];
            });

            return [
                $serieCompleta,
                User::query()->paraMetricasPlataforma()->count(),
                Proveedor::query()->withoutGlobalScopes()->paraMetricasPlataforma()->count(),
            ];
        });

        return View::make('livewire.pulse.usuarios-proveedores', [
            'usuariosPorDia' => $usuariosPorDia,
            'usuariosTotal' => $usuariosTotal,
            'proveedoresTotal' => $proveedoresTotal,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
