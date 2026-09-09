<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 4 decimales para cálculos Opus; la UI puede mostrar menos.
     */
    public function up(): void
    {
        Schema::table('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->decimal('precio_unitario', 15, 4)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->decimal('precio_unitario', 15, 2)->default(0)->change();
        });
    }
};
