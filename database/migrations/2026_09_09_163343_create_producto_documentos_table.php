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
        Schema::create('producto_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('tipo', 50)->default('ficha_tecnica');
            $table->string('nombre')->nullable();
            $table->string('url', 500);
            $table->string('mime', 100)->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index(['producto_id', 'orden']);
            $table->index(['producto_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_documentos');
    }
};
