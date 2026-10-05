<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_cotizacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cotizacion_id')
                ->constrained('solicitud_cotizaciones')
                ->cascadeOnDelete();
            $table->foreignId('producto_id')
                ->nullable()
                ->constrained('productos')
                ->nullOnDelete();

            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->string('unidad', 50)->nullable();
            $table->string('codigo', 100)->nullable();
            $table->string('imagen', 500)->nullable();

            $table->decimal('precio_referencia', 12, 2)->nullable();
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('cantidad', 12, 3)->default(1);
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->boolean('es_sugerencia_empresa')->default(false);
            $table->string('motivo_sugerencia', 255)->nullable();
            $table->text('observaciones_linea')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['solicitud_cotizacion_id', 'orden'], 'sc_detalles_solicitud_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_cotizacion_detalles');
    }
};
