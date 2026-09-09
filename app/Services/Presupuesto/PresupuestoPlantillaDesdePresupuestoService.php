<?php

namespace App\Services\Presupuesto;

use App\Models\Presupuesto;
use App\Models\PresupuestoPlantilla;
use App\Models\PresupuestoPlantillaAnexo;
use App\Models\PresupuestoPlantillaAnexoPdf;
use App\Models\PresupuestoPlantillaConcepto;
use App\Models\User;
use App\Support\PresupuestoAnexoArchivoResponse;
use App\Support\PresupuestoAnexoImagenOptimizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Crea una plantilla a partir de un presupuesto existente (sin receptor).
 * Aislado del flujo duplicar del documento.
 */
class PresupuestoPlantillaDesdePresupuestoService
{
    /**
     * @param  array{
     *   mantener_anexos_imagen?: bool,
     *   mantener_anexos_pdf?: bool,
     *   mantener_tarjeta?: bool,
     *   mantener_tema?: bool
     * }  $opciones
     */
    public function crear(
        Presupuesto $presupuesto,
        User $user,
        string $nombre,
        ?string $descripcion = null,
        array $opciones = []
    ): PresupuestoPlantilla {
        $presupuesto->loadMissing(['conceptos', 'anexos', 'anexosPdf']);

        $mantenerAnexosImagen = (bool) ($opciones['mantener_anexos_imagen'] ?? true);
        $mantenerAnexosPdf = (bool) ($opciones['mantener_anexos_pdf'] ?? true);
        $mantenerTarjeta = (bool) ($opciones['mantener_tarjeta'] ?? true);
        $mantenerTema = (bool) ($opciones['mantener_tema'] ?? true);

        return DB::transaction(function () use (
            $presupuesto,
            $user,
            $nombre,
            $descripcion,
            $mantenerAnexosImagen,
            $mantenerAnexosPdf,
            $mantenerTarjeta,
            $mantenerTema
        ) {
            $emisor = $mantenerTarjeta
                ? [
                    'config_emisor_presupuesto_id' => $presupuesto->config_emisor_presupuesto_id,
                    'empresa_emisora_nombre' => $presupuesto->empresa_emisora_nombre,
                    'empresa_emisora_puesto' => $presupuesto->empresa_emisora_puesto,
                    'empresa_emisora_telefono' => $presupuesto->empresa_emisora_telefono,
                    'empresa_emisora_correo' => $presupuesto->empresa_emisora_correo,
                    'empresa_emisora_nombre_comercial' => $presupuesto->empresa_emisora_nombre_comercial,
                    'incluir_leyenda_atentamente' => (bool) ($presupuesto->incluir_leyenda_atentamente ?? true),
                ]
                : [
                    'config_emisor_presupuesto_id' => null,
                    'empresa_emisora_nombre' => null,
                    'empresa_emisora_puesto' => null,
                    'empresa_emisora_telefono' => null,
                    'empresa_emisora_correo' => null,
                    'empresa_emisora_nombre_comercial' => null,
                    'incluir_leyenda_atentamente' => true,
                ];

            $plantilla = PresupuestoPlantilla::create(array_merge([
                'proveedor_id' => (int) $presupuesto->proveedor_id,
                'user_id' => (int) $user->id,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'activo' => true,
                'concepto_general' => null,
                'titulo_anexos' => $presupuesto->titulo_anexos,
                'titulo_anexos_pdf' => $presupuesto->titulo_anexos_pdf,
                'con_iva' => (bool) $presupuesto->con_iva,
                'iva_porcentaje' => $presupuesto->iva_porcentaje ?? 16,
                'porcentaje_descuento' => $presupuesto->porcentaje_descuento,
                'cantidad_descuento' => $presupuesto->cantidad_descuento,
                'term_cond_dias_vigencia' => $presupuesto->term_cond_dias_vigencia,
                'term_cond_moneda' => $presupuesto->term_cond_moneda ?: 'MXN',
                'term_cond_impuestos_en_pdf' => (bool) ($presupuesto->term_cond_impuestos_en_pdf ?? true),
                'term_cond_iva' => $presupuesto->term_cond_iva,
                'term_cond_tiempo_entrega_dias' => $presupuesto->term_cond_tiempo_entrega_dias,
                'term_cond_inicio_trabajo' => $presupuesto->term_cond_inicio_trabajo,
                'term_cond_inicio_trabajo_porcentaje' => $presupuesto->term_cond_inicio_trabajo_porcentaje,
                'term_cond_inicio_trabajo_cantidad' => $presupuesto->term_cond_inicio_trabajo_cantidad,
                'term_cond_textos_libres' => $presupuesto->term_cond_textos_libres,
                'term_cond_visibilidad' => $presupuesto->term_cond_visibilidad,
                'validacion_alcances' => $presupuesto->validacion_alcances,
                'configuracion_condiciones' => $presupuesto->configuracion_condiciones,
                'obs_garantia_dias' => $presupuesto->obs_garantia_dias,
                'config_mostrar_totales' => (bool) ($presupuesto->config_mostrar_totales ?? true),
                'pdf_theme' => $mantenerTema ? $presupuesto->pdf_theme : null,
                'ppto_config' => $mantenerTema ? $presupuesto->ppto_config : null,
            ], $emisor));

            $this->copiarConceptos($presupuesto, $plantilla);

            if ($mantenerAnexosImagen) {
                $this->copiarAnexosImagen($presupuesto, $plantilla);
            }

            if ($mantenerAnexosPdf) {
                $this->copiarAnexosPdf($presupuesto, $plantilla);
            }

            return $plantilla->fresh(PresupuestoPlantilla::eagerLodable());
        });
    }

