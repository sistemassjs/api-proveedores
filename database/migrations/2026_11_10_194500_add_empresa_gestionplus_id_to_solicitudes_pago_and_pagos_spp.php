<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql5')->table('solicitudes_pago', function (Blueprint $table) {
            if (! Schema::connection('mysql5')->hasColumn('solicitudes_pago', 'empresa_gestionplus_id')) {
                $table->unsignedBigInteger('empresa_gestionplus_id')
                    ->nullable()
                    ->after('empresa_construcc_id')
                    ->comment('Empresa pagadora en plataforma GestionPlus (proveedores.id)');

                $table->foreign('empresa_gestionplus_id', 'spp_empresa_gestionplus_fk')
                    ->references('id')
                    ->on('proveedores')
                    ->nullOnDelete();

                $table->index('empresa_gestionplus_id', 'spp_empresa_gestionplus_idx');
            }
        });

        Schema::connection('mysql5')->table('pagos_spp', function (Blueprint $table) {
            if (! Schema::connection('mysql5')->hasColumn('pagos_spp', 'empresa_gestionplus_id')) {
                $table->unsignedBigInteger('empresa_gestionplus_id')
                    ->nullable()
                    ->after('empresa_construcc_id')
                    ->comment('Empresa pagadora en plataforma GestionPlus (proveedores.id)');

                $table->foreign('empresa_gestionplus_id', 'pago_spp_empresa_gestionplus_fk')
                    ->references('id')
                    ->on('proveedores')
                    ->nullOnDelete();

                $table->index('empresa_gestionplus_id', 'pago_spp_empresa_gestionplus_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql5')->table('solicitudes_pago', function (Blueprint $table) {
            if (Schema::connection('mysql5')->hasColumn('solicitudes_pago', 'empresa_gestionplus_id')) {
                $table->dropForeign('spp_empresa_gestionplus_fk');
                $table->dropIndex('spp_empresa_gestionplus_idx');
                $table->dropColumn('empresa_gestionplus_id');
            }
        });

        Schema::connection('mysql5')->table('pagos_spp', function (Blueprint $table) {
            if (Schema::connection('mysql5')->hasColumn('pagos_spp', 'empresa_gestionplus_id')) {
                $table->dropForeign('pago_spp_empresa_gestionplus_fk');
                $table->dropIndex('pago_spp_empresa_gestionplus_idx');
                $table->dropColumn('empresa_gestionplus_id');
            }
        });
    }
};
