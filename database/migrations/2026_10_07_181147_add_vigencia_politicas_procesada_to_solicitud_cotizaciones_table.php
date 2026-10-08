<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_cotizaciones', function (Blueprint $table) {
            $table->date('vigencia_hasta')->nullable()->after('observaciones_internas');
            $table->text('politicas')->nullable()->after('vigencia_hasta');
            $table->string('solicitante_empresa', 255)->nullable()->after('cliente_notas');
            $table->timestamp('procesada_at')->nullable()->after('cerrada_at');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_cotizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'vigencia_hasta',
                'politicas',
                'solicitante_empresa',
                'procesada_at',
            ]);
        });
    }
};
