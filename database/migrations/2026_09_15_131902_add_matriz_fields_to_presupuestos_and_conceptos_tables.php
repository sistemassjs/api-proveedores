<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('presupuestos', 'config_mostrar_matriz_costos')) {
            Schema::table('presupuestos', function (Blueprint $table) {
                $table->boolean('config_mostrar_matriz_costos')
                    ->default(false)
                    ->after('config_mostrar_totales');
            });
        }

        if (! Schema::hasColumn('presupuesto_conceptos', 'tiene_matriz')) {
            Schema::table('presupuesto_conceptos', function (Blueprint $table) {
                $table->boolean('tiene_matriz')
                    ->default(false)
                    ->after('tipo');
            });
        }

        Schema::dropIfExists('presupuesto_concepto_componentes');

        Schema::create('presupuesto_concepto_componentes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('presupuesto_concepto_id');
            $table->unsignedInteger('orden')->default(0);
            $table->string('categoria', 20);
            $table->unsignedBigInteger('catalogo_concepto_id')->nullable();
            $table->string('clave_snapshot', 40)->nullable();
            $table->string('descripcion', 500);
            $table->string('unidad', 50);
            $table->decimal('cantidad', 15, 4)->default(1);
            $table->decimal('precio_unitario', 15, 4)->default(0);
            $table->decimal('importe', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('presupuesto_concepto_id', 'ppto_lin_comp_padre_fk')
                ->references('id')
                ->on('presupuesto_conceptos')
                ->cascadeOnDelete();
            $table->foreign('catalogo_concepto_id', 'ppto_lin_comp_cat_fk')
                ->references('id')
                ->on('presupuesto_catalogo_conceptos')
                ->nullOnDelete();
            $table->index(['presupuesto_concepto_id', 'orden'], 'ppto_lin_comp_padre_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_concepto_componentes');

        if (Schema::hasColumn('presupuesto_conceptos', 'tiene_matriz')) {
            Schema::table('presupuesto_conceptos', function (Blueprint $table) {
                $table->dropColumn('tiene_matriz');
            });
        }

        if (Schema::hasColumn('presupuestos', 'config_mostrar_matriz_costos')) {
            Schema::table('presupuestos', function (Blueprint $table) {
                $table->dropColumn('config_mostrar_matriz_costos');
            });
        }
    }
};
