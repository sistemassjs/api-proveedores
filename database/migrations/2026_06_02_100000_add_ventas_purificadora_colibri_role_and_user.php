<?php

use App\Enums\EstadoGeneral;
use App\Enums\EstadoUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE_NOMBRE = 'ventas_purificadora_colibri';

    private const USER_TELEFONO = '6682485970';

    private const USER_TELEFONO_CODIGO_PAIS = '52';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        $roleId = DB::table('roles')->where('nombre', self::ROLE_NOMBRE)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'nombre' => self::ROLE_NOMBRE,
                'descripcion' => 'Ventas y gestión de pedidos Purificadora Colibrí',
                'activo' => true,
                'estado' => EstadoGeneral::ACTIVO->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $exists = DB::table('users')
            ->where('telefono_codigo_pais', self::USER_TELEFONO_CODIGO_PAIS)
            ->where('telefono', self::USER_TELEFONO)
            ->exists();

        if ($exists) {
            DB::table('users')
                ->where('telefono_codigo_pais', self::USER_TELEFONO_CODIGO_PAIS)
                ->where('telefono', self::USER_TELEFONO)
                ->update([
                    'name' => 'Purificadora Colibri',
                    'email' => null,
                    'role_id' => $roleId,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('users')->insert([
            'name' => 'Purificadora Colibri',
            'email' => null,
            'telefono_codigo_pais' => self::USER_TELEFONO_CODIGO_PAIS,
            'telefono' => self::USER_TELEFONO,
            'foto_perfil_url' => 'uploads/default.png',
            'password' => Hash::make('123456'),
            'cambiar_pass_default' => true,
            'role_id' => $roleId,
            'status' => EstadoUsuario::VERIFICADO->value,
            'email_verified_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('telefono_codigo_pais', self::USER_TELEFONO_CODIGO_PAIS)
            ->where('telefono', self::USER_TELEFONO)
            ->delete();

        DB::table('roles')->where('nombre', self::ROLE_NOMBRE)->delete();

        // No revertimos email nullable: puede haber otros usuarios sin correo.
    }
};
