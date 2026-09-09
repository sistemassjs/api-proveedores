<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('telefono_codigo_pais', 6)->nullable()->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono_codigo_pais', 6)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('telefono_codigo_pais');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telefono_codigo_pais');
        });
    }
};
