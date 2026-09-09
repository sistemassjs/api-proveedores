<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3:
     * - Limpia columnas legacy que ya no se usarán.
     * - Debe ejecutarse cuando backend/frontend ya lean la estructura nueva.
     */
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn([
                'term_cond_anticipo_porcentaje',
                'obs_traslados',
                'obs_viaticos',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->decimal('term_cond_anticipo_porcentaje', 5, 2)
                ->nullable()
                ->after('term_cond_iva');

            $table->boolean('obs_traslados')
                ->default(false)
                ->after('obs_garantia_dias');

            $table->boolean('obs_viaticos')
                ->default(false)
                ->after('obs_traslados');
        });
    }
};

