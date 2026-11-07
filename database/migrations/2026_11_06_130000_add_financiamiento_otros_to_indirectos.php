<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('presupuestos') && ! Schema::hasColumn('presupuestos', 'indirecto_financiamiento_porcentaje')) {
            Schema::table('presupuestos', function (Blueprint $table) {
                $table->decimal('indirecto_financiamiento_porcentaje', 8, 4)->default(0)->after('indirecto_cargos_porcentaje');
                $table->decimal('indirecto_otros_porcentaje', 8, 4)->default(0)->after('indirecto_financiamiento_porcentaje');
            });
        }

        foreach ([
            'presupuesto_conceptos',
            'presupuesto_catalogo_conceptos',
            'presupuesto_plantilla_conceptos',
        ] as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'indirecto_financiamiento_porcentaje')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->decimal('indirecto_financiamiento_porcentaje', 8, 4)->nullable()->after('indirecto_cargos_porcentaje');
                $table->decimal('indirecto_otros_porcentaje', 8, 4)->nullable()->after('indirecto_financiamiento_porcentaje');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('presupuestos') && Schema::hasColumn('presupuestos', 'indirecto_financiamiento_porcentaje')) {
            Schema::table('presupuestos', function (Blueprint $table) {
                $table->dropColumn([
                    'indirecto_financiamiento_porcentaje',
                    'indirecto_otros_porcentaje',
                ]);
            });
        }

        foreach ([
            'presupuesto_conceptos',
            'presupuesto_catalogo_conceptos',
            'presupuesto_plantilla_conceptos',
        ] as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'indirecto_financiamiento_porcentaje')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn([
                    'indirecto_financiamiento_porcentaje',
                    'indirecto_otros_porcentaje',
                ]);
            });
        }
    }
};
