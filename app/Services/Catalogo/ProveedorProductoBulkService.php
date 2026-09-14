<?php

namespace App\Services\Catalogo;

use App\Enums\EstadoGeneral;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProveedorProductoBulkService
{
    public function __construct(
        private CatalogoOpusHomologacionService $opusMatch
    ) {}

    /**
     * @param  list<array<string, mixed>>  $productos
     * @return array{
     *   importId: string,
     *   processedProducts: int,
     *   createdProducts: int,
     *   updatedProducts: int,
     *   errors: list<string>,
     *   opus_homologados: int,
     *   opus_sin_match: int
     * }
     */
    public function import(Proveedor $proveedor, array $productos, ?string $importId = null): array
    {
        $importId = $importId ?: (string) Str::uuid();
        $created = 0;
        $updated = 0;
        $errors = [];
        $opusOk = 0;
        $opusMiss = 0;

        $marcaCache = [];
        $categoriaCache = [];
        $subcategoriaCache = [];
        $unidadCache = [];

        DB::transaction(function () use (
            $proveedor,
            $productos,
            &$created,
            &$updated,
            &$errors,
            &$opusOk,
            &$opusMiss,
            &$marcaCache,
            &$categoriaCache,
            &$subcategoriaCache,
            &$unidadCache
        ) {
            foreach ($productos as $index => $row) {
                $rowNum = $index + 1;
                try {
                    $codigo = $this->resolveCodigo($row);
                    if ($codigo === '') {
                        $errors[] = "Fila {$rowNum}: falta sku/codigo.";
                        continue;
                    }

                    $marcaId = $this->resolveMarca($proveedor->id, $row, $marcaCache);
                    $categoriaId = $this->resolveCategoria($proveedor->id, $row, $categoriaCache);
                    $subcategoriaId = $this->resolveSubcategoria(
                        $proveedor->id,
                        $row,
                        $categoriaId,
                        $subcategoriaCache
                    );
                    $unidadId = $this->resolveUnidad($row, $unidadCache);

                    // Homologación OPUS para reducir nulls: textos familia/subfamilia o, si faltan, categoría/subcategoría.
                    // IDs explícitos de familia/subfamilia tienen prioridad.
                    [$familiaId, $subfamiliaId, $opusMatched, $opusAttempted] = $this->resolveOpus($row);

                    if ($opusAttempted) {
                        if ($opusMatched) {
                            $opusOk++;
                        } else {
                            $opusMiss++;
                        }
                    }

                    $precioBase = $this->normalizePrecio(
                        $row['precios']['precio_base'] ?? $row['precio_base'] ?? null
                    );
                    $precioMayoreo = $this->normalizePrecio(
                        $row['precios']['precio_mayoreo'] ?? $row['precio_mayoreo'] ?? null
                    );
                    $precioMenudeo = $this->normalizePrecio(
                        $row['precios']['precio_menudeo'] ?? $row['precio_menudeo'] ?? null
                    );

                    if ($precioBase === false || $precioMayoreo === false || $precioMenudeo === false) {
                        $errors[] = "Fila {$rowNum} ({$codigo}): los precios deben ser null, 0 o un número mayor a 0.";
                        continue;
                    }

                    $values = [
                        'nombre' => $row['nombre'],
                        'descripcion' => $row['descripcion'] ?? null,
                        'modelo' => $row['modelo'] ?? null,
                        'tipo' => $row['tipo'] ?? null,
                        'codigo_fabricante' => $row['codigo_fabricante'] ?? null,
                        'codigo_barras' => $row['codigo_barras'] ?? null,
                        'sku' => $codigo,
                        'marca_id' => $marcaId,
                        'categoria_id' => $categoriaId,
                        'subcategoria_id' => $subcategoriaId,
                        'unidad_medida_id' => $unidadId,
                        'familia_id' => $familiaId,
                        'subfamilia_id' => $subfamiliaId,
                        'precio_base' => $precioBase,
                        'precio_mayoreo' => $precioMayoreo,
                        'precio_menudeo' => $precioMenudeo,
                        'presentacion' => $row['presentacion'] ?? null,
                        'disponibilidad' => $row['disponibilidad'] ?? null,
                        'tiempo_entrega' => $row['tiempo_entrega'] ?? null,
                        'url_producto' => $row['url_producto'] ?? null,
                        'tags' => $row['tags'] ?? null,
                        'activo' => array_key_exists('activo', $row) ? (bool) $row['activo'] : true,
                        'mostrar_en_catalogo_publico' => array_key_exists('mostrar_en_catalogo_publico', $row)
                            ? (bool) $row['mostrar_en_catalogo_publico']
                            : false,
                        'stock' => (int) ($row['stock'] ?? $row['stock_inicial'] ?? 0),
                        'estatus' => EstadoGeneral::ACTIVO->value,
                    ];

                    $producto = Producto::updateOrCreate(
                        [
                            'proveedor_id' => $proveedor->id,
                            'codigo_interno' => $codigo,
                        ],
                        $values
                    );

                    if ($producto->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    if (! empty($row['especificaciones']) && is_array($row['especificaciones'])) {
                        $producto->especificaciones()->delete();
                        foreach (array_values($row['especificaciones']) as $i => $spec) {
                            $atributo = $spec['atributo'] ?? $spec['clave'] ?? null;
                            if (! $atributo) {
                                continue;
                            }
                            $producto->especificaciones()->create([
                                'atributo' => $atributo,
                                'valor' => $spec['valor'] ?? '',
                                'unidad' => $spec['unidad'] ?? null,
                                'orden' => $spec['orden'] ?? $i,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Fila {$rowNum}: ".$e->getMessage();
                }
            }
        });

        return [
            'importId' => $importId,
            'processedProducts' => count($productos),
            'createdProducts' => $created,
            'updatedProducts' => $updated,
            'errors' => $errors,
            'opus_homologados' => $opusOk,
            'opus_sin_match' => $opusMiss,
        ];
    }

    private function resolveCodigo(array $row): string
    {
        foreach (['codigo_interno', 'sku', 'codigo'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveMarca(int $proveedorId, array $row, array &$cache): ?int
    {
        if (! empty($row['marca_id'])) {
            return (int) $row['marca_id'];
        }

        $nombre = trim((string) ($row['marca_nombre'] ?? ''));
        if ($nombre === '') {
            return null;
        }

        $key = mb_strtolower($nombre);
        if (! isset($cache[$key])) {
            $marca = Marca::firstOrCreate(
                ['proveedor_id' => $proveedorId, 'nombre' => $nombre],
                ['activo' => true]
            );
            $cache[$key] = $marca->id;
        }

        return $cache[$key];
    }

    private function resolveCategoria(int $proveedorId, array $row, array &$cache): ?int
    {
        if (! empty($row['categoria_id'])) {
            return (int) $row['categoria_id'];
        }

        $nombre = trim((string) ($row['categoria_nombre'] ?? ''));
        if ($nombre === '') {
            return null;
        }

        $key = mb_strtolower($nombre);
        if (! isset($cache[$key])) {
            $cat = Categoria::firstOrCreate(
                [
                    'proveedor_id' => $proveedorId,
                    'nombre' => $nombre,
                    'parent_id' => null,
                ],
                [
                    'nivel' => 0,
                    'activo' => true,
                    'estatus' => EstadoGeneral::ACTIVO->value,
                ]
            );
            $cache[$key] = $cat->id;
        }

        return $cache[$key];
    }

    private function resolveSubcategoria(
        int $proveedorId,
        array $row,
        ?int $categoriaId,
        array &$cache
    ): ?int {
        if (! empty($row['subcategoria_id'])) {
            return (int) $row['subcategoria_id'];
        }

        if (! $categoriaId) {
            return null;
        }

        $nombre = trim((string) ($row['subcategoria_nombre'] ?? ''));
        if ($nombre === '') {
            return null;
        }

        $key = $categoriaId.'|'.mb_strtolower($nombre);
        if (! isset($cache[$key])) {
            $sub = Categoria::firstOrCreate(
                [
                    'proveedor_id' => $proveedorId,
                    'nombre' => $nombre,
                    'parent_id' => $categoriaId,
                ],
                [
                    'nivel' => 1,
                    'activo' => true,
                    'estatus' => EstadoGeneral::ACTIVO->value,
                ]
            );
            $cache[$key] = $sub->id;
        }

        return $cache[$key];
    }

    private function resolveUnidad(array $row, array &$cache): ?int
    {
        if (! empty($row['unidad_medida_id'])) {
            return (int) $row['unidad_medida_id'];
        }

        $nombre = trim((string) ($row['unidad_medida'] ?? ''));
        if ($nombre === '') {
            return null;
        }

        $key = mb_strtolower($nombre);
        if (! isset($cache[$key])) {
            $unidad = UnidadMedida::query()
                ->where(function ($q) use ($key) {
                    $q->whereRaw('LOWER(nombre) = ?', [$key])
                        ->orWhereRaw('LOWER(COALESCE(descripcion, "")) = ?', [$key])
                        ->orWhereRaw('LOWER(COALESCE(clave, "")) = ?', [$key]);
                })
                ->first();

            if (! $unidad) {
                $unidad = UnidadMedida::create([
                    'nombre' => $nombre,
                    'descripcion' => $nombre,
                    'estatus' => EstadoGeneral::ACTIVO->value,
                ]);
            }

            $cache[$key] = $unidad->id;
        }

        return $cache[$key];
    }

    /**
     * Resuelve familia/subfamilia OPUS.
     * Prioridad: IDs explícitos → match por textos familia/subfamilia → fallback categoría/subcategoría.
     * Regla: si no hay match de familia, no se guarda OPUS; si familia sí y subfamilia no, solo familia.
     *
     * @return array{0: ?int, 1: ?int, 2: bool, 3: bool} [familia_id, subfamilia_id, matched, attempted]
     */
    private function resolveOpus(array $row): array
    {
        $hasExplicitFamilia = array_key_exists('familia_id', $row) && $row['familia_id'] !== null && $row['familia_id'] !== '';
        $hasExplicitSubfamilia = array_key_exists('subfamilia_id', $row) && $row['subfamilia_id'] !== null && $row['subfamilia_id'] !== '';

        if ($hasExplicitFamilia || $hasExplicitSubfamilia) {
            $familiaId = $hasExplicitFamilia ? (int) $row['familia_id'] : null;
            $subfamiliaId = $hasExplicitSubfamilia ? (int) $row['subfamilia_id'] : null;

            // Sin familia explícita no persistimos subfamilia suelta.
            if ($familiaId === null) {
                $subfamiliaId = null;
            }

            return [$familiaId, $subfamiliaId, $familiaId !== null, true];
        }

        $familiaTxt = trim((string) ($row['familia'] ?? ''));
        $subfamiliaTxt = trim((string) ($row['subfamilia'] ?? ''));

        if ($familiaTxt === '') {
            $familiaTxt = trim((string) ($row['categoria_nombre'] ?? ''));
        }
        if ($subfamiliaTxt === '') {
            $subfamiliaTxt = trim((string) ($row['subcategoria_nombre'] ?? ''));
        }

        if ($familiaTxt === '' && $subfamiliaTxt === '') {
            return [null, null, false, false];
        }

        $opus = $this->opusMatch->match($familiaTxt, $subfamiliaTxt);

        // Estricto respecto a familia: si no hubo familia_id, no guardar OPUS (ignora fallback solo-subfamilia).
        if ($opus['familia_id'] === null) {
            return [null, null, false, true];
        }

        return [
            (int) $opus['familia_id'],
            $opus['subfamilia_id'] !== null ? (int) $opus['subfamilia_id'] : null,
            (bool) $opus['matched'],
            true,
        ];
    }

    /**
     * Acepta null, 0 y números >= 0.
     * Devuelve false si el valor es inválido (p. ej. negativo o no numérico).
     *
     * @return float|null|false
     */
    private function normalizePrecio(mixed $value): float|null|false
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || strtolower($value) === 'null') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return false;
        }

        $precio = (float) $value;
        if ($precio < 0) {
            return false;
        }

        return $precio;
    }
}
