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
        if (Schema::hasColumn('solicitudes_pago', 'fecha_con_comprobante')) {
            return;
        }

        Schema::table('solicitudes_pago', function (Blueprint $table) {
            $column = $table->dateTime('fecha_con_comprobante')
                ->nullable()
                ->comment('Momento en que se adjuntó el comprobante a la SPP');

            if (Schema::hasColumn('solicitudes_pago', 'fecha_comprobante_pago')) {
                $column->after('fecha_comprobante_pago');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('solicitudes_pago', 'fecha_con_comprobante')) {
            return;
        }

        Schema::table('solicitudes_pago', function (Blueprint $table) {
            $table->dropColumn('fecha_con_comprobante');
        });
    }
};
