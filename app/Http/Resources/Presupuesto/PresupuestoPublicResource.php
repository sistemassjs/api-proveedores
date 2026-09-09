<?php

namespace App\Http\Resources\Presupuesto;

use App\Models\Presupuesto;
use App\Services\Presupuesto\PresupuestoThemeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Representación pública del presupuesto (sin datos sensibles de sesión / timeline).
 * Paridad de documento con {@see PresupuestoResource}: anexos, tema, layout y términos.
 */
class PresupuestoPublicResource extends JsonResource
{
    /**
     * Convierte un string a mayúsculas (UTF-8). Null o vacío se devuelven tal cual.
     */
    private static function upper(?string $value): ?string
    {
        return $value !== null && $value !== '' ? Str::upper($value) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proveedor = $this->proveedor;
        $condiciones = is_array($this->configuracion_condiciones) ? $this->configuracion_condiciones : [];
        $terminosVisibilidadEarly = is_array($this->term_cond_visibilidad) ? $this->term_cond_visibilidad : [];
        $incluyeTraslados = array_key_exists('incluye_traslados', $terminosVisibilidadEarly)
            ? (bool) $terminosVisibilidadEarly['incluye_traslados']
            : null;
        $incluyeViaticos = array_key_exists('incluye_viaticos', $terminosVisibilidadEarly)
            ? (bool) $terminosVisibilidadEarly['incluye_viaticos']
            : null;
        $condiciones = array_merge($condiciones, [
            'vigencia_dias' => $this->term_cond_dias_vigencia,
            'impuestos_activo' => ($this->config_mostrar_totales ?? true)
                && $this->term_cond_impuestos_en_pdf !== false,
            'config_mostrar_totales' => (bool) ($this->config_mostrar_totales ?? true),
            'anticipo_porcentaje' => $this->term_cond_inicio_trabajo_porcentaje,
            'tiempo_entrega_dias' => $this->term_cond_tiempo_entrega_dias,
            'inicio_trabajo' => $this->term_cond_inicio_trabajo,
            'inicio_trabajo_porcentaje' => $this->term_cond_inicio_trabajo_porcentaje,
            'garantia_dias' => $this->obs_garantia_dias,
            'gastos_traslado' => $incluyeTraslados === null
                ? null
                : ($incluyeTraslados ? 'incluidos' : 'no_incluidos'),
            'viaticos' => $incluyeViaticos === null
                ? null
                : ($incluyeViaticos ? 'incluidos' : 'no_incluidos'),
        ]);

        $enunciadosClasificados = $this->resource->getEnunciadosClasificados();
        $terminosTextosLibres = is_array($this->term_cond_textos_libres)
            ? array_slice($this->term_cond_textos_libres, 0, 4)
            : [];
        $terminosVisibilidad = $terminosVisibilidadEarly;
        $validacionAlcances = is_array($this->validacion_alcances) ? $this->validacion_alcances : [];

        $logoUrl = null;
        if ($proveedor && $proveedor->logo) {
            $logoUrl = preg_match('/^https?:\/\//', $proveedor->logo)
                ? $proveedor->logo
                : Storage::disk('public')->url($proveedor->logo);
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'numero_presupuesto' => $this->numero_presupuesto,
            'fecha_emision' => $this->fecha_emision?->format('Y-m-d'),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'concepto_general' => $this->concepto_general,
            'nombre_presupuesto' => $this->nombre_presupuesto,
            'titulo_anexos' => trim((string) ($this->titulo_anexos ?? '')) !== ''
                ? trim((string) $this->titulo_anexos)
                : 'Anexos',
            'titulo_anexos_pdf' => trim((string) ($this->titulo_anexos_pdf ?? '')) !== ''
                ? trim((string) $this->titulo_anexos_pdf)
                : 'Anexos PDF',
            'subtotal' => (float) $this->subtotal,
            'porcentaje_descuento' => $this->porcentaje_descuento !== null ? (int) $this->porcentaje_descuento : null,
            'cantidad_descuento' => $this->cantidad_descuento !== null ? (float) $this->cantidad_descuento : null,
            'con_iva' => (bool) $this->con_iva,
            'iva_porcentaje' => (float) $this->iva_porcentaje,
            'iva_total' => (float) $this->iva_total,
            'total' => (float) $this->total,
            'config_mostrar_totales' => (bool) ($this->config_mostrar_totales ?? true),

            // Compatibilidad temporal con front público legacy
            'condiciones' => $condiciones,
            'observaciones' => null,

            // Términos y condiciones (paridad con PresupuestoResource)
            'term_cond_dias_vigencia' => $this->term_cond_dias_vigencia,
            'term_cond_moneda' => $this->term_cond_moneda ?? 'MXN',
            'term_cond_impuestos_en_pdf' => (bool) ($this->term_cond_impuestos_en_pdf ?? true),
            'term_cond_iva' => (float) ($this->term_cond_iva ?? 16),
            'term_cond_anticipo_porcentaje' => $this->term_cond_inicio_trabajo_porcentaje !== null
                ? (float) $this->term_cond_inicio_trabajo_porcentaje
                : null,
            'term_cond_tiempo_entrega_dias' => $this->term_cond_tiempo_entrega_dias,
            'term_cond_inicio_trabajo' => $this->term_cond_inicio_trabajo,
            'term_cond_inicio_trabajo_porcentaje' => $this->term_cond_inicio_trabajo_porcentaje,
            'term_cond_inicio_trabajo_cantidad' => $this->term_cond_inicio_trabajo_cantidad !== null
                ? (float) $this->term_cond_inicio_trabajo_cantidad
                : null,
            'term_cond_textos_libres' => array_values(array_filter(array_map(
                static fn ($item) => trim((string) $item),
                $terminosTextosLibres
            ), static fn ($item) => $item !== '')),
            'term_cond_visibilidad' => [
                'pago_contra_conformidad' => array_key_exists('pago_contra_conformidad', $terminosVisibilidad)
                    ? (bool) $terminosVisibilidad['pago_contra_conformidad']
                    : true,
                'garantia_calidad' => array_key_exists('garantia_calidad', $terminosVisibilidad)
                    ? (bool) $terminosVisibilidad['garantia_calidad']
                    : true,
                'correccion_defectos' => array_key_exists('correccion_defectos', $terminosVisibilidad)
                    ? (bool) $terminosVisibilidad['correccion_defectos']
                    : true,
                'incluye_materiales_insumos' => array_key_exists('incluye_materiales_insumos', $terminosVisibilidad)
                    ? (bool) $terminosVisibilidad['incluye_materiales_insumos']
                    : true,
                'incluye_traslados' => array_key_exists('incluye_traslados', $terminosVisibilidad)
                    ? (bool) $terminosVisibilidad['incluye_traslados']
                    : true,
                'incluye_viaticos' => array_key_exists('incluye_viaticos', $terminosVisibilidad)
                    ? (bool) $terminosVisibilidad['incluye_viaticos']
                    : true,
            ],
            'validacion_alcances' => [
                'incluye_todos_los_costos' => array_key_exists('incluye_todos_los_costos', $validacionAlcances)
                    ? (bool) $validacionAlcances['incluye_todos_los_costos']
                    : true,
                'sin_costos_adicionales_no_autorizados' => array_key_exists('sin_costos_adicionales_no_autorizados', $validacionAlcances)
                    ? (bool) $validacionAlcances['sin_costos_adicionales_no_autorizados']
                    : true,
                'adicionales_requieren_autorizacion_escrita' => array_key_exists('adicionales_requieren_autorizacion_escrita', $validacionAlcances)
                    ? (bool) $validacionAlcances['adicionales_requieren_autorizacion_escrita']
                    : true,
            ],
            'term_cond_enunciados' => $enunciadosClasificados['terminos'],
            'validaciones_enunciados' => $enunciadosClasificados['validaciones'],
            'observaciones_enunciados' => $enunciadosClasificados['observaciones'],
            'obs_garantia_dias' => (int) ($this->obs_garantia_dias ?? 0),
            'obs_traslados' => array_key_exists('incluye_traslados', $terminosVisibilidad)
                ? (bool) $terminosVisibilidad['incluye_traslados']
                : null,
            'obs_viaticos' => array_key_exists('incluye_viaticos', $terminosVisibilidad)
                ? (bool) $terminosVisibilidad['incluye_viaticos']
                : null,
            'configuracion_condiciones' => $this->configuracion_condiciones,

            'motivo_rechazo' => $this->motivo_rechazo,
            'estado' => $this->estado ?? Presupuesto::ESTADO_BORRADOR,
            'pdf_theme' => $this->pdf_theme ?? PresupuestoThemeService::DEFAULT_THEME_KEY,
            /** CSS custom properties del tema (`--color-primary`, …) para preview sin auth. */
            'pdf_theme_css' => (new PresupuestoThemeService())->variablesAsCssMap(
                (string) ($this->pdf_theme ?? PresupuestoThemeService::DEFAULT_THEME_KEY)
            ),
            'ppto_config' => is_array($this->ppto_config) ? $this->ppto_config : new \stdClass(),

            'proveedor' => [
                'id' => $proveedor?->id ?? $this->proveedor_id,
                'nombre' => self::upper($proveedor?->nombre_comercial ?? $proveedor?->razon_social ?? null),
                'razon_social' => self::upper($proveedor?->razon_social ?? null),
                'logo' => $logoUrl,
                'rfc' => $proveedor?->rfc ?? null,
                'direccion_empresa' => self::upper($proveedor?->direccion_empresa ?? null),
                'ciudad' => self::upper($proveedor?->ciudad ?? null),
                'estado' => self::upper($proveedor?->estado ?? null),
                'telefono' => $proveedor?->telefono ?? null,
                'email' => $proveedor?->email ?? null,
            ],
            'empresa_receptora' => [
                'nombre' => $this->empresa_receptora_nombre,
                'puesto' => $this->empresa_receptora_puesto,
                'empresa' => $this->empresa_receptora_empresa,
                'alias_empresa' => $this->empresa_receptora_alias,
                'telefono' => $this->empresa_receptora_telefono,
                'correo' => $this->empresa_receptora_correo,
            ],
            'config_emisor_presupuesto_id' => $this->config_emisor_presupuesto_id !== null
                ? (int) $this->config_emisor_presupuesto_id
                : null,
            'empresa_emisora_nombre' => $this->empresa_emisora_nombre,
            'empresa_emisora_puesto' => $this->empresa_emisora_puesto,
            'empresa_emisora_telefono' => $this->empresa_emisora_telefono,
            'empresa_emisora_correo' => $this->empresa_emisora_correo,
            'incluir_leyenda_atentamente' => (bool) ($this->incluir_leyenda_atentamente ?? true),
            'empresa_emisora_nombre_comercial' => $this->empresa_emisora_nombre_comercial,
            'conceptos' => PresupuestoConceptoResource::collection($this->whenLoaded('conceptos')),
            'anexos' => PresupuestoAnexoResource::collection($this->whenLoaded('anexos')),
            'anexos_pdf' => PresupuestoAnexoPdfResource::collection($this->whenLoaded('anexosPdf')),
        ];
    }
}
