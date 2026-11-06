<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clave de la partida del presupuesto (ej. 2.3a).
 * Es distinta de la clave del catálogo y de clave_snapshot del insumo de matriz.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['presupuesto_conceptos', 'presupuesto_plantilla_conceptos'] as $tabla) {
            if (! Schema::hasColumn($tabla, 'clave')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->string('clave', 40)->nullable()->after('numero');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['presupuesto_conceptos', 'presupuesto_plantilla_conceptos'] as $tabla) {
            if (Schema::hasColumn($tabla, 'clave')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropColumn('clave');
                });
            }
        }
    }
};
