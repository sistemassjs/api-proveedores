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
        Schema::create('presupuesto_anexo_pdf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')
                ->constrained('presupuestos')
                ->cascadeOnDelete();
            $table->string('titulo', 40);
            $table->unsignedInteger('orden')->default(1);
            $table->string('archivo_path');
            $table->unsignedInteger('paginas')->default(1);
            $table->timestamps();

            $table->index(['presupuesto_id', 'orden'], 'presupuesto_anexo_pdf_presupuesto_orden_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuesto_anexo_pdf');
    }
};
