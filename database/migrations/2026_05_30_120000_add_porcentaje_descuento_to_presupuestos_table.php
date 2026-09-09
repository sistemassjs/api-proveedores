<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->unsignedTinyInteger('porcentaje_descuento')
                ->nullable()
                ->after('subtotal');
            $table->decimal('cantidad_descuento', 12, 2)
                ->nullable()
                ->after('porcentaje_descuento');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn(['porcentaje_descuento', 'cantidad_descuento']);
        });
    }
};
