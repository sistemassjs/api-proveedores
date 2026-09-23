<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Segmenta tokens FCM por app cliente (gestion | nexprov).
 * Legacy → app_key = gestion (compatibilidad GestionPlus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->string('app_key', 32)->default('gestion')->after('user_id');
            $table->index(['user_id', 'app_key', 'is_active'], 'user_device_tokens_user_app_active_idx');
        });

        // Backfill explícito (por si el default no aplica en el motor)
        DB::table('user_device_tokens')->whereNull('app_key')->orWhere('app_key', '')->update([
            'app_key' => 'gestion',
        ]);

        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->dropUnique('user_device_unique');
            $table->unique(['user_id', 'device_id', 'app_key'], 'user_device_app_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->dropUnique('user_device_app_unique');
            $table->dropIndex('user_device_tokens_user_app_active_idx');
            $table->dropColumn('app_key');
            $table->unique(['user_id', 'device_id'], 'user_device_unique');
        });
    }
};
