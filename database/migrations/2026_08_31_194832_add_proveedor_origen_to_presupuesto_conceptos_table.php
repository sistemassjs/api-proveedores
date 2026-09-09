<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->string('proveedor_nombre', 150)->nullable()->after('imagen_path');
            $table->string('proveedor_logo_url', 500)->nullable()->after('proveedor_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->dropColumn(['proveedor_nombre', 'proveedor_logo_url']);
        });
    }
};
