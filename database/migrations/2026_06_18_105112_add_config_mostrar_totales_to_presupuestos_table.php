<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->boolean('config_mostrar_totales')
                ->default(true)
                ->after('pdf_theme')
                ->comment('Si es false, no se muestra el apartado de totales en preview/PDF');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn('config_mostrar_totales');
        });
    }
};
