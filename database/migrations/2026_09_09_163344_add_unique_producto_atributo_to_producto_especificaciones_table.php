<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $log = Log::channel('migrations');

        // Conservar la fila con menor id por (producto_id, atributo)
        $duplicates = DB::table('producto_especificaciones')
            ->select('producto_id', 'atributo', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('producto_id', 'atributo')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $deleted = DB::table('producto_especificaciones')
                ->where('producto_id', $dup->producto_id)
                ->where('atributo', $dup->atributo)
                ->where('id', '!=', $dup->keep_id)
                ->delete();

            $log->info('producto_especificaciones: dedupe', [
                'producto_id' => $dup->producto_id,
                'atributo' => $dup->atributo,
                'deleted' => $deleted,
            ]);
        }

        Schema::table('producto_especificaciones', function (Blueprint $table) {
            $table->unique(['producto_id', 'atributo'], 'uk_producto_atributo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('producto_especificaciones', function (Blueprint $table) {
            $table->dropUnique('uk_producto_atributo');
        });
    }
};
