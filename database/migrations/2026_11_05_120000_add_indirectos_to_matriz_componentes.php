<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Márgenes del compuesto anidado. El árbol más profundo los guarda dentro del JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('presupuesto_concepto_componentes')
            && ! Schema::hasColumn('presupuesto_concepto_componentes', 'indirectos')
        ) {
            Schema::table('presupuesto_concepto_componentes', function (Blueprint $table) {
                $table->json('indirectos')->nullable()->after('componentes_anidados');
            });
        }

        if (Schema::hasTable('presupuesto_plantilla_concepto_componentes')
            && ! Schema::hasColumn('presupuesto_plantilla_concepto_componentes', 'indirectos')
        ) {
            Schema::table('presupuesto_plantilla_concepto_componentes', function (Blueprint $table) {
                $table->json('indirectos')->nullable()->after('componentes_anidados');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('presupuesto_concepto_componentes')
            && Schema::hasColumn('presupuesto_concepto_componentes', 'indirectos')
        ) {
            Schema::table('presupuesto_concepto_componentes', function (Blueprint $table) {
                $table->dropColumn('indirectos');
            });
        }

        if (Schema::hasTable('presupuesto_plantilla_concepto_componentes')
            && Schema::hasColumn('presupuesto_plantilla_concepto_componentes', 'indirectos')
        ) {
            Schema::table('presupuesto_plantilla_concepto_componentes', function (Blueprint $table) {
                $table->dropColumn('indirectos');
            });
        }
    }
};
