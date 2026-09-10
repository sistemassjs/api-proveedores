<?php

namespace App\Services\CSVImport;

use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Services\Catalogo\CatalogoOpusHomologacionService;

class CSVImportProductValidator
{
    private array $optionalFields = [
        'descripcion',
        'subcategoria',
    ];

    private array $requiredFields = [
        'codigo',
        'producto',
        'marca',
        'categoria',
        'unidad_medida',
    ];

    private array $numericFields = [
        'precio',
        'precio_mayoreo',
        'precio_menudeo',
        'cantidad_contenida',
        'factor_conversion',
    ];

    private int $proveedorId;

    private array $existingCodigos = [];

    private array $existingUnidadMedidas = [];

    private CatalogoOpusHomologacionService $opusMatch;

    public function __construct(int $proveedorId)
    {
        $this->proveedorId = $proveedorId;
        $this->opusMatch = new CatalogoOpusHomologacionService;
        $this->loadExistingData();
    }

    /**
     * @return array{errors: list<string>, warnings: list<string>, opus?: array}
     */
    public function validateRow(array $row, int $rowIndex): array
    {
        $errors = [];
        $warnings = [];

        foreach ($this->requiredFields as $field) {
            if (empty(trim($row[$field] ?? ''))) {
                $errors[] = "Campo obligatorio '{$field}' está vacío";
            }
        }

        $codigo = trim($row['codigo'] ?? '');
        if ($codigo) {
            if (in_array($codigo, $this->existingCodigos)) {
                $warnings[] = "Codigo '{$codigo}' ya existe y será actualizado";
            }

            if (! preg_match('/^[a-zA-Z0-9_\-.\/ ]+$/', $codigo)) {
                $errors[] = "Codigo '{$codigo}' contiene caracteres no válidos";
            }

            if (strlen($codigo) > 100) {
                $errors[] = "Codigo '{$codigo}' excede la longitud máxima de 100 caracteres";
            }
        }

        foreach ($this->numericFields as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            // Vacío / null → permitido
            if ($value === '' || strtolower($value) === 'null') {
                continue;
            }
            if (! is_numeric($value)) {
                $errors[] = "Campo '{$field}' debe ser null, 0 o un número mayor a 0";
                continue;
            }
            if ((float) $value < 0) {
                $errors[] = "Campo '{$field}' no puede ser negativo (use null, 0 o un número mayor a 0)";
            }
        }

        $marcaNombre = trim($row['marca'] ?? '');
        if ($marcaNombre && strlen($marcaNombre) > 255) {
            $errors[] = 'Nombre de marca excede 255 caracteres';
        }

        $categoria = trim($row['categoria'] ?? '');
        if ($categoria && strlen($categoria) > 255) {
            $errors[] = 'Nombre de categoría excede 255 caracteres';
        }

        $unidadMedida = trim($row['unidad_medida'] ?? '');
        $unidadNombres = array_map('strval', $this->existingUnidadMedidas);
        if ($unidadMedida && ! in_array($unidadMedida, $unidadNombres, true)) {
            $warnings[] = "Unidad de medida '{$unidadMedida}' no existe, se creará automáticamente";
        }

        $nombreProducto = trim($row['producto'] ?? '');
        if ($nombreProducto && strlen($nombreProducto) > 255) {
            $errors[] = 'Nombre del producto excede 255 caracteres';
        }

        $descripcion = trim($row['descripcion'] ?? '');
        if ($descripcion && strlen($descripcion) > 65535) {
            $warnings[] = 'Descripción muy larga, puede ser truncada';
        }

        $familiaTxt = trim($row['familia'] ?? '') !== '' ? trim($row['familia']) : $categoria;
        $subfamiliaTxt = trim($row['subfamilia'] ?? '') !== ''
            ? trim($row['subfamilia'])
            : trim($row['subcategoria'] ?? '');

        $opus = $this->opusMatch->match($familiaTxt, $subfamiliaTxt);
        if ($opus['warning']) {
            $warnings[] = $opus['warning'];
        } elseif ($opus['matched']) {
            $warnings[] = 'Homologado a catálogo OPUS (familia/subfamilia)';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'opus' => $opus,
        ];
    }

    private function loadExistingData(): void
    {
        $this->existingCodigos = Producto::where('proveedor_id', $this->proveedorId)
            ->pluck('codigo_interno')
            ->toArray();

        $this->existingUnidadMedidas = UnidadMedida::query()
            ->pluck('nombre')
            ->merge(UnidadMedida::query()->pluck('descripcion'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    public function getExpectedHeaders(): array
    {
        return [
            'codigo' => 'required',
            'producto' => 'required',
            'descripcion' => 'optional',
            'marca' => 'required',
            'categoria' => 'required',
            'subcategoria' => 'optional',
            'unidad_medida' => 'required',
            'precio' => 'optional',
            'precio_mayoreo' => 'optional',
            'precio_menudeo' => 'optional',
            'familia' => 'optional',
            'subfamilia' => 'optional',
            'tipo' => 'optional',
            'modelo' => 'optional',
            'codigo_fabricante' => 'optional',
            'codigo_barras' => 'optional',
            'presentacion' => 'optional',
            'cantidad_contenida' => 'optional',
            'unidad_contenido' => 'optional',
            'unidad_base' => 'optional',
            'factor_conversion' => 'optional',
            'disponibilidad' => 'optional',
            'tiempo_entrega' => 'optional',
            'url_producto' => 'optional',
            'tags' => 'optional',
        ];
    }

    public function validateHeaders(array $headers): array
    {
        $errors = [];
        $warnings = [];
        $expected = $this->getExpectedHeaders();

        foreach ($expected as $header => $requirement) {
            if ($requirement === 'required' && ! in_array($header, $headers)) {
                $errors[] = "Columna obligatoria '{$header}' no encontrada";
            }
        }

        foreach ($headers as $header) {
            if (! array_key_exists($header, $expected) && ! $this->isPropiedadHeader($header)) {
                $warnings[] = "Columna desconocida '{$header}' será ignorada";
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function isPropiedadHeader(string $header): bool
    {
        return (bool) preg_match('/^propiedad\d+_(clave|valor)$/i', $header);
    }
}
