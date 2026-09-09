<?php

use App\Enums\EstadoUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MIGRATION_ID = '2026_05_29_160000_sync_proveedor_registro_completado_estatus';

    private const TABLAS_RESPALDO = [
        'proveedores' => 'TEMP_proveedores',
        'users' => 'TEMP_users',
        'user_proveedor' => 'TEMP_user_proveedor',
    ];

    public function up(): void
    {
        $this->log('info', 'Inicio de migración', [
            'migration' => self::MIGRATION_ID,
            'fase' => 'up',
        ]);

        $resumen = [
            'migration' => self::MIGRATION_ID,
            'inicio' => now()->toIso8601String(),
            'respaldos' => [],
            'enum_estatus' => null,
            'proveedores_registro_completado' => 0,
            'proveedores_bloqueados' => 0,
        ];

        foreach (self::TABLAS_RESPALDO as $origen => $destino) {
            $filas = $this->clonarTablaRespaldo($origen, $destino);
            $resumen['respaldos'][$destino] = [
                'tabla_origen' => $origen,
                'filas_clonadas' => $filas,
            ];
            $this->log('info', 'Respaldo TEMP_* creado', [
                'origen' => $origen,
                'destino' => $destino,
                'filas_clonadas' => $filas,
            ]);
        }

        $values = implode("','", EstadoUsuario::values());
        DB::statement(
            "ALTER TABLE proveedores MODIFY COLUMN estatus ENUM('{$values}') NOT NULL DEFAULT '"
                . EstadoUsuario::REGISTRADO->value
                . "'"
        );
        $resumen['enum_estatus'] = EstadoUsuario::values();
        $this->log('info', 'ENUM estatus actualizado en proveedores', [
            'valores' => $resumen['enum_estatus'],
        ]);

        $proveedorIdsConUsuario = DB::table('user_proveedor')
            ->distinct()
            ->pluck('proveedor_id');

        $this->log('info', 'Proveedores con relación en user_proveedor', [
            'cantidad_ids' => $proveedorIdsConUsuario->count(),
        ]);

        if ($proveedorIdsConUsuario->isNotEmpty()) {
            $ids = $proveedorIdsConUsuario->all();
            $candidatosCompletado = DB::table('proveedores')
                ->whereIn('id', $ids)
                ->count();

            $resumen['proveedores_registro_completado'] = DB::table('proveedores')
                ->whereIn('id', $ids)
                ->update([
                    'estatus' => EstadoUsuario::REGISTRO_COMPLETADO->value,
                    'registro_completado_at' => DB::raw('COALESCE(registro_completado_at, updated_at, created_at, NOW())'),
                ]);

            $this->log('info', 'Proveedores marcados como registro_completado', [
                'candidatos' => $candidatosCompletado,
                'filas_actualizadas' => $resumen['proveedores_registro_completado'],
                'estatus' => EstadoUsuario::REGISTRO_COMPLETADO->value,
            ]);
        }

        $idsExcluidos = $proveedorIdsConUsuario->isEmpty() ? [0] : $proveedorIdsConUsuario->all();
        $campoFecha = Schema::hasColumn('proveedores', 'fecha_registro') ? 'fecha_registro' : 'created_at';
        $fechaLimite = now()->toDateString();

        $candidatosBloqueo = DB::table('proveedores')
            ->whereNotIn('id', $idsExcluidos)
            ->whereNull('registro_completado_at')
            ->whereDate($campoFecha, '<=', $fechaLimite)
            ->count();

        $resumen['proveedores_bloqueados'] = DB::table('proveedores')
            ->whereNotIn('id', $idsExcluidos)
            ->whereNull('registro_completado_at')
            ->whereDate($campoFecha, '<=', $fechaLimite)
            ->update([
                'estatus' => EstadoUsuario::BLOQUEADO->value,
            ]);

        $this->log('info', 'Proveedores sin usuario marcados como bloqueado', [
            'campo_fecha_usado' => $campoFecha,
            'candidatos' => $candidatosBloqueo,
            'filas_actualizadas' => $resumen['proveedores_bloqueados'],
            'estatus' => EstadoUsuario::BLOQUEADO->value,
        ]);

        $resumen['fin'] = now()->toIso8601String();
        $resumen['resumen_humano'] = sprintf(
            'Respaldos: %s | ENUM ampliado con registro_completado | %d proveedor(es) con usuario → registro_completado | %d proveedor(es) sin usuario (fecha <= hoy) → bloqueado',
            implode(', ', array_keys($resumen['respaldos'])),
            $resumen['proveedores_registro_completado'],
            $resumen['proveedores_bloqueados']
        );

        $this->log('info', 'RESUMEN MIGRACIÓN', $resumen);
    }

    public function down(): void
    {
        $this->log('info', 'Inicio rollback', [
            'migration' => self::MIGRATION_ID,
            'fase' => 'down',
        ]);

        $revertidos = DB::table('proveedores')
            ->where('estatus', EstadoUsuario::REGISTRO_COMPLETADO->value)
            ->update(['estatus' => EstadoUsuario::REGISTRADO->value]);

        $this->log('info', 'Estatus registro_completado revertido a registrado', [
            'filas_actualizadas' => $revertidos,
        ]);

        $values = implode("','", array_filter(
            EstadoUsuario::values(),
            fn(string $v) => $v !== EstadoUsuario::REGISTRO_COMPLETADO->value
        ));
        DB::statement(
            "ALTER TABLE proveedores MODIFY COLUMN estatus ENUM('{$values}') NOT NULL DEFAULT '"
                . EstadoUsuario::REGISTRADO->value
                . "'"
        );

        $this->log('info', 'ENUM estatus sin registro_completado', [
            'valores' => array_filter(
                EstadoUsuario::values(),
                fn(string $v) => $v !== EstadoUsuario::REGISTRO_COMPLETADO->value
            ),
        ]);

        $this->log('info', 'RESUMEN ROLLBACK', [
            'migration' => self::MIGRATION_ID,
            'proveedores_revertidos_a_registrado' => $revertidos,
            'nota' => 'Las tablas TEMP_* no se eliminan en rollback; pueden usarse para restauración manual.',
            'tablas_respaldo' => array_values(self::TABLAS_RESPALDO),
        ]);
    }

    private function clonarTablaRespaldo(string $tablaOrigen, string $tablaDestino): int
    {
        if (! Schema::hasTable($tablaOrigen)) {
            $this->log('warning', 'Tabla origen no existe; se omite clonado', [
                'tabla_origen' => $tablaOrigen,
            ]);

            return 0;
        }

        DB::statement("DROP TABLE IF EXISTS `{$tablaDestino}`");
        DB::statement("CREATE TABLE `{$tablaDestino}` LIKE `{$tablaOrigen}`");
        DB::statement("INSERT INTO `{$tablaDestino}` SELECT * FROM `{$tablaOrigen}`");

        return (int) DB::table($tablaDestino)->count();
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $payload = array_merge([
            'migration' => self::MIGRATION_ID,
        ], $context);

        try {
            if (is_array(config('logging.channels.migrations'))) {
                Log::channel('migrations')->{$level}($message, $payload);

                return;
            }
        } catch (\Throwable) {
            // Canal no disponible (p. ej. config en caché antigua); escribir directo al archivo.
        }

        $line = json_encode([
            'datetime' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
            'context' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $path = storage_path('logs/migrations.log');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
};
