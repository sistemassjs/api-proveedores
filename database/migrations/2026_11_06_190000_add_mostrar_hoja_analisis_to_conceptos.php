<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['presupuesto_conceptos', 'presupuesto_plantilla_conceptos'] as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'mostrar_hoja_analisis')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->boolean('mostrar_hoja_analisis')->default(false)->after('tiene_matriz');
            });
        }
    }

    public function down(): void
    {
        foreach (['presupuesto_conceptos', 'presupuesto_plantilla_conceptos'] as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'mostrar_hoja_analisis')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('mostrar_hoja_analisis');
            });
        }
    }
};
