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
        Schema::table('import_audits', function (Blueprint $table) {
            $table->string('plantilla_version', 20)->nullable()->after('formato');
            $table->date('plantilla_fecha')->nullable()->after('plantilla_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_audits', function (Blueprint $table) {
            $table->dropColumn(['plantilla_version', 'plantilla_fecha']);
        });
    }
};
