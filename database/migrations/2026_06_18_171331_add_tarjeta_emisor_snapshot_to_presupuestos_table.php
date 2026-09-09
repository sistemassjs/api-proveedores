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
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->foreignId('config_emisor_presupuesto_id')
                ->nullable()
                ->after('proveedor_id')
                ->constrained('config_emisor_receptor_presupuestos')
                ->nullOnDelete();

            $table->string('empresa_emisora_nombre')->nullable()->after('config_emisor_presupuesto_id');
            $table->string('empresa_emisora_puesto')->nullable()->after('empresa_emisora_nombre');
            $table->string('empresa_emisora_telefono', 30)->nullable()->after('empresa_emisora_puesto');
            $table->string('empresa_emisora_correo')->nullable()->after('empresa_emisora_telefono');

            $table->index('config_emisor_presupuesto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropForeign(['config_emisor_presupuesto_id']);
            $table->dropColumn([
                'config_emisor_presupuesto_id',
                'empresa_emisora_nombre',
                'empresa_emisora_puesto',
                'empresa_emisora_telefono',
                'empresa_emisora_correo',
            ]);
        });
    }
};
