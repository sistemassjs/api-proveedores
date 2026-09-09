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
        Schema::create('catalogo_publico_items', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80);
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('marca')->nullable();
            $table->string('categoria')->nullable();
            $table->string('subcategoria')->nullable();
            $table->string('unidad', 50)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('empresa', 100);
            $table->string('logo', 500)->nullable();
            $table->decimal('precio_base', 15, 2)->nullable();
            $table->decimal('precio_mayoreo', 15, 2)->nullable();
            $table->decimal('precio_menudeo', 15, 2)->nullable();
            $table->json('propiedades')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa', 'codigo'], 'catalogo_publico_items_empresa_codigo_unique');
            $table->index('empresa');
            $table->index('categoria');
            $table->index('marca');
            $table->index('activo');
            $table->index('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_publico_items');
    }
};
