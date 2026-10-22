<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige niveles de categorías creados por import CSV legacy:
 * raíz (parent_id null) → nivel 0; hija directa de raíz → nivel 1.
 * Alineado con ProveedorCategoriaController y ProductoStore/UpdateRequest.
 */
return new class extends Migration
{
    private function connectionName(): string
    {
        return (new \App\Models\Categoria)->getConnectionName() ?: config('database.default');
    }

    public function up(): void
    {
        $log = Log::channel('migrations');
        $log->info('fix_categorias_nivel_raiz_importacion: inicio');

        $connection = $this->connectionName();
        $schema = Schema::connection($connection);
        $db = DB::connection($connection);

        if (! $schema->hasTable('categorias') || ! $schema->hasColumn('categorias', 'nivel')) {
            $log->info('fix_categorias_nivel_raiz_importacion: tabla/columna ausente, skip');

            return;
        }

        $raices = $db->table('categorias')
            ->whereNull('parent_id')
            ->where('nivel', '!=', 0)
            ->update(['nivel' => 0, 'updated_at' => now()]);

        $hijaIds = $db->table('categorias as c')
            ->join('categorias as p', 'c.parent_id', '=', 'p.id')
            ->whereNull('p.parent_id')
            ->where('c.nivel', '!=', 1)
            ->pluck('c.id');

        $hijas = 0;
        if ($hijaIds->isNotEmpty()) {
            $hijas = $db->table('categorias')
                ->whereIn('id', $hijaIds)
                ->update(['nivel' => 1, 'updated_at' => now()]);
        }

        $log->info('fix_categorias_nivel_raiz_importacion: fin', [
            'connection' => $connection,
            'raices_actualizadas' => $raices,
            'hijas_directas_actualizadas' => $hijas,
        ]);
    }

    /**
     * No reversible de forma segura: no se puede distinguir
     * raíces que siempre fueron 0 de las corregidas desde 1.
     */
    public function down(): void
    {
        Log::channel('migrations')->info(
            'fix_categorias_nivel_raiz_importacion: down omitido (dato no reversible)'
        );
    }
};
