<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresupuestoPlantilla extends BaseModel
{
    protected $table = 'presupuesto_plantillas';

    protected static $filters = [
        'proveedor_id' => 'ProveedorId',
        'activo' => 'Activo',
        'search' => 'Search',
    ];

    protected $fillable = [
        'proveedor_id',
        'user_id',
        'nombre',
        'descripcion',
        'activo',
        'concepto_general',
        'titulo_anexos',
        'titulo_anexos_pdf',
        'con_iva',
        'iva_porcentaje',
        'porcentaje_descuento',
        'cantidad_descuento',
        'term_cond_dias_vigencia',
        'term_cond_moneda',
        'term_cond_impuestos_en_pdf',
        'term_cond_iva',
        'term_cond_tiempo_entrega_dias',
        'term_cond_inicio_trabajo',
        'term_cond_inicio_trabajo_porcentaje',
        'term_cond_inicio_trabajo_cantidad',
        'term_cond_textos_libres',
        'term_cond_visibilidad',
        'validacion_alcances',
        'configuracion_condiciones',
        'obs_garantia_dias',
        'config_mostrar_totales',
        'pdf_theme',
        'ppto_config',
        'config_emisor_presupuesto_id',
        'empresa_emisora_nombre',
        'empresa_emisora_puesto',
        'empresa_emisora_telefono',
        'empresa_emisora_correo',
        'incluir_leyenda_atentamente',
        'empresa_emisora_nombre_comercial',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'con_iva' => 'boolean',
        'term_cond_impuestos_en_pdf' => 'boolean',
        'config_mostrar_totales' => 'boolean',
        'incluir_leyenda_atentamente' => 'boolean',
        'iva_porcentaje' => 'decimal:2',
        'cantidad_descuento' => 'decimal:2',
        'term_cond_iva' => 'decimal:2',
        'term_cond_inicio_trabajo_porcentaje' => 'decimal:2',
        'term_cond_inicio_trabajo_cantidad' => 'decimal:2',
        'porcentaje_descuento' => 'integer',
        'term_cond_dias_vigencia' => 'integer',
        'term_cond_tiempo_entrega_dias' => 'integer',
        'term_cond_inicio_trabajo' => 'integer',
        'obs_garantia_dias' => 'integer',
        'term_cond_textos_libres' => 'array',
        'term_cond_visibilidad' => 'array',
        'validacion_alcances' => 'array',
        'configuracion_condiciones' => 'array',
        'ppto_config' => 'array',
    ];

    /**
     * @return array<int, string>
     */
    public static function eagerLodable(): array
    {
        return ['conceptos', 'anexos', 'anexosPdf', 'proveedor', 'user'];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(PresupuestoPlantillaConcepto::class, 'presupuesto_plantilla_id')
            ->orderBy('numero');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(PresupuestoPlantillaAnexo::class, 'presupuesto_plantilla_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function anexosPdf(): HasMany
    {
        return $this->hasMany(PresupuestoPlantillaAnexoPdf::class, 'presupuesto_plantilla_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function configEmisorPresupuesto(): BelongsTo
    {
        return $this->belongsTo(ConfigEmisorReceptorPresupuesto::class, 'config_emisor_presupuesto_id');
    }

    public function filterByProveedorId($query, $value)
    {
        return $query->whereIn('proveedor_id', explode(',', (string) $value));
    }

    public function filterByActivo($query, $value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            return $query;
        }

        return $query->where('activo', $bool);
    }

    public function filterBySearch($query, string $value)
    {
        $term = trim($value);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('nombre', 'like', "%{$term}%")
                ->orWhere('descripcion', 'like', "%{$term}%")
                ->orWhere('concepto_general', 'like', "%{$term}%");
        });
    }
}
