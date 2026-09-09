<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purificadora_pedidos', function (Blueprint $table) {
            $table->text('notas')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('purificadora_pedidos', function (Blueprint $table) {
            $table->dropColumn('notas');
        });
    }
};
