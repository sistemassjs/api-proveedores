<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('token_completar_registro', 100)->nullable()->index();
            $table->timestamp('token_completar_registro_generado_at')->nullable();
            $table->timestamp('registro_completado_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex(['token_completar_registro']);
            $table->dropColumn([
                'token_completar_registro',
                'token_completar_registro_generado_at',
                'registro_completado_at',
            ]);
        });
    }
};
