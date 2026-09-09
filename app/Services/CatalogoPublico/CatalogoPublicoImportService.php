<?php

namespace App\Services\CatalogoPublico;

use App\Models\CatalogoPublicoItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class CatalogoPublicoImportService
{
    private const FIXED_FIELDS = [
        'codigo',
        'nombre',
        'descripcion',
        'marca',
        'categoria',
        'subcategoria',
        'unidad',
        'modelo',
        'empresa',
        'logo',
        'imagen',
        'precio_base',
        'precio_mayoreo',
        'precio_menudeo',
    ];

    /**
     * @return array{total:int,nuevos:int,actualizados:int,omitidos:int,errores:list<array{fila:int,mensaje:string}>}
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->parseFile($file);

        $stats = [
            'total' => count($rows),
            'nuevos' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        DB::connection((new CatalogoPublicoItem())->getConnectionName())->transaction(function () use ($rows, &$stats) {
            foreach ($rows as $index => $row) {
                $fila = $index + 2;
                $mapped = $this->mapRow($row);

                if ($mapped === null) {
                    $stats['omitidos']++;

                    continue;
                }

                if ($mapped['codigo'] === '' || $mapped['empresa'] === '' || $mapped['nombre'] === '') {
                    $stats['errores'][] = [
                        'fila' => $fila,
                        'mensaje' => 'Faltan codigo, empresa o nombre.',
                    ];
                    $stats['omitidos']++;

                    continue;
                }

                $existing = CatalogoPublicoItem::query()
                    ->where('empresa', $mapped['empresa'])
                    ->where('codigo', $mapped['codigo'])
                    ->first();

                if ($existing) {
                    $existing->update($mapped);
                    $stats['actualizados']++;
                } else {
                    CatalogoPublicoItem::create($mapped);
                    $stats['nuevos']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseFile(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsv($file->getRealPath() ?: $file->getPathname());
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseExcel($file->getRealPath() ?: $file->getPathname());
        }

        throw new RuntimeException('Formato de archivo no soportado. Use CSV o Excel.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('No se pudo leer el archivo CSV.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return [];
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), str_getcsv($firstLine, $delimiter));

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->isEmptyRow($data)) {
                continue;
            }
            $rows[] = $this->combineRow($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseExcel(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo Excel: '.$e->getMessage(), 0, $e);
        }

        $rows = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetRows = $sheet->toArray(null, true, true, false);
            if ($sheetRows === []) {
                continue;
            }

            $headers = [];
            $categoriaHoja = trim((string) $sheet->getTitle());
            if ($categoriaHoja === '' || str_starts_with(mb_strtolower($categoriaHoja), 'worksheet')) {
                $categoriaHoja = null;
            }

            foreach ($sheetRows as $data) {
                if (! is_array($data) || $this->isEmptyRow($data)) {
                    continue;
                }

                $data = array_values($data);

                if ($headers === []) {
                    $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $data);

                    continue;
                }

                $row = $this->combineRow($headers, $data);

                if ($categoriaHoja && trim((string) ($row['categoria'] ?? '')) === '') {
                    $row['categoria'] = $categoriaHoja;
                }
                $rows[] = $row;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $data
     * @return array<string, mixed>
     */
    private function combineRow(array $headers, array $data): array
    {
        $row = [];
        foreach ($headers as $i => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = $data[$i] ?? '';
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $aliased = [];
        foreach ($row as $key => $value) {
            $canonical = $this->canonicalField($key);
            $aliased[$canonical] = $value;
        }

        $codigo = $this->asString($aliased['codigo'] ?? '');
        $nombre = $this->asString($aliased['nombre'] ?? '');
        $empresa = $this->asString($aliased['empresa'] ?? '');

        if ($codigo === '' && $nombre === '' && $empresa === '') {
            return null;
        }

        $propiedades = [];
        foreach ($aliased as $key => $value) {
            if (in_array($key, self::FIXED_FIELDS, true)) {
                continue;
            }
            $text = $this->asString($value);
            if ($text !== '') {
                $propiedades[$key] = $text;
            }
        }

        return [
            'codigo' => mb_substr($codigo, 0, 80),
            'nombre' => mb_substr($nombre, 0, 255),
            'descripcion' => $this->nullableString($aliased['descripcion'] ?? null),
            'marca' => $this->limitNullable($aliased['marca'] ?? null, 255),
            'categoria' => $this->limitNullable($aliased['categoria'] ?? null, 255),
            'subcategoria' => $this->limitNullable($aliased['subcategoria'] ?? null, 255),
            'unidad' => $this->limitNullable($aliased['unidad'] ?? null, 50),
            'modelo' => $this->limitNullable($aliased['modelo'] ?? null, 100),
            'empresa' => mb_substr($empresa, 0, 100),
            'logo' => $this->limitNullable($aliased['logo'] ?? null, 500),
            'imagen' => $this->limitNullable($aliased['imagen'] ?? null, 500),
            'precio_base' => $this->parsePrice($aliased['precio_base'] ?? null),
            'precio_mayoreo' => $this->parsePrice($aliased['precio_mayoreo'] ?? null),
            'precio_menudeo' => $this->parsePrice($aliased['precio_menudeo'] ?? null),
            'propiedades' => $propiedades === [] ? null : $propiedades,
            'activo' => true,
        ];
    }

    private function canonicalField(string $header): string
    {
        $aliases = [
            'codigo' => 'codigo',
            'sku' => 'codigo',
            'codigo_interno' => 'codigo',
            'clave' => 'codigo',
            'producto' => 'nombre',
            'nombre' => 'nombre',
            'nombre_producto' => 'nombre',
            'descripcion' => 'descripcion',
            'descripcion_producto' => 'descripcion',
            'marca' => 'marca',
            'nombre_marca' => 'marca',
            'categoria' => 'categoria',
            'nombre_categoria' => 'categoria',
            'nombre_categoria_nivel_1' => 'categoria',
            'subcategoria' => 'subcategoria',
            'nombre_categoria_nivel_2' => 'subcategoria',
            'unidad' => 'unidad',
            'unidad_medida' => 'unidad',
            'modelo' => 'modelo',
            'empresa' => 'empresa',
            'proveedor' => 'empresa',
            'nombre_empresa' => 'empresa',
            'logo' => 'logo',
            'logo_url' => 'logo',
            'logo_empresa' => 'logo',
            'imagen' => 'imagen',
            'imagen_url' => 'imagen',
            'imagen_producto' => 'imagen',
            'foto' => 'imagen',
            'foto_producto' => 'imagen',
            'image' => 'imagen',
            'precio' => 'precio_base',
            'precio_base' => 'precio_base',
            'precio_mayoreo' => 'precio_mayoreo',
            'precio_menudeo' => 'precio_menudeo',
            'precio_menuedeo' => 'precio_menudeo',
        ];

        return $aliases[$header] ?? $header;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = mb_strtolower($header);
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ];
        $header = strtr($header, $replacements);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    /**
     * @param  list<mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function asString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = $this->asString($value);

        return $text === '' ? null : $text;
    }

    private function limitNullable(mixed $value, int $max): ?string
    {
        $text = $this->nullableString($value);

        return $text === null ? null : mb_substr($text, 0, $max);
    }

    private function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', (string) $value) ?? '';
        if ($clean === '' || $clean === '-') {
            return null;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace(',', '', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }
}
