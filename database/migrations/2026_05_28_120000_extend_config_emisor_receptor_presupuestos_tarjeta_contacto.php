<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_emisor_receptor_presupuestos', function (Blueprint $table) {
            $table->string('subfijo', 30)->nullable()->after('tipo');
            $table->string('ape1', 60)->nullable()->after('nombre');
            $table->string('ape2', 60)->nullable()->after('ape1');
            $table->string('telefono', 30)->nullable()->after('puesto');
            $table->string('correo', 120)->nullable()->after('telefono');
            $table->string('color_fondo', 7)->nullable()->after('correo');
            $table->string('foto_perfil')->nullable()->after('file_firma');
        });

        if (Schema::hasColumn('config_emisor_receptor_presupuestos', 'apellido')) {
            DB::table('config_emisor_receptor_presupuestos')
                ->whereNotNull('apellido')
                ->update([
                    'ape1' => DB::raw('apellido'),
                ]);

            Schema::table('config_emisor_receptor_presupuestos', function (Blueprint $table) {
                $table->dropColumn('apellido');
            });
        }
    }

    public function down(): void
    {
        Schema::table('config_emisor_receptor_presupuestos', function (Blueprint $table) {
            $table->string('apellido', 60)->nullable()->after('nombre');
        });

        DB::table('config_emisor_receptor_presupuestos')
            ->whereNotNull('ape1')
            ->update([
                'apellido' => DB::raw('ape1'),
            ]);

        Schema::table('config_emisor_receptor_presupuestos', function (Blueprint $table) {
            $table->dropColumn([
                'subfijo',
                'ape1',
                'ape2',
                'telefono',
                'correo',
                'color_fondo',
                'foto_perfil',
            ]);
        });
    }
};
