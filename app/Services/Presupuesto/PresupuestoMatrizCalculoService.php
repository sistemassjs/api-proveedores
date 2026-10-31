<?php

namespace App\Services\Presupuesto;

use App\Models\Presupuesto;
use App\Models\PresupuestoCatalogoConcepto;
use App\Models\PresupuestoCatalogoConceptoComponente;
use App\Models\PresupuestoConcepto;
use App\Models\PresupuestoConceptoComponente;
use App\Models\PresupuestoPlantillaConcepto;
use InvalidArgumentException;

/**
 * Motor único de matriz de P.U.: importe = cantidad × precio_unitario; P.U. padre = Σ importes.
 * Tipos de insumo: material|mano_obra|herramienta|equipo|auxiliar|producto|servicio.
 */
class PresupuestoMatrizCalculoService
{
    public function calcularImporte(float|string $cantidad, float|string $precioUnitario): float
    {
        return round((float) $cantidad * (float) $precioUnitario, 4);
    }

    /**
     * @param  list<array<string, mixed>>  $componentes
     */
    public function sumarImportes(array $componentes): float
    {
        $total = 0.0;
        foreach ($componentes as $row) {
            $importe = $row['importe'] ?? null;
            if ($importe === null) {
                $importe = $this->calcularImporte(
                    $row['cantidad'] ?? 0,
                    $row['precio_unitario'] ?? 0
                );
            }
            $total += (float) $importe;
        }

        return round($total, 4);
    }

