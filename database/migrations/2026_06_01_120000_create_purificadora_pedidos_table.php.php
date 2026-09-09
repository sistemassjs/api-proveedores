<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purificadora_pedidos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 120);
            $table->string('celular', 10);
            $table->string('correo', 180)->nullable();
            $table->string('calle', 120);
            $table->string('numero', 20);
            $table->string('colonia', 120);
            $table->char('codigo_postal', 5)->nullable();
            $table->string('municipio', 120)->default('Ahome');

            $table->unsignedTinyInteger('estado')->default(0);
            $table->dateTime('pendiente_fecha')->nullable();
            $table->dateTime('en_proceso_fecha')->nullable();
            $table->dateTime('completado_fecha')->nullable();
            $table->dateTime('cancelado_fecha')->nullable();

            $table->timestamps();

            $table->index('celular');
            $table->index('codigo_postal');
            $table->index('estado');
            $table->index('nombre');
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('pendiente_fecha');
            $table->index('en_proceso_fecha');
            $table->index('completado_fecha');
            $table->index('cancelado_fecha');
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purificadora_pedidos');
    }
};
