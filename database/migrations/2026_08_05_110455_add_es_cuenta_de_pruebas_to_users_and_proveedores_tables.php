<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_cuenta_de_pruebas')
                ->default(false)
                ->after('status')
                ->comment('Cuenta de pruebas: excluir de métricas/reportes de plataforma');
            $table->index('es_cuenta_de_pruebas');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->boolean('es_cuenta_de_pruebas')
                ->default(false)
                ->after('estatus')
                ->comment('Empresa de pruebas: excluir de métricas/reportes de plataforma');
            $table->index('es_cuenta_de_pruebas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['es_cuenta_de_pruebas']);
            $table->dropColumn('es_cuenta_de_pruebas');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex(['es_cuenta_de_pruebas']);
            $table->dropColumn('es_cuenta_de_pruebas');
        });
    }
};
