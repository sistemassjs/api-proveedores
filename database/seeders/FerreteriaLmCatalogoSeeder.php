<?php

namespace Database\Seeders;

use App\Enums\EstadoGeneral;
use App\Models\CatalogoFamilia;
use App\Models\CatalogoSubfamilia;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Catalogo\CatalogoOpusHomologacionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Genera catálogo demo (marcas, categorías, productos) para FERRETERIA LM
 * (usuario telefono 6681175490 → proveedor_id 19).
 */
class FerreteriaLmCatalogoSeeder extends Seeder
{
    private const TELEFONO = '6681175490';

    private const PROVEEDOR_ID = 19;

    public function run(): void
    {
        $proveedor = $this->resolveProveedor();
        if (! $proveedor) {
            $this->command?->error('No se encontró proveedor para el usuario '.self::TELEFONO);

            return;
        }

        $this->command?->info("Sembrando catálogo para {$proveedor->nombre_comercial} (id={$proveedor->id})");

        $this->ensureUnidades();
        $marcas = $this->seedMarcas($proveedor->id);
        $taxonomia = $this->seedCategorias($proveedor->id);
        $this->seedProductos($proveedor->id, $marcas, $taxonomia);

        $this->command?->info(sprintf(
            'Listo: marcas=%d categorias=%d productos=%d unidades=%d',
            Marca::where('proveedor_id', $proveedor->id)->count(),
            Categoria::where('proveedor_id', $proveedor->id)->count(),
            Producto::where('proveedor_id', $proveedor->id)->count(),
            UnidadMedida::count()
        ));
    }

    private function resolveProveedor(): ?Proveedor
    {
        $user = User::query()
            ->where('telefono', self::TELEFONO)
            ->orWhere('telefono', 'like', '%'.self::TELEFONO.'%')
            ->first();

        if ($user && method_exists($user, 'proveedores')) {
            $fromUser = $user->proveedores()->first();
            if ($fromUser) {
                return $fromUser;
            }
        }

        return Proveedor::find(self::PROVEEDOR_ID);
    }

    private function ensureUnidades(): void
    {
        $unidades = [
            ['nombre' => 'Pieza', 'clave' => 'PZA', 'descripcion' => 'pza'],
            ['nombre' => 'Metro', 'clave' => 'MTR', 'descripcion' => 'm'],
            ['nombre' => 'Metro Cuadrado', 'clave' => 'MTK', 'descripcion' => 'm2'],
            ['nombre' => 'Kilogramo', 'clave' => 'KGM', 'descripcion' => 'kg'],
            ['nombre' => 'Litro', 'clave' => 'LTR', 'descripcion' => 'lt'],
            ['nombre' => 'Saco', 'clave' => 'SAC', 'descripcion' => 'saco'],
            ['nombre' => 'Caja', 'clave' => 'XBX', 'descripcion' => 'cj'],
            ['nombre' => 'Rollo', 'clave' => 'RO', 'descripcion' => 'ro'],
            ['nombre' => 'Par', 'clave' => 'PR', 'descripcion' => 'par'],
            ['nombre' => 'Juego', 'clave' => 'SET', 'descripcion' => 'jgo'],
        ];

        foreach ($unidades as $u) {
            UnidadMedida::firstOrCreate(
                ['nombre' => $u['nombre']],
                [
                    'clave' => $u['clave'],
                    'descripcion' => $u['descripcion'],
                    'estatus' => EstadoGeneral::ACTIVO->value,
                ]
            );
        }
    }

    /**
     * @return array<string, int> nombre => id
     */
    private function seedMarcas(int $proveedorId): array
    {
        $nombres = [
            'Truper', 'Pretul', 'Fiero', 'Volteck', 'Foset',
            'Argos', 'Cemex', 'Deacero', 'Aceros DM', '3M',
        ];

        $map = [];
        foreach ($nombres as $nombre) {
            $marca = Marca::firstOrCreate(
                ['proveedor_id' => $proveedorId, 'nombre' => $nombre],
                ['activo' => true, 'descripcion' => "Marca {$nombre}"]
            );
            $map[$nombre] = $marca->id;
        }

        return $map;
    }

