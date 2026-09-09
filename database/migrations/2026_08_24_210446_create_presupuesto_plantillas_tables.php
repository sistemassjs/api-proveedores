<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantillas de presupuesto: recurso aislado del documento `presupuestos`.
     */
    public function up(): void
    {
        Schema::create('presupuesto_plantillas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('concepto_general', 500)->nullable();
            $table->string('titulo_anexos', 80)->nullable();
            $table->string('titulo_anexos_pdf', 80)->nullable();
            $table->boolean('con_iva')->default(true);
            $table->decimal('iva_porcentaje', 5, 2)->default(16);
            $table->unsignedInteger('porcentaje_descuento')->nullable();
            $table->decimal('cantidad_descuento', 15, 2)->nullable();
            $table->unsignedInteger('term_cond_dias_vigencia')->nullable();
            $table->string('term_cond_moneda', 10)->default('MXN');
            $table->boolean('term_cond_impuestos_en_pdf')->default(true);
            $table->decimal('term_cond_iva', 5, 2)->nullable();
            $table->unsignedInteger('term_cond_tiempo_entrega_dias')->nullable();
            $table->unsignedTinyInteger('term_cond_inicio_trabajo')->nullable();
            $table->decimal('term_cond_inicio_trabajo_porcentaje', 5, 2)->nullable();
            $table->decimal('term_cond_inicio_trabajo_cantidad', 15, 2)->nullable();
            $table->json('term_cond_textos_libres')->nullable();
            $table->json('term_cond_visibilidad')->nullable();
            $table->json('validacion_alcances')->nullable();
            $table->json('configuracion_condiciones')->nullable();
            $table->unsignedInteger('obs_garantia_dias')->nullable();
            $table->boolean('config_mostrar_totales')->default(true);
            $table->string('pdf_theme', 50)->nullable();
            $table->json('ppto_config')->nullable();
            $table->foreignId('config_emisor_presupuesto_id')
                ->nullable()
                ->constrained('config_emisor_receptor_presupuestos')
                ->nullOnDelete();
            $table->string('empresa_emisora_nombre')->nullable();
            $table->string('empresa_emisora_puesto')->nullable();
            $table->string('empresa_emisora_telefono')->nullable();
            $table->string('empresa_emisora_correo')->nullable();
            $table->boolean('incluir_leyenda_atentamente')->default(true);
            $table->string('empresa_emisora_nombre_comercial')->nullable();
            $table->timestamps();

            $table->index(['proveedor_id', 'activo']);
            $table->index(['proveedor_id', 'nombre']);
        });

        Schema::create('presupuesto_plantilla_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_plantilla_id')
                ->constrained('presupuesto_plantillas')
                ->cascadeOnDelete();
            $table->unsignedInteger('numero')->default(1);
            $table->string('tipo', 20)->default('concepto');
            $table->text('descripcion');
            $table->decimal('cantidad', 15, 4)->default(1);
            $table->string('unidad', 50)->nullable();
            $table->decimal('precio_unitario', 15, 2)->default(0);
            $table->string('imagen_path')->nullable();
            $table->timestamps();

            $table->index(['presupuesto_plantilla_id', 'numero'], 'ppc_plantilla_numero_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_plantilla_conceptos');
        Schema::dropIfExists('presupuesto_plantillas');
    }
};
