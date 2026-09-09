<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuentas OAuth vinculadas a users.
 *
 * Hereda BaseModel, pero NO usa mysql5: debe compartír conexión con User
 * para no provocar Lock wait timeout en el FK user_id.
 */
class OauthAccount extends BaseModel
{
    /**
     * null = conexión default (igual que User).
     * Anula el mysql5 de BaseModel.
     */
    protected $connection = null;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'avatar',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
