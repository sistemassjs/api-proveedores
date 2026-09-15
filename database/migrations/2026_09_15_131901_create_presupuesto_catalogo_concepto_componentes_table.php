<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('presupuesto_catalogo_concepto_componentes');

        Schema::create('presupuesto_catalogo_concepto_componentes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('catalogo_concepto_id');
            $table->unsignedInteger('orden')->default(0);
            $table->string('categoria', 20);
            $table->unsignedBigInteger('catalogo_concepto_componente_id')->nullable();
            $table->string('clave_snapshot', 40)->nullable();
            $table->string('descripcion', 500);
            $table->string('unidad', 50);
            $table->decimal('cantidad', 15, 4)->default(1);
            $table->decimal('precio_unitario', 15, 4)->default(0);
            $table->decimal('importe', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('catalogo_concepto_id', 'ppto_cat_comp_padre_fk')
                ->references('id')
                ->on('presupuesto_catalogo_conceptos')
                ->cascadeOnDelete();
            $table->foreign('catalogo_concepto_componente_id', 'ppto_cat_comp_recurso_fk')
                ->references('id')
                ->on('presupuesto_catalogo_conceptos')
                ->nullOnDelete();
            $table->index(['catalogo_concepto_id', 'orden'], 'ppto_cat_comp_padre_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_catalogo_concepto_componentes');
    }
};
