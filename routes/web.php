<?php

use Illuminate\Support\Facades\Route;

Route::middleware(config('pulse.middleware'))
    ->prefix(config('pulse.path', 'pulse'))
    ->group(function () {
        Route::view('/metricas', 'pulse.metricas')->name('pulse.metricas');
    });
