<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nombres legados → bandera corta. La columna `tipo` ya existe (string nullable).
     *
     * @var array<string, string>
     */
    private array $haciaBandera = [
        'producto' => 'p',
        'servicio' => 's',
        'renta' => 'r',
    ];

    public function up(): void
    {
        foreach ($this->haciaBandera as $nombre => $bandera) {
            DB::connection('mysql5')->table('productos')
                ->whereRaw('LOWER(tipo) = ?', [$nombre])
                ->update(['tipo' => $bandera]);
        }
    }

    public function down(): void
    {
        foreach ($this->haciaBandera as $nombre => $bandera) {
            DB::connection('mysql5')->table('productos')
                ->where('tipo', $bandera)
                ->update(['tipo' => $nombre]);
        }
    }
};
