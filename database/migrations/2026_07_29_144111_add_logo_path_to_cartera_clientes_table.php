<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cartera_clientes', function (Blueprint $table) {
            $table->string('logo_path', 500)->nullable()->after('correo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cartera_clientes', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
