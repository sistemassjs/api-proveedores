<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facturas asociadas a un pago (principalmente pagos origen=directo).
     * Un pago puede abarcar N facturas; PDF/XML pueden estar incompletos.
     */
    public function up(): void
    {
        Schema::connection('mysql5')->create('pago_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_spp_id')
                ->constrained('pagos_spp')
                ->cascadeOnDelete();
            $table->string('folio_factura', 100)->nullable();
            $table->string('ruta_archivo_factura_pdf', 500)->nullable();
            $table->string('ruta_archivo_factura_xml', 500)->nullable();
            $table->string('metodo_pago', 10)->nullable()->comment('PUE | PPD');
            $table->decimal('monto', 15, 2)->nullable();
            $table->timestamps();

            $table->index('pago_spp_id');
            $table->index('folio_factura');
            $table->index('metodo_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql5')->dropIfExists('pago_facturas');
    }
};
