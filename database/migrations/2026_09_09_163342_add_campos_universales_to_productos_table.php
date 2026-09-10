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
        Schema::table('productos', function (Blueprint $table) {
            $table->string('tipo', 30)->nullable()->after('modelo');
            $table->string('codigo_fabricante', 100)->nullable()->after('codigo_interno');
            $table->string('codigo_barras', 100)->nullable()->after('codigo_fabricante');

            $table->string('presentacion', 255)->nullable()->after('unidad_medida_id');
            $table->decimal('cantidad_contenida', 15, 4)->nullable()->after('presentacion');
            $table->foreignId('unidad_contenido_id')
                ->nullable()
                ->after('cantidad_contenida')
                ->constrained('unidad_medidas')
                ->nullOnDelete();
            $table->foreignId('unidad_base_id')
                ->nullable()
                ->after('unidad_contenido_id')
                ->constrained('unidad_medidas')
                ->nullOnDelete();
            $table->decimal('factor_conversion', 15, 6)->nullable()->after('unidad_base_id');

            $table->string('disponibilidad', 50)->nullable()->after('stock');
            $table->string('tiempo_entrega', 100)->nullable()->after('disponibilidad');
            $table->string('url_producto', 500)->nullable()->after('imagen_principal');
            $table->json('tags')->nullable()->after('url_producto');

            $table->foreignId('familia_id')
                ->nullable()
                ->after('subcategoria_id')
                ->constrained('catalogo_familias')
                ->nullOnDelete();
            $table->foreignId('subfamilia_id')
                ->nullable()
                ->after('familia_id')
                ->constrained('catalogo_subfamilias')
                ->nullOnDelete();

            $table->index(['proveedor_id', 'codigo_barras'], 'idx_proveedor_codigo_barras');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['familia_id']);
            $table->dropForeign(['subfamilia_id']);
            $table->dropForeign(['unidad_contenido_id']);
            $table->dropForeign(['unidad_base_id']);

            $table->dropIndex('idx_proveedor_codigo_barras');

            $table->dropColumn([
                'tipo',
                'codigo_fabricante',
                'codigo_barras',
                'presentacion',
                'cantidad_contenida',
                'unidad_contenido_id',
                'unidad_base_id',
                'factor_conversion',
                'disponibilidad',
                'tiempo_entrega',
                'url_producto',
                'tags',
                'familia_id',
                'subfamilia_id',
            ]);
        });
    }
};
