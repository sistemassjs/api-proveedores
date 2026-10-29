<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto multiempresa: rol por membresía y última empresa elegida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('user_proveedor', 'role_id')) {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('proveedor_id')
                    ->constrained('roles')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('user_proveedor', 'role_id')) {
            DB::statement('
                UPDATE user_proveedor up
                INNER JOIN users u ON u.id = up.user_id
                SET up.role_id = u.role_id
                WHERE up.role_id IS NULL AND u.role_id IS NOT NULL
            ');
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ultima_empresa_id')) {
                $table->foreignId('ultima_empresa_id')
                    ->nullable()
                    ->after('role_id')
                    ->constrained('proveedores')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ultima_empresa_id')) {
                $table->dropConstrainedForeignId('ultima_empresa_id');
            }
        });

        Schema::table('user_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('user_proveedor', 'role_id')) {
                $table->dropConstrainedForeignId('role_id');
            }
        });
    }
};
