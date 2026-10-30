<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->decimal('indirecto_oficina_porcentaje', 8, 4)->default(0)->after('config_mostrar_matriz_costos');
            $table->decimal('indirecto_campo_porcentaje', 8, 4)->default(0)->after('indirecto_oficina_porcentaje');
            $table->decimal('indirecto_utilidad_porcentaje', 8, 4)->default(0)->after('indirecto_campo_porcentaje');
            $table->decimal('indirecto_cargos_porcentaje', 8, 4)->default(0)->after('indirecto_utilidad_porcentaje');
        });

        foreach ([
            'presupuesto_conceptos',
            'presupuesto_catalogo_conceptos',
            'presupuesto_plantilla_conceptos',
        ] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('indirectos_modo', 10)->default('general');
                $table->decimal('indirecto_oficina_porcentaje', 8, 4)->nullable();
                $table->decimal('indirecto_campo_porcentaje', 8, 4)->nullable();
                $table->decimal('indirecto_utilidad_porcentaje', 8, 4)->nullable();
                $table->decimal('indirecto_cargos_porcentaje', 8, 4)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn([
                'indirecto_oficina_porcentaje',
                'indirecto_campo_porcentaje',
                'indirecto_utilidad_porcentaje',
                'indirecto_cargos_porcentaje',
            ]);
        });

        foreach ([
            'presupuesto_conceptos',
            'presupuesto_catalogo_conceptos',
            'presupuesto_plantilla_conceptos',
        ] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn([
                    'indirectos_modo',
                    'indirecto_oficina_porcentaje',
                    'indirecto_campo_porcentaje',
                    'indirecto_utilidad_porcentaje',
                    'indirecto_cargos_porcentaje',
                ]);
            });
        }
    }
};
