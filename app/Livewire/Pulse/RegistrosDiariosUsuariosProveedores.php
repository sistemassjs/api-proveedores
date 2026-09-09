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
class RegistrosDiariosUsuariosProveedores extends Card
{
    public function render(): Renderable
    {
        [[$series], $time, $runAt] = $this->remember(function () {
            $usuariosPorDia = User::query()
                ->paraMetricasPlataforma()
                ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                ->whereNotNull('created_at')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('fecha')
                ->get()
                ->keyBy('fecha');

            $proveedoresPorDia = Proveedor::query()
                ->withoutGlobalScopes()
                ->paraMetricasPlataforma()
                ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                ->whereNotNull('created_at')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('fecha')
                ->get()
                ->keyBy('fecha');

            $serie = Collection::times(14, function (int $index) use ($usuariosPorDia, $proveedoresPorDia) {
                $fecha = CarbonImmutable::today()->subDays(13 - $index)->toDateString();

                return (object) [
                    'fecha' => $fecha,
                    'usuarios' => (int) ($usuariosPorDia->get($fecha)->total ?? 0),
                    'proveedores' => (int) ($proveedoresPorDia->get($fecha)->total ?? 0),
                ];
            });

            return [$serie];
        });

        return View::make('livewire.pulse.registros-diarios-usuarios-proveedores', [
            'series' => $series,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
