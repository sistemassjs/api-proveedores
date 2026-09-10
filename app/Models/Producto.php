<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends BaseModel
{
    use Filterable, HasFactory;

    protected $fillable = [
        'sku',
        'imagen_principal',
        'codigo_interno',
        'codigo_fabricante',
        'codigo_barras',
        'proveedor_id',
        'nombre',
        'descripcion',
        'marca_id',
        'categoria_id',
        'subcategoria_id',
        'familia_id',
        'subfamilia_id',
        'unidad_medida_id',
        'unidad_contenido_id',
        'unidad_base_id',
        'presentacion',
        'cantidad_contenida',
        'factor_conversion',
        'precio_base',
        'precio_mayoreo',
        'precio_menudeo',
        'modelo',
        'tipo',
        'disponibilidad',
        'tiempo_entrega',
        'url_producto',
        'tags',
        'activo',
        'stock',
        'destacado',
        'principal',
        'estatus',
        'mostrar_precios',
    ];

    protected static $filters = [
        'nombre' => 'Nombre',
        'descripcion' => 'Descripcion',
        'sku' => 'Sku',
        'codigo' => 'Codigo',
        'categoria_id' => 'CategoriaId',
        'subcategoria_id' => 'SubCategoriaId',
        'familia_id' => 'FamiliaId',
        'subfamilia_id' => 'SubfamiliaId',
        'proveedor_id' => 'ProveedorId',
        'marca_id' => 'MarcaId',
        'tipo' => 'Tipo',
        'activo' => 'Activo',
        'estatus' => 'Estatus',
    ];

    protected $casts = [
        'tags' => 'array',
        'precio_base' => 'float',
        'precio_mayoreo' => 'float',
        'precio_menudeo' => 'float',
        'cantidad_contenida' => 'float',
        'factor_conversion' => 'float',
        'principal' => 'boolean',
        'destacado' => 'boolean',
        'activo' => 'boolean',
        'mostrar_precios' => 'boolean',
    ];

    public static function eagerLodable(): array
    {
        return [
            'marca',
            'categoria',
            'subcategoria',
            'familia',
            'subfamilia',
            'unidad_medida',
            'unidadContenido',
            'unidadBase',
            'especificaciones',
            'imagenes',
            'documentos',
        ];
    }

    /** ----------------
     * Filtros
     * ----------------- */
    public function filterByCategoriaId($query, $value)
    {
        return $query->whereIn('categoria_id', explode(',', $value));
    }

    public function filterBySubCategoriaId($query, $value)
    {
        return $query->whereIn('subcategoria_id', explode(',', $value));
    }

    public function filterByFamiliaId($query, $value)
    {
        return $query->whereIn('familia_id', explode(',', (string) $value));
    }

    public function filterBySubfamiliaId($query, $value)
    {
        return $query->whereIn('subfamilia_id', explode(',', (string) $value));
    }

    public function filterByMarcaId($query, $value)
    {
        return $query->whereIn('marca_id', explode(',', $value));
    }

    public function filterByProveedorId($query, $value)
    {
        return $query->whereIn('proveedor_id', explode(',', $value));
    }

    public function filterByNombre($query, $value)
    {
        return $query->where('nombre', 'like', "%$value%");
    }

    public function filterByDescripcion($query, $value)
    {
        return $query->where('descripcion', 'like', "%$value%");
    }

    public function filterBySku($query, $value)
    {
        return $query->where('sku', 'like', "%$value%");
    }

    public function filterByCodigo($query, $value)
    {
        return $query->where('codigo_interno', 'like', "%$value%");
    }

    public function filterByTipo($query, $value)
    {
        return $query->where('tipo', $value);
    }

    public function filterByActivo($query, $value)
    {
        return $query->where('activo', (bool) $value);
    }

    public function filterByEstatus($query, $value)
    {
        return $query->where('estatus', $value);
    }

    /** ----------------
     * Relaciones
     * ----------------- */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function unidad_medida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function unidadContenido(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_contenido_id');
    }

    public function unidadBase(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_base_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function subcategoria()
    {
        return $this->belongsTo(Categoria::class, 'subcategoria_id');
    }

    public function familia(): BelongsTo
    {
        return $this->belongsTo(CatalogoFamilia::class, 'familia_id');
    }

    public function subfamilia(): BelongsTo
    {
        return $this->belongsTo(CatalogoSubfamilia::class, 'subfamilia_id');
    }

    public function especificaciones(): HasMany
    {
        return $this->hasMany(ProductoEspecificacion::class);
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ProductoImagen::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(ProductoDocumento::class);
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class)
            ->withPivot('stock_local', 'precio_local', 'activo')
            ->withTimestamps();
    }
}
