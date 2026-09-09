<?php

namespace App\Livewire\Pulse;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class ErroresGenerales extends Card
{
    #[Url(as: 'direction')]
    public string $direction = 'desc';

    #[Url(as: 'metric')]
    public string $metric = 'max';

    public function render(): Renderable
    {
        [[$errores, $erroresTotales], $time, $runAt] = $this->remember(function () {
            $errores = $this->aggregate(
                'exception',
                ['max', 'count'],
                $this->metric,
                $this->direction
            )->map(function ($row) {
                [$clase, $ubicacion] = json_decode($row->key, true) ?? ['Error desconocido', 'Sin ubicacion'];

                return (object) [
                    'clase' => $clase ?: 'Error desconocido',
                    'ubicacion' => $ubicacion ?: 'Sin ubicacion',
                    'ultimo' => CarbonImmutable::createFromTimestamp((int) $row->max),
                    'conteo' => (int) $row->count,
                ];
            });

            return [
                $errores,
                (int) $errores->sum('conteo'),
            ];
        });

        return View::make('livewire.pulse.errores-generales', [
            'errores' => $errores,
            'erroresTotales' => $erroresTotales,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
