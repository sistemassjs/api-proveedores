<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Configurado para Los Mochis, Sinaloa, México
     */
    public function run(): void
    {
        // ====================================================================
        // SEEDERS BASE (SIEMPRE SE EJECUTAN)
        // ====================================================================
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            TipoEmpresaSeeder::class,
        ]);

        if (config('app.debug')) {
            // ================================================================
            // SEEDERS DE DESARROLLO Y DATOS DE PRUEBA
            // ================================================================

            // Seeders base para catálogos y proveedores
            $this->call([
                // Proveedores originales
                ProveedorSeeder::class,


                // Catálogos básicos
                SucursalSeeder::class,
                UnidadMedidaSeeder::class,
                CatalogoOpusFamiliasSeeder::class,
                CategoriaSeeder::class,
                MarcaSeeder::class,

                // Productos (requerido para CotizacionDetalle)
                ProductoSeeder::class,

                // Acceso rápido y cotizaciones originales
                AccesoRapidoSeeder::class,
                CotizacionesSeeder::class,

                // Presupuestos para todos los proveedores (borrador, enviado, aceptado, rechazado, vencido)
                // PresupuestosSeeder::class,
                // Presupuestos con 1, 5, 10, 20, 30, 50 conceptos (para pruebas de duplicación/listas)
                // PresupuestosVariadosConceptosSeeder::class,
                // PedidosSeeder::class,

                // OrdenCompraSeeder::class,
                EmpresaConstruccSeeder::class,
            ]);

            // ================================================================
            // SEEDERS SP (SOLICITUDES DE PAGO) - Los Mochis, Sinaloa
            // ================================================================
            // IMPORTANTE: Ejecutar en este orden específico

            $this->call([
                // Empresas constructoras (requerido para SolicitudPago)
                EmpresaConstruccSeeder::class,

                // 1. Proveedores SP adicionales
                ProveedoresSPSeeder::class,

                // 2. Cotizaciones para proveedores SP
                CotizacionesSPSeeder::class,

                // 3. Detalles de cotizaciones SP
                CotizacionDetalleSeeder::class,

                // 4. Órdenes de compra (requerido antes de SP para relaciones)
                // OrdenCompraSeeder::class,

                // 5. Solicitudes de pago basadas en cotizaciones
                // SolicitudPagoSeeder::class,

                // PagosSPPTestSeeder::class,
            ]);

            echo "\n";
            echo "\t Seeders ejecutados correctamente para Los Mochis, Sinaloa, México\n";
            echo "\t \t Zona horaria configurada: America/Mazatlan\n";
            echo "\t \t Datos de proveedores SP y solicitudes de pago generados\n";
            echo "\n";
        }

        // ====================================================================
        // SEEDERS COMENTADOS (PARA REFERENCIA)
        // ====================================================================
        // $this->call([UserSeeder::class]);

        // TipoEmpresa::factory()->count(10)->create();
        // Proveedor::factory()->count(10)->create();
        // UnidadMedida::factory()->count(5)->create();
        // Categoria::factory()->count(5)->create();
        // Marca::factory()->count(5)->create();
        // Producto::factory()->count(1000)->create();
    }
}
