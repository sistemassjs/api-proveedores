<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->json('ppto_config')
                ->nullable()
                ->after('config_mostrar_totales')
                ->comment('Configuración de layout PDF/preview (márgenes mm, gaps); JSON plano key:value');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn('ppto_config');
        });
    }
};
