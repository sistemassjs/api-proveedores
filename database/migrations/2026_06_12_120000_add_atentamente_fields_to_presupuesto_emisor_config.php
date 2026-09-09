<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql5')->table('config_emisor_receptor_presupuestos', function (Blueprint $table) {
            if (! Schema::connection('mysql5')->hasColumn('config_emisor_receptor_presupuestos', 'incluir_leyenda_atentamente')) {
                $table->boolean('incluir_leyenda_atentamente')->default(true)->after('estado');
            }
        });

        Schema::connection('mysql5')->table('presupuestos', function (Blueprint $table) {
            if (! Schema::connection('mysql5')->hasColumn('presupuestos', 'incluir_leyenda_atentamente')) {
                $table->boolean('incluir_leyenda_atentamente')->default(true)->after('empresa_emisora_correo');
            }
            if (! Schema::connection('mysql5')->hasColumn('presupuestos', 'empresa_emisora_nombre_comercial')) {
                $table->string('empresa_emisora_nombre_comercial', 255)->nullable()->after('incluir_leyenda_atentamente');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql5')->table('presupuestos', function (Blueprint $table) {
            if (Schema::connection('mysql5')->hasColumn('presupuestos', 'empresa_emisora_nombre_comercial')) {
                $table->dropColumn('empresa_emisora_nombre_comercial');
            }
            if (Schema::connection('mysql5')->hasColumn('presupuestos', 'incluir_leyenda_atentamente')) {
                $table->dropColumn('incluir_leyenda_atentamente');
            }
        });

        Schema::connection('mysql5')->table('config_emisor_receptor_presupuestos', function (Blueprint $table) {
            if (Schema::connection('mysql5')->hasColumn('config_emisor_receptor_presupuestos', 'incluir_leyenda_atentamente')) {
                $table->dropColumn('incluir_leyenda_atentamente');
            }
        });
    }
};
