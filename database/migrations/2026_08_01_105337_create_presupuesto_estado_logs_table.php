<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_estado_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')
                ->constrained('presupuestos')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('fecha');
            $table->string('estado_anterior')->nullable();
            $table->string('estado');
            $table->text('nota')->nullable()->comment('Motivo de rechazo u otra nota del cambio de estado');
            $table->timestamps();

            $table->index(['presupuesto_id', 'fecha']);
            $table->index(['presupuesto_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_estado_logs');
    }
};
