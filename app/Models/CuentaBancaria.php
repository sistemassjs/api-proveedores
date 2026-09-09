<?php

namespace App\Models;

use App\Enums\EstadoCuentaBancaria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaBancaria extends BaseModel
{
    use HasFactory;

    protected $table = 'cuentas_bancarias';

    /**
     * Campos de identificación bancaria:
     * - cuenta: número de cuenta; solo dígitos. Longitud libre en validación (columna BD string(255)).
     * - clabe: 18 dígitos.
     * - tarjeta: 16 dígitos.
     */
    protected $fillable = [
        'proveedor_id',
        'alias',
        'banco_clave',
        'banco_nombre',
        'cuenta',
        'clabe',
        'tarjeta',
        'titular_cuenta',
        'referencia',
        'estatus',
        'sucursal',
        'swift',
        'preferida',
    ];

    protected $casts = [
        'estatus' => EstadoCuentaBancaria::class,
        'preferida' => 'boolean',
    ];

    protected static $filters = [
        'proveedor_id' => 'ProveedorId',
        'alias' => 'Alias',
        'banco_clave' => 'BancoClave',
        'banco_nombre' => 'BancoNombre',
        'cuenta' => 'Cuenta',
        'clabe' => 'Clabe',
        'tarjeta' => 'Tarjeta',
        'titular_cuenta' => 'TitularCuenta',
        'referencia' => 'Referencia',
        'estatus' => 'Estatus',
        'sucursal' => 'Sucursal',
        'swift' => 'Swift',
        'preferida' => 'Preferida',
    ];

    /**
     * Relación con el proveedor
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Relación con solicitudes de pago (a través de tabla pivot)
     */
    public function solicitudesPago(): HasMany
    {
        return $this->hasMany(SolicitudPagoCuentaBancaria::class, 'cuenta_bancaria_id');
    }

    // ================== SCOPES ==================

    public function scopeProveedor($query, int $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }

    public function scopeAlias($query, string $alias)
    {
        return $query->where('alias', 'like', "%{$alias}%");
    }

    public function scopeCuenta($query, string $cuenta)
    {
        return $query->where('cuenta', 'like', "%{$cuenta}%");
    }

    public function scopeClabe($query, string $clabe)
    {
        return $query->where('clabe', 'like', "%{$clabe}%");
    }

    public function scopeTarjeta($query, string $tarjeta)
    {
        return $query->where('tarjeta', 'like', "%{$tarjeta}%");
    }

    public function scopeBanco($query, string $claveBanco)
    {
        return $query->where('banco_clave', $claveBanco);
    }

    public function scopeActivas($query)
    {
        return $query->where('estatus', EstadoCuentaBancaria::ACTIVA);
    }

    public function scopeTitular($query, string $titular)
    {
        return $query->where('titular_cuenta', 'like', "%{$titular}%");
    }

    public function scopeReferencia($query, string $referencia)
    {
        return $query->where('referencia', 'like', "%{$referencia}%");
    }

    public function scopeSucursal($query, string $sucursal)
    {
        return $query->where('sucursal', 'like', "%{$sucursal}%");
    }

    public function scopeSwift($query, string $swift)
    {
        return $query->where('swift', 'like', "%{$swift}%");
    }

    public function scopePreferida($query, bool $preferida = true)
    {
        return $query->where('preferida', $preferida);
    }

    /**
     * Obtiene el número principal para pagos (clabe, cuenta o tarjeta según disponibilidad).
     */
    public function obtenerNumeroPago(): ?string
    {
        return $this->clabe ?? $this->cuenta ?? $this->tarjeta;
    }
}
