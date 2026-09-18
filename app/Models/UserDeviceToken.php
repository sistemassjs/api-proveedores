<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_id',
        'device_name',
        'metadata',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $dates = [
        'last_used_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Relación con User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para tokens activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope por plataforma
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope para tokens usados recientemente
     */
    public function scopeRecentlyUsed($query, int $days = 30)
    {
        return $query->where('last_used_at', '>=', now()->subDays($days));
    }

    /**
     * Marcar token como usado
     */
    public function markAsUsed(): void
    {
        $this->update([
            'last_used_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Desactivar token
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Verificar si el token está expirado (no usado en X días)
     */
    public function isExpired(int $days = 60): bool
    {
        if (! $this->last_used_at) {
            return $this->created_at->lt(now()->subDays($days));
        }

        return $this->last_used_at->lt(now()->subDays($days));
    }

    /**
     * Obtener información del dispositivo
     */
    public function getDeviceInfoAttribute(): array
    {
        return [
            'platform' => $this->platform,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'last_used' => $this->last_used_at?->diffForHumans(),
            'metadata' => $this->metadata,
        ];
    }
}
