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
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->string('imagen_path')->nullable()->after('precio_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->dropColumn('imagen_path');
        });
    }
};
