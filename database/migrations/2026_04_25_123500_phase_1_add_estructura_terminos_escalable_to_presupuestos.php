<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1:
     * - Agrega estructura escalable para términos y validaciones/alcances.
     * - Mantiene columnas legacy para no romper compatibilidad inmediata.
     */
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            // Opción b de inicio de trabajos: monto fijo en lugar de porcentaje.
            $table->decimal('term_cond_inicio_trabajo_cantidad', 12, 2)
                ->nullable()
                ->after('term_cond_inicio_trabajo_porcentaje');

            // Lista de hasta 4 textos libres definidos por usuario.
            $table->json('term_cond_textos_libres')
                ->nullable()
                ->after('configuracion_condiciones');

            // Controla visibilidad de términos fijos y opcionales (show/hide por presupuesto).
            $table->json('term_cond_visibilidad')
                ->nullable()
                ->after('term_cond_textos_libres');

            // Bloque "VALIDACIÓN Y ALCANCES" configurable por presupuesto.
            $table->json('validacion_alcances')
                ->nullable()
                ->after('term_cond_visibilidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn([
                'term_cond_inicio_trabajo_cantidad',
                'term_cond_textos_libres',
                'term_cond_visibilidad',
                'validacion_alcances',
            ]);
        });
    }
};

