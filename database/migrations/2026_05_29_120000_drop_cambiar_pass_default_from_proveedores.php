<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('proveedores', 'cambiar_pass_default')) {
            return;
        }

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('cambiar_pass_default');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('proveedores', 'cambiar_pass_default')) {
            return;
        }

        Schema::table('proveedores', function (Blueprint $table) {
            $table->boolean('cambiar_pass_default')
                ->default(false)
                ->after('is_proveedor_catalogo');
        });
    }
};
