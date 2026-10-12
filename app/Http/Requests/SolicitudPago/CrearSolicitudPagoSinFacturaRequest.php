<?php

namespace App\Http\Requests\SolicitudPago;

use Illuminate\Foundation\Http\FormRequest;

class CrearSolicitudPagoSinFacturaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // requeridos para la SP sin Factura pdf y xml
            'descripcion_concepto' => 'required|string|min:1|max:500',
            'monto_total' => 'required|numeric|min:0',
            'cotizacion' => 'required|file|mimes:pdf,jpg,jpeg,png,bmp,gif,webp,doc,docx,xls,xlsx|max:10240',

            'proveedor_id' => 'required|exists:proveedores,id',
            'empresa_construcc_id' => 'nullable|exists:empresa_construcc,id',
            'empresa' => 'nullable|string|max:255',

            // Datos del usuario Construcc que genera la SP
            'usuario_id' => 'nullable|integer',
            'usuario_nombre' => 'nullable|string|max:255',

            // Legacy support - residente maps to usuario_nombre
            'residente' => 'nullable|string|max:255',

            'cotizacion_id' => 'nullable|integer',
            'observaciones' => 'nullable|string|max:1000',

            // Validaciones para cuentas bancarias
            'cuentas_bancarias' => 'nullable|array',
            'cuentas_bancarias.*.cuenta_bancaria_id' => 'required|integer|exists:cuentas_bancarias,id',
            'cuentas_bancarias.*.datos_especificos' => 'nullable|array',
            'cuentas_bancarias.*.datos_especificos.alias' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.banco_clave' => 'nullable|string|max:10',
            'cuentas_bancarias.*.datos_especificos.banco_nombre' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.tipo_cuenta' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.campo_dependiente' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.titular_cuenta' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.referencia' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.estatus' => 'nullable|integer|min:0|max:2',
            'cuentas_bancarias.*.datos_especificos.sucursal' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.swift' => 'nullable|string|max:255',
            'cuentas_bancarias.*.datos_especificos.preferida' => 'nullable|boolean',
        ];
    }

    public function messages()
    {
        return [
            'descripcion_concepto.required' => 'La descripción del concepto es obligatoria',
            'descripcion_concepto.min' => 'La descripción debe tener al menos 10 caracteres',
            'descripcion_concepto.max' => 'La descripción no debe exceder los 500 caracteres',
            // 'factura_pdf.required' => 'El archivo PDF de la factura es obligatorio',
            // 'factura_pdf.mimes' => 'El archivo debe ser un PDF válido',
            // 'factura_pdf.max' => 'El archivo PDF no debe superar los 10MB',
            // 'factura_xml.required' => 'El archivo XML de la factura es obligatorio',
            // 'factura_xml.mimes' => 'El archivo debe ser un XML válido',
            // 'factura_xml.max' => 'El archivo XML no debe superar los 5MB',
            'cotizacion.required' => 'El archivo de cotización es obligatorio',
            'cotizacion.mimes' => 'El archivo de cotización debe ser un formato válido (PDF, imagen o documento)',
            'cotizacion.max' => 'El archivo de cotización no debe superar los 10MB',
            'proveedor_id.required' => 'Debe seleccionar un proveedor',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe',
            'empresa_construcc_id.exists' => 'La empresa de construcción seleccionada no existe',
            'empresa.required' => 'El nombre de la empresa es obligatorio',
            'empresa.max' => 'El nombre de la empresa no debe exceder 255 caracteres',

            // Mensajes para usuario Construcc
            'usuario_id.integer' => 'El ID del usuario debe ser un número entero',
            'usuario_nombre.max' => 'El nombre del usuario no debe exceder 255 caracteres',

            // Legacy
            'residente.max' => 'El nombre del residente no debe exceder 255 caracteres',

            'cotizacion_id.integer' => 'El ID de cotización debe ser un número entero',
            'monto_total.required' => 'El monto total es obligatorio',
            'monto_total.numeric' => 'El monto total debe ser un número válido',
            'monto_total.min' => 'El monto total no puede ser negativo',

            // Mensajes para cuentas bancarias
            'cuentas_bancarias.array' => 'Las cuentas bancarias deben ser un array válido',
            'cuentas_bancarias.*.cuenta_bancaria_id.required' => 'El ID de la cuenta bancaria es obligatorio',
            'cuentas_bancarias.*.cuenta_bancaria_id.integer' => 'El ID de la cuenta bancaria debe ser un número entero',
            'cuentas_bancarias.*.cuenta_bancaria_id.exists' => 'La cuenta bancaria seleccionada no existe',
            'cuentas_bancarias.*.datos_especificos.array' => 'Los datos específicos deben ser un array válido',
        ];
    }
}
