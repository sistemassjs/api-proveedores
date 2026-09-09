<?php

namespace App\Models;

use App\Traits\AutoSwaggerSchema;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

abstract class BaseModel extends Model
{
    use AutoSwaggerSchema, Filterable;

    protected $connection = 'mysql5';
    public $timestamps = true;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected static $filters = [];

    /**
     * Aplicar filtros con OR encadenado.
     *
     * @param $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query, array $filters)
    {
        foreach ($filters as $filter => $value) {
            Log::debug("Recibiendo filtro: $filter = $value");

            if (! isset(static::$filters[$filter]) || $value === null || $value === '') {
                continue;
            }

            $method = 'filterBy' . ucfirst(static::$filters[$filter]);
            Log::debug("Aplicando método: $method");

            if (method_exists($this, $method)) {
                $query = $this->$method($query, $value); // 👈 importante
            } else {
                Log::warning("Método $method no existe en " . static::class);
            }
        }

        return $query;
    }

    /**
     * Obtener los filtros definidos en la clase.
     */
    public static function getFilters(): array
    {
        return array_keys(static::$filters);
    }
}
