<?php

namespace App\Services\Presupuesto;

use App\Models\Presupuesto;
use App\Models\PresupuestoCatalogoConcepto;
use App\Models\PresupuestoCatalogoConceptoComponente;
use App\Models\PresupuestoConcepto;
use App\Models\PresupuestoConceptoComponente;
use App\Models\PresupuestoCuadrilla;
use App\Models\PresupuestoPlantillaConcepto;
use InvalidArgumentException;

/**
 * Motor único de matriz de P.U.: importe = cantidad × precio_unitario, o porcentaje de la mano de obra del mismo nivel. P.U. padre = Σ importes.
 * Tipos de insumo: material|mano_obra|herramienta|equipo|auxiliar|producto|servicio.
 */
class PresupuestoMatrizCalculoService
{
    public const CALCULO_CANTIDAD = 'cantidad';

    public const CALCULO_PORCENTAJE_MO = 'porcentaje_mo';

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
        int $profundidad = 0,
        ?array $indirectosGenerales = null,
        ?int $presupuestoId = null,
        ?array $cuadrillasPorId = null
    ): array {
        if ($profundidad > 12) {
            throw new InvalidArgumentException(
                'La matriz anidada supera el máximo de niveles permitido.'
            );
        }

        if ($cuadrillasPorId === null && $presupuestoId !== null && $presupuestoId > 0) {
            $cuadrillasPorId = $this->mapaCuadrillasPresupuesto($presupuestoId);
        }

        $normalizados = [];
        $numeroClave = 0;

        foreach (array_values($componentes) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $anidadosRaw = $row['componentes_anidados'] ?? null;
            $tieneAnidados = is_array($anidadosRaw) && count($anidadosRaw) > 0;
            $anidados = $tieneAnidados
                ? $this->normalizarComponentes(
                    $anidadosRaw,
                    $proveedorId,
                    null,
                    $profundidad + 1,
                    $indirectosGenerales,
                    $presupuestoId,
                    $cuadrillasPorId
                )
                : [];

            $cuadrillaId = isset($row['presupuesto_cuadrilla_id']) && $row['presupuesto_cuadrilla_id'] !== ''
                ? (int) $row['presupuesto_cuadrilla_id']
                : 0;
            if ($cuadrillaId > 0 && ! $tieneAnidados && is_array($cuadrillasPorId)) {
                $cuadrilla = $cuadrillasPorId[$cuadrillaId] ?? null;
                if (! $cuadrilla instanceof PresupuestoCuadrilla) {
                    throw new InvalidArgumentException(
                        'La cuadrilla indicada no pertenece a este presupuesto.'
                    );
                }
                $cantidad = (float) ($row['cantidad'] ?? 1);
                if ($cantidad < 0) {
                    throw new InvalidArgumentException(
                        'La cantidad del componente no puede ser negativa.'
                    );
                }
                $precio = (float) $cuadrilla->costo_unitario;
                $numeroClave++;
                $claveCapturada = $this->normalizarClave($row['clave_snapshot'] ?? $row['clave'] ?? null);
                $normalizados[] = [
                    'orden' => (int) ($row['orden'] ?? $index),
                    'categoria' => PresupuestoCatalogoConcepto::INSUMO_MANO_OBRA,
                    'catalogo_concepto_componente_id' => null,
                    'catalogo_concepto_id' => null,
                    'presupuesto_cuadrilla_id' => (int) $cuadrilla->id,
                    'clave_snapshot' => $claveCapturada
                        ?? $this->normalizarClave($cuadrilla->clave)
                        ?? (string) $numeroClave,
                    'descripcion' => $cuadrilla->descripcionParaMatriz(),
                    'unidad' => mb_substr(trim((string) $cuadrilla->unidad) ?: 'jor', 0, 50),
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'importe' => $this->calcularImporte($cantidad, $precio),
                    'calculo' => self::CALCULO_CANTIDAD,
                    'porcentaje' => null,
                    'es_compuesto_ref' => false,
                    'componentes_anidados' => [],
                ];

                continue;
            }

            $refId = $row['catalogo_concepto_componente_id']
                ?? $row['catalogo_concepto_id']
                ?? null;
            $refId = $refId !== null && $refId !== '' ? (int) $refId : null;

            // Matriz anidada editada: el PU incluye los indirectos del subcompuesto.
            if ($refId !== null && $refId > 0 && ! $tieneAnidados) {
                if ($padreCatalogoId !== null) {
                    $this->assertSinCiclo($padreCatalogoId, $refId);
                }
                $snapshot = $this->snapshotDesdeCatalogo($refId, $proveedorId);
                $cantidad = (float) ($row['cantidad'] ?? 1);
                $precio = (float) $snapshot['precio_unitario'];
                $numeroClave++;
                $claveCapturada = $this->normalizarClave($row['clave_snapshot'] ?? null);
                $normalizados[] = [
                    'orden' => (int) ($row['orden'] ?? $index),
                    'categoria' => $snapshot['categoria'],
                    'catalogo_concepto_componente_id' => $refId,
                    'catalogo_concepto_id' => $refId,
                    'presupuesto_cuadrilla_id' => null,
                    'clave_snapshot' => $claveCapturada
                        ?? $this->normalizarClave($snapshot['clave'])
                        ?? (string) $numeroClave,
                    'descripcion' => $snapshot['descripcion'],
                    'unidad' => $snapshot['unidad'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'importe' => $this->calcularImporte($cantidad, $precio),
                    'calculo' => $row['calculo'] ?? self::CALCULO_CANTIDAD,
                    'porcentaje' => $row['porcentaje'] ?? null,
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
            $costoDirecto = $tieneAnidados
                ? $this->sumarImportes($anidados)
                : (float) ($row['precio_unitario'] ?? 0);
            $precio = $tieneAnidados
                ? $this->precioDeCompuestoAnidado($costoDirecto, $row, $indirectosGenerales)
                : $costoDirecto;
            if ($cantidad < 0 || $precio < 0) {
                throw new InvalidArgumentException(
                    'Cantidad y precio unitario del componente no pueden ser negativos.'
                );
            }

            $esParrafo = in_array(mb_strtolower($unidad), ['párrafo', 'parrafo'], true);
            if ($esParrafo) {
                $clave = null;
            } else {
                $numeroClave++;
                $clave = $this->normalizarClave($row['clave_snapshot'] ?? $row['clave'] ?? null)
                    ?? (string) $numeroClave;
            }
            $esCompuesto = $tieneAnidados
                || filter_var($row['es_compuesto_ref'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $normalizados[] = array_merge([
                'orden' => (int) ($row['orden'] ?? $index),
                'categoria' => $categoria,
                'catalogo_concepto_componente_id' => null,
                'catalogo_concepto_id' => null,
                'presupuesto_cuadrilla_id' => null,
                'clave_snapshot' => $clave,
                'descripcion' => mb_substr($descripcion, 0, 500),
                'unidad' => mb_substr($unidad, 0, 50),
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'importe' => $this->calcularImporte($cantidad, $precio),
                'calculo' => $row['calculo'] ?? self::CALCULO_CANTIDAD,
                'porcentaje' => $row['porcentaje'] ?? null,
                'es_compuesto_ref' => $esCompuesto,
                'componentes_anidados' => $anidados,
            ], $this->indirectosPersistibles($row) ?? []);
        }

        return $this->aplicarPorcentajesManoObra($normalizados);
    }

    /**
     * @return array<int, PresupuestoCuadrilla>
     */
    public function mapaCuadrillasPresupuesto(int $presupuestoId): array
    {
        if ($presupuestoId <= 0) {
            return [];
        }

        $mapa = [];
        foreach (
            PresupuestoCuadrilla::query()
                ->where('presupuesto_id', $presupuestoId)
                ->get() as $cuadrilla
        ) {
            $mapa[(int) $cuadrilla->id] = $cuadrilla;
        }

        return $mapa;
    }

    /**
     * Si cambia el costo de una cuadrilla, las líneas de matriz que la usan vuelven a calcular el P.U.
     */
    public function propagarCostoCuadrillaEnPresupuesto(Presupuesto $presupuesto, int $cuadrillaId): void
    {
        $presupuesto->loadMissing(['conceptos.componentes']);
        $generales = $this->normalizarPorcentajesIndirectos([
            'indirecto_oficina_porcentaje' => $presupuesto->indirecto_oficina_porcentaje,
            'indirecto_campo_porcentaje' => $presupuesto->indirecto_campo_porcentaje,
            'indirecto_financiamiento_porcentaje' => $presupuesto->indirecto_financiamiento_porcentaje,
            'indirecto_utilidad_porcentaje' => $presupuesto->indirecto_utilidad_porcentaje,
            'indirecto_cargos_porcentaje' => $presupuesto->indirecto_cargos_porcentaje,
            'indirecto_otros_porcentaje' => $presupuesto->indirecto_otros_porcentaje,
        ]);

        foreach ($presupuesto->conceptos as $concepto) {
            if (! $concepto->tiene_matriz || $concepto->componentes->isEmpty()) {
                continue;
            }
            $usaCuadrilla = $concepto->componentes->contains(
                fn ($comp) => (int) ($comp->presupuesto_cuadrilla_id ?? 0) === $cuadrillaId
                    || $this->anidadosUsanCuadrilla($comp->componentes_anidados, $cuadrillaId)
            );
            if (! $usaCuadrilla) {
                continue;
            }

            $filas = $concepto->componentes->map(function ($comp) {
                return array_merge([
                    'orden' => (int) $comp->orden,
                    'categoria' => $comp->categoria,
                    'catalogo_concepto_id' => $comp->catalogo_concepto_id,
                    'presupuesto_cuadrilla_id' => $comp->presupuesto_cuadrilla_id,
                    'clave_snapshot' => $comp->clave_snapshot,
                    'descripcion' => $comp->descripcion,
                    'unidad' => $comp->unidad,
                    'cantidad' => (float) $comp->cantidad,
                    'precio_unitario' => (float) $comp->precio_unitario,
                    'calculo' => $comp->calculo ?? self::CALCULO_CANTIDAD,
                    'porcentaje' => $comp->porcentaje !== null ? (float) $comp->porcentaje : null,
                    'es_compuesto_ref' => (bool) ($comp->es_compuesto_ref ?? false),
                    'componentes_anidados' => is_array($comp->componentes_anidados) ? $comp->componentes_anidados : [],
                ], is_array($comp->indirectos) ? $comp->indirectos : []);
            })->values()->all();

            $this->sincronizarComponentesLinea(
                $concepto,
                $filas,
                (int) $presupuesto->proveedor_id,
                $this->resolverPorcentajesLinea([
                    'indirectos_modo' => $concepto->indirectos_modo,
                    'indirecto_oficina_porcentaje' => $concepto->indirecto_oficina_porcentaje,
                    'indirecto_campo_porcentaje' => $concepto->indirecto_campo_porcentaje,
                    'indirecto_financiamiento_porcentaje' => $concepto->indirecto_financiamiento_porcentaje,
                    'indirecto_utilidad_porcentaje' => $concepto->indirecto_utilidad_porcentaje,
                    'indirecto_cargos_porcentaje' => $concepto->indirecto_cargos_porcentaje,
                    'indirecto_otros_porcentaje' => $concepto->indirecto_otros_porcentaje,
                ], $presupuesto),
                $generales
            );
        }

        $presupuesto->recalcularDesdeConceptos();
        $presupuesto->save();
    }

    private function anidadosUsanCuadrilla(mixed $anidados, int $cuadrillaId): bool
    {
        if (! is_array($anidados) || $anidados === []) {
            return false;
        }
        foreach ($anidados as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['presupuesto_cuadrilla_id'] ?? 0) === $cuadrillaId) {
                return true;
            }
            if ($this->anidadosUsanCuadrilla($row['componentes_anidados'] ?? null, $cuadrillaId)) {
                return true;
            }
        }

        return false;
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
                'calculo' => $row['calculo'] ?? self::CALCULO_CANTIDAD,
                'porcentaje' => $row['porcentaje'] ?? null,
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
        $filas = [];
        foreach ($concepto->componentes as $c) {
            $cantidad = (float) $c->cantidad;
            $precio = (float) $c->precio_unitario;
            $filas[] = [
                'categoria' => (string) $c->categoria,
                'unidad' => (string) $c->unidad,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'importe' => $this->calcularImporte($cantidad, $precio),
                'calculo' => $c->calculo ?? self::CALCULO_CANTIDAD,
                'porcentaje' => $c->porcentaje,
                'es_compuesto_ref' => false,
                'componentes_anidados' => [],
            ];
        }
        $filas = $this->aplicarPorcentajesManoObra($filas);
        foreach ($concepto->componentes->values() as $i => $c) {
            $fila = $filas[$i];
            $c->calculo = $fila['calculo'];
            $c->porcentaje = $fila['porcentaje'];
            $c->cantidad = $fila['cantidad'];
            $c->precio_unitario = $fila['precio_unitario'];
            $c->importe = $fila['importe'];
            $c->save();
        }

        $concepto->precio_unitario = $this->sumarImportes($filas);
        $concepto->save();

        return $concepto->fresh(['componentes']) ?? $concepto;
    }

    /**
     * Persiste matriz de una línea de presupuesto y asigna precio_unitario calculado.
     *
     * @param  list<array<string, mixed>>  $componentes
     */
    /**
     * @param  array{oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float}|null  $indirectos
     */
    public function sincronizarComponentesLinea(
        PresupuestoConcepto $concepto,
        array $componentes,
        int $proveedorId,
        ?array $indirectos = null,
        ?array $indirectosGenerales = null
    ): PresupuestoConcepto {
        $normalizados = $this->normalizarComponentes(
            $componentes,
            $proveedorId,
            null,
            0,
            $indirectosGenerales,
            (int) $concepto->presupuesto_id
        );

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
     * Concepto y compuesto siempre llevan clave de partida.
     * Si el usuario no escribió una, se usa el número de renglón.
     * El párrafo no lleva clave.
     */
    public function clavePartida(mixed $clave, int $numero, bool $esParrafo): ?string
    {
        if ($esParrafo) {
            return null;
        }

        return $this->normalizarClave($clave) ?? (string) max(1, $numero);
    }

    /**
     * Pie de precio. Cada importe se redondea a 2 decimales (hoja P1.6).
     *
     * @param  array{oficina?: float|int|string, campo?: float|int|string, financiamiento?: float|int|string, utilidad?: float|int|string, cargos?: float|int|string, otros?: float|int|string}  $porcentajes
     */
    public function precioConIndirectos(float|string $costoDirecto, array $porcentajes): float
    {
        return $this->desgloseIndirectos($costoDirecto, $porcentajes)['precio_venta'];
    }

    /**
     * Cadena Opus de una base (costo directo ya extendido por la cantidad de la línea).
     * Oficina y campo salen del costo directo. Financiamiento, de esa suma.
     * Utilidad, de la suma ya con financiamiento. Cargos, del precio con utilidad.
     * Otros, del precio ya con cargos.
     *
     * @param  array{oficina?: float|int|string, campo?: float|int|string, financiamiento?: float|int|string, utilidad?: float|int|string, cargos?: float|int|string, otros?: float|int|string}  $porcentajes
     * @return array{costo_directo: float, oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float, precio_venta: float, pct: array{oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float}}
     */
    public function desgloseIndirectos(float|string $costoDirecto, array $porcentajes): array
    {
        $pct = $this->normalizarPorcentajesIndirectos($porcentajes);
        $cd = round((float) $costoDirecto, 2);
        $oficina = round($cd * ($pct['oficina'] / 100), 2);
        $campo = round($cd * ($pct['campo'] / 100), 2);
        $baseIndirectos = round($cd + $oficina + $campo, 2);
        $financiamiento = round($baseIndirectos * ($pct['financiamiento'] / 100), 2);
        $baseUtilidad = round($baseIndirectos + $financiamiento, 2);
        $utilidad = round($baseUtilidad * ($pct['utilidad'] / 100), 2);
        $baseCargos = round($baseUtilidad + $utilidad, 2);
        $cargos = round($baseCargos * ($pct['cargos'] / 100), 2);
        $baseOtros = round($baseCargos + $cargos, 2);
        $otros = round($baseOtros * ($pct['otros'] / 100), 2);

        return [
            'costo_directo' => $cd,
            'oficina' => $oficina,
            'campo' => $campo,
            'financiamiento' => $financiamiento,
            'utilidad' => $utilidad,
            'cargos' => $cargos,
            'otros' => $otros,
            'precio_venta' => round($baseOtros + $otros, 2),
            'pct' => $pct,
        ];
    }

    /**
     * Hojas de análisis opt-in (una por compuesto con flag), para anexar al PDF.
     *
     * @param  array<string, mixed>  $presupuesto
     * @return list<array<string, mixed>>
     */
    public function hojasAnalisisPresupuesto(array $presupuesto): array
    {
        $hojas = [];
        foreach (array_values($presupuesto['conceptos'] ?? []) as $index => $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $hoja = $this->armarHojaAnalisisLinea($linea, $presupuesto, $index + 1);
            if ($hoja !== null) {
                $hojas[] = $hoja;
            }
        }

        return $hojas;
    }

    /**
     * Desglose tipo Opus/Charay de un compuesto: grupos, CD, sobrecostos, P.U., IVA y total.
     *
     * @param  array<string, mixed>  $linea
     * @param  array<string, mixed>  $presupuesto
     * @return array<string, mixed>|null
     */
    public function armarHojaAnalisisLinea(array $linea, array $presupuesto, int $numeroFallback = 1): ?array
    {
        if (! filter_var($linea['mostrar_hoja_analisis'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }
        if (\App\Support\PresupuestoParrafoPdf::esLineaParrafo($linea)) {
            return null;
        }
        $componentes = is_array($linea['componentes'] ?? null) ? $linea['componentes'] : [];
        $tieneMatriz = filter_var($linea['tiene_matriz'] ?? false, FILTER_VALIDATE_BOOLEAN) && $componentes !== [];
        if (! $tieneMatriz) {
            return null;
        }

        $generales = $this->normalizarPorcentajesIndirectos([
            'indirecto_oficina_porcentaje' => $presupuesto['indirecto_oficina_porcentaje'] ?? 0,
            'indirecto_campo_porcentaje' => $presupuesto['indirecto_campo_porcentaje'] ?? 0,
            'indirecto_financiamiento_porcentaje' => $presupuesto['indirecto_financiamiento_porcentaje'] ?? 0,
            'indirecto_utilidad_porcentaje' => $presupuesto['indirecto_utilidad_porcentaje'] ?? 0,
            'indirecto_cargos_porcentaje' => $presupuesto['indirecto_cargos_porcentaje'] ?? 0,
            'indirecto_otros_porcentaje' => $presupuesto['indirecto_otros_porcentaje'] ?? 0,
        ]);
        $propio = ($linea['indirectos_modo'] ?? 'general') === 'propio';
        $pct = $propio ? $this->normalizarPorcentajesIndirectos($linea) : $generales;

        $gruposMap = [];
        $ordenGrupos = [
            'material',
            'mano_obra',
            'herramienta',
            'equipo',
            'auxiliar',
            'flete',
            'trabajo',
            'producto',
            'servicio',
        ];
        foreach ($componentes as $comp) {
            if (! is_array($comp)) {
                continue;
            }
            $unidad = mb_strtolower(trim((string) ($comp['unidad'] ?? '')));
            if ($unidad === 'párrafo' || $unidad === 'parrafo') {
                continue;
            }
            $categoria = strtolower(trim((string) ($comp['categoria'] ?? 'material')));
            if ($categoria === '') {
                $categoria = 'material';
            }
            if (! isset($gruposMap[$categoria])) {
                $gruposMap[$categoria] = [
                    'categoria' => $categoria,
                    'etiqueta' => $this->etiquetaCategoriaHojaAnalisis($categoria),
                    'filas' => [],
                    'subtotal' => 0.0,
                ];
            }
            $cantidad = (float) ($comp['cantidad'] ?? 0);
            $precio = (float) ($comp['precio_unitario'] ?? 0);
            $importe = isset($comp['importe']) && is_numeric($comp['importe'])
                ? round((float) $comp['importe'], 4)
                : round($cantidad * $precio, 4);
            $esPorcentaje = ($comp['calculo'] ?? self::CALCULO_CANTIDAD) === self::CALCULO_PORCENTAJE_MO;
            $gruposMap[$categoria]['filas'][] = [
                'clave' => $comp['clave_snapshot'] ?? $comp['clave'] ?? null,
                'descripcion' => (string) ($comp['descripcion'] ?? ''),
                'unidad' => (string) ($comp['unidad'] ?? ''),
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'importe' => $importe,
                'calculo' => $esPorcentaje ? self::CALCULO_PORCENTAJE_MO : self::CALCULO_CANTIDAD,
                'porcentaje' => $esPorcentaje && isset($comp['porcentaje'])
                    ? (float) $comp['porcentaje']
                    : null,
            ];
            $gruposMap[$categoria]['subtotal'] = round(
                $gruposMap[$categoria]['subtotal'] + $importe,
                2
            );
        }

        $grupos = [];
        foreach ($ordenGrupos as $cat) {
            if (isset($gruposMap[$cat])) {
                $grupos[] = $gruposMap[$cat];
                unset($gruposMap[$cat]);
            }
        }
        foreach ($gruposMap as $resto) {
            $grupos[] = $resto;
        }

        $costoDirecto = $this->costoDirectoComponentes($componentes);
        $desglose = $this->desgloseIndirectos($costoDirecto, $pct);
        $precioUnitarioGuardado = round((float) ($linea['precio_unitario'] ?? 0), 2);
        $delta = round($precioUnitarioGuardado - $desglose['precio_venta'], 2);
        if (abs($delta) >= 0.01 && $precioUnitarioGuardado > 0) {
            $desglose = $this->cuadrarDesgloseConVenta($desglose, $delta);
        }

        $cantidadLinea = (float) ($linea['cantidad'] ?? 1);
        if ($cantidadLinea <= 0) {
            $cantidadLinea = 1;
        }
        $precioUnitario = round((float) $desglose['precio_venta'], 2);
        $importeLinea = round($precioUnitario * $cantidadLinea, 2);
        $conIva = filter_var($presupuesto['con_iva'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $ivaPct = $conIva ? $this->acotarPorcentaje($presupuesto['iva_porcentaje'] ?? 0) : 0.0;
        $ivaImporte = $conIva ? round($importeLinea * ($ivaPct / 100), 2) : 0.0;
        $total = round($importeLinea + $ivaImporte, 2);
        $sobrecostos = round(
            $desglose['oficina'] + $desglose['campo'] + $desglose['financiamiento']
            + $desglose['utilidad'] + $desglose['cargos'] + $desglose['otros'],
            2
        );

        $clave = trim((string) ($linea['clave'] ?? ''));
        if ($clave === '') {
            $clave = (string) $numeroFallback;
        }

        return [
            'clave' => $clave,
            'descripcion' => (string) ($linea['descripcion'] ?? ''),
            'unidad' => (string) ($linea['unidad'] ?? ''),
            'cantidad' => $cantidadLinea,
            'grupos' => $grupos,
            'costo_directo' => $desglose['costo_directo'],
            'sobrecostos' => [
                'total' => $sobrecostos,
                'oficina' => $desglose['oficina'],
                'campo' => $desglose['campo'],
                'financiamiento' => $desglose['financiamiento'],
                'utilidad' => $desglose['utilidad'],
                'cargos' => $desglose['cargos'],
                'otros' => $desglose['otros'],
                'pct' => $desglose['pct'],
            ],
            'precio_unitario' => $precioUnitario,
            'importe' => $importeLinea,
            'con_iva' => $conIva,
            'iva_porcentaje' => $ivaPct,
            'iva_importe' => $ivaImporte,
            'total' => $total,
        ];
    }

    private function etiquetaCategoriaHojaAnalisis(string $categoria): string
    {
        return match ($categoria) {
            'material' => 'Materiales',
            'mano_obra' => 'Mano de obra',
            'herramienta' => 'Herramienta',
            'equipo' => 'Equipo',
            'auxiliar' => 'Auxiliares',
            'flete' => 'Fletes',
            'trabajo' => 'Trabajos',
            'producto' => 'Productos',
            'servicio' => 'Servicios',
            default => ucfirst(str_replace('_', ' ', $categoria)),
        };
    }

    /**
     * Indirectos del documento, una sola vez, para pintarlos antes de los totales.
     * Los importes cierran con el precio de venta de cada línea.
     *
     * @param  array<string, mixed>  $presupuesto
     * @return list<array{etiqueta: string, importe: float, porcentaje: ?float}>
     */
    public function filasIndirectosDocumento(array $presupuesto): array
    {
        $generales = $this->normalizarPorcentajesIndirectos([
            'indirecto_oficina_porcentaje' => $presupuesto['indirecto_oficina_porcentaje'] ?? 0,
            'indirecto_campo_porcentaje' => $presupuesto['indirecto_campo_porcentaje'] ?? 0,
            'indirecto_financiamiento_porcentaje' => $presupuesto['indirecto_financiamiento_porcentaje'] ?? 0,
            'indirecto_utilidad_porcentaje' => $presupuesto['indirecto_utilidad_porcentaje'] ?? 0,
            'indirecto_cargos_porcentaje' => $presupuesto['indirecto_cargos_porcentaje'] ?? 0,
            'indirecto_otros_porcentaje' => $presupuesto['indirecto_otros_porcentaje'] ?? 0,
        ]);
        $costoDirecto = 0.0;
        $oficina = 0.0;
        $campo = 0.0;
        $financiamiento = 0.0;
        $utilidad = 0.0;
        $cargos = 0.0;
        $otros = 0.0;
        $firmas = [];

        foreach (array_values($presupuesto['conceptos'] ?? []) as $linea) {
            if (! is_array($linea) || \App\Support\PresupuestoParrafoPdf::esLineaParrafo($linea)) {
                continue;
            }
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            $venta = round(((float) ($linea['precio_unitario'] ?? 0)) * $cantidad, 2);
            $componentes = is_array($linea['componentes'] ?? null) ? $linea['componentes'] : [];
            $tieneMatriz = (bool) ($linea['tiene_matriz'] ?? false) && $componentes !== [];
            if (! $tieneMatriz) {
                $costoDirecto += $venta;
                continue;
            }

            $cdLinea = round($this->costoDirectoComponentes($componentes) * $cantidad, 2);
            $propio = ($linea['indirectos_modo'] ?? 'general') === 'propio';
            $pct = $propio
                ? $this->normalizarPorcentajesIndirectos($linea)
                : $generales;
            $desglose = $this->desgloseIndirectos($cdLinea, $pct);
            $delta = round($venta - $desglose['precio_venta'], 2);
            if (abs($delta) >= 0.01) {
                $desglose = $this->cuadrarDesgloseConVenta($desglose, $delta);
            }
            $costoDirecto += $desglose['costo_directo'];
            $oficina += $desglose['oficina'];
            $campo += $desglose['campo'];
            $financiamiento += $desglose['financiamiento'];
            $utilidad += $desglose['utilidad'];
            $cargos += $desglose['cargos'];
            $otros += $desglose['otros'];
            $sumaPie = $desglose['oficina'] + $desglose['campo'] + $desglose['financiamiento']
                + $desglose['utilidad'] + $desglose['cargos'] + $desglose['otros'];
            if ($sumaPie > 0.004) {
                $firmas[] = $pct['oficina'].'|'.$pct['campo'].'|'.$pct['financiamiento'].'|'
                    .$pct['utilidad'].'|'.$pct['cargos'].'|'.$pct['otros'];
            }
        }

        $oficina = round($oficina, 2);
        $campo = round($campo, 2);
        $financiamiento = round($financiamiento, 2);
        $utilidad = round($utilidad, 2);
        $cargos = round($cargos, 2);
        $otros = round($otros, 2);
        $sumaImportes = $oficina + $campo + $financiamiento + $utilidad + $cargos + $otros;
        $sumaGenerales = $generales['oficina'] + $generales['campo'] + $generales['financiamiento']
            + $generales['utilidad'] + $generales['cargos'] + $generales['otros'];
        $hayPorcentajeGeneral = $sumaGenerales >= 0.01;
        if ($sumaImportes < 0.01 && ! $hayPorcentajeGeneral) {
            return [];
        }
        if ($sumaImportes < 0.01 && $hayPorcentajeGeneral) {
            $filasCero = [$this->filaIndirectoDocumento('Costo directo', round($costoDirecto, 2), null)];
            foreach ([
                'Oficina' => 'oficina',
                'Campo' => 'campo',
                'Financiamiento' => 'financiamiento',
                'Utilidad' => 'utilidad',
                'Cargos' => 'cargos',
                'Otros' => 'otros',
            ] as $nombre => $clave) {
                if ($generales[$clave] >= 0.01) {
                    $filasCero[] = $this->filaIndirectoDocumento($nombre, 0.0, $generales[$clave]);
                }
            }

            return $filasCero;
        }

        $partesFirma = count($firmas) > 0 && count(array_unique($firmas)) === 1
            ? explode('|', $firmas[0])
            : null;
        $pctUnico = is_array($partesFirma) && count($partesFirma) === 6
            ? $this->normalizarPorcentajesIndirectos([
                'oficina' => (float) $partesFirma[0],
                'campo' => (float) $partesFirma[1],
                'financiamiento' => (float) $partesFirma[2],
                'utilidad' => (float) $partesFirma[3],
                'cargos' => (float) $partesFirma[4],
                'otros' => (float) $partesFirma[5],
            ])
            : null;
        if ($pctUnico === null && $firmas === [] && $hayPorcentajeGeneral) {
            $pctUnico = $generales;
        }

        $filas = [$this->filaIndirectoDocumento('Costo directo', round($costoDirecto, 2), null)];
        foreach ([
            ['Oficina', 'oficina', $oficina],
            ['Campo', 'campo', $campo],
            ['Financiamiento', 'financiamiento', $financiamiento],
            ['Utilidad', 'utilidad', $utilidad],
            ['Cargos', 'cargos', $cargos],
            ['Otros', 'otros', $otros],
        ] as [$nombre, $clave, $importe]) {
            $pct = $pctUnico === null ? null : $pctUnico[$clave];
            if ($importe < 0.01 && ($pct === null || $pct < 0.01)) {
                continue;
            }
            $filas[] = $this->filaIndirectoDocumento($nombre, $importe, $pct);
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $componentes
     */
    private function costoDirectoComponentes(array $componentes): float
    {
        $total = 0.0;
        foreach ($componentes as $comp) {
            if (! is_array($comp)) {
                continue;
            }
            $unidad = mb_strtolower(trim((string) ($comp['unidad'] ?? '')));
            if ($unidad === 'párrafo' || $unidad === 'parrafo') {
                continue;
            }
            $total += isset($comp['importe']) && is_numeric($comp['importe'])
                ? (float) $comp['importe']
                : ((float) ($comp['cantidad'] ?? 0)) * ((float) ($comp['precio_unitario'] ?? 0));
        }

        return round($total, 2);
    }

    /**
     * @param  array{costo_directo: float, oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float, precio_venta: float, pct: array{oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float}}  $desglose
     * @return array{costo_directo: float, oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float, precio_venta: float, pct: array{oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float}}
     */
    private function cuadrarDesgloseConVenta(array $desglose, float $delta): array
    {
        foreach (['otros', 'cargos', 'utilidad', 'financiamiento', 'campo', 'oficina', 'costo_directo'] as $clave) {
            if (abs($desglose[$clave]) >= 0.01 || $clave === 'costo_directo') {
                $desglose[$clave] = round($desglose[$clave] + $delta, 2);
                break;
            }
        }
        $desglose['precio_venta'] = round($desglose['precio_venta'] + $delta, 2);

        return $desglose;
    }

    /**
     * @return array{etiqueta: string, importe: float, porcentaje: ?float}
     */
    private function filaIndirectoDocumento(string $etiqueta, float $importe, ?float $porcentaje): array
    {
        return [
            'etiqueta' => $etiqueta,
            'importe' => round($importe, 2),
            'porcentaje' => ($porcentaje !== null && $porcentaje > 0) ? $porcentaje : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float}
     */
    public function normalizarPorcentajesIndirectos(array $data): array
    {
        return [
            'oficina' => $this->acotarPorcentaje($data['oficina'] ?? $data['indirecto_oficina_porcentaje'] ?? 0),
            'campo' => $this->acotarPorcentaje($data['campo'] ?? $data['indirecto_campo_porcentaje'] ?? 0),
            'financiamiento' => $this->acotarPorcentaje($data['financiamiento'] ?? $data['indirecto_financiamiento_porcentaje'] ?? 0),
            'utilidad' => $this->acotarPorcentaje($data['utilidad'] ?? $data['indirecto_utilidad_porcentaje'] ?? 0),
            'cargos' => $this->acotarPorcentaje($data['cargos'] ?? $data['indirecto_cargos_porcentaje'] ?? 0),
            'otros' => $this->acotarPorcentaje($data['otros'] ?? $data['indirecto_otros_porcentaje'] ?? 0),
        ];
    }

    /**
     * Columnas de override. Modo general deja los porcentajes en null.
     *
     * @param  array<string, mixed>  $data
     * @return array{indirectos_modo: string, indirecto_oficina_porcentaje: ?float, indirecto_campo_porcentaje: ?float, indirecto_financiamiento_porcentaje: ?float, indirecto_utilidad_porcentaje: ?float, indirecto_cargos_porcentaje: ?float, indirecto_otros_porcentaje: ?float}
     */
    public function columnasIndirectosPropios(array $data): array
    {
        $modo = ($data['indirectos_modo'] ?? 'general') === 'propio' ? 'propio' : 'general';
        if ($modo !== 'propio') {
            return [
                'indirectos_modo' => 'general',
                'indirecto_oficina_porcentaje' => null,
                'indirecto_campo_porcentaje' => null,
                'indirecto_financiamiento_porcentaje' => null,
                'indirecto_utilidad_porcentaje' => null,
                'indirecto_cargos_porcentaje' => null,
                'indirecto_otros_porcentaje' => null,
            ];
        }

        $pct = $this->normalizarPorcentajesIndirectos($data);

        return [
            'indirectos_modo' => 'propio',
            'indirecto_oficina_porcentaje' => $pct['oficina'],
            'indirecto_campo_porcentaje' => $pct['campo'],
            'indirecto_financiamiento_porcentaje' => $pct['financiamiento'],
            'indirecto_utilidad_porcentaje' => $pct['utilidad'],
            'indirecto_cargos_porcentaje' => $pct['cargos'],
            'indirecto_otros_porcentaje' => $pct['otros'],
        ];
    }

    /**
     * Porcentajes que aplican a una línea: los propios, o los generales del presupuesto.
     *
     * @param  array<string, mixed>  $data
     * @return array{oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float}
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
            'indirecto_financiamiento_porcentaje' => $presupuesto->indirecto_financiamiento_porcentaje,
            'indirecto_utilidad_porcentaje' => $presupuesto->indirecto_utilidad_porcentaje,
            'indirecto_cargos_porcentaje' => $presupuesto->indirecto_cargos_porcentaje,
            'indirecto_otros_porcentaje' => $presupuesto->indirecto_otros_porcentaje,
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
            'indirecto_financiamiento_porcentaje' => $model->indirecto_financiamiento_porcentaje !== null
                ? (float) $model->indirecto_financiamiento_porcentaje
                : null,
            'indirecto_utilidad_porcentaje' => $model->indirecto_utilidad_porcentaje !== null
                ? (float) $model->indirecto_utilidad_porcentaje
                : null,
            'indirecto_cargos_porcentaje' => $model->indirecto_cargos_porcentaje !== null
                ? (float) $model->indirecto_cargos_porcentaje
                : null,
            'indirecto_otros_porcentaje' => $model->indirecto_otros_porcentaje !== null
                ? (float) $model->indirecto_otros_porcentaje
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

                $filas = [];
                foreach ($padre->componentes as $componente) {
                    $filas[] = [
                        'categoria' => (string) $componente->categoria,
                        'unidad' => (string) $componente->unidad,
                        'cantidad' => (float) $componente->cantidad,
                        'precio_unitario' => (float) $componente->precio_unitario,
                        'importe' => (float) $componente->importe,
                        'calculo' => $componente->calculo ?? self::CALCULO_CANTIDAD,
                        'porcentaje' => $componente->porcentaje,
                        'es_compuesto_ref' => false,
                        'componentes_anidados' => [],
                    ];
                }
                $filas = $this->aplicarPorcentajesManoObra($filas);
                foreach ($padre->componentes->values() as $i => $componente) {
                    $fila = $filas[$i];
                    $componente->calculo = $fila['calculo'];
                    $componente->porcentaje = $fila['porcentaje'];
                    $componente->cantidad = $fila['cantidad'];
                    $componente->precio_unitario = $fila['precio_unitario'];
                    $componente->importe = $fila['importe'];
                    $componente->save();
                }

                $padre->precio_unitario = $this->sumarImportes($filas);
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
            'presupuesto_cuadrilla_id' => $row['presupuesto_cuadrilla_id'] ?? null,
            'clave_snapshot' => $row['clave_snapshot'],
            'descripcion' => $row['descripcion'],
            'unidad' => $row['unidad'],
            'cantidad' => $row['cantidad'],
            'precio_unitario' => $row['precio_unitario'],
            'importe' => $row['importe'],
            'calculo' => $row['calculo'] ?? self::CALCULO_CANTIDAD,
            'porcentaje' => ($row['calculo'] ?? self::CALCULO_CANTIDAD) === self::CALCULO_PORCENTAJE_MO
                ? ($row['porcentaje'] ?? null)
                : null,
            'es_compuesto_ref' => $esCompuesto,
            'componentes_anidados' => $anidados === [] ? null : $anidados,
            'indirectos' => $this->indirectosPersistibles($row),
        ];
    }

    /**
     * Márgenes propios de un compuesto anidado. Null conserva el costo directo (datos viejos).
     *
     * @param  array<string, mixed>  $row
     * @return array{indirectos_modo: string, indirecto_oficina_porcentaje: ?float, indirecto_campo_porcentaje: ?float, indirecto_financiamiento_porcentaje: ?float, indirecto_utilidad_porcentaje: ?float, indirecto_cargos_porcentaje: ?float, indirecto_otros_porcentaje: ?float}|null
     */
    public function indirectosPersistibles(array $row): ?array
    {
        $modo = $row['indirectos_modo'] ?? null;
        if ($modo !== 'propio' && $modo !== 'general') {
            return null;
        }
        if ($modo === 'general') {
            return [
                'indirectos_modo' => 'general',
                'indirecto_oficina_porcentaje' => null,
                'indirecto_campo_porcentaje' => null,
                'indirecto_financiamiento_porcentaje' => null,
                'indirecto_utilidad_porcentaje' => null,
                'indirecto_cargos_porcentaje' => null,
                'indirecto_otros_porcentaje' => null,
            ];
        }

        $pct = $this->normalizarPorcentajesIndirectos($row);

        return [
            'indirectos_modo' => 'propio',
            'indirecto_oficina_porcentaje' => $pct['oficina'],
            'indirecto_campo_porcentaje' => $pct['campo'],
            'indirecto_financiamiento_porcentaje' => $pct['financiamiento'],
            'indirecto_utilidad_porcentaje' => $pct['utilidad'],
            'indirecto_cargos_porcentaje' => $pct['cargos'],
            'indirecto_otros_porcentaje' => $pct['otros'],
        ];
    }

    /**
     * @return array{indirectos_modo: ?string, indirecto_oficina_porcentaje: ?float, indirecto_campo_porcentaje: ?float, indirecto_financiamiento_porcentaje: ?float, indirecto_utilidad_porcentaje: ?float, indirecto_cargos_porcentaje: ?float, indirecto_otros_porcentaje: ?float}
     */
    public function indirectosComponenteParaRecurso(mixed $indirectos): array
    {
        $data = is_array($indirectos) ? $indirectos : [];
        $guardados = $this->indirectosPersistibles($data);

        return $guardados ?? [
            'indirectos_modo' => null,
            'indirecto_oficina_porcentaje' => null,
            'indirecto_campo_porcentaje' => null,
            'indirecto_financiamiento_porcentaje' => null,
            'indirecto_utilidad_porcentaje' => null,
            'indirecto_cargos_porcentaje' => null,
            'indirecto_otros_porcentaje' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{oficina: float, campo: float, financiamiento: float, utilidad: float, cargos: float, otros: float}|null  $generales
     */
    private function precioDeCompuestoAnidado(float $costoDirecto, array $row, ?array $generales): float
    {
        $modo = $row['indirectos_modo'] ?? null;
        if ($modo === 'propio') {
            return $this->precioConIndirectos($costoDirecto, $this->normalizarPorcentajesIndirectos($row));
        }
        if ($modo === 'general' && is_array($generales)) {
            return $this->precioConIndirectos($costoDirecto, $generales);
        }

        return round($costoDirecto, 2);
    }

    /**
     * Segunda vuelta: los renglones porcentaje_mo valen un % de la mano de obra de este nivel.
     * La base es la mano de obra en cantidad (la cuadrilla). Un renglón de mano de obra
     * marcado como porcentaje (mando intermedio) queda fuera de esa base.
     * Párrafo y compuesto anidado se quedan en cantidad × precio.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function aplicarPorcentajesManoObra(array $rows): array
    {
        $base = 0.0;
        foreach ($rows as $row) {
            if (($row['categoria'] ?? '') !== 'mano_obra') {
                continue;
            }
            if (($row['calculo'] ?? self::CALCULO_CANTIDAD) === self::CALCULO_PORCENTAJE_MO) {
                continue;
            }
            $base += (float) ($row['importe'] ?? 0);
        }
        $base = round($base, 4);

        foreach ($rows as $i => $row) {
            $porcentaje = $this->porcentajeManoObraAplicable($row);
            if ($porcentaje === null) {
                $rows[$i]['calculo'] = self::CALCULO_CANTIDAD;
                $rows[$i]['porcentaje'] = null;

                continue;
            }
            $importe = round($base * $porcentaje / 100, 4);
            $rows[$i]['calculo'] = self::CALCULO_PORCENTAJE_MO;
            $rows[$i]['porcentaje'] = $porcentaje;
            $rows[$i]['cantidad'] = 1;
            $rows[$i]['precio_unitario'] = $importe;
            $rows[$i]['importe'] = $importe;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function porcentajeManoObraAplicable(array $row): ?float
    {
        if (($row['calculo'] ?? self::CALCULO_CANTIDAD) !== self::CALCULO_PORCENTAJE_MO) {
            return null;
        }
        $unidad = mb_strtolower(trim((string) ($row['unidad'] ?? '')));
        if ($unidad === 'párrafo' || $unidad === 'parrafo') {
            return null;
        }
        $anidados = $row['componentes_anidados'] ?? [];
        if (is_array($anidados) && count($anidados) > 0) {
            return null;
        }
        if (filter_var($row['es_compuesto_ref'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return $this->acotarPorcentaje($row['porcentaje'] ?? 0);
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
