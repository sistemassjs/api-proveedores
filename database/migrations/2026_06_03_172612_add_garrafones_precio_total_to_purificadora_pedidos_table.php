<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purificadora_pedidos', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_garrafones')->default(1)->after('municipio');
            $table->decimal('precio_unitario', 10, 2)->default(25)->after('cantidad_garrafones');
            $table->decimal('total', 12, 2)->default(25)->after('precio_unitario');
        });

        DB::table('purificadora_pedidos')->update([
            'total' => DB::raw('cantidad_garrafones * precio_unitario'),
        ]);
    }

    public function down(): void
    {
        Schema::table('purificadora_pedidos', function (Blueprint $table) {
            $table->dropColumn(['cantidad_garrafones', 'precio_unitario', 'total']);
        });
    }
};
