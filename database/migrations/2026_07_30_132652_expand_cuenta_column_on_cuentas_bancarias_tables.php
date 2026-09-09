<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía `cuenta` (antes string(20)) para permitir longitudes variables por banco.
     */
    public function up(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->string('cuenta', 255)->nullable()->change();
        });

        Schema::table('solicitud_pago_cuentas_bancarias', function (Blueprint $table) {
            $table->string('cuenta', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->string('cuenta', 20)->nullable()->change();
        });

        Schema::table('solicitud_pago_cuentas_bancarias', function (Blueprint $table) {
            $table->string('cuenta', 20)->nullable()->change();
        });
    }
};
