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
        Schema::create('catalogo_subfamilias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('familia_id')->constrained('catalogo_familias')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('codigo', 50)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['familia_id', 'nombre']);
            $table->index('codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_subfamilias');
    }
};
