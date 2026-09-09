<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserProveedor extends Pivot
{
    use HasFactory;

    protected $table = 'user_proveedor';

    /**
     * Indica que la tabla pivot tiene timestamps
     */
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'proveedor_id',
        'tipo_relacion',
        'activo',
        'fecha_asignacion',
        'fecha_desasignacion',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_asignacion' => 'datetime',
        'fecha_desasignacion' => 'datetime',
    ];

    /**
     * Relación con el usuario asociado
     *
     * @return BelongsTo<User> El usuario de la relación
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el proveedor asociado
     *
     * @return BelongsTo<Proveedor> El proveedor de la relación
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Scope para obtener solo relaciones activas
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para obtener solo relaciones principales
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePrincipales($query)
    {
        return $query->where('tipo_relacion', 'PRINCIPAL');
    }

    /**
     * Scope para obtener solo relaciones secundarias
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSecundarios($query)
    {
        return $query->where('tipo_relacion', 'SECUNDARIO');
    }

    /**
     * Desactiva la relación marcando fecha de desasignación
     *
     * @param  string|null  $observacion  Motivo de la desasignación
     */
    public function desactivar(?string $observacion = null): bool
    {
        return $this->update([
            'activo' => false,
            'fecha_desasignacion' => now(),
            'observaciones' => $observacion ?? $this->observaciones,
        ]);
    }

    /**
     * Reactiva la relación limpiando fecha de desasignación
     */
    public function reactivar(): bool
    {
        return $this->update([
            'activo' => true,
            'fecha_desasignacion' => null,
        ]);
    }
}
