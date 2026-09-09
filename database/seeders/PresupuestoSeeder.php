<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Presupuesto;
use App\Models\PresupuestoConcepto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Str;

class PresupuestoSeeder extends Seeder
{
    public function run(): void
    {
        $proveedores = Proveedor::all();
        $usuarios = User::all();

        if ($proveedores->isEmpty() || $usuarios->isEmpty()) {
            $this->command->warn('No hay proveedores o usuarios.');
            return;
        }

        for ($i = 1; $i <= 50; $i++) {

            $proveedor = $proveedores->random();
            $user = $usuarios->random();

            // Fechas coherentes
            $fechaEmision = fake()->dateTimeBetween('-90 days', 'now');
            $fechaVencimiento = fake()->dateTimeBetween($fechaEmision, '+45 days');

            // Estado coherente con fecha
            if ($fechaVencimiento < now()) {
                $estado = Presupuesto::ESTADO_VENCIDO;
            } else {
                $estado = collect([
                    Presupuesto::ESTADO_BORRADOR,
                    Presupuesto::ESTADO_ENVIADO,
                    Presupuesto::ESTADO_ACEPTADO,
                    Presupuesto::ESTADO_RECHAZADO,
                ])->random();
            }

            $presupuesto = Presupuesto::create([
                'uuid' => (string) Str::uuid(),
                'numero_presupuesto' => Presupuesto::generarNumeroPresupuesto($proveedor->id),

                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,

                'concepto_general' => fake()->sentence(),
                'nombre_presupuesto' => fake()->words(3, true),

                'con_iva' => fake()->boolean(80),
                'iva_porcentaje' => 16,

                'empresa_receptora_nombre' => fake()->name(),
                'empresa_receptora_empresa' => fake()->company(),
                'empresa_receptora_correo' => fake()->safeEmail(),

                'term_cond_dias_vigencia' => rand(7, 30),
                'term_cond_moneda' => collect(['MXN', 'USD'])->random(),
                'term_cond_tiempo_entrega_dias' => rand(3, 25),
                'term_cond_inicio_trabajo' => 1,
                'term_cond_iva' => 16,
                'term_cond_impuestos_en_pdf' => true,

                'obs_garantia_dias' => rand(30, 365),

                'configuracion_condiciones' => [
                    'vigencia_activo' => true,
                    'moneda_activo' => true,
                    'impuestos_activo' => true,
                    'tiempo_entrega_activo' => true,
                    'inicio_trabajos_activo' => true,
                ],

                'term_cond_visibilidad' => [
                    'pago_contra_conformidad' => true,
                    'garantia_calidad' => true,
                    'correccion_defectos' => true,
                    'incluye_materiales_insumos' => true,
                ],

                'validacion_alcances' => [
                    'incluye_todos_los_costos' => true,
                    'sin_costos_adicionales_no_autorizados' => true,
                    'adicionales_requieren_autorizacion_escrita' => true,
                ],

                'estado' => $estado,

                'proveedor_id' => $proveedor->id,
                'user_id' => $user->id,
            ]);

            $totalConceptos = rand(2, 6);

            for ($j = 1; $j <= $totalConceptos; $j++) {

                $concepto = new PresupuestoConcepto([
                    'numero' => $j,
                    'descripcion' => fake()->sentence(4),
                    'cantidad' => rand(1, 15),
                    'unidad' => collect(['pza', 'servicio', 'm2', 'kg'])->random(),
                    'precio_unitario' => rand(100, 5000),
                ]);

                $concepto->calcularImporte();

                $presupuesto->conceptos()->save($concepto);
            }

            $presupuesto->calcularTotales();
            $presupuesto->save();
        }
    }
}