<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresupuestoCatalogoConcepto extends BaseModel
{
    public const CATEGORIA_PRODUCTO = 'producto';

    public const CATEGORIA_SERVICIO = 'servicio';

    /** Tipos de insumo en matriz de costos (componentes). */
    public const INSUMO_MATERIAL = 'material';

    public const INSUMO_MANO_OBRA = 'mano_obra';

    public const INSUMO_HERRAMIENTA = 'herramienta';

    public const INSUMO_EQUIPO = 'equipo';

    public const INSUMO_AUXILIAR = 'auxiliar';

    public const INSUMO_FLETE = 'flete';

    public const INSUMO_TRABAJO = 'trabajo';

    public const DESCRIPCION_MAX = 500;

    public const CLAVE_MAX = 40;

    protected $table = 'presupuesto_catalogo_conceptos';

    protected static $filters = [
        'proveedor_id' => 'ProveedorId',
        'categoria' => 'Categoria',
        'search' => 'Search',
        'activo' => 'Activo',
        'es_compuesto' => 'EsCompuesto',
    ];

    protected $fillable = [
        'proveedor_id',
        'descripcion',
        'categoria',
        'es_compuesto',
        'clave',
        'unidad',
        'precio_unitario',
        'imagen_path',
        'activo',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:4',
        'activo' => 'boolean',
        'es_compuesto' => 'boolean',
    ];

    /**
     * Proveedor dueño del catálogo.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Componentes de la matriz (solo compuestos).
     */
    public function componentes(): HasMany
    {
        return $this->hasMany(PresupuestoCatalogoConceptoComponente::class, 'catalogo_concepto_id')
            ->orderBy('orden');
    }

    public function esCompuesto(): bool
    {
        return (bool) $this->es_compuesto;
    }

    public function esBasico(): bool
    {
        return ! $this->esCompuesto();
    }

    /**
     * Scope por proveedor.
     */
    public function scopeByProveedor($query, int $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }

    /**
     * Filtro por proveedor.
     */
    public function filterByProveedorId($query, $value)
    {
        return $query->whereIn('proveedor_id', explode(',', (string) $value));
    }

    /**
     * Filtro por categoría (producto|servicio).
     */
    public function filterByCategoria($query, string $value)
    {
        return $query->where('categoria', $value);
    }

    /**
     * Filtro por compuesto (true|false|1|0).
     */
    public function filterByEsCompuesto($query, $value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            return $query;
        }

        return $query->where('es_compuesto', $bool);
    }

    /**
     * Búsqueda general en descripción / unidad / clave / id.
     */
    public function filterBySearch($query, string $value)
    {
        $numericId = ctype_digit($value) ? (int) $value : null;

        return $query->where(function ($q) use ($value, $numericId) {
            $q->where('descripcion', 'like', "%{$value}%")
                ->orWhere('unidad', 'like', "%{$value}%")
                ->orWhere('clave', 'like', "%{$value}%");
            if ($numericId !== null) {
                $q->orWhere('id', $numericId);
            }
        });
    }

    /**
     * Filtro por activo (true|false|1|0|si|no).
     */
    public function filterByActivo($query, $value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            return $query;
        }

        return $query->where('activo', $bool);
    }

    /**
     * Categoría comercial del concepto de catálogo (producto|servicio).
     *
     * @return list<string>
     */
    public static function categoriasValidas(): array
    {
        return [
            self::CATEGORIA_PRODUCTO,
            self::CATEGORIA_SERVICIO,
        ];
    }

    /**
     * Tipos de insumo Opus válidos en componentes de matriz
     * (sin producto/servicio: esas son categorías comerciales del catálogo).
     *
     * @return list<string>
     */
    public static function categoriasInsumoValidas(): array
    {
        return [
            self::INSUMO_MATERIAL,
            self::INSUMO_MANO_OBRA,
            self::INSUMO_HERRAMIENTA,
            self::INSUMO_EQUIPO,
            self::INSUMO_AUXILIAR,
            self::INSUMO_FLETE,
            self::INSUMO_TRABAJO,
        ];
    }

    /**
     * Acepta tipos Opus o legacy producto/servicio (mapeados).
     */
    public static function coerceCategoriaInsumo(?string $categoria): string
    {
        $cat = trim((string) $categoria);
        if (self::esCategoriaInsumoValida($cat)) {
            return $cat;
        }
        if ($cat === self::CATEGORIA_PRODUCTO) {
            return self::INSUMO_MATERIAL;
        }
        if ($cat === self::CATEGORIA_SERVICIO) {
            return self::INSUMO_MANO_OBRA;
        }

        return self::INSUMO_MATERIAL;
    }

    public static function esCategoriaInsumoValida(?string $categoria): bool
    {
        return $categoria !== null
            && $categoria !== ''
            && in_array($categoria, self::categoriasInsumoValidas(), true);
    }

    public static function labelCategoriaInsumo(string $categoria): string
    {
        return match (self::coerceCategoriaInsumo($categoria)) {
            self::INSUMO_MATERIAL => 'Material',
            self::INSUMO_MANO_OBRA => 'Mano de obra',
            self::INSUMO_HERRAMIENTA => 'Herramienta',
            self::INSUMO_EQUIPO => 'Equipo',
            self::INSUMO_AUXILIAR => 'Auxiliar',
            self::INSUMO_FLETE => 'Flete',
            self::INSUMO_TRABAJO => 'Trabajo',
            default => $categoria,
        };
    }
}
