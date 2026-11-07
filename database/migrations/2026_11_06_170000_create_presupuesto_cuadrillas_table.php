<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('presupuesto_cuadrillas')) {
            Schema::create('presupuesto_cuadrillas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('presupuesto_id');
                $table->unsignedInteger('orden')->default(0);
                $table->string('clave', 40)->nullable();
                $table->string('nombre', 150);
                $table->string('integrantes', 500)->nullable();
                $table->string('unidad', 50)->default('jor');
                $table->decimal('costo_unitario', 15, 4)->default(0);
                $table->timestamps();

                $table->foreign('presupuesto_id', 'ppto_cua_presupuesto_fk')
                    ->references('id')
                    ->on('presupuestos')
                    ->cascadeOnDelete();
                $table->index(['presupuesto_id', 'orden'], 'ppto_cua_presupuesto_orden_idx');
            });
        }

        foreach ([
            'presupuesto_concepto_componentes',
            'presupuesto_plantilla_concepto_componentes',
        ] as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'presupuesto_cuadrilla_id')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->unsignedBigInteger('presupuesto_cuadrilla_id')->nullable()->after('catalogo_concepto_id');
                if ($tabla === 'presupuesto_concepto_componentes') {
                    $table->foreign('presupuesto_cuadrilla_id', 'ppto_lin_comp_cua_fk')
                        ->references('id')
                        ->on('presupuesto_cuadrillas')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'presupuesto_concepto_componentes',
            'presupuesto_plantilla_concepto_componentes',
        ] as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'presupuesto_cuadrilla_id')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if ($tabla === 'presupuesto_concepto_componentes') {
                    $table->dropForeign('ppto_lin_comp_cua_fk');
                }
                $table->dropColumn('presupuesto_cuadrilla_id');
            });
        }

        Schema::dropIfExists('presupuesto_cuadrillas');
    }
};
