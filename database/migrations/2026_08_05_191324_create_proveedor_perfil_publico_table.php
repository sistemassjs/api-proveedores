<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perfil público de empresa: un registro activo por proveedor + token opaco.
     */
    public function up(): void
    {
        Schema::create('proveedor_perfil_publico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')
                ->constrained('proveedores')
                ->cascadeOnDelete();
            $table->string('token', 64)->nullable();
            $table->string('theme_key', 64)->default('corporativo');
            $table->boolean('is_published')->default(false);
            /** @var array flags de secciones + ids seleccionados (borrador) */
            $table->json('sections')->nullable();
            /** Snapshot congelado al publicar/actualizar (solo lo marcado) */
            $table->json('snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique('proveedor_id');
            $table->unique('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_perfil_publico');
    }
};
