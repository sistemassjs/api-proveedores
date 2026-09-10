<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    public function run()
    {
        $unidades = [
            ['descripcion' => 'm', 'nombre' => 'Metro', 'clave' => 'MTR'],
            ['descripcion' => 'm2', 'nombre' => 'Metro Cuadrado', 'clave' => 'MTK'],
            ['descripcion' => 'm3', 'nombre' => 'Metro Cúbico', 'clave' => 'MTQ'],
            ['descripcion' => 'kg', 'nombre' => 'Kilogramo', 'clave' => 'KGM'],
            ['descripcion' => 'g', 'nombre' => 'Gramo', 'clave' => 'GRM'],
            ['descripcion' => 't', 'nombre' => 'Tonelada', 'clave' => 'TNE'],
            ['descripcion' => 'lt', 'nombre' => 'Litro', 'clave' => 'LTR'],
            ['descripcion' => 'ml', 'nombre' => 'Mililitro', 'clave' => 'MLT'],
            ['descripcion' => 'pza', 'nombre' => 'Pieza', 'clave' => 'H87'],
            ['descripcion' => 'cj', 'nombre' => 'Caja', 'clave' => 'XBX'],
            ['descripcion' => 'bulto', 'nombre' => 'Bulto', 'clave' => 'BX'],
            ['descripcion' => 'par', 'nombre' => 'Par', 'clave' => 'PR'],
            ['descripcion' => 'jgo', 'nombre' => 'Juego', 'clave' => 'SET'],
            ['descripcion' => 'ro', 'nombre' => 'Rollo', 'clave' => 'RO'],
            ['descripcion' => 'mll', 'nombre' => 'Milla', 'clave' => 'SMI'],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::firstOrCreate(
                ['nombre' => $unidad['nombre']],
                [
                    'descripcion' => $unidad['descripcion'],
                    'clave' => $unidad['clave'],
                    'estatus' => 'activo',
                ]
            );
        }
    }
}
