<?php

namespace Database\Seeders;

use App\Models\CatalogoFamilia;
use App\Models\CatalogoSubfamilia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Catálogo global OPUS 2025 (familias / subfamilias).
 * Fuente: LISTADO DE FAMILIAS Y SUB FAMILIAS OPUS 2025.xlsx
 * Datos: database/data/catalogo_opus_familias.json
 */
class CatalogoOpusFamiliasSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/catalogo_opus_familias.json');
        if (! File::exists($path)) {
            throw new RuntimeException("No se encontró el listado OPUS en {$path}");
        }

        /** @var list<array{codigo: string, nombre: string, subfamilias: list<string>}> $items */
        $items = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $familiaIds = [];
        $subfamiliaIds = [];

        foreach ($items as $item) {
            $familia = CatalogoFamilia::updateOrCreate(
                ['codigo' => $item['codigo']],
                [
                    'nombre' => $item['nombre'],
                    'activo' => true,
                ]
            );

            $familiaIds[] = $familia->id;
            $currentSubIds = [];

            foreach ($item['subfamilias'] as $subNombre) {
                $subNombre = trim((string) $subNombre);
                if ($subNombre === '') {
                    continue;
                }

                $sub = $familia->subfamilias()->updateOrCreate(
                    ['nombre' => $subNombre],
                    ['activo' => true]
                );
                $currentSubIds[] = $sub->id;
                $subfamiliaIds[] = $sub->id;
            }

            $familia->subfamilias()
                ->when(
                    $currentSubIds !== [],
                    fn ($q) => $q->whereNotIn('id', $currentSubIds),
                    fn ($q) => $q
                )
                ->update(['activo' => false]);
        }

        if ($familiaIds !== []) {
            CatalogoFamilia::query()
                ->whereNotIn('id', $familiaIds)
                ->update(['activo' => false]);
        }

        if ($subfamiliaIds !== []) {
            CatalogoSubfamilia::query()
                ->whereNotIn('id', $subfamiliaIds)
                ->update(['activo' => false]);
        }

        $this->command?->info(sprintf(
            'OPUS 2025: %d familias activas, %d subfamilias activas.',
            count($familiaIds),
            count(array_unique($subfamiliaIds))
        ));
    }
}
