<?php

namespace App\Livewire\Pulse;

use App\Models\Proveedor;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class TotalProveedores extends Card
{
    public function render(): Renderable
    {
        [[$totalProveedores], $time, $runAt] = $this->remember(fn () => [
            Proveedor::query()->withoutGlobalScopes()->paraMetricasPlataforma()->count(),
        ]);

        return View::make('livewire.pulse.total-proveedores', [
            'totalProveedores' => $totalProveedores,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
