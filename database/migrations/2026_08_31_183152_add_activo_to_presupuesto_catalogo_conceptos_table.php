<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('imagen_path');
            $table->index(['proveedor_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->dropIndex(['proveedor_id', 'activo']);
            $table->dropColumn('activo');
        });
    }
};
