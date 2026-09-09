<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía la descripción del catálogo de conceptos a 500 caracteres.
     */
    public function up(): void
    {
        Schema::table('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->string('descripcion', 500)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->string('descripcion', 200)->change();
        });
    }
};
