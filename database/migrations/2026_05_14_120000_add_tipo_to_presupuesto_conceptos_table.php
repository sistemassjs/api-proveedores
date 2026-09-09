<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->string('tipo', 20)->default('concepto')->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
