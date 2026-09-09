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
        Schema::table('presupuesto_anexos', function (Blueprint $table) {
            $table->unsignedInteger('archivo_width')->nullable()->after('archivo_path');

            $table->unsignedInteger('archivo_height')->nullable()->after('archivo_width');

            $table->decimal('archivo_aspect_ratio', 12, 6)
                ->nullable()
                ->after('archivo_height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuesto_anexos', function (Blueprint $table) {
            $table->dropColumn([
                'archivo_width',
                'archivo_height',
                'archivo_aspect_ratio',
            ]);
        });
    }
};