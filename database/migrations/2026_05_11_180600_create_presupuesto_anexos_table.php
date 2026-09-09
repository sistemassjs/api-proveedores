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
        Schema::create('presupuesto_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')
                ->constrained('presupuestos')
                ->cascadeOnDelete();
            $table->string('titulo', 40);
            $table->string('descripcion', 100)->nullable();
            $table->decimal('precio', 15, 2)->nullable();
            $table->unsignedInteger('orden')->default(1);
            $table->longText('archivo_path');
            $table->timestamps();

            $table->index(['presupuesto_id', 'orden'], 'presupuesto_anexos_presupuesto_orden_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuesto_anexos');
    }
};