    /**
     * @return array{categorias: array<string,int>, subcategorias: array<string,int>}
     */
    private function seedCategorias(int $proveedorId): array
    {
        $arbol = [
            'Aceros y perfiles' => ['Varilla', 'PTR', 'Ángulos', 'Lámina'],
            'Cementos y agregados' => ['Cemento', 'Arena', 'Grava', 'Block'],
            'Herramientas manuales' => ['Martillos', 'Destornilladores', 'Llaves', 'Pinzas'],
            'Herramientas eléctricas' => ['Taladros', 'Esmeriladoras', 'Sierras'],
            'Electricidad' => ['Cables', 'Contactos', 'Interruptores', 'Luminarias'],
            'Plomería' => ['Tubería PVC', 'Conexiones', 'Válvulas', 'Ceses'],
            'Pinturas y acabados' => ['Pintura vinílica', 'Esmalte', 'Impermeabilizante', 'Brochas'],
            'Fijaciones' => ['Tornillos', 'Taquetes', 'Clavos', 'Remaches'],
        ];

        $categorias = [];
        $subcategorias = [];

        foreach ($arbol as $catNombre => $subs) {
            $cat = Categoria::firstOrCreate(
                [
                    'proveedor_id' => $proveedorId,
                    'nombre' => $catNombre,
                    'parent_id' => null,
                ],
                [
                    'nivel' => 0,
                    'activo' => true,
                    'estatus' => EstadoGeneral::ACTIVO->value,
                    'descripcion' => $catNombre,
                ]
            );
            $categorias[$catNombre] = $cat->id;

            foreach ($subs as $subNombre) {
                $sub = Categoria::firstOrCreate(
                    [
                        'proveedor_id' => $proveedorId,
                        'nombre' => $subNombre,
                        'parent_id' => $cat->id,
                    ],
                    [
                        'nivel' => 1,
                        'activo' => true,
                        'estatus' => EstadoGeneral::ACTIVO->value,
                    ]
                );
                $subcategorias["{$catNombre}|{$subNombre}"] = $sub->id;
            }
        }

        return compact('categorias', 'subcategorias');
    }

