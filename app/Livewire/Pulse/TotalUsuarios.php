<?php

namespace App\Livewire\Pulse;

use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class TotalUsuarios extends Card
{
    public function render(): Renderable
    {
        [[$totalUsuarios], $time, $runAt] = $this->remember(fn () => [
            User::query()->paraMetricasPlataforma()->count(),
        ]);

        return View::make('livewire.pulse.total-usuarios', [
            'totalUsuarios' => $totalUsuarios,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
