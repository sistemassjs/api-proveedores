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
        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->string('app_key', 32)->default('gestion')->after('user_id');
            $table->index(['user_id', 'app_key', 'is_active'], 'user_device_app_active_idx');
        });

        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->dropUnique('user_device_unique');
        });

        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->unique(['user_id', 'app_key', 'device_id'], 'user_device_app_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->dropUnique('user_device_app_unique');
            $table->dropIndex('user_device_app_active_idx');
            $table->dropColumn('app_key');
        });

        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->unique(['user_id', 'device_id'], 'user_device_unique');
        });
    }
};
