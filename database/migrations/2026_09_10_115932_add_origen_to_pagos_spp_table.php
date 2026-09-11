<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * origen=spp: pago ligado a SPP (flujo con autorización).
     * origen=directo: pago sin SPP ni aprobación.
     */
    public function up(): void
    {
        Schema::connection('mysql5')->table('pagos_spp', function (Blueprint $table) {
            $table->string('origen', 20)
                ->default('spp')
                ->after('empresa_construcc_id')
                ->comment('spp | directo');
            $table->index('origen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql5')->table('pagos_spp', function (Blueprint $table) {
            $table->dropIndex(['origen']);
            $table->dropColumn('origen');
        });
    }
};
