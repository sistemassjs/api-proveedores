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
        'app_key',
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
     * Scope por app cliente (gestion | nexprov)
     */
    public function scopeByAppKey($query, string $appKey)
    {
        return $query->where('app_key', \App\Support\ClientApp::normalize($appKey));
    }

    /**
     * @param  list<string>  $appKeys
     */
    public function scopeByAppKeys($query, array $appKeys)
    {
        $normalized = array_values(array_unique(array_map(
            fn (string $key) => \App\Support\ClientApp::normalize($key),
            $appKeys
        )));

        return $query->whereIn('app_key', $normalized);
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
            'app_key' => $this->app_key,
            'platform' => $this->platform,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'last_used' => $this->last_used_at?->diffForHumans(),
            'metadata' => $this->metadata,
        ];
    }
}
