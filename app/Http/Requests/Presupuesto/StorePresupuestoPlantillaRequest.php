<?php

namespace App\Http\Requests\Presupuesto;

use App\Models\PresupuestoConcepto;
use App\Models\PresupuestoPlantillaConcepto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePresupuestoPlantillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->plantillaRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function plantillaRules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'concepto_general' => ['nullable', 'string', 'max:500'],
            'titulo_anexos' => ['nullable', 'string', 'max:80'],
            'titulo_anexos_pdf' => ['nullable', 'string', 'max:80'],
            'con_iva' => ['nullable', 'boolean'],
            'iva_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'porcentaje_descuento' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cantidad_descuento' => ['nullable', 'numeric', 'min:0'],
            'term_cond_dias_vigencia' => ['nullable', 'integer', 'min:0'],
            'term_cond_moneda' => ['nullable', 'string', Rule::in(['MXN', 'USD', 'EUR'])],
            'term_cond_impuestos_en_pdf' => ['nullable', 'boolean'],
            'term_cond_iva' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'term_cond_tiempo_entrega_dias' => ['nullable', 'integer', 'min:0'],
            'term_cond_inicio_trabajo' => ['nullable', 'integer', 'in:1,2'],
            'term_cond_inicio_trabajo_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'term_cond_inicio_trabajo_cantidad' => ['nullable', 'numeric', 'min:0'],
            'term_cond_textos_libres' => ['nullable', 'array', 'max:4'],
            'term_cond_textos_libres.*' => ['nullable', 'string', 'max:1000'],
            'term_cond_visibilidad' => ['nullable', 'array'],
            'validacion_alcances' => ['nullable', 'array'],
            'configuracion_condiciones' => ['nullable', 'array'],
            'obs_garantia_dias' => ['nullable', 'integer', 'min:0'],
            'config_mostrar_totales' => ['nullable', 'boolean'],
            'pdf_theme' => ['nullable', 'string', 'max:50'],
            'ppto_config' => ['nullable', 'array'],
            'config_emisor_presupuesto_id' => ['nullable', 'integer'],
            'empresa_emisora_nombre' => ['nullable', 'string', 'max:255'],
            'empresa_emisora_puesto' => ['nullable', 'string', 'max:255'],
            'empresa_emisora_telefono' => ['nullable', 'string', 'max:50'],
            'empresa_emisora_correo' => ['nullable', 'email', 'max:255'],
            'incluir_leyenda_atentamente' => ['nullable', 'boolean'],
            'empresa_emisora_nombre_comercial' => ['nullable', 'string', 'max:255'],
            'conceptos' => ['nullable', 'array'],
            'conceptos.*.tipo' => ['nullable', 'string', Rule::in([
                PresupuestoPlantillaConcepto::TIPO_CONCEPTO,
                PresupuestoPlantillaConcepto::TIPO_PARRAFO,
                PresupuestoConcepto::TIPO_CONCEPTO,
                PresupuestoConcepto::TIPO_PARRAFO,
            ])],
            'conceptos.*.descripcion' => ['required_with:conceptos', 'string'],
            'conceptos.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'conceptos.*.unidad' => ['nullable', 'string', 'max:50'],
            'conceptos.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'conceptos.*.imagen_base64' => ['nullable', 'string'],
            'conceptos.*.imagen_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la plantilla es obligatorio.',
            'nombre.max' => 'El nombre de la plantilla no debe exceder 120 caracteres.',
            'term_cond_moneda.in' => 'La moneda debe ser MXN, USD o EUR.',
            'conceptos.*.descripcion.required_with' => 'Cada línea de la plantilla requiere descripción.',
        ];
    }
}