    private function copiarConceptos(Presupuesto $presupuesto, PresupuestoPlantilla $plantilla): void
    {
        $proveedorId = (int) $plantilla->proveedor_id;
        $numero = 1;

        foreach ($presupuesto->conceptos as $linea) {
            $tipo = $linea->tipo ?: PresupuestoPlantillaConcepto::TIPO_CONCEPTO;
            $esParrafo = $tipo === PresupuestoPlantillaConcepto::TIPO_PARRAFO;
            $imagenPath = null;

            if (! $esParrafo && $linea->imagen_path) {
                $imagenPath = $this->copiarImagenConcepto(
                    $proveedorId,
                    (int) $plantilla->id,
                    (string) $linea->imagen_path
                );
            }

            PresupuestoPlantillaConcepto::create([
                'presupuesto_plantilla_id' => $plantilla->id,
                'numero' => $numero++,
                'tipo' => $tipo,
                'descripcion' => $linea->descripcion,
                'cantidad' => $esParrafo ? 0 : (float) $linea->cantidad,
                'unidad' => $esParrafo ? '' : ($linea->unidad ?: 'pieza'),
                'precio_unitario' => $esParrafo ? 0 : (float) $linea->precio_unitario,
                'imagen_path' => $imagenPath,
            ]);
        }
    }

    private function copiarAnexosImagen(Presupuesto $presupuesto, PresupuestoPlantilla $plantilla): void
    {
        $disk = Storage::disk('public');
        $proveedorId = (int) $plantilla->proveedor_id;

        foreach ($presupuesto->anexos as $anexo) {
            $origenPath = (string) ($anexo->archivo_path ?? '');
            if ($origenPath === '' || ! $disk->exists($origenPath)) {
                continue;
            }

            $extension = pathinfo($origenPath, PATHINFO_EXTENSION) ?: 'jpg';
            $nuevoPath = sprintf(
                'presupuesto-plantillas/%d/%d/anexos/%s.%s',
                $proveedorId,
                (int) $plantilla->id,
                Str::uuid()->toString(),
                $extension
            );
            $disk->copy($origenPath, $nuevoPath);

            PresupuestoPlantillaAnexo::create([
                'presupuesto_plantilla_id' => $plantilla->id,
                'titulo' => $anexo->titulo,
                'descripcion' => $anexo->descripcion,
                'precio' => $anexo->precio,
                'orden' => $anexo->orden,
                'archivo_path' => $nuevoPath,
                'archivo_width' => $anexo->archivo_width,
                'archivo_height' => $anexo->archivo_height,
                'archivo_aspect_ratio' => $anexo->archivo_aspect_ratio,
            ]);
        }
    }

    private function copiarAnexosPdf(Presupuesto $presupuesto, PresupuestoPlantilla $plantilla): void
    {
        $disk = Storage::disk('public');
        $proveedorId = (int) $plantilla->proveedor_id;

        foreach ($presupuesto->anexosPdf as $anexo) {
            $origenPath = (string) ($anexo->archivo_path ?? '');
            if ($origenPath === '' || ! $disk->exists($origenPath)) {
                continue;
            }

            $nuevoPath = sprintf(
                'presupuesto-plantillas/%d/%d/anexos-pdf/%s.pdf',
                $proveedorId,
                (int) $plantilla->id,
                Str::uuid()->toString()
            );
            $disk->copy($origenPath, $nuevoPath);

            PresupuestoPlantillaAnexoPdf::create([
                'presupuesto_plantilla_id' => $plantilla->id,
                'titulo' => $anexo->titulo,
                'orden' => $anexo->orden,
                'archivo_path' => $nuevoPath,
                'paginas' => $anexo->paginas,
                'mostrar_estampado' => $anexo->mostrar_estampado,
                'mostrar_numero_pagina' => $anexo->mostrar_numero_pagina,
                'mostrar_datos_presupuesto' => $anexo->mostrar_datos_presupuesto,
            ]);
        }
    }

    private function copiarImagenConcepto(int $proveedorId, int $plantillaId, string $origenPath): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($origenPath)) {
            $base64 = PresupuestoAnexoArchivoResponse::archivoBase64($origenPath);
            if ($base64 === null) {
                return null;
            }

            return $this->guardarImagenConceptoBase64($proveedorId, $plantillaId, $base64);
        }

        $ext = pathinfo($origenPath, PATHINFO_EXTENSION) ?: 'jpg';
        $destino = sprintf(
            'presupuesto-plantillas/%d/%d/conceptos/%s.%s',
            $proveedorId,
            $plantillaId,
            Str::uuid()->toString(),
            $ext
        );
        $disk->copy($origenPath, $destino);

        return $destino;
    }

    private function guardarImagenConceptoBase64(int $proveedorId, int $plantillaId, string $base64): ?string
    {
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/', $base64, $m)) {
            return null;
        }
        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            return null;
        }
        $optimized = PresupuestoAnexoImagenOptimizer::optimizarParaAlmacenamiento($binary);
        $path = sprintf(
            'presupuesto-plantillas/%d/%d/conceptos/%s.%s',
            $proveedorId,
            $plantillaId,
            Str::uuid()->toString(),
            $optimized['extension'] ?? 'jpg'
        );
        Storage::disk('public')->put($path, $optimized['binary'] ?? $binary);

        return $path;
    }
}
