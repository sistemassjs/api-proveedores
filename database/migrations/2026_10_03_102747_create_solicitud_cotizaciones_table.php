<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->string('folio', 30);
            $table->string('origen', 40)->default('publico_cotizador');
            $table->string('estatus', 30)->default('recibida');

            $table->string('cliente_nombre', 255);
            $table->string('cliente_email', 255);
            $table->string('cliente_telefono', 40)->nullable();
            $table->string('cliente_whatsapp', 40)->nullable();
            $table->text('cliente_notas')->nullable();

            $table->text('observaciones_internas')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('respondida_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();

            $table->unique(['proveedor_id', 'folio']);
            $table->index(['proveedor_id', 'estatus']);
            $table->index(['proveedor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_cotizaciones');
    }
};
