<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmpresaConstrucc;
use App\Models\Sucursal;
use App\Models\Proveedor;
use App\Enums\EstadoSP;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SolicitudPagoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // --- Dependencias ---
            $proveedores = Proveedor::where('is_proveedor_sp', true)->get();

            if ($proveedores->isEmpty()) {
                echo "⚠️ No hay proveedores SP.\n";
                return;
            }

            $empresasConstrucc = EmpresaConstrucc::where('activo', true)->pluck('id')->toArray();

            if (empty($empresasConstrucc)) {
                echo "⚠️ No hay empresas constructoras.\n";
                return;
            }

            $sucursales = Sucursal::pluck('id')->toArray();

            // --- Rango abril 2026 ---
            $inicio = Carbon::create(2026, 4, 1, 0, 0, 0, 'America/Mazatlan');
            $fin = Carbon::create(2026, 4, 30, 23, 59, 59, 'America/Mazatlan');

            // --- Control de folios ---
            $consecutivos = $this->obtenerSiguienteConsecutivoPorProveedor();

            $solicitudes = [];
            $totalSolicitudes = rand(80, 150);

            // Cachear usuario principal por proveedor
            $usuariosPrincipales = [];

            foreach ($proveedores as $prov) {
                $usuariosPrincipales[$prov->id] = $prov->usuarioPrincipal()?->id;
            }

            for ($i = 0; $i < $totalSolicitudes; $i++) {

                $proveedor = $proveedores->random();
                $proveedorId = $proveedor->id;

                if (!isset($consecutivos[$proveedorId])) {
                    $consecutivos[$proveedorId] = 1;
                }

                $consecutivo = $consecutivos[$proveedorId]++;
                $folio = str_pad($proveedorId, 4, '0', STR_PAD_LEFT) . '-' . $consecutivo;

                $fechaSolicitud = Carbon::createFromTimestamp(
                    rand($inicio->timestamp, $fin->timestamp)
                )->setTimezone('America/Mazatlan');

                $estadoSolicitud = $this->generarEstadoSolicitudAleatorio();

                $solicitudes[] = [
                    'numero_folio_solicitud' => $folio,
                    'descripcion_concepto' => $this->generarDescripcionConceptoFake($proveedor),

                    'ruta_archivo_factura_xml' => $this->generarRutaArchivo('xml', $i + 1, $fechaSolicitud),
                    'ruta_archivo_factura_pdf' => $this->generarRutaArchivo('pdf', $i + 1, $fechaSolicitud),

                    'ruta_archivo_comprobante_pago' => $this->debeGenerarComprobante($estadoSolicitud)
                        ? $this->generarRutaArchivo('comprobante', $i + 1, $fechaSolicitud)
                        : null,

                    'estado_solicitud' => $estadoSolicitud,
                    'usuario_creador_id' => $usuariosPrincipales[$proveedorId] ?? null,
                    'proveedor_id' => $proveedorId,
                    'empresa_construcc_id' => $empresasConstrucc[array_rand($empresasConstrucc)],
                    'residente' => $this->generarNombreResidente(),

                    'cotizacion_id' => null,

                    'sucursal_id' => !empty($sucursales)
                        ? $sucursales[array_rand($sucursales)]
                        : null,

                    'motivo_rechazo' => $estadoSolicitud === EstadoSP::RECHAZADA->value
                        ? $this->generarMotivoRechazo()
                        : null,

                    'dg' => $this->generarEstadoDepartamento($estadoSolicitud, 'dg'),
                    'dg_fecha' => $this->generarFechaEstado($fechaSolicitud, $estadoSolicitud),

                    'dt' => $this->generarEstadoDepartamento($estadoSolicitud, 'dt'),
                    'dt_fecha' => $this->generarFechaEstado($fechaSolicitud, $estadoSolicitud),

                    'pc' => $this->generarEstadoDepartamento($estadoSolicitud, 'pc'),
                    'pc_fecha' => $this->generarFechaEstado($fechaSolicitud, $estadoSolicitud),

                    'si' => $this->generarEstadoDepartamento($estadoSolicitud, 'si'),
                    'si_fecha' => $this->generarFechaEstado($fechaSolicitud, $estadoSolicitud),

                    'ro' => $this->generarEstadoDepartamento($estadoSolicitud, 'ro'),
                    'ro_fecha' => $this->generarFechaEstado($fechaSolicitud, $estadoSolicitud),

                    'created_at' => $fechaSolicitud,
                    'updated_at' => $fechaSolicitud->copy()->addDays(rand(0, 10)),
                ];
            }

            foreach (array_chunk($solicitudes, 50) as $chunk) {
                DB::table('solicitudes_pago')->insert($chunk);
            }

            echo "✅ Seeder ejecutado correctamente.\n";
            echo "📊 Total solicitudes: {$totalSolicitudes}\n";
            echo "📅 Abril 2026\n";
        });
    }

    // ----------- FOLIOS -----------

    private function obtenerSiguienteConsecutivoPorProveedor(): array
    {
        $folios = DB::table('solicitudes_pago')
            ->select('proveedor_id', DB::raw("MAX(numero_folio_solicitud) as ultimo"))
            ->groupBy('proveedor_id')
            ->get();

        $map = [];

        foreach ($folios as $row) {
            if (!$row->ultimo) {
                $map[$row->proveedor_id] = 1;
                continue;
            }

            preg_match('/(\d+)$/', $row->ultimo, $matches);
            $ultimoNumero = isset($matches[1]) ? (int) $matches[1] : 0;

            $map[$row->proveedor_id] = $ultimoNumero + 1;
        }

        return $map;
    }

    // ----------- AUX -----------

    private function generarEstadoSolicitudAleatorio(): string
    {
        $valores = EstadoSP::values();
        return $valores[array_rand($valores)];
    }

    private function generarDescripcionConceptoFake($proveedor): string
    {
        $conceptos = [
            "Pago por suministro - {$proveedor->nombre_comercial}",
            "Pago de servicios contratados",
            "Pago operativo mensual",
            "Liquidación de materiales",
            "Pago administrativo interno",
        ];

        return $conceptos[array_rand($conceptos)];
    }

    private function generarRutaArchivo(string $tipo, int $numero, Carbon $fecha): string
    {
        $año = $fecha->format('Y');
        $mes = $fecha->format('m');

        return match ($tipo) {
            'xml' => "uploads/facturas/xml/{$año}/{$mes}/factura_{$numero}.xml",
            'pdf' => "uploads/facturas/pdf/{$año}/{$mes}/factura_{$numero}.pdf",
            'comprobante' => "uploads/comprobantes/{$año}/{$mes}/comprobante_pago_{$numero}.pdf",
            default => "uploads/{$tipo}/{$año}/{$mes}/archivo_{$numero}.pdf",
        };
    }

    private function debeGenerarComprobante(string $estado): bool
    {
        return in_array($estado, [
            EstadoSP::PAGADO->value,
            EstadoSP::AUTORIZADA->value
        ]) && rand(1, 100) <= 80;
    }

    private function generarNombreResidente(): string
    {
        $nombres = [
            'Ing. Carlos Alberto Mendoza',
            'Arq. María Elena Rodríguez',
            'Ing. José Luis Félix Castro',
            'Ing. Patricia Alejandra Ruiz',
            'Arq. Fernando Javier López',
        ];

        return $nombres[array_rand($nombres)];
    }

    private function generarMotivoRechazo(): string
    {
        $motivos = [
            'Documentación incompleta',
            'Factura no cumple requisitos',
            'Monto excede autorización',
            'Requiere revisión adicional',
        ];

        return $motivos[array_rand($motivos)];
    }

    private function generarEstadoDepartamento(string $estadoSolicitud, string $departamento): int
    {
        if ($estadoSolicitud === EstadoSP::PENDIENTE->value) {
            return 0;
        }

        $probabilidades = [
            'dg' => 90,
            'dt' => 85,
            'pc' => 80,
            'si' => 75,
            'ro' => 70,
        ];

        $p = $probabilidades[$departamento] ?? 70;

        return rand(1, 100) <= $p ? rand(1, 3) : 0;
    }

    private function generarFechaEstado(Carbon $base, string $estadoSolicitud): ?Carbon
    {
        if ($estadoSolicitud === EstadoSP::PENDIENTE->value) {
            return null;
        }

        return rand(1, 100) <= 80
            ? $base->copy()->addDays(rand(1, 20))
            : null;
    }
}