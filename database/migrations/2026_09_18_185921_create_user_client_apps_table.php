<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_client_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('app_key', 32);
            $table->timestamps();

            $table->unique(['user_id', 'app_key'], 'user_client_apps_user_app_unique');
            $table->index(['app_key'], 'user_client_apps_app_key_idx');
        });

        // Usuarios existentes: acceso GestionPlus (compatibilidad).
        $now = now();
        DB::table('users')->orderBy('id')->chunkById(500, function ($users) use ($now) {
            $rows = [];
            foreach ($users as $user) {
                $rows[] = [
                    'user_id' => $user->id,
                    'app_key' => 'gestion',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('user_client_apps')->insertOrIgnore($rows);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_client_apps');
    }
};