    /**
     * Normaliza renglones de entrada (catálogo o línea PPTO) y calcula importes.
     * Si trae catalogo_concepto_id / catalogo_concepto_componente_id, aplica snapshot.
     *
     * @param  list<array<string, mixed>>  $componentes
     * @return list<array<string, mixed>>
     */
    public function normalizarComponentes(
        array $componentes,
        int $proveedorId,
        ?int $padreCatalogoId = null,
        int $profundidad = 0
    ): array {
        if ($profundidad > 12) {
            throw new InvalidArgumentException(
                'La matriz anidada supera el máximo de niveles permitido.'
            );
        }

        $normalizados = [];

        foreach (array_values($componentes) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $anidadosRaw = $row['componentes_anidados'] ?? null;
            $tieneAnidados = is_array($anidadosRaw) && count($anidadosRaw) > 0;
            $anidados = $tieneAnidados
                ? $this->normalizarComponentes($anidadosRaw, $proveedorId, null, $profundidad + 1)
                : [];

            $refId = $row['catalogo_concepto_componente_id']
                ?? $row['catalogo_concepto_id']
                ?? null;
            $refId = $refId !== null && $refId !== '' ? (int) $refId : null;

            // Matriz anidada editada: el PU es el costo directo del hijo; no re-snapshotear catálogo.
            if ($refId !== null && $refId > 0 && ! $tieneAnidados) {
                if ($padreCatalogoId !== null) {
                    $this->assertSinCiclo($padreCatalogoId, $refId);
                }
                $snapshot = $this->snapshotDesdeCatalogo($refId, $proveedorId);
                $cantidad = (float) ($row['cantidad'] ?? 1);
                $precio = (float) $snapshot['precio_unitario'];
                $normalizados[] = [
                    'orden' => (int) ($row['orden'] ?? $index),
                    'categoria' => $snapshot['categoria'],
                    'catalogo_concepto_componente_id' => $refId,
                    'catalogo_concepto_id' => $refId,
                    'clave_snapshot' => $snapshot['clave'],
                    'descripcion' => $snapshot['descripcion'],
                    'unidad' => $snapshot['unidad'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'importe' => $this->calcularImporte($cantidad, $precio),
                    'es_compuesto_ref' => false,
                    'componentes_anidados' => [],
                ];

                continue;
            }

            $categoria = PresupuestoCatalogoConcepto::coerceCategoriaInsumo(
                (string) ($row['categoria'] ?? '')
            );

            $descripcion = trim((string) ($row['descripcion'] ?? ''));
            $unidad = trim((string) ($row['unidad'] ?? ''));
            if ($descripcion === '' || $unidad === '') {
                throw new InvalidArgumentException(
                    'Cada componente manual requiere descripción y unidad.'
                );
            }

            $cantidad = (float) ($row['cantidad'] ?? 1);
            $precio = $tieneAnidados
                ? $this->sumarImportes($anidados)
                : (float) ($row['precio_unitario'] ?? 0);
            if ($cantidad < 0 || $precio < 0) {
                throw new InvalidArgumentException(
                    'Cantidad y precio unitario del componente no pueden ser negativos.'
                );
            }

            $clave = $this->normalizarClave($row['clave_snapshot'] ?? $row['clave'] ?? null);
            $esCompuesto = $tieneAnidados
                || filter_var($row['es_compuesto_ref'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $normalizados[] = [
                'orden' => (int) ($row['orden'] ?? $index),
                'categoria' => $categoria,
                'catalogo_concepto_componente_id' => null,
                'catalogo_concepto_id' => null,
                'clave_snapshot' => $clave,
                'descripcion' => mb_substr($descripcion, 0, 500),
                'unidad' => mb_substr($unidad, 0, 50),
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'importe' => $this->calcularImporte($cantidad, $precio),
                'es_compuesto_ref' => $esCompuesto,
                'componentes_anidados' => $anidados,
            ];
        }

        return $normalizados;
    }

    /**
     * Persiste componentes del catálogo compuesto y recalcula precio_unitario del padre.
     *
     * @param  list<array<string, mixed>>  $componentes
     */
    public function sincronizarComponentesCatalogo(
        PresupuestoCatalogoConcepto $concepto,
        array $componentes
    ): PresupuestoCatalogoConcepto {
        if (! $concepto->es_compuesto) {
            $concepto->componentes()->delete();

            return $concepto->fresh(['componentes']) ?? $concepto;
        }

        $normalizados = $this->normalizarComponentes(
            $componentes,
            (int) $concepto->proveedor_id,
            (int) $concepto->id
        );

        if ($normalizados === []) {
            throw new InvalidArgumentException(
                'Un concepto compuesto requiere al menos un componente.'
            );
        }

        $concepto->componentes()->delete();

        foreach ($normalizados as $row) {
            $concepto->componentes()->create([
                'orden' => $row['orden'],
                'categoria' => $row['categoria'],
                'catalogo_concepto_componente_id' => $row['catalogo_concepto_componente_id'],
                'clave_snapshot' => $row['clave_snapshot'],
                'descripcion' => $row['descripcion'],
                'unidad' => $row['unidad'],
                'cantidad' => $row['cantidad'],
                'precio_unitario' => $row['precio_unitario'],
                'importe' => $row['importe'],
            ]);
        }

        $concepto->precio_unitario = $this->sumarImportes($normalizados);
        $concepto->save();

        return $concepto->fresh(['componentes']) ?? $concepto;
    }

    /**
     * Recalcula P.U. de un compuesto ya persistido (relee componentes).
     */
    public function recalcularCompuestoCatalogo(PresupuestoCatalogoConcepto $concepto): PresupuestoCatalogoConcepto
    {
        if (! $concepto->es_compuesto) {
            return $concepto;
        }

        $concepto->loadMissing('componentes');
        $rows = $concepto->componentes->map(function (PresupuestoCatalogoConceptoComponente $c) {
            $importe = $this->calcularImporte((float) $c->cantidad, (float) $c->precio_unitario);
            if ((float) $c->importe !== $importe) {
                $c->importe = $importe;
                $c->save();
            }

            return ['importe' => $importe];
        })->all();

        $concepto->precio_unitario = $this->sumarImportes($rows);
        $concepto->save();

        return $concepto->fresh(['componentes']) ?? $concepto;
    }

    /**
     * Persiste matriz de una línea de presupuesto y asigna precio_unitario calculado.
     *
     * @param  list<array<string, mixed>>  $componentes
     */
    /**
     * @param  array{oficina: float, campo: float, utilidad: float, cargos: float}|null  $indirectos
     */
    public function sincronizarComponentesLinea(
        PresupuestoConcepto $concepto,
        array $componentes,
        int $proveedorId,
        ?array $indirectos = null
    ): PresupuestoConcepto {
        $normalizados = $this->normalizarComponentes($componentes, $proveedorId, null);

        if ($normalizados === []) {
            throw new InvalidArgumentException(
                'Una línea con matriz requiere al menos un componente.'
            );
        }

        $concepto->componentes()->delete();

        foreach ($normalizados as $row) {
            $concepto->componentes()->create($this->atributosComponentePersistible($row));
        }

        $costoDirecto = $this->sumarImportes($normalizados);
        $concepto->precio_unitario = $indirectos === null
            ? round($costoDirecto, 2)
            : $this->precioConIndirectos($costoDirecto, $indirectos);
        $concepto->tiene_matriz = true;
        $concepto->calcularImporte();
        $concepto->save();

        return $concepto->fresh(['componentes']) ?? $concepto;
    }

    /**
     * Persiste matriz de una línea de plantilla y asigna precio_unitario calculado.
     *
     * @param  list<array<string, mixed>>  $componentes
     */
    public function sincronizarComponentesPlantillaLinea(
        PresupuestoPlantillaConcepto $concepto,
        array $componentes,
        int $proveedorId
    ): PresupuestoPlantillaConcepto {
        $normalizados = $this->normalizarComponentes($componentes, $proveedorId, null);

        if ($normalizados === []) {
            throw new InvalidArgumentException(
                'Una línea con matriz requiere al menos un componente.'
            );
        }

        $concepto->componentes()->delete();

        foreach ($normalizados as $row) {
            $concepto->componentes()->create($this->atributosComponentePersistible($row));
        }

        $concepto->precio_unitario = round($this->sumarImportes($normalizados), 2);
        $concepto->tiene_matriz = true;
        $concepto->save();

        return $concepto->fresh(['componentes']) ?? $concepto;
    }

    /**
     * @return array{categoria: string, clave: ?string, descripcion: string, unidad: string, precio_unitario: float}
     */
    public function snapshotDesdeCatalogo(int $catalogoConceptoId, int $proveedorId): array
    {
        $item = PresupuestoCatalogoConcepto::query()
            ->where('id', $catalogoConceptoId)
            ->where('proveedor_id', $proveedorId)
            ->first();

        if (! $item) {
            throw new InvalidArgumentException(
                'El componente de catálogo no existe o no pertenece al proveedor.'
            );
        }

        return [
            'categoria' => PresupuestoCatalogoConcepto::coerceCategoriaInsumo((string) $item->categoria),
            'clave' => $this->normalizarClave($item->clave),
            'descripcion' => (string) $item->descripcion,
            'unidad' => (string) $item->unidad,
            'precio_unitario' => (float) $item->precio_unitario,
        ];
    }

    public function assertSinCiclo(int $padreId, int $componenteCatalogoId): void
    {
        if ($padreId === $componenteCatalogoId) {
            throw new InvalidArgumentException(
                'Un compuesto no puede incluirse a sí mismo como componente.'
            );
        }

        $visitados = [];
        $cola = [$componenteCatalogoId];

        while ($cola !== []) {
            $actual = array_shift($cola);
            if ($actual === null || isset($visitados[$actual])) {
                continue;
            }
            $visitados[$actual] = true;

            if ($actual === $padreId) {
                throw new InvalidArgumentException(
                    'La matriz generaría una referencia circular entre compuestos.'
                );
            }

            $hijos = PresupuestoCatalogoConceptoComponente::query()
                ->where('catalogo_concepto_id', $actual)
                ->whereNotNull('catalogo_concepto_componente_id')
                ->pluck('catalogo_concepto_componente_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($hijos as $hijoId) {
                if ($hijoId > 0) {
                    $cola[] = $hijoId;
                }
            }
        }
    }

    public function normalizarClave(mixed $clave): ?string
    {
        if ($clave === null) {
            return null;
        }
        $clave = trim((string) $clave);

        return $clave === '' ? null : mb_substr($clave, 0, 40);
    }

    /**
     * Pie de precio. Cada importe se redondea a 2 decimales (hoja P1.6).
     *
     * @param  array{oficina?: float|int|string, campo?: float|int|string, utilidad?: float|int|string, cargos?: float|int|string}  $porcentajes
     */
    public function precioConIndirectos(float|string $costoDirecto, array $porcentajes): float
    {
        $pct = $this->normalizarPorcentajesIndirectos($porcentajes);
        $cd = round((float) $costoDirecto, 2);
        $oficina = round($cd * ($pct['oficina'] / 100), 2);
        $campo = round($cd * ($pct['campo'] / 100), 2);
        $base = round($cd + $oficina + $campo, 2);
        $utilidad = round($base * ($pct['utilidad'] / 100), 2);
        $base2 = round($base + $utilidad, 2);
        $cargos = round($base2 * ($pct['cargos'] / 100), 2);

        return round($base2 + $cargos, 2);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{oficina: float, campo: float, utilidad: float, cargos: float}
     */
    public function normalizarPorcentajesIndirectos(array $data): array
    {
        return [
            'oficina' => $this->acotarPorcentaje($data['oficina'] ?? $data['indirecto_oficina_porcentaje'] ?? 0),
            'campo' => $this->acotarPorcentaje($data['campo'] ?? $data['indirecto_campo_porcentaje'] ?? 0),
            'utilidad' => $this->acotarPorcentaje($data['utilidad'] ?? $data['indirecto_utilidad_porcentaje'] ?? 0),
            'cargos' => $this->acotarPorcentaje($data['cargos'] ?? $data['indirecto_cargos_porcentaje'] ?? 0),
        ];
    }

    /**
     * Columnas de override. Modo general deja los porcentajes en null.
     *
     * @param  array<string, mixed>  $data
     * @return array{indirectos_modo: string, indirecto_oficina_porcentaje: ?float, indirecto_campo_porcentaje: ?float, indirecto_utilidad_porcentaje: ?float, indirecto_cargos_porcentaje: ?float}
     */
    public function columnasIndirectosPropios(array $data): array
    {
        $modo = ($data['indirectos_modo'] ?? 'general') === 'propio' ? 'propio' : 'general';
        if ($modo !== 'propio') {
            return [
                'indirectos_modo' => 'general',
                'indirecto_oficina_porcentaje' => null,
                'indirecto_campo_porcentaje' => null,
                'indirecto_utilidad_porcentaje' => null,
                'indirecto_cargos_porcentaje' => null,
            ];
        }

        $pct = $this->normalizarPorcentajesIndirectos($data);

        return [
            'indirectos_modo' => 'propio',
            'indirecto_oficina_porcentaje' => $pct['oficina'],
            'indirecto_campo_porcentaje' => $pct['campo'],
            'indirecto_utilidad_porcentaje' => $pct['utilidad'],
            'indirecto_cargos_porcentaje' => $pct['cargos'],
        ];
    }

    /**
     * Porcentajes que aplican a una línea: los propios, o los generales del presupuesto.
     *
     * @param  array<string, mixed>  $data
     * @return array{oficina: float, campo: float, utilidad: float, cargos: float}
     */
    public function resolverPorcentajesLinea(array $data, Presupuesto $presupuesto): array
    {
        $modo = ($data['indirectos_modo'] ?? 'general') === 'propio' ? 'propio' : 'general';
        if ($modo === 'propio') {
            return $this->normalizarPorcentajesIndirectos($data);
        }

        return $this->normalizarPorcentajesIndirectos([
            'indirecto_oficina_porcentaje' => $presupuesto->indirecto_oficina_porcentaje,
            'indirecto_campo_porcentaje' => $presupuesto->indirecto_campo_porcentaje,
            'indirecto_utilidad_porcentaje' => $presupuesto->indirecto_utilidad_porcentaje,
            'indirecto_cargos_porcentaje' => $presupuesto->indirecto_cargos_porcentaje,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function atributosIndirectosRecurso(object $model): array
    {
        $modo = ($model->indirectos_modo ?? 'general') === 'propio' ? 'propio' : 'general';

        return [
            'indirectos_modo' => $modo,
            'indirecto_oficina_porcentaje' => $model->indirecto_oficina_porcentaje !== null
                ? (float) $model->indirecto_oficina_porcentaje
                : null,
            'indirecto_campo_porcentaje' => $model->indirecto_campo_porcentaje !== null
                ? (float) $model->indirecto_campo_porcentaje
                : null,
            'indirecto_utilidad_porcentaje' => $model->indirecto_utilidad_porcentaje !== null
                ? (float) $model->indirecto_utilidad_porcentaje
                : null,
            'indirecto_cargos_porcentaje' => $model->indirecto_cargos_porcentaje !== null
                ? (float) $model->indirecto_cargos_porcentaje
                : null,
        ];
    }

    /**
     * Si cambia un compuesto, las matrices del catálogo que lo usan vuelven a sumar su costo directo.
     */
    public function propagarCostoEnCatalogo(int $conceptoId, int $proveedorId): void
    {
        $visitados = [];
        $cola = [$conceptoId];

        while ($cola !== []) {
            $id = array_shift($cola);
            if ($id === null || isset($visitados[$id])) {
                continue;
            }
            $visitados[$id] = true;

            $padreIds = PresupuestoCatalogoConceptoComponente::query()
                ->where('catalogo_concepto_componente_id', $id)
                ->pluck('catalogo_concepto_id')
                ->map(fn ($padreId) => (int) $padreId)
                ->unique()
                ->all();

            foreach ($padreIds as $padreId) {
                $padre = PresupuestoCatalogoConcepto::query()
                    ->where('id', $padreId)
                    ->where('proveedor_id', $proveedorId)
                    ->first();
                if (! $padre || ! $padre->es_compuesto) {
                    continue;
                }

                $padre->load('componentes');
                foreach ($padre->componentes as $componente) {
                    $ref = (int) ($componente->catalogo_concepto_componente_id ?? 0);
                    if ($ref !== $id) {
                        continue;
                    }
                    $snapshot = $this->snapshotDesdeCatalogo($ref, $proveedorId);
                    $componente->categoria = $snapshot['categoria'];
                    $componente->clave_snapshot = $snapshot['clave'];
                    $componente->descripcion = $snapshot['descripcion'];
                    $componente->unidad = $snapshot['unidad'];
                    $componente->precio_unitario = $snapshot['precio_unitario'];
                    $componente->importe = $this->calcularImporte(
                        (float) $componente->cantidad,
                        (float) $componente->precio_unitario
                    );
                    $componente->save();
                }

                $padre->precio_unitario = $this->sumarImportes(
                    $padre->componentes->map(fn ($c) => ['importe' => $c->importe])->all()
                );
                $padre->save();
                $cola[] = (int) $padre->id;
            }
        }
    }

    /**
     * Columnas a persistir en presupuesto_concepto_componentes / plantilla.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function atributosComponentePersistible(array $row): array
    {
        $anidados = is_array($row['componentes_anidados'] ?? null)
            ? array_values($row['componentes_anidados'])
            : [];
        $esCompuesto = ! empty($anidados)
            || filter_var($row['es_compuesto_ref'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'orden' => $row['orden'],
            'categoria' => $row['categoria'],
            'catalogo_concepto_id' => $row['catalogo_concepto_id'],
            'clave_snapshot' => $row['clave_snapshot'],
            'descripcion' => $row['descripcion'],
            'unidad' => $row['unidad'],
            'cantidad' => $row['cantidad'],
            'precio_unitario' => $row['precio_unitario'],
            'importe' => $row['importe'],
            'es_compuesto_ref' => $esCompuesto,
            'componentes_anidados' => $anidados === [] ? null : $anidados,
        ];
    }

    private function acotarPorcentaje(mixed $value): float
    {
        $n = round((float) $value, 4);
        if ($n < 0) {
            return 0.0;
        }
        if ($n > 100) {
            return 100.0;
        }

        return $n;
    }
}
