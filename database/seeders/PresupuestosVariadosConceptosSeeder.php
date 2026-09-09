<?php

namespace Database\Seeders;

use App\Models\CarteraCliente;
use App\Models\Presupuesto;
use App\Models\PresupuestoConcepto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\UserProveedor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeder que genera presupuestos con distinto número de conceptos (1, 5, 10, 20, 30, 50).
 * Útil para probar duplicación, reorden y render de listas largas.
 */
class PresupuestosVariadosConceptosSeeder extends Seeder
{
    private const CONCEPTOS_POR_PRESUPUESTO = [1, 5, 10, 20, 30, 50];

    private const PLANTILLAS_CONCEPTO = [
        ['descripcion' => 'Material base unidad', 'cantidad' => 1, 'unidad' => 'pieza', 'precio_unitario' => 150.00],
        ['descripcion' => 'Servicio de instalación', 'cantidad' => 1, 'unidad' => 'servicio', 'precio_unitario' => 850.00],
        ['descripcion' => 'Refacción especializada', 'cantidad' => 2, 'unidad' => 'pieza', 'precio_unitario' => 320.00],
        ['descripcion' => 'Mano de obra por hora', 'cantidad' => 4, 'unidad' => 'hora', 'precio_unitario' => 180.00],
        ['descripcion' => 'Componente eléctrico', 'cantidad' => 1, 'unidad' => 'pieza', 'precio_unitario' => 420.00],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $proveedores = Proveedor::withoutGlobalScopes()
                ->whereIn('id', UserProveedor::where('activo', true)->pluck('proveedor_id'))
                ->orderBy('id')
                // ->limit(2)
                ->get();

            if ($proveedores->isEmpty()) {
                throw new RuntimeException('No hay proveedores. Ejecuta ProveedorSeeder primero.');
            }

            $totalCreados = 0;

            foreach ($proveedores as $proveedor) {
                $userId = $this->resolverUserId($proveedor);
                $clientes = $this->crearClientes($proveedor->id);
                $consecutivo = $this->resolverConsecutivo($proveedor);

                foreach (self::CONCEPTOS_POR_PRESUPUESTO as $numConceptos) {
                    $folio = 'PRES-' . str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
                    $consecutivo++;

                    $presupuesto = Presupuesto::create([
                        'numero_presupuesto' => $folio,
                        'fecha_emision' => now()->toDateString(),
                        'concepto_general' => "Presupuesto de prueba con {$numConceptos} concepto(s) para validar duplicación y listas.",
                        'nombre_presupuesto' => "Prueba {$numConceptos} conceptos",
                        'con_iva' => true,
                        'iva_porcentaje' => 16,
                        'proveedor_id' => $proveedor->id,
                        'empresa_receptora_id' => $clientes['alfa']->id,
                        'empresa_receptora_nombre' => $clientes['alfa']->nombre,
                        'empresa_receptora_puesto' => $clientes['alfa']->puesto,
                        'empresa_receptora_empresa' => $clientes['alfa']->empresa,
                        'empresa_receptora_telefono' => $clientes['alfa']->telefono,
                        'empresa_receptora_correo' => $clientes['alfa']->correo,
                        'user_id' => $userId,
                        'estado' => Presupuesto::ESTADO_BORRADOR,
                        'term_cond_dias_vigencia' => 15,
                        'term_cond_moneda' => 'MXN',
                        'term_cond_iva' => 16,
                        'term_cond_inicio_trabajo' => 2,
                        'term_cond_inicio_trabajo_porcentaje' => 50,
                        'term_cond_tiempo_entrega_dias' => 10,
                        'obs_garantia_dias' => 60,
                        'term_cond_visibilidad' => [
                            'incluye_traslados' => true,
                            'incluye_viaticos' => true,
                        ],
                    ]);

                    $conceptos = $this->generarConceptos($numConceptos);
                    foreach ($conceptos as $i => $c) {
                        $concepto = new PresupuestoConcepto([
                            'numero' => $i + 1,
                            'descripcion' => $c['descripcion'],
                            'cantidad' => $c['cantidad'],
                            'unidad' => $c['unidad'],
                            'precio_unitario' => $c['precio_unitario'],
                        ]);
                        $concepto->calcularImporte();
                        $presupuesto->conceptos()->save($concepto);
                    }

                    $presupuesto->recalcularDesdeConceptos();
                    $presupuesto->save();
                    $totalCreados++;
                }

                $proveedor->consecutivo_presupuesto_siguiente = $consecutivo;
                $proveedor->save();
            }

            $this->command->info("Presupuestos variados creados: {$totalCreados} (con 1, 5, 10, 20, 30, 50 conceptos cada uno).");
        });
    }

    private function generarConceptos(int $cantidad): array
    {
        $conceptos = [];
        $plantillas = self::PLANTILLAS_CONCEPTO;
        for ($i = 0; $i < $cantidad; $i++) {
            $p = $plantillas[$i % count($plantillas)];
            $conceptos[] = [
                'descripcion' => $cantidad > 1 ? "{$p['descripcion']} (item " . ($i + 1) . ")" : $p['descripcion'],
                'cantidad' => $p['cantidad'] * (1 + ($i % 3)),
                'unidad' => $p['unidad'],
                'precio_unitario' => $p['precio_unitario'] * (1 + ($i % 5) * 0.1),
            ];
        }
        return $conceptos;
    }

    private function crearClientes(int $proveedorId): array
    {
        $alfa = CarteraCliente::firstOrCreate(
            ['proveedor_id' => $proveedorId, 'nombre' => 'Laura Méndez', 'empresa' => 'Constructora Alfa Norte S.A.'],
            ['puesto' => 'Directora de Compras', 'telefono' => '6681112201', 'correo' => 'laura.mendez@alfanorte.mx']
        );
        return ['alfa' => $alfa];
    }

    private function resolverUserId(Proveedor $proveedor): int
    {
        $userId = UserProveedor::where('proveedor_id', $proveedor->id)->where('activo', true)->value('user_id');
        if ($userId) {
            return (int) $userId;
        }
        $userId = User::orderBy('id')->value('id');
        if (! $userId) {
            throw new RuntimeException('No hay usuarios. Ejecuta UserSeeder primero.');
        }
        return (int) $userId;
    }

    private function resolverConsecutivo(Proveedor $proveedor): int
    {
        $max = Presupuesto::where('proveedor_id', $proveedor->id)
            ->pluck('numero_presupuesto')
            ->map(fn (?string $n) => preg_match('/^PRES-(\d+)$/', $n ?? '', $m) ? (int) $m[1] : 0)
            ->max() ?? 0;
        return max(1, (int) ($proveedor->consecutivo_presupuesto_siguiente ?? 1), $max + 1);
    }
}
