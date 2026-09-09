<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Orden de listados admin: registros bloqueados / de baja al final.
 */
class AdminListOrdering
{
  public static function applyUserStatusPriority(Builder $query, string $table = 'users'): Builder
  {
    $status = "{$table}.status";

    return $query->orderByRaw(
      "CASE
        WHEN LOWER(COALESCE(CAST({$status} AS CHAR), '')) IN ('bloqueado', 'suspendido') THEN 2
        WHEN LOWER(COALESCE(CAST({$status} AS CHAR), '')) IN ('0', 'false', 'inactivo') THEN 1
        ELSE 0
      END ASC"
    );
  }

  public static function applyProveedorEstatusPriority(Builder $query, string $table = 'proveedores'): Builder
  {
    $estatus = "{$table}.estatus";

    return $query->orderByRaw(
      "CASE
        WHEN {$estatus} IN ('bloqueado', 'suspendido') THEN 1
        ELSE 0
      END ASC"
    );
  }

  /**
   * Usuarios vinculados a empresa: vínculo inactivo o cuenta restringida al final.
   * Recibe la relación BelongsToMany ($proveedor->users()) por usar columnas del pivot.
   */
  public static function applyProveedorUsuarioPriority(BelongsToMany $query): BelongsToMany
  {
    return $query
      ->orderByRaw('CASE WHEN user_proveedor.activo = 0 THEN 1 ELSE 0 END ASC')
      ->orderByRaw(
        "CASE
          WHEN LOWER(COALESCE(CAST(users.status AS CHAR), '')) IN ('bloqueado', 'suspendido') THEN 1
          WHEN LOWER(COALESCE(CAST(users.status AS CHAR), '')) IN ('0', 'false', 'inactivo') THEN 1
          ELSE 0
        END ASC"
      );
  }
}
