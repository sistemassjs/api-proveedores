<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\EstadoUsuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;



class Proveedor extends BaseModel
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        // Información general
        'logo',
        'nombre_comercial',
        'pagina_web',
        'email',
        'telefono_codigo_pais',
        'telefono',
        'celular',
        'estatus',
        'notas',
        'validado_por',
        'user_id',
        'nombre_propietario',
        'nombre_de_quien_registra',
        'monto_pagado',
        // NOTA: El UNIQUE de razon_social se desactivó en una migración para permitir el registro manual de Manuel.
        'razon_social',
        'tipos_empresa_id',
        'tipos_empresa_otro',
        'descripcion_giro_empresa',
        'direccion_empresa',
        'estado',
        'municipio',
        'codigo_postal',
        'ciudad',
        'contacto_nombre',
        'contacto_cargo',
        'contacto_telefono',
        'contacto_correo',
        'principal',
        'calificacion',
        'categoria',
        // default values
        'is_proveedor_sp',
        'is_proveedor_catalogo',
        'perfil_empresa_completo',
        'es_cuenta_de_pruebas',

        // Información fiscal (al final)
        'rfc',
        'tipo_persona',
        'regimen_fiscal_clave',
        'regimen_fiscal_nombre',
        'constancia_fiscal',
        'direccion_fiscal',

        //
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'ciudad',
        'estado',
        'codigo_postal',
        'pais',
        'tipo_alta',    // 1: Proveedor  2: UserConstrucc
        'user_construcc_alta',
        'empresa_construcc_alta',
        'consecutivo_presupuesto_siguiente',
        'token_completar_registro',
        'token_completar_registro_generado_at',
        'registro_completado_at',
    ];

    protected $attributes = [
        'is_proveedor_sp' => true,
        'is_proveedor_catalogo' => false,
        'perfil_empresa_completo' => false,
        'es_cuenta_de_pruebas' => false,
        'tipo_alta' => 1, // Por defecto se asume que el alta es por Proveedor, no por UserConstrucc
        'consecutivo_presupuesto_siguiente' => 1, // Iniciar el consecutivo de presupuestos en 1
        'calificacion' => 0, // Calificación inicial en 0
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'is_proveedor_sp' => 'boolean',
        'is_proveedor_catalogo' => 'boolean',
        'perfil_empresa_completo' => 'boolean',
        'es_cuenta_de_pruebas' => 'boolean',
        'tipo_alta' => 'integer',
        'user_construcc_alta' => 'integer',
        'empresa_construcc_alta' => 'integer',
        'consecutivo_presupuesto_siguiente' => 'integer',
        'token_completar_registro_generado_at' => 'datetime',
        'registro_completado_at' => 'datetime',
    ];

    protected static $filters = [
        'nombre_comercial' => 'nombre_comercial',
        'razon_social' => 'razon_social',
        'rfc' => 'rfc',
        'direccion_fiscal' => 'direccion_fiscal',
        'estado' => 'estado',
        'municipio' => 'municipio',
        'fecha_registro' => 'fecha_registro',
        'estatus' => 'Estatus',
        'grupo_operativos' => 'GrupoOperativos',
        'tipo_alta' => 'TipoAlta',
        'cuentas_pruebas' => 'CuentasPruebas',
        'notas' => 'notas',
        'email' => 'email',
        'descripcion_giro_empresa' => 'descripcion_giro_empresa',
        'direccion_empresa' => 'direccion_empresa',
        'pagina_web' => 'pagina_web',
        // 👇 Nuevo filtro
        'empresas_construcc' => 'EmpresasConstrucc',
        'search' => 'Search',
        'oauth_provider' => 'OauthProvider',
    ];


    public static function eagerLodable(): array
    {
        return [
            'categorias',
            'marcas',
            'sucursales',
            'productos',
            'cuentasBancarias',
            'regimenesFiscales',
            'empresasConstrucc',
            'solicitudesPago',
        ];
    }

    // ================== SCOPES ==================

    public function scopeFilterByNombreComercial($query, $value)
    {
        return $query->where('nombre_comercial', 'like', "%$value%");
    }

    public function scopeFilterByRazonSocial($query, $value)
    {
        return $query->where('razon_social', 'like', "%$value%");
    }

    public function scopeFilterByRfc($query, $value)
    {
        return $query->where('rfc', 'like', "%$value%");
    }

    public function scopeFilterByDireccionFiscal($query, $value)
    {
        return $query->where('direccion_fiscal', 'like', "%$value%");
    }

    public function scopeFilterByEstado($query, $value)
    {
        return $query->where('estado', 'like', "%$value%");
    }

    public function scopeFilterByMunicipio($query, $value)
    {
        return $query->where('municipio', 'like', "%$value%");
    }

    public function scopeFilterByFechaRegistro($query, $value)
    {
        return $query->whereDate('fecha_registro', $value);
    }

    public function scopeFilterByEstatus($query, $value)
    {
        return $this->filterByEstatus($query, $value);
    }

    /**
     * Filtro por estatus (listado admin).
     */
    public function filterByEstatus($query, $value)
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where('estatus', $value);
    }

    /**
     * Listado admin: segmento «Operativos» (excluye bloqueados y suspendidos).
     */
    public function scopeFilterByGrupoOperativos($query, $value)
    {
        return $this->filterByGrupoOperativos($query, $value);
    }

    public function filterByGrupoOperativos($query, $value)
    {
        if ($value === null || $value === '' || $value === '0' || $value === false) {
            return $query;
        }

        return $query->whereNotIn('estatus', ['bloqueado', 'suspendido']);
    }

    /**
     * 1 = alta proveedor (incluye null). 2 = alta usuario construcción.
     */
    public function scopeFilterByTipoAlta($query, $value)
    {
        return $this->filterByTipoAlta($query, $value);
    }

    public function filterByTipoAlta($query, $value)
    {
        if ($value === null || $value === '') {
            return $query;
        }

        $tipo = (int) $value;

        if ($tipo === 1) {
            return $query->where(function ($q) {
                $q->where('tipo_alta', 1)->orWhereNull('tipo_alta');
            });
        }

        return $query->where('tipo_alta', $tipo);
    }

    /**
     * Listado admin: productivas (default) / solo pruebas / todas.
     * Valores: excluir|0 · solo|1 · todas|all
     */
    public function scopeFilterByCuentasPruebas($query, $value)
    {
        return $this->filterByCuentasPruebas($query, $value);
    }

    public function filterByCuentasPruebas($query, $value)
    {
        $v = is_string($value) ? strtolower(trim($value)) : $value;

        if ($v === null || $v === '' || $v === 'excluir' || $v === '0' || $v === false) {
            return $query->where('es_cuenta_de_pruebas', false);
        }

        if ($v === 'solo' || $v === '1' || $v === true) {
            return $query->where('es_cuenta_de_pruebas', true);
        }

        if ($v === 'todas' || $v === 'all') {
            return $query;
        }

        return $query->where('es_cuenta_de_pruebas', false);
    }

    /**
     * Empresas cuyo usuario PRINCIPAL activo tiene OAuth (ej. google).
     */
    public function filterByOauthProvider($query, $value)
    {
        $provider = strtolower(trim((string) $value));
        if ($provider === '') {
            return $query;
        }

        return $query->whereHas('users', function ($q) use ($provider) {
            $q->where('user_proveedor.tipo_relacion', 'PRINCIPAL')
                ->where('user_proveedor.activo', true)
                ->whereHas('oauthAccounts', function ($oq) use ($provider) {
                    $oq->where('provider', $provider);
                });
        });
    }

    public function scopeFilterByNotas($query, $value)
    {
        return $query->where('notas', 'like', "%$value%");
    }

    public function scopeFilterByEmail($query, $value)
    {
        return $query->where('email', 'like', "%$value%");
    }

    // ================== RELACIONES ==================

    /**
     * Relación con los pagos SPP realizados al proveedor.
     */
    public function pagosSPP(): HasMany
    {
        return $this->hasMany(PagoSPP::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    public function marcas(): HasMany
    {
        return $this->hasMany(Marca::class);
    }

    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    public function sucursalesActivas(): HasMany
    {
        return $this->hasMany(Sucursal::class)->where('activa', true);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function tipos_empresa()
    {
        return $this->belongsTo(TipoEmpresa::class);
    }

    public function validador()
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function userProveedores(): HasMany
    {
        return $this->hasMany(UserProveedor::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_proveedor')
            ->withPivot('tipo_relacion', 'activo', 'fecha_asignacion', 'fecha_desasignacion', 'observaciones')
            ->withTimestamps();
    }

    public function usuarioPrincipal()
    {
        return $this->userProveedores()
            ->where('activo', true)
            ->where('tipo_relacion', 'PRINCIPAL')
            ->with('user')
            ->first()?->user;
    }

    public function usuariosActivos(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('activo', true)
            ->wherePivotNull('fecha_desasignacion')
            ->where('users.status', true);
    }
    public function usuariosSecundarios(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('activo', true)
            ->wherePivot('tipo_relacion', 'SECUNDARIO');
    }

    public function tieneUsuarioConAcceso(int $userId): bool
    {
        return $this->userProveedores()
            ->where('user_id', $userId)
            ->where('activo', true)
            ->exists();
    }

    public function scopeFilterByEmpresasConstrucc($query, $value)
    {
        $empresas = explode(',', $value);

        return $query->whereHas('empresasConstrucc', function ($q) use ($empresas) {
            $q->whereIn('empresa_construcc.id', $empresas);

            if ($this->empresa_construcc_alta) {
                $q->orWhere('empresa_construcc.id', $this->empresa_construcc_alta);
            }
        });
    }

    /**
     * Filtro de búsqueda global
     * Busca en múltiples campos: folio, concepto, observaciones, usuario, referencia OC y empresa
     */
    public function filterBySearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('nombre_comercial', 'like', "%$value%")
                ->orWhere('nombre_propietario', 'like', "%$value%")
                ->orWhere('razon_social', 'like', "%$value%")
                ->orWhere('rfc', 'like', "%$value%")
                ->orWhere('direccion_fiscal', 'like', "%$value%")
                ->orWhere('estado', 'like', "%$value%")
                ->orWhere('municipio', 'like', "%$value%")
                // ->orWhere('fecha_registro', 'like', "%$value%")
                ->orWhere('estatus', 'like', "%$value%")
                ->orWhere('notas', 'like', "%$value%")
                ->orWhere('email', 'like', "%$value%")
                ->orWhere('descripcion_giro_empresa', 'like', "%$value%")
                ->orWhere('direccion_empresa', 'like', "%$value%")
                ->orWhere('pagina_web', 'like', "%$value%");
        });
    }

    // ================== CUENTAS BANCARIAS ==================

    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(CuentaBancaria::class);
    }

    /**
     * Regímenes fiscales de la constancia (1:N).
     */
    public function regimenesFiscales(): HasMany
    {
        return $this->hasMany(ProveedorRegimenFiscal::class)
            ->orderByDesc('es_principal')
            ->orderBy('clave');
    }

    public function perfilPublico(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProveedorPerfilPublico::class);
    }

    public function scopeCuentasActivas($query)
    {
        return $query->whereHas('cuentasBancarias', function ($q) {
            $q->where('estatus', 'activo');
        });
    }

    public function scopeFilterByCuentaAlias($query, $alias)
    {
        return $query->whereHas('cuentasBancarias', function ($q) use ($alias) {
            $q->where('alias', 'like', "%{$alias}%");
        });
    }

    public function scopeFilterByTipoCuenta($query, $tipoCuenta)
    {
        return $query->whereHas('cuentasBancarias', function ($q) use ($tipoCuenta) {
            $q->whereNotNull($tipoCuenta)->where($tipoCuenta, '!=', '');
        });
    }

    public function scopeFilterByBancoClave($query, $bancoClave)
    {
        return $query->whereHas('cuentasBancarias', function ($q) use ($bancoClave) {
            $q->where('banco_clave', $bancoClave);
        });
    }

    public function empresasConstrucc()
    {
        return $this->belongsToMany(EmpresaConstrucc::class, 'empresa_construcc_proveedor')
            ->withPivot('usuario_construcc_id', 'usuario_construcc_nombre')
            ->withTimestamps();
    }

    /**
     * Empresa constructora que registró el alta cuando tipo_alta = 2 (UserConstrucc).
     */
    public function empresaConstruccAlta(): BelongsTo
    {
        return $this->belongsTo(EmpresaConstrucc::class, 'empresa_construcc_alta');
    }

    /**
     * Nombre del usuario construcción que dio de alta al proveedor (pivot empresa–proveedor).
     */
    public function nombreUsuarioConstruccAlta(): ?string
    {
        if (!$this->user_construcc_alta || !$this->relationLoaded('empresasConstrucc')) {
            return null;
        }

        foreach ($this->empresasConstrucc as $empresa) {
            if ($this->empresa_construcc_alta && (int) $empresa->id !== (int) $this->empresa_construcc_alta) {
                continue;
            }
            if ((int) ($empresa->pivot->usuario_construcc_id ?? 0) === (int) $this->user_construcc_alta) {
                $nombre = $empresa->pivot->usuario_construcc_nombre ?? null;

                return $nombre !== null && $nombre !== '' ? $nombre : null;
            }
        }

        return null;
    }

    // ================== PRESUPUESTOS ==================

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }

    // ================== SOLICITUDES DE PAGO ==================

    public function solicitudesPago(): HasMany
    {
        return $this->hasMany(SolicitudPago::class);
    }

    public function solicitudesPagoActivas(): HasMany
    {
        return $this->solicitudesPago()->whereIn('estado_solicitud', ['pendiente', 'autorizada', 'procesando']);
    }

    /**
     * Incrementa y devuelve el siguiente consecutivo de presupuesto.
     */
    public function obtenerConsecutivoSiguientePresupuesto(): int
    {
        return DB::transaction(function () {
            $this->refresh();

            $folioSiguiente = $this->consecutivo_presupuesto_siguiente ?? 1;
            $this->consecutivo_presupuesto_siguiente = ((int) ($this->consecutivo_presupuesto_siguiente ?? 1)) + 1;
            $this->save();

            return (int) $folioSiguiente;
        });
    }

    /**
     * Obtiene el folio formateado del siguiente presupuesto.
     * Formato: PRES-0001
     */
    public function obtenerFolioSiguientePresupuesto(): string
    {
        $consecutivo = $this->obtenerConsecutivoSiguientePresupuesto();

        return 'PRES-' . str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }


    /**
     * GLOBALS FILTERS
     */



    /**
     * Consultas de listado/gestión en rutas administradoras (incluye suspendidos y bloqueados).
     */
    public static function queryParaAdmin(): Builder
    {
        return static::withoutGlobalScope('solo_activos');
    }

    /**
     * Empresas que sí cuentan en totales de métricas de plataforma.
     *
     * @see docs/context/platform-shared.md
     */
    public function scopeParaMetricasPlataforma($query)
    {
        return $query->where('es_cuenta_de_pruebas', false);
    }

    /**
     * Boot del modelo.
     *
     * Se ejecuta automáticamente cuando el modelo es inicializado por Eloquent.
     *
     * Aquí se registra un Global Scope llamado "solo_activos" que restringe
     * todas las consultas del modelo Proveedor para incluir únicamente
     * proveedores con estatus:
     *
     * - REGISTRADO
     * - VERIFICADO
     *
     * Esto significa que cualquier consulta como:
     * Proveedor::all()
     * Proveedor::query()
     * Proveedor::with(...)
     *
     * automáticamente aplicará:
     * WHERE estatus IN ('registrado', 'verificado')
     *
     * ⚠ IMPORTANTE:
     * Si se requiere consultar proveedores suspendidos o bloqueados,
     * se debe usar:
     *
     * Proveedor::withoutGlobalScope('solo_activos')
     *
     * o
     *
     * Proveedor::withoutGlobalScopes()
     * 
     * Para el prefijo de rutas: 'api/admin', se ignora este global scope para permitir la gestión completa de proveedores, incluyendo los bloqueados.
     * mendiante Route Model Binding condicional registrado en RouteServiceProvider.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('solo_activos', function (Builder $builder) {
            $builder->whereNotIn('estatus', [
                EstadoUsuario::SUSPENDIDO->value,
                EstadoUsuario::BLOQUEADO->value,
            ]);
        });
    }
}
