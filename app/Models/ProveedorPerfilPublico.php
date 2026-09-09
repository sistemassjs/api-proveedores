<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProveedorPerfilPublico extends BaseModel
{
    protected $table = 'proveedor_perfil_publico';

    protected $fillable = [
        'proveedor_id',
        'token',
        'theme_key',
        'is_published',
        'sections',
        'snapshot',
        'published_at',
    ];

    protected $casts = [
        'proveedor_id' => 'integer',
        'is_published' => 'boolean',
        'sections' => 'array',
        'snapshot' => 'array',
        'published_at' => 'datetime',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function asegurarToken(): string
    {
        if ($this->token) {
            return $this->token;
        }

        do {
            $token = Str::random(40);
        } while (static::query()->where('token', $token)->exists());

        $this->token = $token;
        $this->save();

        return $token;
    }
}
