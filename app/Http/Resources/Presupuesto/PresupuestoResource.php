<?php

namespace App\Http\Resources\Presupuesto;

use App\Models\Presupuesto;
use App\Services\Presupuesto\PresupuestoThemeService;
use App\Http\Resources\Presupuesto\PresupuestoEstadoLogResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Representación completa del presupuesto básico.
 */
class PresupuestoResource extends JsonResource
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
        $proveedorNombre = $this->proveedor?->nombre_comercial
            ?? $this->proveedor?->razon_social
            ?? null;

        $configCond = $this->configuracion_condiciones;
        $proveedorReceptorId = (int) ($this->proveedor_receptor_id ?? 0);
        if ($proveedorReceptorId <= 0 && is_array($configCond) && isset($configCond['proveedor_receptor_id'])) {
            $proveedorReceptorId = (int) $configCond['proveedor_receptor_id'];
        }
        $receptorEsProveedorCatalogo = $proveedorReceptorId > 0
            || (is_array($configCond) && ! empty($configCond['receptor_es_proveedor_catalogo']));
        $empresaReceptoraIdRespuesta = $this->empresaReceptora?->id
            ?? ($proveedorReceptorId > 0 ? $proveedorReceptorId : null)
            ?? $this->empresa_receptora_id;
        $origenReceptor = $receptorEsProveedorCatalogo
            ? 'proveedor'
            : ($this->empresa_receptora_id ? 'cartera' : 'captura');

        $doc = $this->resource->empresaReceptoraParaDocumento();
        $enunciadosClasificados = $this->resource->getEnunciadosClasificados();
        $terminosTextosLibres = is_array($this->term_cond_textos_libres) ? array_slice($this->term_cond_textos_libres, 0, 4) : [];
        $terminosVisibilidad = is_array($this->term_cond_visibilidad) ? $this->term_cond_visibilidad : [];
        $validacionAlcances = is_array($this->validacion_alcances) ? $this->validacion_alcances : [];

        return [
            // Data general
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

            // terminos y condiciones 
            'term_cond_dias_vigencia' => $this->term_cond_dias_vigencia,
            'term_cond_moneda' => $this->term_cond_moneda ?? 'MXN',
            'term_cond_impuestos_en_pdf' => (bool) ($this->term_cond_impuestos_en_pdf ?? true),
            'term_cond_iva' => (float) ($this->term_cond_iva ?? 16),
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
            // observaciones (traslados/viáticos: derivados de term_cond_visibilidad; columnas BD droppeadas)
            'obs_garantia_dias' => (int) ($this->obs_garantia_dias ?? 0),
            'obs_traslados' => array_key_exists('incluye_traslados', $terminosVisibilidad)
                ? (bool) $terminosVisibilidad['incluye_traslados']
                : null,
            'obs_viaticos' => array_key_exists('incluye_viaticos', $terminosVisibilidad)
                ? (bool) $terminosVisibilidad['incluye_viaticos']
                : null,
            'configuracion_condiciones' => $this->configuracion_condiciones,

            // estado
            'estado' => $this->estado ?? Presupuesto::ESTADO_BORRADOR,
            'motivo_rechazo' => $this->motivo_rechazo,
            'item_visto' => (bool) ($this->item_visto ?? false),
            'token_publico' => $this->token_publico,
            'pdf_theme' => $this->pdf_theme ?? PresupuestoThemeService::DEFAULT_THEME_KEY,
            'pdf_theme_css' => (new PresupuestoThemeService())->variablesAsCssMap(
                (string) ($this->pdf_theme ?? PresupuestoThemeService::DEFAULT_THEME_KEY)
            ),
            'ppto_config' => is_array($this->ppto_config) ? $this->ppto_config : new \stdClass(),
            'fecha_envio' => $this->resource->fechaDeEstado(Presupuesto::ESTADO_ENVIADO)?->format('Y-m-d H:i:s'),
            'fecha_aceptacion' => $this->resource->fechaDeEstado(Presupuesto::ESTADO_ACEPTADO)?->format('Y-m-d H:i:s'),
            'fecha_rechazo' => ($this->resource->fechaDeEstado(Presupuesto::ESTADO_RECHAZADO_CON_OBSERVACION)
                ?? $this->resource->fechaDeEstado(Presupuesto::ESTADO_RECHAZADO))?->format('Y-m-d H:i:s'),
            'estado_logs' => PresupuestoEstadoLogResource::collection($this->whenLoaded('estadoLogs')),

            'proveedor' => [
                'id' => $this->proveedor?->id ?? $this->proveedor_id,
                'logo' => $this->proveedor?->logo
                    ? Storage::disk('public')->url($this->proveedor?->logo)
                    : null,
                'empresa' => $this->proveedor?->razon_social ?? null,
                'alias_empresa' => $this->proveedor?->nombre_comercial ?? null,
                'rfc' => $this->proveedor?->rfc ?? null,
                'direccion' => $this->proveedor?->direccion_fiscal ?? null,
                'telefono' => $this->proveedor?->telefono ?? null,
                'correo' => $this->proveedor?->email ?? null,
                'origen' => $this->proveedor?->origen ?? null,
            ],
            'proveedor_receptor_id' => $this->proveedor_receptor_id !== null
                ? (int) $this->proveedor_receptor_id
                : null,
            'empresa_receptora_logo' => (int) ($this->proveedor_receptor_id ?? 0) > 0
                ? $this->resource->empresaReceptoraLogoUrlParaApi()
                : null,
            'empresa_receptora' => [
                'id' => $empresaReceptoraIdRespuesta,
                'nombre' => $doc['nombre'],
                'puesto' => $doc['puesto'],
                'empresa' => $doc['empresa'],
                'alias_empresa' => $doc['alias_empresa'],
                'telefono' => $doc['telefono'],
                'correo' => $doc['correo'],
                'origen' => $origenReceptor,
            ],
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                ];
            }),
            'empresa_receptora_nombre' => $doc['nombre'],
            'empresa_receptora_puesto' => $doc['puesto'],
            'empresa_receptora_empresa' => $doc['empresa'],
            'empresa_receptora_alias' => $doc['alias_empresa'],
            'empresa_receptora_telefono' => $doc['telefono'],
            'empresa_receptora_correo' => $doc['correo'],
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
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
