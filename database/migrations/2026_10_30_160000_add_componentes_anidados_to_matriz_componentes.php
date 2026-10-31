<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot de matriz anidada (compuesto dentro de compuesto) en la línea del presupuesto
 * y en plantillas. El árbol viaja en JSON; el renglón padre guarda el costo directo hijo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('presupuesto_concepto_componentes')
            && ! Schema::hasColumn('presupuesto_concepto_componentes', 'componentes_anidados')
        ) {
            Schema::table('presupuesto_concepto_componentes', function (Blueprint $table) {
                $table->json('componentes_anidados')->nullable()->after('importe');
                $table->boolean('es_compuesto_ref')->default(false)->after('componentes_anidados');
            });
        }

        if (Schema::hasTable('presupuesto_plantilla_concepto_componentes')
            && ! Schema::hasColumn('presupuesto_plantilla_concepto_componentes', 'componentes_anidados')
        ) {
            Schema::table('presupuesto_plantilla_concepto_componentes', function (Blueprint $table) {
                $table->json('componentes_anidados')->nullable()->after('importe');
                $table->boolean('es_compuesto_ref')->default(false)->after('componentes_anidados');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('presupuesto_concepto_componentes')
            && Schema::hasColumn('presupuesto_concepto_componentes', 'componentes_anidados')
        ) {
            Schema::table('presupuesto_concepto_componentes', function (Blueprint $table) {
                $table->dropColumn(['componentes_anidados', 'es_compuesto_ref']);
            });
        }

        if (Schema::hasTable('presupuesto_plantilla_concepto_componentes')
            && Schema::hasColumn('presupuesto_plantilla_concepto_componentes', 'componentes_anidados')
        ) {
            Schema::table('presupuesto_plantilla_concepto_componentes', function (Blueprint $table) {
                $table->dropColumn(['componentes_anidados', 'es_compuesto_ref']);
            });
        }
    }
};
