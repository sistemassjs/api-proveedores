<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_publico_items', function (Blueprint $table) {
            $table->boolean('mostrar_en_listado')
                ->default(true)
                ->after('activo')
                ->comment('Si la empresa aparece en listados públicos / picker de presupuestos');
            $table->index('mostrar_en_listado');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_publico_items', function (Blueprint $table) {
            $table->dropIndex(['mostrar_en_listado']);
            $table->dropColumn('mostrar_en_listado');
        });
    }
};
