<?php

namespace App\Http\Requests\Presupuesto;

use App\Models\PresupuestoConcepto;
use App\Support\PresupuestoParrafoPdf;
use App\Services\Presupuesto\PresupuestoThemeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Valida la creación de un presupuesto básico con sus conceptos.
 *
 * Receptor: con empresa_receptora_id el servidor completa datos desde cartera o proveedor;
 * sin id, captura manual → nombre y empresa obligatorios.
 */
class StorePresupuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $id = $this->input('empresa_receptora_id');
        $merge = [];
        if ($id === '' || $id === false) {
            $merge['empresa_receptora_id'] = null;
        }

        foreach ([
            'empresa_receptora_nombre',
            'empresa_receptora_puesto',
            'empresa_receptora_empresa',
            'empresa_receptora_alias',
            'empresa_receptora_telefono',
            'empresa_receptora_correo',
            'empresa_emisora_nombre',
            'empresa_emisora_puesto',
            'empresa_emisora_telefono',
            'empresa_emisora_correo',
        ] as $field) {
            $merge[$field] = $this->normalizeReceptorText($this->input($field));
        }

        $this->merge($merge);

        $conceptos = $this->input('conceptos');
        if (is_array($conceptos)) {
            foreach ($conceptos as $index => $concepto) {
                if (! is_array($concepto)) {
                    continue;
                }
                $tipo = $concepto['tipo'] ?? PresupuestoConcepto::TIPO_CONCEPTO;
                if ($tipo !== PresupuestoConcepto::TIPO_PARRAFO) {
                    continue;
                }
                $conceptos[$index]['descripcion'] = PresupuestoParrafoPdf::sanitizarTexto(
                    (string) ($concepto['descripcion'] ?? '')
                );
            }
            $this->merge(['conceptos' => $conceptos]);
        }
    }

    private function normalizeReceptorText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return preg_match('/^[_\-\x{2013}\x{2014}]+$/u', $text) === 1 ? null : $text;
    }

    private function validateConceptoImagenBase64(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        $matches = [];
        if (! is_string($value) || ! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/', $value, $matches)) {
            $fail('La imagen del concepto debe estar en formato JPG, JPEG, PNG o WEBP en base64.');
            return;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            $fail('La imagen del concepto no contiene un base64 válido.');
            return;
        }

        if (strlen($binary) > 5 * 1024 * 1024) {
            $fail('La imagen del concepto no debe superar 5 MB.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'presupuesto_id' => 'nullable|integer|exists:presupuestos,id',
            'numero_presupuesto' => 'nullable|string|max:255',
            'proveedor_id' => 'required|exists:proveedores,id',
            'es_proveedor_receptor' => 'nullable|boolean', // es para indicar si el receptor es un proveedor
            'fecha_emision' => 'required|date',
            'concepto_general' => 'required|string',
            'nombre_presupuesto' => 'nullable|string|max:120',
            'titulo_anexos' => 'nullable|string|max:80',
            'titulo_anexos_pdf' => 'nullable|string|max:80',

            /**
             * Validación de cartera vs proveedor: 
             *  - en el controlador (exists en ambas tablas no es compatible con una sola regla).
             */
            'empresa_receptora_id' => 'nullable|integer',
            'empresa_receptora_nombre' => 'nullable|string|max:255', //|required_without:empresa_receptora_id',
            'empresa_receptora_puesto' => 'nullable|string|max:255',
            'empresa_receptora_empresa' => 'nullable|string|max:255', //|required_without:empresa_receptora_id',
            'empresa_receptora_alias' => 'nullable|string|max:255',
            'empresa_receptora_telefono' => 'nullable|string|max:30',
            'empresa_receptora_correo' => 'nullable|email|max:255',

            'config_emisor_presupuesto_id' => 'nullable|integer|exists:config_emisor_receptor_presupuestos,id',
            'empresa_emisora_nombre' => 'nullable|string|max:255',
            'empresa_emisora_puesto' => 'nullable|string|max:255',
            'empresa_emisora_telefono' => 'nullable|string|max:30',
            'empresa_emisora_correo' => 'nullable|email|max:255',
            'incluir_leyenda_atentamente' => 'nullable|boolean',
            'empresa_emisora_nombre_comercial' => 'nullable|string|max:255',
            
            /**
             * Conf: Terminos Condiciones Obs
             */
            'con_iva' => 'nullable|boolean',
            'config_mostrar_totales' => 'nullable|boolean',
            'ppto_config' => 'nullable|array',
            'ppto_config.margen_hoja_mm' => 'nullable|numeric|min:0|max:80',
            'ppto_config.margen_lateral_mm' => 'nullable|numeric|min:0|max:80',
            'ppto_config.margen_superior_mm' => 'nullable|numeric|min:0|max:80',
            'ppto_config.gap_logo_info_mm' => 'nullable|numeric|min:0|max:80',
            'ppto_config.gap_header_rule_mm' => 'nullable|numeric|min:0|max:80',
            'ppto_config.footer_height_mm' => 'nullable|numeric|min:0|max:80',
            'ppto_config.gap_atentamente_footer_mm' => 'nullable|numeric|min:0|max:80',
            'ppto_config.espacio_tras_titulo_atentamente_mm' => 'nullable|numeric|min:0|max:80',
            'iva_porcentaje' => 'nullable|numeric|min:0|max:100',
            'porcentaje_descuento' => 'nullable|integer|min:0|max:100',
            'cantidad_descuento' => 'nullable|numeric|min:0',

            'term_cond_dias_vigencia' => 'nullable|integer|min:0',
            'term_cond_moneda' => 'nullable|string|max:10',
            'term_cond_impuestos_en_pdf' => 'nullable|boolean',
            'term_cond_iva' => 'nullable|numeric|min:0|max:100',
            'term_cond_tiempo_entrega_dias' => 'nullable|integer|min:0',
            'term_cond_inicio_trabajo' => 'nullable|integer|in:1,2',
            'term_cond_inicio_trabajo_porcentaje' => 'nullable|numeric|min:0|max:100',
            'term_cond_inicio_trabajo_cantidad' => 'nullable|numeric|min:0.01',
            'obs_garantia_dias' => 'nullable|integer|min:0',
            'term_cond_textos_libres' => 'nullable|array|max:4',
            'term_cond_textos_libres.*' => 'nullable|string|max:1000',
            'term_cond_visibilidad' => 'nullable|array',
            'term_cond_visibilidad.pago_contra_conformidad' => 'nullable|boolean',
            'term_cond_visibilidad.garantia_calidad' => 'nullable|boolean',
            'term_cond_visibilidad.correccion_defectos' => 'nullable|boolean',
            'term_cond_visibilidad.incluye_materiales_insumos' => 'nullable|boolean',
            'term_cond_visibilidad.incluye_traslados' => 'nullable|boolean',
            'term_cond_visibilidad.incluye_viaticos' => 'nullable|boolean',

            'validacion_alcances' => 'nullable|array',
            'validacion_alcances.incluye_todos_los_costos' => 'nullable|boolean',
            'validacion_alcances.sin_costos_adicionales_no_autorizados' => 'nullable|boolean',
            'validacion_alcances.adicionales_requieren_autorizacion_escrita' => 'nullable|boolean',
            
            'configuracion_condiciones' => 'nullable|array',
            'pdf_theme' => 'nullable|string|max:64',

            /**
             * conceptos includos en el presupuesto
             */
            'conceptos' => 'required|array|min:1',
            'conceptos.*.tipo' => 'nullable|string|in:concepto,parrafo',
            'conceptos.*.descripcion' => 'required|string|max:5000',
            'conceptos.*.cantidad' => 'required|numeric|min:0.0001',
            'conceptos.*.unidad' => 'required|string|max:50',
            'conceptos.*.precio_unitario' => 'required|numeric|min:0',
            'conceptos.*.imagen_path' => 'nullable|string|max:255',
            'conceptos.*.imagen_url' => 'nullable|string|max:500|url',
            'conceptos.*.proveedor_nombre' => 'nullable|string|max:150',
            'conceptos.*.proveedor_logo_url' => 'nullable|string|max:500',
            'conceptos.*.imagen_base64' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $this->validateConceptoImagenBase64($attribute, $value, $fail);
                },
            ],


            'estado' => 'nullable|string|in:borrador,enviado,aceptado,rechazado,rechazado_con_observacion,vencido',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $pdfTheme = $data['pdf_theme'] ?? null;
            if ($pdfTheme !== null && $pdfTheme !== '' && ! app(PresupuestoThemeService::class)->themeExists((string) $pdfTheme)) {
                $v->errors()->add('pdf_theme', 'El estilo seleccionado no está disponible.');
            }

            $id = $data['empresa_receptora_id'] ?? null;
            $esProveedorReceptor = filter_var($data['es_proveedor_receptor'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $inicioTrabajo = isset($data['term_cond_inicio_trabajo']) ? (int) $data['term_cond_inicio_trabajo'] : null;
            $inicioPct = $data['term_cond_inicio_trabajo_porcentaje'] ?? null;
            $inicioMonto = $data['term_cond_inicio_trabajo_cantidad'] ?? null;

            if ($esProveedorReceptor && ($id === null || $id === '')) {
                $v->errors()->add(
                    'empresa_receptora_id',
                    'Debe indicar el id del proveedor del catálogo cuando es_proveedor_receptor es verdadero.'
                );

                return;
            }

            if ($id !== null && $id !== '') {
                return;
            }

            $nombre = trim((string) ($data['empresa_receptora_nombre'] ?? ''));
            $empresa = trim((string) ($data['empresa_receptora_empresa'] ?? ''));
            if ($nombre === '') {
                $v->errors()->add(
                    'empresa_receptora_nombre',
                    'El nombre del contacto es obligatorio en captura manual (sin cliente de cartera ni proveedor del catálogo).'
                );
            }
            if ($empresa === '') {
                $v->errors()->add(
                    'empresa_receptora_empresa',
                    'La razón social o empresa es obligatoria en captura manual (sin cliente de cartera ni proveedor del catálogo).'
                );
            }

            if ($inicioTrabajo === 2) {
                $tienePct = $inicioPct !== null && $inicioPct !== '' && (float) $inicioPct > 0;
                $tieneMonto = $inicioMonto !== null && $inicioMonto !== '' && (float) $inicioMonto > 0;

                if (! $tienePct && ! $tieneMonto) {
                    $v->errors()->add(
                        'term_cond_inicio_trabajo',
                        'Cuando el inicio de trabajo es por anticipo, debe indicar porcentaje o cantidad.'
                    );
                }
            }

            if (
                $inicioPct !== null && $inicioPct !== ''
                && $inicioMonto !== null && $inicioMonto !== ''
                && (float) $inicioPct > 0 && (float) $inicioMonto > 0
            ) {
                $v->errors()->add(
                    'term_cond_inicio_trabajo_cantidad',
                    'Debe enviar solo una opción de anticipo: porcentaje o cantidad.'
                );
            }

            $conceptos = $data['conceptos'] ?? [];
            if (is_array($conceptos)) {
                $maxParrafo = PresupuestoConcepto::DESCRIPCION_PARRAFO_MAX;
                foreach ($conceptos as $index => $concepto) {
                    if (! is_array($concepto)) {
                        continue;
                    }
                    $tipo = $concepto['tipo'] ?? PresupuestoConcepto::TIPO_CONCEPTO;
                    if ($tipo !== PresupuestoConcepto::TIPO_PARRAFO) {
                        continue;
                    }
                    $desc = (string) ($concepto['descripcion'] ?? '');
                    if (mb_strlen($desc) > $maxParrafo) {
                        $v->errors()->add(
                            "conceptos.{$index}.descripcion",
                            "El párrafo no puede exceder {$maxParrafo} caracteres (aprox. nueve renglones en el PDF)."
                        );
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_presupuesto.string' => 'El número de presupuesto debe ser texto.',
            'numero_presupuesto.max' => 'El número de presupuesto no debe exceder 255 caracteres.',
            'proveedor_id.required' => 'El proveedor emisor es obligatorio.',
            'proveedor_id.exists' => 'El proveedor emisor seleccionado no existe.',
            'empresa_receptora_id.exists' => 'El cliente seleccionado en cartera no existe.',
            'empresa_receptora_nombre.required_without' => 'El nombre de la persona es obligatorio cuando no se envía empresa_receptora_id.',
            'empresa_receptora_nombre.string' => 'El nombre de la persona debe ser texto.',
            'empresa_receptora_nombre.max' => 'El nombre de la persona no debe exceder 255 caracteres.',
            'empresa_receptora_puesto.string' => 'El puesto debe ser texto.',
            'empresa_receptora_puesto.max' => 'El puesto no debe exceder 255 caracteres.',
            'empresa_receptora_empresa.required_without' => 'La empresa es obligatoria cuando no se envía empresa_receptora_id.',
            'empresa_receptora_empresa.string' => 'La empresa debe ser texto.',
            'empresa_receptora_empresa.max' => 'La empresa no debe exceder 255 caracteres.',
            'empresa_receptora_telefono.string' => 'El teléfono debe ser texto.',
            'empresa_receptora_telefono.max' => 'El teléfono no debe exceder 30 caracteres.',
            'empresa_receptora_correo.email' => 'El correo debe ser válido.',
            'empresa_receptora_correo.max' => 'El correo no debe exceder 255 caracteres.',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
            'fecha_emision.date' => 'La fecha de emisión debe tener un formato válido.',
            'concepto_general.required' => 'El concepto general es obligatorio.',
            'concepto_general.string' => 'El concepto general debe ser texto.',
            'nombre_presupuesto.string' => 'El nombre del presupuesto debe ser texto.',
            'nombre_presupuesto.max' => 'El nombre del presupuesto no debe exceder 120 caracteres.',
            'titulo_anexos.string' => 'El título de anexos debe ser texto.',
            'titulo_anexos.max' => 'El título de anexos no debe exceder 80 caracteres.',
            'titulo_anexos_pdf.string' => 'El título de anexos PDF debe ser texto.',
            'titulo_anexos_pdf.max' => 'El título de anexos PDF no debe exceder 80 caracteres.',
            'con_iva.boolean' => 'El indicador con IVA debe ser verdadero o falso.',
            'iva_porcentaje.numeric' => 'El porcentaje de IVA debe ser numérico.',
            'iva_porcentaje.min' => 'El porcentaje de IVA no puede ser menor a 0.',
            'iva_porcentaje.max' => 'El porcentaje de IVA no puede ser mayor a 100.',
            'porcentaje_descuento.integer' => 'El porcentaje de descuento debe ser un número entero.',
            'porcentaje_descuento.min' => 'El porcentaje de descuento no puede ser menor a 0.',
            'porcentaje_descuento.max' => 'El porcentaje de descuento no puede ser mayor a 100.',
            'cantidad_descuento.numeric' => 'La cantidad de descuento debe ser numérica.',
            'cantidad_descuento.min' => 'La cantidad de descuento no puede ser menor a 0.',
            'term_cond_dias_vigencia.integer' => 'Los días de vigencia deben ser un número entero.',
            'term_cond_moneda.string' => 'La moneda debe ser texto.',
            'term_cond_iva.numeric' => 'El IVA debe ser numérico.',
            'term_cond_tiempo_entrega_dias.integer' => 'Los días de tiempo de entrega deben ser un número entero.',
            'term_cond_inicio_trabajo.in' => 'La opción de inicio de trabajo debe ser 1 (autorización) o 2 (anticipo).',
            'term_cond_inicio_trabajo_porcentaje.numeric' => 'El anticipo por porcentaje debe ser numérico.',
            'term_cond_inicio_trabajo_porcentaje.min' => 'El anticipo por porcentaje no puede ser menor a 0.',
            'term_cond_inicio_trabajo_porcentaje.max' => 'El anticipo por porcentaje no puede ser mayor a 100.',
            'term_cond_inicio_trabajo_cantidad.numeric' => 'El anticipo por cantidad debe ser numérico.',
            'term_cond_inicio_trabajo_cantidad.min' => 'El anticipo por cantidad debe ser mayor a 0.',
            'obs_garantia_dias.integer' => 'Los días de garantía deben ser un número entero.',
            'term_cond_textos_libres.array' => 'Los términos de texto libre deben enviarse como arreglo.',
            'term_cond_textos_libres.max' => 'Solo puede registrar hasta 4 textos libres.',
            'term_cond_textos_libres.*.string' => 'Cada texto libre debe ser texto.',
            'term_cond_textos_libres.*.max' => 'Cada texto libre no debe exceder 1000 caracteres.',
            'conceptos.required' => 'Debe registrar al menos un concepto.',
            'conceptos.array' => 'Los conceptos deben enviarse como arreglo.',
            'conceptos.min' => 'Debe registrar al menos un concepto.',
            'conceptos.*.descripcion.required' => 'La descripción del concepto es obligatoria.',
            'conceptos.*.descripcion.string' => 'La descripción del concepto debe ser texto.',
            'conceptos.*.cantidad.required' => 'La cantidad del concepto es obligatoria.',
            'conceptos.*.cantidad.numeric' => 'La cantidad del concepto debe ser numérica.',
            'conceptos.*.cantidad.min' => 'La cantidad del concepto debe ser mayor a cero.',
            'conceptos.*.unidad.required' => 'La unidad del concepto es obligatoria.',
            'conceptos.*.unidad.string' => 'La unidad del concepto debe ser texto.',
            'conceptos.*.unidad.max' => 'La unidad del concepto no debe exceder 50 caracteres.',
            'conceptos.*.precio_unitario.required' => 'El precio unitario del concepto es obligatorio.',
            'conceptos.*.precio_unitario.numeric' => 'El precio unitario del concepto debe ser numérico.',
            'conceptos.*.precio_unitario.min' => 'El precio unitario del concepto no puede ser negativo.',
        ];
    }
}
