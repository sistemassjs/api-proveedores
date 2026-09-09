<?php

namespace App\Http\Resources\Presupuesto;

use App\Services\Presupuesto\PresupuestoThemeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PresupuestoPlantilla
 */
class PresupuestoPlantillaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conceptos = $this->whenLoaded('conceptos');

        return [
            'id' => $this->id,
            'proveedor_id' => (int) $this->proveedor_id,
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'activo' => (bool) $this->activo,
            'concepto_general' => $this->concepto_general,
            'titulo_anexos' => $this->titulo_anexos,
            'titulo_anexos_pdf' => $this->titulo_anexos_pdf,
            'con_iva' => (bool) $this->con_iva,
            'iva_porcentaje' => (float) ($this->iva_porcentaje ?? 16),
            'porcentaje_descuento' => $this->porcentaje_descuento !== null ? (int) $this->porcentaje_descuento : null,
            'cantidad_descuento' => $this->cantidad_descuento !== null ? (float) $this->cantidad_descuento : null,
            'term_cond_dias_vigencia' => $this->term_cond_dias_vigencia !== null ? (int) $this->term_cond_dias_vigencia : null,
            'term_cond_moneda' => $this->term_cond_moneda ?? 'MXN',
            'term_cond_impuestos_en_pdf' => (bool) ($this->term_cond_impuestos_en_pdf ?? true),
            'term_cond_iva' => $this->term_cond_iva !== null ? (float) $this->term_cond_iva : null,
            'term_cond_tiempo_entrega_dias' => $this->term_cond_tiempo_entrega_dias !== null
                ? (int) $this->term_cond_tiempo_entrega_dias
                : null,
            'term_cond_inicio_trabajo' => $this->term_cond_inicio_trabajo !== null
                ? (int) $this->term_cond_inicio_trabajo
                : null,
            'term_cond_inicio_trabajo_porcentaje' => $this->term_cond_inicio_trabajo_porcentaje !== null
                ? (float) $this->term_cond_inicio_trabajo_porcentaje
                : null,
            'term_cond_inicio_trabajo_cantidad' => $this->term_cond_inicio_trabajo_cantidad !== null
                ? (float) $this->term_cond_inicio_trabajo_cantidad
                : null,
            'term_cond_textos_libres' => is_array($this->term_cond_textos_libres) ? $this->term_cond_textos_libres : [],
            'term_cond_visibilidad' => is_array($this->term_cond_visibilidad) ? $this->term_cond_visibilidad : null,
            'validacion_alcances' => is_array($this->validacion_alcances) ? $this->validacion_alcances : null,
            'configuracion_condiciones' => is_array($this->configuracion_condiciones) ? $this->configuracion_condiciones : null,
            'obs_garantia_dias' => $this->obs_garantia_dias !== null ? (int) $this->obs_garantia_dias : null,
            'config_mostrar_totales' => (bool) ($this->config_mostrar_totales ?? true),
            'pdf_theme' => $this->pdf_theme,
            'pdf_theme_css' => (new PresupuestoThemeService())->variablesAsCssMap(
                (string) ($this->pdf_theme ?? PresupuestoThemeService::DEFAULT_THEME_KEY)
            ),
            'ppto_config' => is_array($this->ppto_config) ? $this->ppto_config : new \stdClass(),
            'config_emisor_presupuesto_id' => $this->config_emisor_presupuesto_id !== null
                ? (int) $this->config_emisor_presupuesto_id
                : null,
            'empresa_emisora_nombre' => $this->empresa_emisora_nombre,
            'empresa_emisora_puesto' => $this->empresa_emisora_puesto,
            'empresa_emisora_telefono' => $this->empresa_emisora_telefono,
            'empresa_emisora_correo' => $this->empresa_emisora_correo,
            'incluir_leyenda_atentamente' => (bool) ($this->incluir_leyenda_atentamente ?? true),
            'empresa_emisora_nombre_comercial' => $this->empresa_emisora_nombre_comercial,
            'conceptos_count' => $this->when(
                $conceptos !== null,
                fn () => $this->conceptos->count()
            ),
            'conceptos' => PresupuestoPlantillaConceptoResource::collection($this->whenLoaded('conceptos')),
            'anexos' => PresupuestoPlantillaAnexoResource::collection($this->whenLoaded('anexos')),
            'anexos_pdf' => PresupuestoPlantillaAnexoPdfResource::collection($this->whenLoaded('anexosPdf')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
