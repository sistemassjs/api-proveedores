<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renglón de matriz por porcentaje de la mano de obra del mismo nivel.
 * calculo: cantidad | porcentaje_mo. El anidado guarda ambos dentro del JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'presupuesto_catalogo_concepto_componentes',
            'presupuesto_concepto_componentes',
            'presupuesto_plantilla_concepto_componentes',
        ] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'calculo')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('calculo', 20)->default('cantidad')->after('importe');
                $table->decimal('porcentaje', 8, 4)->nullable()->after('calculo');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'presupuesto_catalogo_concepto_componentes',
            'presupuesto_concepto_componentes',
            'presupuesto_plantilla_concepto_componentes',
        ] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'calculo')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['calculo', 'porcentaje']);
            });
        }
    }
};
