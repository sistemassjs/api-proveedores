<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_anexo_pdf', function (Blueprint $table) {
            $table->boolean('mostrar_estampado')->default(true)->after('paginas');
            $table->boolean('mostrar_numero_pagina')->default(true)->after('mostrar_estampado');
            $table->boolean('mostrar_datos_presupuesto')->default(true)->after('mostrar_numero_pagina');
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_anexo_pdf', function (Blueprint $table) {
            $table->dropColumn([
                'mostrar_estampado',
                'mostrar_numero_pagina',
                'mostrar_datos_presupuesto',
            ]);
        });
    }
};
