<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->boolean('es_compuesto')->default(false)->after('categoria');
            $table->string('clave', 40)->nullable()->after('es_compuesto');

            $table->index(['proveedor_id', 'es_compuesto'], 'ppto_cat_conceptos_prov_compuesto_idx');
            $table->unique(['proveedor_id', 'clave'], 'ppto_cat_conceptos_proveedor_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->dropUnique('ppto_cat_conceptos_proveedor_clave_unique');
            $table->dropIndex('ppto_cat_conceptos_prov_compuesto_idx');
            $table->dropColumn(['es_compuesto', 'clave']);
        });
    }
};
