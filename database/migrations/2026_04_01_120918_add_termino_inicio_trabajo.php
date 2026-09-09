<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            /**
             * Valores:
             * 1 = autorización
             * 2 = anticipo
             */
            $table->unsignedTinyInteger('term_cond_inicio_trabajo')
                ->nullable()
                ->after('term_cond_tiempo_entrega_dias');

            /**
             * Porcentaje (0 - 100)
             */
            $table->decimal('term_cond_inicio_trabajo_porcentaje', 5, 2)
                ->nullable()
                ->after('term_cond_inicio_trabajo');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn([
                'term_cond_inicio_trabajo',
                'term_cond_inicio_trabajo_porcentaje',
            ]);
        });
    }
};
