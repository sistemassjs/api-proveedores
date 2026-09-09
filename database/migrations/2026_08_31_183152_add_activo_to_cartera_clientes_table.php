<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartera_clientes', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('logo_path');
            $table->index(['proveedor_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::table('cartera_clientes', function (Blueprint $table) {
            $table->dropIndex(['proveedor_id', 'activo']);
            $table->dropColumn('activo');
        });
    }
};
