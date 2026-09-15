<?php

namespace App\Services\Presupuesto;

use App\Models\PresupuestoCatalogoConcepto;
use App\Models\PresupuestoCatalogoConceptoComponente;
use App\Models\PresupuestoConcepto;
use App\Models\PresupuestoConceptoComponente;
use InvalidArgumentException;

/**
 * Motor único de matriz de P.U.: importe = cantidad × precio_unitario; P.U. padre = Σ importes.
 * Sin strategies por tipo en v1 (producto|servicio solo clasifican).
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
    public function normalizarComponentes(array $componentes, int $proveedorId, ?int $padreCatalogoId = null): array
    {
        $normalizados = [];

        foreach (array_values($componentes) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $refId = $row['catalogo_concepto_componente_id']
                ?? $row['catalogo_concepto_id']
                ?? null;
            $refId = $refId !== null && $refId !== '' ? (int) $refId : null;

            if ($refId !== null && $refId > 0) {
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
                ];

                continue;
            }

            $categoria = (string) ($row['categoria'] ?? '');
            if (! in_array($categoria, PresupuestoCatalogoConcepto::categoriasValidas(), true)) {
                throw new InvalidArgumentException(
                    'Cada componente debe ser producto o servicio.'
                );
            }

            $descripcion = trim((string) ($row['descripcion'] ?? ''));
            $unidad = trim((string) ($row['unidad'] ?? ''));
            if ($descripcion === '' || $unidad === '') {
                throw new InvalidArgumentException(
                    'Cada componente manual requiere descripción y unidad.'
                );
            }

            $cantidad = (float) ($row['cantidad'] ?? 1);
            $precio = (float) ($row['precio_unitario'] ?? 0);
            if ($cantidad < 0 || $precio < 0) {
                throw new InvalidArgumentException(
                    'Cantidad y precio unitario del componente no pueden ser negativos.'
                );
            }

            $clave = $this->normalizarClave($row['clave_snapshot'] ?? $row['clave'] ?? null);

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
    public function sincronizarComponentesLinea(
        PresupuestoConcepto $concepto,
        array $componentes,
        int $proveedorId
    ): PresupuestoConcepto {
        $normalizados = $this->normalizarComponentes($componentes, $proveedorId, null);

        if ($normalizados === []) {
            throw new InvalidArgumentException(
                'Una línea con matriz requiere al menos un componente.'
            );
        }

        $concepto->componentes()->delete();

        foreach ($normalizados as $row) {
            $concepto->componentes()->create([
                'orden' => $row['orden'],
                'categoria' => $row['categoria'],
                'catalogo_concepto_id' => $row['catalogo_concepto_id'],
                'clave_snapshot' => $row['clave_snapshot'],
                'descripcion' => $row['descripcion'],
                'unidad' => $row['unidad'],
                'cantidad' => $row['cantidad'],
                'precio_unitario' => $row['precio_unitario'],
                'importe' => $row['importe'],
            ]);
        }

        // Columna de línea sigue en 2 decimales de negocio del documento.
        $concepto->precio_unitario = round($this->sumarImportes($normalizados), 2);
        $concepto->tiene_matriz = true;
        $concepto->calcularImporte();
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
            'categoria' => (string) $item->categoria,
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
}
