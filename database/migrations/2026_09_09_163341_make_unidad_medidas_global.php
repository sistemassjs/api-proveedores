<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Convierte unidad_medidas de catálogo por proveedor a catálogo global:
     * deduplica por nombre, reapunta FKs de productos y elimina proveedor_id.
     */
    public function up(): void
    {
        $log = Log::channel('migrations');
        $log->info('make_unidad_medidas_global: inicio');

        if (! Schema::hasColumn('unidad_medidas', 'proveedor_id')) {
            $log->info('make_unidad_medidas_global: proveedor_id ya no existe, skip');

            return;
        }

        // Mapa nombre normalizado → id canónico (menor id)
        $canonicalByNombre = [];
        $rows = DB::table('unidad_medidas')->orderBy('id')->get(['id', 'nombre']);

        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string) $row->nombre));
            if ($key === '') {
                $key = '__empty__';
            }
            if (! isset($canonicalByNombre[$key])) {
                $canonicalByNombre[$key] = (int) $row->id;
            }
        }

        $remap = [];
        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string) $row->nombre));
            if ($key === '') {
                $key = '__empty__';
            }
            $canonicalId = $canonicalByNombre[$key];
            if ((int) $row->id !== $canonicalId) {
                $remap[(int) $row->id] = $canonicalId;
            }
        }

        $log->info('make_unidad_medidas_global: duplicados a fusionar', [
            'total_unidades' => $rows->count(),
            'canonicos' => count($canonicalByNombre),
            'remap_count' => count($remap),
        ]);

        if ($remap !== []) {
            foreach ($remap as $fromId => $toId) {
                DB::table('productos')
                    ->where('unidad_medida_id', $fromId)
                    ->update(['unidad_medida_id' => $toId]);
            }

            DB::table('unidad_medidas')->whereIn('id', array_keys($remap))->delete();
        }

        Schema::table('unidad_medidas', function (Blueprint $table) {
            // Unique por proveedor previo (nombre, proveedor_id)
            try {
                $table->dropUnique(['nombre', 'proveedor_id']);
            } catch (\Throwable $e) {
                // Nombre de índice puede variar según motor
            }
        });

        // Intentar dropear unique por nombre de índice MySQL típico
        $this->dropIndexIfExists('unidad_medidas', 'unidad_medidas_nombre_proveedor_id_unique');

        Schema::table('unidad_medidas', function (Blueprint $table) {
            try {
                $table->dropForeign(['proveedor_id']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('unidad_medidas', function (Blueprint $table) {
            if (Schema::hasColumn('unidad_medidas', 'proveedor_id')) {
                $table->dropColumn('proveedor_id');
            }
        });

        Schema::table('unidad_medidas', function (Blueprint $table) {
            $table->unique('nombre');
        });

        $log->info('make_unidad_medidas_global: fin');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unidad_medidas', function (Blueprint $table) {
            try {
                $table->dropUnique(['nombre']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        $this->dropIndexIfExists('unidad_medidas', 'unidad_medidas_nombre_unique');

        Schema::table('unidad_medidas', function (Blueprint $table) {
            $table->foreignId('proveedor_id')
                ->nullable()
                ->after('id')
                ->constrained('proveedores')
                ->nullOnDelete();
            $table->unique(['nombre', 'proveedor_id']);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
        if ($indexes !== []) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
