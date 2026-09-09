<?php

namespace App\Http\Requests\Construcc;

use Illuminate\Foundation\Http\FormRequest;

class ConstruccProveedorGenerarSppRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            // Datos generales de la SPP
            'descripcion_concepto' => 'required|string|min:1|max:500',
            'monto_total' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',

            // Archivos - Validación condicional
            // Si hay cotización, factura no es obligatoria
            // Si NO hay cotización, factura SÍ es obligatoria
            // 'cotizacion' => 'nullable|file|mimes:pdf,jpg,jpeg,png,bmp,gif,webp,doc,docx,xls,xlsx|max:10240',
            'cotizacion' => 'nullable|file|max:10240',


            // Cuenta bancaria del proveedor: Es opcional para las SPP generadas desde usuarios cosntrucc
            'cuenta_bancaria_id' => 'nullable',

            // Recursos de construcción
            'empresa_construcc_id' => 'required|exists:empresa_construcc,id',
            'usuario_id' => 'required|integer',
            'usuario_nombre' => 'required|string|max:255',
            'nivel_id' => 'required|integer|in:0,1,2,3,4,5,6,7', // 0=Admin, 1=DG, 2=DT, 3=DA, 4=SI, 5=PC, 6=RO, 7

            // Campos adicionales de la solicitud de pago
            'obra_id' => 'nullable|integer',
            'tipo' => 'nullable|string|max:255',
            'tipo_id' => 'nullable|integer',
            'notas' => 'nullable|string|max:1000',
            'utilizara' => 'nullable|string|max:255',
            'equipo' => 'nullable|string|max:255',
            'equipo_id' => 'nullable|integer',

            // Monto parcial
            'monto_parcial' => 'nullable|numeric|min:0',
            'motivo_parcial' => 'nullable|string|max:1000',
        ];

        // Validación condicional de factura
        if ($this->hasFile('cotizacion')) {
            // Si hay cotización, la factura es opcional
            $rules['factura_pdf'] = 'nullable|file|mimes:pdf|max:10240';
            $rules['factura_xml'] = 'nullable|file|mimes:xml|max:5120';
        } else {
            // Si NO hay cotización, la factura es obligatoria
            $rules['factura_pdf'] = 'required|file|mimes:pdf|max:10240';
            $rules['factura_xml'] = 'required|file|mimes:xml|max:5120';
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'cuenta_bancaria_id' => $this->cuenta_bancaria_id ?: null,
            'monto_parcial' => $this->monto_parcial ?: null,
            'motivo_parcial' => $this->motivo_parcial ?: null,
        ]);
    }

    public function messages()
    {
        return [
            // Mensajes para datos generales
            'descripcion_concepto.required' => 'La descripción del concepto es obligatoria',
            'descripcion_concepto.min' => 'La descripción debe tener al menos 1 caracter',
            'descripcion_concepto.max' => 'La descripción no debe exceder los 500 caracteres',
            'monto_total.required' => 'El monto total es obligatorio',
            'monto_total.numeric' => 'El monto total debe ser un número válido',
            'monto_total.min' => 'El monto total no puede ser negativo',
            'observaciones.max' => 'Las observaciones no deben exceder los 1000 caracteres',

            // Mensajes para archivos
            'factura_pdf.required' => 'El archivo PDF de la factura es obligatorio cuando no se adjunta cotización',
            'factura_pdf.file' => 'La factura PDF debe ser un archivo válido',
            'factura_pdf.mimes' => 'El archivo debe ser un PDF válido',
            'factura_pdf.max' => 'El archivo PDF no debe superar los 10MB',
            'factura_xml.required' => 'El archivo XML de la factura es obligatorio cuando no se adjunta cotización',
            'factura_xml.file' => 'La factura XML debe ser un archivo válido',
            'factura_xml.mimes' => 'El archivo debe ser un XML válido',
            'factura_xml.max' => 'El archivo XML no debe superar los 5MB',
            'cotizacion.file' => 'La cotización debe ser un archivo válido',
            'cotizacion.mimes' => 'El archivo de cotización debe ser un formato válido (PDF, imagen o documento)',
            'cotizacion.max' => 'El archivo de cotización no debe superar los 10MB',

            // Mensajes para cuenta bancaria
            'cuenta_bancaria_id.nullable' => 'La cuenta bancaria es opcional',
            'cuenta_bancaria_id.exists' => 'La cuenta bancaria seleccionada no existe',

            // Mensajes para recursos de construcción
            'empresa_construcc_id.required' => 'La empresa de construcción es obligatoria',
            'empresa_construcc_id.exists' => 'La empresa de construcción seleccionada no existe',
            'usuario_id.required' => 'El ID del usuario es obligatorio',
            'usuario_id.integer' => 'El ID del usuario debe ser un número entero',
            'usuario_nombre.required' => 'El nombre del usuario es obligatorio',
            'usuario_nombre.max' => 'El nombre del usuario no debe exceder 255 caracteres',
            'nivel_id.required' => 'El nivel del usuario es obligatorio',
            'nivel_id.integer' => 'El nivel del usuario debe ser un número entero',
            'nivel_id.in' => 'El nivel del usuario no es válido',

            // Mensajes para campos adicionales de la solicitud de pago
            'obra_id.integer' => 'El ID de la obra debe ser un número entero',
            'tipo.max' => 'El tipo no debe exceder los 255 caracteres',
            'tipo_id.integer' => 'El ID del tipo debe ser un número entero',
            'notas.max' => 'Las notas no deben exceder los 1000 caracteres',
            'utilizara.max' => 'El campo utilizara no debe exceder los 255 caracteres',
            'equipo.max' => 'El equipo no debe exceder los 255 caracteres',
            'equipo_id.integer' => 'El ID del equipo debe ser un número entero',
        ];
    }
}
