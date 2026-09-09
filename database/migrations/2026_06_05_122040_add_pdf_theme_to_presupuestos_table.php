<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->string('pdf_theme', 64)
                ->default('corporativo')
                ->after('token_publico')
                ->comment('Clave del tema visual del PDF (PresupuestoThemeService)');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn('pdf_theme');
        });
    }
};
