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
        Schema::create('config_emisor_receptor_presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->integer('tipo')->default(1); // 1: emisor, 2: receptor
            $table->string('nombre', 60);
            $table->string('apellido', 60)->nullable();
            $table->string('puesto', 40)->nullable();
            $table->string('file_firma')->nullable();
            $table->integer('estado')->default(1); // 1: activo, 2: inactivo, 3: default
            $table->timestamps();

            $table->index(['proveedor_id', 'tipo']);
            $table->index(['proveedor_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_emisor_receptor_presupuestos');
    }
};
