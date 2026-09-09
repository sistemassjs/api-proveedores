<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait Filterable
{
    /**
     * Aplica filtros dinámicos definidos en el modelo.
     *
     * Cada filtro debe estar definido en la propiedad $filters del modelo,
     * y debe tener un método filterByX($query, $value) correspondiente.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
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
                $this->$method($query, $value);
            } else {
                Log::warning("Método $method no existe en " . static::class);
            }
        }

        return $query;
    }

    /**
     * Devuelve las claves disponibles para aplicar filtros dinámicos.
     *
     * @return array
     */
    public static function getFilters(): array
    {
        return array_keys(static::$filters ?? []);
    }
}