    /**
     * @param  array<string, int>  $marcas
     * @param  array{categorias: array<string,int>, subcategorias: array<string,int>}  $taxonomia
     */
    private function seedProductos(int $proveedorId, array $marcas, array $taxonomia): void
    {
        $opus = new CatalogoOpusHomologacionService;
        $unidad = fn (string $nombre) => UnidadMedida::where('nombre', $nombre)->value('id');

        $productos = [
            [
                'codigo' => 'VAR-38-12',
                'nombre' => 'Varilla corrugada 3/8" x 12 m',
                'descripcion' => 'Varilla de acero de refuerzo grado 42, longitud 12 m.',
                'marca' => 'Deacero',
                'categoria' => 'Aceros y perfiles',
                'subcategoria' => 'Varilla',
                'unidad' => 'Pieza',
                'precio_base' => 185.50,
                'precio_mayoreo' => 175.00,
                'precio_menudeo' => 195.00,
                'opus_familia' => '01. Aceros de refuerzo',
                'opus_subfamilia' => 'Varilla corrugada',
                'modelo' => 'G42-3/8',
                'presentacion' => 'Barra 12 m',
            ],
            [
                'codigo' => 'PTR-2X2-14',
                'nombre' => 'PTR 2" x 2" calibre 14',
                'descripcion' => 'Perfil tubular rectangular para estructuras ligeras.',
                'marca' => 'Aceros DM',
                'categoria' => 'Aceros y perfiles',
                'subcategoria' => 'PTR',
                'unidad' => 'Metro',
                'precio_base' => 96.00,
                'precio_mayoreo' => 89.00,
                'precio_menudeo' => 105.00,
                'opus_familia' => '02. Aceros estructurales',
                'opus_subfamilia' => 'PTR',
                'modelo' => 'PTR-2x2-C14',
            ],
            [
                'codigo' => 'ANG-1X1-18',
                'nombre' => 'Ángulo 1" x 1" calibre 18',
                'descripcion' => 'Ángulo de acero estructural.',
                'marca' => 'Aceros DM',
                'categoria' => 'Aceros y perfiles',
                'subcategoria' => 'Ángulos',
                'unidad' => 'Metro',
                'precio_base' => 42.00,
                'precio_mayoreo' => 38.50,
                'precio_menudeo' => 48.00,
                'opus_familia' => '02. Aceros estructurales',
                'opus_subfamilia' => 'Ángulo',
            ],
            [
                'codigo' => 'CMT-CPC30-50',
                'nombre' => 'Cemento CPC 30R saco 50 kg',
                'descripcion' => 'Cemento Portland compuesto para usos generales.',
                'marca' => 'Cemex',
                'categoria' => 'Cementos y agregados',
                'subcategoria' => 'Cemento',
                'unidad' => 'Saco',
                'precio_base' => 168.00,
                'precio_mayoreo' => 158.00,
                'precio_menudeo' => 175.00,
                'opus_familia' => '05. Cementantes',
                'opus_subfamilia' => 'Cemento gris',
                'presentacion' => 'Saco 50 kg',
                'cantidad_contenida' => 50,
                'unidad_contenido' => 'Kilogramo',
                'unidad_base' => 'Kilogramo',
                'factor_conversion' => 50,
            ],
            [
                'codigo' => 'BLK-12-HUE',
                'nombre' => 'Block hueco 12x20x40',
                'descripcion' => 'Block de concreto hueco para muros.',
                'marca' => 'Argos',
                'categoria' => 'Cementos y agregados',
                'subcategoria' => 'Block',
                'unidad' => 'Pieza',
                'precio_base' => 12.50,
                'precio_mayoreo' => 11.00,
                'precio_menudeo' => 14.00,
                'opus_familia' => '06. Agregados',
                'opus_subfamilia' => null,
            ],
            [
                'codigo' => 'MAR-16OZ-TRU',
                'nombre' => 'Martillo uña 16 oz mango fibra',
                'descripcion' => 'Martillo de carpintero con mango de fibra de vidrio.',
                'marca' => 'Truper',
                'categoria' => 'Herramientas manuales',
                'subcategoria' => 'Martillos',
                'unidad' => 'Pieza',
                'precio_base' => 189.00,
                'precio_mayoreo' => 169.00,
                'precio_menudeo' => 210.00,
                'opus_familia' => '301. Herramienta menor de albañilería',
                'opus_subfamilia' => null,
                'modelo' => 'M-16F',
            ],
            [
                'codigo' => 'DES-PH2-J6',
                'nombre' => 'Juego destornilladores PH/PL 6 pzas',
                'descripcion' => 'Juego de destornilladores punta phillips y plana.',
                'marca' => 'Pretul',
                'categoria' => 'Herramientas manuales',
                'subcategoria' => 'Destornilladores',
                'unidad' => 'Juego',
                'precio_base' => 145.00,
                'precio_mayoreo' => 129.00,
                'precio_menudeo' => 159.00,
                'opus_familia' => '301. Herramienta menor de albañilería',
                'opus_subfamilia' => null,
            ],
            [
                'codigo' => 'TAL-12V-TRU',
                'nombre' => 'Taladro inalámbrico 12V',
                'descripcion' => 'Taladro/atornillador con batería de litio 12V.',
                'marca' => 'Truper',
                'categoria' => 'Herramientas eléctricas',
                'subcategoria' => 'Taladros',
                'unidad' => 'Pieza',
                'precio_base' => 1299.00,
                'precio_mayoreo' => 1199.00,
                'precio_menudeo' => 1399.00,
                'opus_familia' => '209. Herramienta eléctrica',
                'opus_subfamilia' => null,
                'modelo' => 'TAL-12LI',
            ],
            [
                'codigo' => 'CAB-THW-12',
                'nombre' => 'Cable THW calibre 12 rollo 100 m',
                'descripcion' => 'Cable eléctrico THW calibre 12 AWG.',
                'marca' => 'Volteck',
                'categoria' => 'Electricidad',
                'subcategoria' => 'Cables',
                'unidad' => 'Rollo',
                'precio_base' => 980.00,
                'precio_mayoreo' => 920.00,
                'precio_menudeo' => 1050.00,
                'opus_familia' => '110. Electricidad',
                'opus_subfamilia' => null,
                'presentacion' => 'Rollo 100 m',
                'cantidad_contenida' => 100,
                'unidad_contenido' => 'Metro',
                'unidad_base' => 'Metro',
                'factor_conversion' => 100,
            ],
            [
                'codigo' => 'PVC-20MM-6',
                'nombre' => 'Tubería PVC hidráulica 20 mm x 6 m',
                'descripcion' => 'Tubo PVC para instalación hidráulica.',
                'marca' => 'Foset',
                'categoria' => 'Plomería',
                'subcategoria' => 'Tubería PVC',
                'unidad' => 'Pieza',
                'precio_base' => 68.00,
                'precio_mayoreo' => 62.00,
                'precio_menudeo' => 75.00,
                'opus_familia' => '108. Instalación hidráulica',
                'opus_subfamilia' => null,
            ],
            [
                'codigo' => 'PIN-VIN-19L',
                'nombre' => 'Pintura vinílica blanca cubeta 19 L',
                'descripcion' => 'Pintura vinílica lavable para interiores.',
                'marca' => 'Comex',
                'categoria' => 'Pinturas y acabados',
                'subcategoria' => 'Pintura vinílica',
                'unidad' => 'Pieza',
                'precio_base' => 890.00,
                'precio_mayoreo' => 820.00,
                'precio_menudeo' => 950.00,
                'opus_familia' => '107. Pintura',
                'opus_subfamilia' => null,
                'presentacion' => 'Cubeta 19 L',
                'cantidad_contenida' => 19,
                'unidad_contenido' => 'Litro',
                'unidad_base' => 'Litro',
                'factor_conversion' => 19,
            ],
            [
                'codigo' => 'IMP-ACR-19L',
                'nombre' => 'Impermeabilizante acrílico cubeta 19 L',
                'descripcion' => 'Impermeabilizante acrílico fibra reforzada.',
                'marca' => 'Fiero',
                'categoria' => 'Pinturas y acabados',
                'subcategoria' => 'Impermeabilizante',
                'unidad' => 'Pieza',
                'precio_base' => 1150.00,
                'precio_mayoreo' => 1050.00,
                'precio_menudeo' => 1250.00,
                'opus_familia' => '115. Impermeabilización',
                'opus_subfamilia' => null,
                'presentacion' => 'Cubeta 19 L',
                'cantidad_contenida' => 19,
                'unidad_contenido' => 'Litro',
                'unidad_base' => 'Litro',
                'factor_conversion' => 19,
            ],
            [
                'codigo' => 'TOR-PJA-1',
                'nombre' => 'Tornillo pija #8 x 1" caja 100 pzas',
                'descripcion' => 'Pijas cabeza phillips para uso general.',
                'marca' => 'Truper',
                'categoria' => 'Fijaciones',
                'subcategoria' => 'Tornillos',
                'unidad' => 'Caja',
                'precio_base' => 45.00,
                'precio_mayoreo' => 39.00,
                'precio_menudeo' => 52.00,
                'opus_familia' => '03. Tornillería y fijaciones estructurales',
                'opus_subfamilia' => 'Tornillos estructurales',
            ],
            [
                'codigo' => 'TAQ-PLAS-1-4',
                'nombre' => 'Taquete plástico 1/4" bolsa 50 pzas',
                'descripcion' => 'Taquetes plásticos para muro.',
                'marca' => 'Pretul',
                'categoria' => 'Fijaciones',
                'subcategoria' => 'Taquetes',
                'unidad' => 'Pieza',
                'precio_base' => 28.00,
                'precio_mayoreo' => 24.00,
                'precio_menudeo' => 32.00,
                'opus_familia' => '03. Tornillería y fijaciones estructurales',
                'opus_subfamilia' => 'Taquetes',
            ],
            [
                'codigo' => 'LAM-GAL-26',
                'nombre' => 'Lámina galvanizada R-101 calibre 26',
                'descripcion' => 'Lámina acanalada galvanizada para cubierta.',
                'marca' => 'Aceros DM',
                'categoria' => 'Aceros y perfiles',
                'subcategoria' => 'Lámina',
                'unidad' => 'Metro Cuadrado',
                'precio_base' => 210.00,
                'precio_mayoreo' => 195.00,
                'precio_menudeo' => 230.00,
                'opus_familia' => '02. Aceros estructurales',
                'opus_subfamilia' => 'Lámina',
            ],
        ];

        // Marca Comex no estaba en lista base
        if (! isset($marcas['Comex'])) {
            $marcas['Comex'] = Marca::firstOrCreate(
                ['proveedor_id' => $proveedorId, 'nombre' => 'Comex'],
                ['activo' => true]
            )->id;
        }

        DB::transaction(function () use ($productos, $proveedorId, $marcas, $taxonomia, $unidad, $opus) {
            foreach ($productos as $p) {
                $catId = $taxonomia['categorias'][$p['categoria']] ?? null;
                $subId = $taxonomia['subcategorias']["{$p['categoria']}|{$p['subcategoria']}"] ?? null;
                $marcaId = $marcas[$p['marca']] ?? null;
                $unidadId = $unidad($p['unidad']);

                if (! $catId || ! $subId || ! $marcaId || ! $unidadId) {
                    $this->command?->warn("Omitido {$p['codigo']}: faltan relaciones");
                    continue;
                }

                $match = $opus->match($p['opus_familia'] ?? null, $p['opus_subfamilia'] ?? null);

                $unidadContenidoId = isset($p['unidad_contenido']) ? $unidad($p['unidad_contenido']) : null;
                $unidadBaseId = isset($p['unidad_base']) ? $unidad($p['unidad_base']) : null;

                $producto = Producto::updateOrCreate(
                    [
                        'proveedor_id' => $proveedorId,
                        'codigo_interno' => $p['codigo'],
                    ],
                    [
                        'sku' => $p['codigo'],
                        'nombre' => $p['nombre'],
                        'descripcion' => $p['descripcion'],
                        'modelo' => $p['modelo'] ?? null,
                        'tipo' => 'producto',
                        'marca_id' => $marcaId,
                        'categoria_id' => $catId,
                        'subcategoria_id' => $subId,
                        'unidad_medida_id' => $unidadId,
                        'familia_id' => $match['familia_id'],
                        'subfamilia_id' => $match['subfamilia_id'],
                        'precio_base' => $p['precio_base'],
                        'precio_mayoreo' => $p['precio_mayoreo'],
                        'precio_menudeo' => $p['precio_menudeo'],
                        'presentacion' => $p['presentacion'] ?? null,
                        'cantidad_contenida' => $p['cantidad_contenida'] ?? null,
                        'unidad_contenido_id' => $unidadContenidoId,
                        'unidad_base_id' => $unidadBaseId,
                        'factor_conversion' => $p['factor_conversion'] ?? null,
                        'disponibilidad' => 'disponible',
                        'activo' => true,
                        'stock' => random_int(5, 120),
                        'estatus' => EstadoGeneral::ACTIVO->value,
                    ]
                );

                // Specs de ejemplo en algunos productos
                if (str_starts_with($p['codigo'], 'VAR-')) {
                    $producto->especificaciones()->delete();
                    $producto->especificaciones()->createMany([
                        ['atributo' => 'Diámetro', 'valor' => '3/8"', 'orden' => 1],
                        ['atributo' => 'Longitud', 'valor' => '12', 'unidad' => 'm', 'orden' => 2],
                        ['atributo' => 'Grado', 'valor' => '42', 'orden' => 3],
                    ]);
                }
            }
        });
    }
}
