<?php

namespace Database\Seeders;

use App\Enums\EstadoGeneral;
use App\Enums\EstadoUsuario;
use App\Models\AccesoRapido;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\TipoEmpresa;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * TEMPORAL: datos de demo para la tiendita.
 * Ejecutar: php artisan db:seed --class=TiendaTemporalSeeder
 * Idempotente por RFC / SKU con prefijo TEMP-TIENDA.
 */
class TiendaTemporalSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedAccesosRapidos();

            $tipo = TipoEmpresa::firstOrCreate(
                ['clave' => 'comercial'],
                ['nombre' => 'Constructora de Obra Comercial']
            );

            $proveedoresData = [
                [
                    'rfc' => 'TEMPTIENDA001AAA',
                    'nombre_comercial' => 'Demo Aceros Tienda',
                    'razon_social' => 'Demo Aceros Tienda S.A. de C.V.',
                    'email' => 'tienda.demo.aceros@example.com',
                    'logo' => $this->imageUrl('logo-aceros', 200, 200),
                    'calificacion' => 4.8,
                    'categorias' => ['Láminas y Aceros', 'Material de Construcción'],
                    'marcas' => ['AceroMax', 'BuildPro'],
                    'productos' => [
                        ['sku' => 'TEMP-TIENDA-LAM-001', 'nombre' => 'Lámina galvanizada cal.26', 'categoria' => 'Láminas y Aceros', 'marca' => 'AceroMax', 'precio' => 285.50, 'stock' => 120, 'destacado' => true, 'imagen' => $this->imageUrl('lamina-galvanizada')],
                        ['sku' => 'TEMP-TIENDA-VAR-002', 'nombre' => 'Varilla 3/8 corrugada', 'categoria' => 'Material de Construcción', 'marca' => 'BuildPro', 'precio' => 98.00, 'stock' => 500, 'destacado' => true, 'imagen' => $this->imageUrl('varilla-acero')],
                        ['sku' => 'TEMP-TIENDA-CMT-003', 'nombre' => 'Cemento gris 50kg', 'categoria' => 'Material de Construcción', 'marca' => 'BuildPro', 'precio' => 245.00, 'stock' => 80, 'destacado' => false, 'imagen' => $this->imageUrl('cemento-gris')],
                        ['sku' => 'TEMP-TIENDA-PTR-004', 'nombre' => 'PTR 2x2 cal.14', 'categoria' => 'Láminas y Aceros', 'marca' => 'AceroMax', 'precio' => 175.00, 'stock' => 60, 'destacado' => true, 'imagen' => $this->imageUrl('ptr-acero')],
                    ],
                ],
                [
                    'rfc' => 'TEMPTIENDA002BBB',
                    'nombre_comercial' => 'Demo Herramientas Tienda',
                    'razon_social' => 'Demo Herramientas Tienda S.A. de C.V.',
                    'email' => 'tienda.demo.herramientas@example.com',
                    'logo' => $this->imageUrl('logo-herramientas', 200, 200),
                    'calificacion' => 4.5,
                    'categorias' => ['Herramientas Manuales', 'Medición'],
                    'marcas' => ['ToolKing', 'MetroFlex'],
                    'productos' => [
                        ['sku' => 'TEMP-TIENDA-MRT-005', 'nombre' => 'Martillo carpintero 16oz', 'categoria' => 'Herramientas Manuales', 'marca' => 'ToolKing', 'precio' => 189.90, 'stock' => 45, 'destacado' => true, 'imagen' => $this->imageUrl('martillo-carpintero')],
                        ['sku' => 'TEMP-TIENDA-PNZ-006', 'nombre' => 'Pinza universal 8"', 'categoria' => 'Herramientas Manuales', 'marca' => 'ToolKing', 'precio' => 156.00, 'stock' => 70, 'destacado' => false, 'imagen' => $this->imageUrl('pinza-universal')],
                        ['sku' => 'TEMP-TIENDA-CNT-007', 'nombre' => 'Cinta métrica 5m', 'categoria' => 'Medición', 'marca' => 'MetroFlex', 'precio' => 79.50, 'stock' => 200, 'destacado' => true, 'imagen' => $this->imageUrl('cinta-metrica')],
                        ['sku' => 'TEMP-TIENDA-NIV-008', 'nombre' => 'Nivel de burbuja 24"', 'categoria' => 'Medición', 'marca' => 'MetroFlex', 'precio' => 220.00, 'stock' => 35, 'destacado' => false, 'imagen' => $this->imageUrl('nivel-burbuja')],
                    ],
                ],
                [
                    'rfc' => 'TEMPTIENDA003CCC',
                    'nombre_comercial' => 'Demo Eléctricos Tienda',
                    'razon_social' => 'Demo Eléctricos Tienda S.A. de C.V.',
                    'email' => 'tienda.demo.electricos@example.com',
                    'logo' => $this->imageUrl('logo-electricos', 200, 200),
                    'calificacion' => 4.2,
                    'categorias' => ['Iluminación', 'Cableado'],
                    'marcas' => ['LuxHome', 'CableSafe'],
                    'productos' => [
                        ['sku' => 'TEMP-TIENDA-LED-009', 'nombre' => 'Foco LED 12W', 'categoria' => 'Iluminación', 'marca' => 'LuxHome', 'precio' => 45.00, 'stock' => 300, 'destacado' => true, 'imagen' => $this->imageUrl('foco-led')],
                        ['sku' => 'TEMP-TIENDA-CAB-010', 'nombre' => 'Cable THW 12 AWG (rollo 100m)', 'categoria' => 'Cableado', 'marca' => 'CableSafe', 'precio' => 890.00, 'stock' => 25, 'destacado' => false, 'imagen' => $this->imageUrl('cable-thw')],
                        ['sku' => 'TEMP-TIENDA-INT-011', 'nombre' => 'Interruptor sencillo', 'categoria' => 'Cableado', 'marca' => 'CableSafe', 'precio' => 32.00, 'stock' => 150, 'destacado' => false, 'imagen' => $this->imageUrl('interruptor-sencillo')],
                        ['sku' => 'TEMP-TIENDA-LAM-012', 'nombre' => 'Lámpara plafón LED', 'categoria' => 'Iluminación', 'marca' => 'LuxHome', 'precio' => 350.00, 'stock' => 40, 'destacado' => true, 'imagen' => $this->imageUrl('lampara-plafon')],
                    ],
                ],
            ];

            foreach ($proveedoresData as $data) {
                $this->seedProveedorCatalogo($data, $tipo->id);
            }
        });

        $this->command?->info('TiendaTemporalSeeder: proveedores, catálogo y accesos listos (prefijo TEMP-TIENDA).');
    }

    private function seedAccesosRapidos(): void
    {
        $accesos = [
            [
                'titulo' => 'Productos Destacados',
                'descripcion' => 'Ver productos más populares y recomendados',
                'icono' => 'star-outline',
                'url' => '/tienda/productos/destacados',
                'color' => '#ffc107',
                'orden' => 1,
            ],
            [
                'titulo' => 'Proveedores Principales',
                'descripcion' => 'Explorar proveedores de confianza',
                'icono' => 'storefront-outline',
                'url' => '/tienda/proveedores/principales',
                'color' => '#007bff',
                'orden' => 2,
            ],
            [
                'titulo' => 'Más Pedidos',
                'descripcion' => 'Productos con mayor demanda',
                'icono' => 'bag-handle-outline',
                'url' => '/tienda/productos/mas-pedidos',
                'color' => '#28a745',
                'orden' => 3,
            ],
            [
                'titulo' => 'Novedades',
                'descripcion' => 'Productos agregados recientemente',
                'icono' => 'time-outline',
                'url' => '/tienda/productos/recientes',
                'color' => '#17a2b8',
                'orden' => 4,
            ],
            [
                'titulo' => 'Catálogo',
                'descripcion' => 'Explorar todo el catálogo de productos',
                'icono' => 'grid-outline',
                'url' => '/tienda/catalogo',
                'color' => '#fd7e14',
                'orden' => 5,
            ],
        ];

        foreach ($accesos as $acceso) {
            AccesoRapido::updateOrCreate(
                ['titulo' => $acceso['titulo']],
                array_merge($acceso, ['activo' => true])
            );
        }
    }

    private function seedProveedorCatalogo(array $data, int $tipoEmpresaId): void
    {
        $proveedor = Proveedor::withoutGlobalScope('solo_activos')->updateOrCreate(
            ['rfc' => $data['rfc']],
            [
                'nombre_comercial' => $data['nombre_comercial'],
                'razon_social' => $data['razon_social'],
                'email' => $data['email'],
                'telefono' => '6670000000',
                'logo' => null,
                'tipos_empresa_id' => $tipoEmpresaId,
                'descripcion_giro_empresa' => 'Proveedor demo temporal para tienda',
                'direccion_empresa' => 'Los Mochis, Sinaloa',
                'estado' => 'Sinaloa',
                'municipio' => 'Ahome',
                'codigo_postal' => '81200',
                'nombre_propietario' => 'Demo Tienda',
                'nombre_de_quien_registra' => 'Seeder Temporal',
                'contacto_nombre' => 'Contacto Demo',
                'contacto_cargo' => 'Ventas',
                'contacto_telefono' => '6670000001',
                'contacto_correo' => $data['email'],
                'principal' => true,
                'calificacion' => $data['calificacion'],
                'logo' => $data['logo'] ?? null,
                'is_proveedor_sp' => false,
                'is_proveedor_catalogo' => true,
                'perfil_empresa_completo' => true,
                'estatus' => EstadoUsuario::VERIFICADO->value,
            ]
        );

        $unidad = UnidadMedida::firstOrCreate(
            [
                'proveedor_id' => $proveedor->id,
                'clave' => 'PZA',
            ],
            [
                'nombre' => 'Pieza',
                'descripcion' => 'Unidad pieza (demo tienda)',
                'estatus' => EstadoGeneral::ACTIVO->value,
            ]
        );

        $categorias = [];
        foreach ($data['categorias'] as $nombreCat) {
            $categorias[$nombreCat] = Categoria::firstOrCreate(
                [
                    'proveedor_id' => $proveedor->id,
                    'nombre' => $nombreCat,
                ],
                [
                    'descripcion' => "Categoría demo: {$nombreCat}",
                    'nivel' => 1,
                    'activo' => true,
                ]
            );
        }

        $marcas = [];
        foreach ($data['marcas'] as $nombreMarca) {
            $marcas[$nombreMarca] = Marca::firstOrCreate(
                [
                    'proveedor_id' => $proveedor->id,
                    'nombre' => $nombreMarca,
                ],
                [
                    'descripcion' => "Marca demo: {$nombreMarca}",
                    'activo' => true,
                ]
            );
        }

        foreach ($data['productos'] as $producto) {
            $categoria = $categorias[$producto['categoria']] ?? null;
            $marca = $marcas[$producto['marca']] ?? null;

            Producto::updateOrCreate(
                [
                    'proveedor_id' => $proveedor->id,
                    'sku' => $producto['sku'],
                ],
                [
                    'codigo_interno' => $producto['sku'],
                    'nombre' => $producto['nombre'],
                    'descripcion' => 'Producto demo temporal para la tiendita. SKU: '.$producto['sku'],
                    'marca_id' => $marca?->id,
                    'categoria_id' => $categoria?->id,
                    'unidad_medida_id' => $unidad->id,
                    'precio_base' => $producto['precio'],
                    'precio_mayoreo' => round($producto['precio'] * 0.9, 2),
                    'precio_menudeo' => $producto['precio'],
                    'stock' => $producto['stock'],
                    'destacado' => (bool) $producto['destacado'],
                    'activo' => true,
                    'estatus' => EstadoGeneral::ACTIVO->value,
                    'imagen_principal' => $producto['imagen'] ?? $this->imageUrl($producto['sku']),
                ]
            );
        }
    }

    private function imageUrl(string $seed, int $width = 600, int $height = 600): string
    {
        $seed = rawurlencode($seed);

        return "https://picsum.photos/seed/{$seed}/{$width}/{$height}";
    }
}
