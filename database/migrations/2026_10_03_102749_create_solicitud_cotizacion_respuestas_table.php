<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_cotizacion_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cotizacion_id')
                ->constrained('solicitud_cotizaciones')
                ->cascadeOnDelete();
            $table->foreignId('enviado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('canales', 30);
            $table->text('mensaje')->nullable();
            $table->string('pdf_path', 500)->nullable();

            $table->string('email_estado', 30)->nullable();
            $table->text('email_error')->nullable();

            $table->string('whatsapp_modo', 30)->nullable();
            $table->string('whatsapp_estado', 40)->nullable();
            $table->string('whatsapp_link', 1000)->nullable();
            $table->text('whatsapp_error')->nullable();

            $table->json('payload_resumen')->nullable();
            $table->timestamps();

            $table->index(['solicitud_cotizacion_id', 'created_at'], 'sc_respuestas_solicitud_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_cotizacion_respuestas');
    }
};
