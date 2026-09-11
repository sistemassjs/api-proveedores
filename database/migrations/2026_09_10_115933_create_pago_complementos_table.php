<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complementos de pago (CFDI tipo P) ligados a una factura del pago.
     * Una factura PPD puede tener 0..N complementos.
     */
    public function up(): void
    {
        Schema::connection('mysql5')->create('pago_complementos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_factura_id')
                ->constrained('pago_facturas')
                ->cascadeOnDelete();
            $table->foreignId('pago_spp_id')
                ->constrained('pagos_spp')
                ->cascadeOnDelete();
            $table->string('folio_complemento', 100)->nullable();
            $table->string('ruta_archivo_pdf', 500)->nullable();
            $table->string('ruta_archivo_xml', 500)->nullable();
            $table->timestamps();

            $table->index('pago_factura_id');
            $table->index('pago_spp_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql5')->dropIfExists('pago_complementos');
    }
};
