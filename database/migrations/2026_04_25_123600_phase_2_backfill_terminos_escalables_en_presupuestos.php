<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fase 2:
     * - Migra datos legacy hacia los nuevos JSON escalables.
     * - No elimina columnas viejas todavía (seguridad para despliegue gradual).
     */
    public function up(): void
    {
        DB::table('presupuestos')
            ->select([
                'id',
                'configuracion_condiciones',
                'obs_traslados',
                'obs_viaticos',
                'term_cond_textos_libres',
                'term_cond_visibilidad',
                'validacion_alcances',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $config = json_decode((string) ($row->configuracion_condiciones ?? ''), true);
                    if (! is_array($config)) {
                        $config = [];
                    }

                    $textosLibres = [
                        (string) ($config['condicionantes_adicionales_1'] ?? ''),
                        (string) ($config['condicionantes_adicionales_2'] ?? ''),
                        (string) ($config['condicionantes_adicionales_3'] ?? ''),
                        (string) ($config['condicionantes_adicionales_4'] ?? ''),
                    ];
                    $textosLibres = array_values(array_filter(array_map('trim', $textosLibres), fn ($txt) => $txt !== ''));

                    $visibilidad = [
                        'pago_contra_conformidad' => true,
                        'garantia_calidad' => true,
                        'correccion_defectos' => true,
                        'incluye_materiales_insumos' => true,
                        // Se mantiene continuidad con datos legacy.
                        'incluye_traslados' => (bool) $row->obs_traslados,
                        'incluye_viaticos' => (bool) $row->obs_viaticos,
                    ];

                    $alcances = [
                        'incluye_todos_los_costos' => true,
                        'sin_costos_adicionales_no_autorizados' => true,
                        'adicionales_requieren_autorizacion_escrita' => true,
                    ];

                    DB::table('presupuestos')
                        ->where('id', $row->id)
                        ->update([
                            'term_cond_textos_libres' => empty($textosLibres) ? null : json_encode($textosLibres, JSON_UNESCAPED_UNICODE),
                            'term_cond_visibilidad' => json_encode($visibilidad, JSON_UNESCAPED_UNICODE),
                            'validacion_alcances' => json_encode($alcances, JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('presupuestos')->update([
            'term_cond_textos_libres' => null,
            'term_cond_visibilidad' => null,
            'validacion_alcances' => null,
        ]);
    }
};

