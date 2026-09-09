<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anexos de plantillas de presupuesto (aislados del documento).
     */
    public function up(): void
    {
        Schema::create('presupuesto_plantilla_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_plantilla_id')
                ->constrained('presupuesto_plantillas')
                ->cascadeOnDelete();
            $table->string('titulo', 40);
            $table->string('descripcion', 100)->nullable();
            $table->decimal('precio', 15, 2)->nullable();
            $table->unsignedInteger('orden')->default(1);
            $table->longText('archivo_path');
            $table->unsignedInteger('archivo_width')->nullable();
            $table->unsignedInteger('archivo_height')->nullable();
            $table->float('archivo_aspect_ratio')->nullable();
            $table->timestamps();

            $table->index(
                ['presupuesto_plantilla_id', 'orden'],
                'ppa_plantilla_orden_idx'
            );
        });

        Schema::create('presupuesto_plantilla_anexo_pdf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_plantilla_id')
                ->constrained('presupuesto_plantillas')
                ->cascadeOnDelete();
            $table->string('titulo', 40);
            $table->unsignedInteger('orden')->default(1);
            $table->string('archivo_path');
            $table->unsignedInteger('paginas')->default(1);
            $table->boolean('mostrar_estampado')->default(true);
            $table->boolean('mostrar_numero_pagina')->default(true);
            $table->boolean('mostrar_datos_presupuesto')->default(true);
            $table->timestamps();

            $table->index(
                ['presupuesto_plantilla_id', 'orden'],
                'ppap_plantilla_orden_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_plantilla_anexo_pdf');
        Schema::dropIfExists('presupuesto_plantilla_anexos');
    }
};
