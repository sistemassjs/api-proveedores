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
        Schema::create('catalogo_familias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 50)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique('nombre');
            $table->unique('codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_familias');
    }
};
