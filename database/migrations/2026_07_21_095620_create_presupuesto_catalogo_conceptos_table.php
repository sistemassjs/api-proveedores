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
        Schema::create('presupuesto_catalogo_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->string('descripcion', 200);
            $table->string('categoria', 20);
            $table->string('unidad', 50);
            $table->decimal('precio_unitario', 15, 2)->default(0);
            $table->string('imagen_path')->nullable();
            $table->timestamps();

            $table->index('proveedor_id');
            $table->index(['proveedor_id', 'categoria']);
            $table->index(['proveedor_id', 'descripcion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuesto_catalogo_conceptos');
    }
};
