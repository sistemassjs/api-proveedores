<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite repetir el RFC genérico SAT XAXX010101000 en proveedores,
 * manteniendo unicidad del resto de RFCs vía columna generada.
 *
 * Nota: el unique histórico era global; sin este cambio la validación Laravel
 * no basta (MySQL rechaza el segundo insert).
 */
return new class extends Migration
{
    private const CONNECTION = 'mysql5';

    private const RFC_GENERICO = 'XAXX010101000';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $db = DB::connection(self::CONNECTION);

        if (!$schema->hasTable('proveedores')) {
            return;
        }

        // Quitar unique absoluto sobre rfc (el índice no-único de búsqueda puede quedarse).
        // Blueprint::dropUnique no se puede try/catch (se ejecuta al cerrar el closure).
        $this->dropIndexIfExists('proveedores', 'proveedores_rfc_unique');

        if (!$schema->hasColumn('proveedores', 'rfc_unico')) {
            // MySQL: NULL/NOT NULL va después de VIRTUAL|STORED, no antes de GENERATED.
            // `rfc` es string(255) en el esquema original; rfc_unico debe caber igual.
            $rfc = self::RFC_GENERICO;
            $db->statement("
                ALTER TABLE `proveedores`
                ADD COLUMN `rfc_unico` VARCHAR(255)
                GENERATED ALWAYS AS (
                    IF(UPPER(`rfc`) = '{$rfc}', NULL, `rfc`)
                ) STORED NULL
            ");
        }

        $schema->table('proveedores', function (Blueprint $table) {
            $table->unique('rfc_unico', 'proveedores_rfc_unico_unique');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (!$schema->hasTable('proveedores')) {
            return;
        }

        $this->dropIndexIfExists('proveedores', 'proveedores_rfc_unico_unique');

        if ($schema->hasColumn('proveedores', 'rfc_unico')) {
            $schema->table('proveedores', function (Blueprint $table) {
                $table->dropColumn('rfc_unico');
            });
        }

        // Falla si ya existen varios XAXX010101000.
        $schema->table('proveedores', function (Blueprint $table) {
            $table->unique('rfc');
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            DB::connection(self::CONNECTION)->statement(
                "ALTER TABLE `{$table}` DROP INDEX `{$index}`"
            );
        } catch (\Throwable $e) {
            // Índice ausente o nombre distinto
        }
    }
};
