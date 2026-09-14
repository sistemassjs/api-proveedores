<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('mostrar_en_catalogo_publico')
                ->default(false)
                ->after('mostrar_precios');
            $table->index(['proveedor_id', 'mostrar_en_catalogo_publico'], 'productos_proveedor_catalogo_publico_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_proveedor_catalogo_publico_idx');
            $table->dropColumn('mostrar_en_catalogo_publico');
        });
    }
};
