<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('proveedor_regimenes_fiscales', function (Blueprint $table) {
            $table->dropUnique('proveedor_regimen_clave_unique');
            $table->unique(
                ['proveedor_id', 'clave', 'nombre'],
                'proveedor_regimen_clave_nombre_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('proveedor_regimenes_fiscales', function (Blueprint $table) {
            $table->dropUnique('proveedor_regimen_clave_nombre_unique');
            $table->unique(['proveedor_id', 'clave'], 'proveedor_regimen_clave_unique');
        });
    }
};
