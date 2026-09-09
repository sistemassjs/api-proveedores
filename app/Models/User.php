<?php

namespace App\Models;

use App\Enums\EstadoUsuario;
use App\Traits\AutoSwaggerSchema;
use App\Traits\Filterable;
use App\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use AutoSwaggerSchema, Filterable;
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'telefono_codigo_pais',
        'telefono',
        'foto_perfil_url',
        'password',
        'cambiar_pass_default',
        'role_id',
        'status',
        'es_cuenta_de_pruebas',
    ];

    protected $hidden = ['password', 'remember_token'];

    // Filtros disponibles para este modelo
    protected static $filters = [
        'nombre' => 'Nombre',
        'email' => 'Email',
        'role' => 'Role',
        'search' => 'Search',
        'role_id' => 'RoleId',
        'proveedor_id' => 'ProveedorId',
        'grupo_activos' => 'GrupoActivos',
        'grupo_inactivos' => 'GrupoInactivos',
        'grupo_pendientes' => 'GrupoPendientes',
        'grupo_registro_completados' => 'GrupoRegistroCompletados',
        'oauth_provider' => 'OauthProvider',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'string',
            'cambiar_pass_default' => 'boolean',
            'es_cuenta_de_pruebas' => 'boolean',
        ];
    }

    // Filtro específico para 'name'
    public function filterByNombre($query, $value)
    {
        return $query->where('name', 'like', "%$value%");
    }

    // Filtro específico para 'email'
    public function filterByEmail($query, $value)
    {
        return $query->where('email', 'like', "%$value%");
    }

    // Filtro específico para 'role'
    public function filterByRole($query, $value)
    {
        return $query->whereHas('role', function ($q) use ($value) {
            $q->where('nombre', 'like', "%$value%");
        });
    }

    public function filterBySearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('name', 'like', "%$value%")
                ->orWhere('email', 'like', "%$value%")
                ->orWhere('telefono', 'like', "%$value%");
        });
    }

    public function filterByRoleId($query, $value)
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where('role_id', (int) $value);
    }

    public function filterByOauthProvider($query, $value)
    {
        $provider = strtolower(trim((string) $value));
        if ($provider === '') {
            return $query;
        }

        return $query->whereHas('oauthAccounts', function ($q) use ($provider) {
            $q->where('provider', $provider);
        });
    }

    public function filterByProveedorId($query, $value)
    {
        if ($value === null || $value === '') {
            return $query;
        }

        $id = (int) $value;

        return $query->whereHas('proveedores', function ($q) use ($id) {
            $q->where('proveedores.id', $id);
        });
    }

    /** Segmento listado admin: cuentas activas (status = true). */
    public function filterByGrupoActivos($query, $value)
    {
        if ($value === null || $value === '' || $value === '0' || $value === false) {
            return $query;
        }

        return $query->where('status', true);
    }

    /** Segmento listado admin: cuentas inactivas (status = false). */
    public function filterByGrupoInactivos($query, $value)
    {
        if ($value === null || $value === '' || $value === '0' || $value === false) {
            return $query;
        }

        return $query->where('status', false);
    }

    /** Segmento listado admin: correo sin verificar. */
    public function filterByGrupoPendientes($query, $value)
    {
        if ($value === null || $value === '' || $value === '0' || $value === false) {
            return $query;
        }

        return $query->whereNull('email_verified_at');
    }

    /** Segmento listado admin: relación activa con estado registro_completado en user_proveedor. */
    public function filterByGrupoRegistroCompletados($query, $value)
    {
        if ($value === null || $value === '' || $value === '0' || $value === false) {
            return $query;
        }

        $estado = EstadoUsuario::REGISTRO_COMPLETADO->value;

        return $query->whereHas('userProveedores', function ($q) use ($estado) {
            $q->where('activo', true)->where('estado', $estado);
        });
    }

    // /**
    //  * Filtra los resultados de acuerdo a los filtros definidos.
    //  *
    //  * @param \Illuminate\Database\Eloquent\Builder $query
    //  * @param array $filters
    //  * @return \Illuminate\Database\Eloquent\Builder
    //  */
    // public function scopeFilter($query, array $filters)
    // {
    //     foreach ($filters as $key => $value) {
    //         if (isset(static::$filters[$key])) {
    //             $method = 'filterBy' . ucfirst($filters[$key]);
    //             if (method_exists($this, $method)) {
    //                 $query = $this->$method($query, $value);
    //             }
    //         }
    //     }
    //     return $query;
    // }

    // /**
    //  * Obtener los filtros definidos en la clase.
    //  *
    //  * @return array
    //  */
    // public static function getFilters(): array
    // {
    //     return array_values(static::$filters);
    // }

    /**
     * Define las relaciones permitidas para cargar con with() (eager loading).
     * Esto evita el problema N+1 y mejora el rendimiento de las consultas.
     *
     * @return string[]
     */
    public static function eagerLodable(): array
    {
        return [
            'role',
            'proveedores',
            'oauthAccounts',
        ];
    }

    /**
     * Relación con el rol del usuario
     *
     * @return BelongsTo<Role> El rol asignado al usuario
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Cuentas OAuth vinculadas (Google, etc.). Ver docs/context/platform-auth-socialite.md
     */
    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(OauthAccount::class);
    }

    /**
     * Relación directa con la tabla pivot user_proveedor
     * Útil para consultas complejas y acceso a campos pivot
     *
     * @return HasMany<UserProveedor> Colección de registros de la tabla pivot
     */
    public function userProveedores(): HasMany
    {
        return $this->hasMany(UserProveedor::class);
    }

    /**
     * Relación many-to-many con proveedores a través de tabla pivot
     * Incluye campos adicionales de la relación (tipo, estado, fechas)
     *
     * @return BelongsToMany<Proveedor> Colección de proveedores relacionados con datos pivot
     */
    public function proveedores(): BelongsToMany
    {
        return $this->belongsToMany(Proveedor::class, 'user_proveedor')
            ->using(UserProveedor::class)
            ->withPivot('tipo_relacion', 'activo', 'estado', 'fecha_asignacion', 'fecha_desasignacion', 'observaciones')
            ->withTimestamps();
    }

    /**
     * Obtiene el proveedor principal activo del usuario
     * Un usuario puede tener solo un proveedor principal activo
     *
     * @return Proveedor|null El proveedor principal del usuario o null si no tiene
     */
    public function proveedorPrincipal()
    {
        return $this->userProveedores()
            ->where('activo', true)
            ->whereIn('tipo_relacion', ['PRINCIPAL', 'SECUNDARIO'])
            ->orderByRaw("FIELD(tipo_relacion, 'PRINCIPAL', 'SECUNDARIO')") // prioridad
            ->with('proveedor')
            ->first()?->proveedor;
    }

    /**
     * Obtiene todos los proveedores activos del usuario
     * Incluye tanto principales como secundarios que estén activos
     *
     * @return BelongsToMany<Proveedor> Colección de proveedores activos
     */
    public function proveedoresActivos(): BelongsToMany
    {
        return $this->proveedores()->wherePivot('activo', true);
    }

    /**
     * Obtiene todos los proveedores secundarios activos del usuario
     * Excluye al proveedor principal
     *
     * @return BelongsToMany<Proveedor> Colección de proveedores secundarios activos
     */
    public function proveedoresSecundarios(): BelongsToMany
    {
        return $this->proveedores()
            ->wherePivot('activo', true)
            ->wherePivot('tipo_relacion', 'SECUNDARIO');
    }

    /**
     * Verifica si el usuario tiene acceso a un proveedor específico
     * Útil para validaciones de autorización
     *
     * @param  int  $proveedorId  ID del proveedor a verificar
     * @return bool True si el usuario tiene acceso activo al proveedor
     */
    public function tieneAccesoAProveedor(int $proveedorId): bool
    {
        return $this->userProveedores()
            ->where('proveedor_id', $proveedorId)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Obtiene el tipo de relación del usuario con un proveedor específico
     *
     * @param  int  $proveedorId  ID del proveedor
     * @return string|null 'PRINCIPAL', 'SECUNDARIO' o null si no hay relación activa
     */
    public function tipoRelacionConProveedor(int $proveedorId): ?string
    {
        return $this->userProveedores()
            ->where('proveedor_id', $proveedorId)
            ->where('activo', true)
            ->value('tipo_relacion');
    }

    /**
     * Relación con los tokens de dispositivos FCM
     *
     * @return HasMany<UserDeviceToken> Colección de tokens de dispositivos del usuario
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(UserDeviceToken::class);
    }

    /**
     * Obtiene tokens de dispositivos activos
     *
     * @return HasMany<UserDeviceToken> Tokens activos del usuario
     */
    public function activeDeviceTokens(): HasMany
    {
        return $this->deviceTokens()->active();
    }

    /**
     * Obtiene tokens activos por plataforma
     *
     * @param  string  $platform  'ios', 'android', 'web'
     * @return HasMany<UserDeviceToken> Tokens activos de la plataforma especificada
     */
    public function deviceTokensByPlatform(string $platform): HasMany
    {
        return $this->activeDeviceTokens()->byPlatform($platform);
    }

    /**
     * Obtiene todos los tokens activos para envío de push notifications
     *
     * @return array Array de strings con los tokens FCM
     */
    public function getFcmTokensAttribute(): array
    {
        return $this->activeDeviceTokens()
            ->recentlyUsed(30) // Solo tokens usados en los últimos 30 días
            ->pluck('token')
            ->toArray();
    }


    /**
     * HELPERS
     */

    /**
     * Determina si el usuario tiene un correo electrónico registrado
     * Esto es útil para decidir si se pueden enviar notificaciones por email o si se requiere solicitar un correo al usuario para completar su perfil
     */
    public function solicitarCorreo(): bool
    {
        // validar que el email sea un email valido, no solo que no sea null
        $emailIsValid = filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
        return $this->email !== null && !$emailIsValid;
    }


    /**
     * Obtiene los usuarios con rol de ADMINISTRADOR y status activo (true)
     * Esto es útil para enviar notificaciones o asignar tareas a los administradores activos del sistema
     * 
     */
    public function scopeAdministradoresActivos($query)
    {
        $roleId = Role::where('nombre', 'ADMINISTRADOR')->value('id');

        return $query
            ->where('role_id', $roleId);
    }

    /**
     * Usuarios que sí deben contar en totales / altas de métricas de plataforma.
     * Excluye roles internos (config), es_cuenta_de_pruebas y vínculos a empresas de pruebas.
     *
     * @see docs/context/platform-shared.md
     */
    public function scopeParaMetricasPlataforma($query)
    {
        $rolesExcluidos = config('metricas_plataforma.roles_excluidos', []);

        return $query
            ->where('es_cuenta_de_pruebas', false)
            ->where(function ($q) use ($rolesExcluidos) {
                $q->whereNull('role_id')
                    ->orWhereDoesntHave('role', function ($roleQuery) use ($rolesExcluidos) {
                        $roleQuery->whereIn('nombre', $rolesExcluidos);
                    });
            })
            ->whereDoesntHave('proveedores', function ($proveedorQuery) {
                $proveedorQuery->withoutGlobalScopes()
                    ->where('es_cuenta_de_pruebas', true);
            });
    }

    /**
     * Complemento de paraMetricasPlataforma: usuarios a excluir de KPIs.
     */
    public function scopeExcluidosDeMetricasPlataforma($query)
    {
        $rolesExcluidos = config('metricas_plataforma.roles_excluidos', []);

        return $query->where(function ($q) use ($rolesExcluidos) {
            $q->where('es_cuenta_de_pruebas', true)
                ->orWhereHas('role', function ($roleQuery) use ($rolesExcluidos) {
                    $roleQuery->whereIn('nombre', $rolesExcluidos);
                })
                ->orWhereHas('proveedores', function ($proveedorQuery) {
                    $proveedorQuery->withoutGlobalScopes()
                        ->where('es_cuenta_de_pruebas', true);
                });
        });
    }

    /**
     * Usuarios de producto para el listado admin (registrados).
     * Solo GERENTE / SUPERVISOR / VENTAS / AUXILIAR / CLIENTE.
     */
    public function scopeParaListadoAdminUsuarios($query)
    {
        $roles = config('metricas_plataforma.roles_listado_usuarios_admin', [
            'GERENTE',
            'SUPERVISOR',
            'VENTAS',
            'AUXILIAR',
            'CLIENTE',
        ]);

        return $query->whereHas('role', function ($roleQuery) use ($roles) {
            $roleQuery->whereIn('nombre', $roles);
        });
    }
}
