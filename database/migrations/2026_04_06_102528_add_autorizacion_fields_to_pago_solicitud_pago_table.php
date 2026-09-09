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
        Schema::table('pago_solicitud_pago', function (Blueprint $table) {
            // Datos de autorización del pago aplicado a la SPP
            $table->unsignedBigInteger('usuario_autorizo_id')->nullable()->after('fecha_aplicacion');
            $table->string('usuario_autorizo_nombre')->nullable()->after('usuario_autorizo_id');

            $table->decimal('monto_autorizado', 15, 2)->nullable()->after('usuario_autorizo_nombre');
            $table->string('motivo_autorizacion')->nullable()->after('monto_autorizado');
            $table->dateTime('fecha_autorizacion')->nullable()->after('motivo_autorizacion');

            // (Opcional pero recomendado) índice
            $table->index('usuario_autorizo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pago_solicitud_pago', function (Blueprint $table) {
            $table->dropIndex(['usuario_autorizo_id']);

            $table->dropColumn([
                'usuario_autorizo_id',
                'usuario_autorizo_nombre',
                'monto_autorizado',
                'motivo_autorizacion',
                'fecha_autorizacion',
            ]);
        });
    }
};
