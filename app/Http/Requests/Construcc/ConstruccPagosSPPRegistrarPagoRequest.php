<?php

namespace App\Http\Requests\Construcc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConstruccPagosSPPRegistrarPagoRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    $NIVEL_USUARIO_CONSTRUCC_DG_ADMIN = 0; // Rol DA
    $NIVEL_USUARIO_CONSTRUCC_DG = 1; // Rol DA
    $NIVEL_USUARIO_CONSTRUCC_DT = 2; // Rol DA
    $NIVEL_USUARIO_CONSTRUCC_DA = 3; // Rol DA

    return [

      // =========================
      // Comprobante de pago
      // =========================
      'comprobante_pago' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

      /**
       * Cuentas bancarias de la empresa de construcción; para consolidación de cierres contable
       */
      'cuenta_bancaria_empresa_construcc_id' => ['nullable', 'numeric'],
      'cuenta_destino_id' => ['required', 'integer'],

      'monto_total' => ['required', 'numeric', 'min:0.01'],

      // =========================
      // Datos básicos del pago
      // =========================
      'empresa_id'     => ['required', 'integer'],
      'proveedor_id'   => ['required', 'integer'],
      'usuario_id'     => ['required', 'integer'],
      'usuario_nombre' => ['required', 'string', 'max:255'],
      'nivel_usuario'  => ['required', 'integer', Rule::in([
        $NIVEL_USUARIO_CONSTRUCC_DA,
        $NIVEL_USUARIO_CONSTRUCC_DG_ADMIN,
        $NIVEL_USUARIO_CONSTRUCC_DG,
        $NIVEL_USUARIO_CONSTRUCC_DT
      ])],

      // =========================
      // Información extraída del comprobante (OCR)
      // =========================
      'info_comprobante'                    => ['nullable', 'array'],
      'info_comprobante.monto'              => ['nullable', 'numeric', 'min:0.01'],
      'info_comprobante.fecha'              => ['nullable', 'date'],
      'info_comprobante.hora'               => ['nullable', 'string'],
      'info_comprobante.referencia'         => ['nullable', 'string', 'max:50'],
      'info_comprobante.bancoDestino'       => ['nullable', 'string', 'max:255'],
      'info_comprobante.nombreBeneficiario' => ['nullable', 'string', 'max:255'],
      'info_comprobante.claveRastreo'       => ['nullable', 'string', 'max:255'],

      // =========================
      // Solicitudes de pago
      // =========================
      'solicitudes'                   => ['required', 'array', 'min:1'],
      'solicitudes.*.solicitud_id'    => ['required', 'integer', 'exists:solicitudes_pago,id'],
      'solicitudes.*.monto_pago'      => ['required', 'numeric', 'min:0.01'],
      // =========================
      // datos de facturacion
      // =========================
      'solicitudes.*.uso' => ['nullable', 'string'],
      'solicitudes.*.mp' => ['nullable', 'string'],
      'solicitudes.*.fp' => ['nullable', 'string'],
      'solicitudes.*.rf' => ['nullable', 'string'],
      'solicitudes.*.razon_social_id' => ['nullable', 'numeric'],
      'solicitudes.*.datos_facturacion_id' => ['nullable', 'numeric'],

      // Otros datos
      'fecha_pago'      => ['nullable', 'date'],              // --> cambio por info_comprobante.fecha + info_comprobante.hora

      // // =========================
      // // datos de facturacion
      // // =========================
      // 'uso' => ['nullable', 'string'],
      // 'mp' => ['nullable', 'string'],
      // 'fp' => ['nullable', 'string'],
      // 'datos_facturacion_id' => ['nullable', 'numeric'],

    ];
  }

  public function messages(): array
  {
    return [

      'comprobante_pago.required' => 'Debes subir el comprobante de pago.',
      'comprobante_pago.file'     => 'El comprobante debe ser un archivo válido.',
      'comprobante_pago.mimes'    => 'El comprobante debe ser PDF, JPG o PNG.',
      'comprobante_pago.max'      => 'El comprobante no debe pesar más de 10 MB.',

      'cuenta_destino_id.required' => 'Debes seleccionar la cuenta destino del proveedor.',
      'cuenta_destino_id.integer'  => 'La cuenta destino debe ser un valor válido.',
      'cuenta_destino_terminacion.required' => 'La terminación de la cuenta destino es obligatoria.',
      'cuenta_destino_terminacion.string'   => 'La terminación de la cuenta debe ser texto.',
      'cuenta_destino_terminacion.max'      => 'La terminación de la cuenta no debe exceder 4 caracteres.',

      'empresa_id.required'     => 'No se recibió la empresa.',
      'proveedor_id.required'   => 'No se recibió el proveedor.',
      'usuario_id.required'     => 'No se pudo identificar al usuario.',
      'usuario_nombre.required' => 'El nombre del usuario es obligatorio.',

      'monto_total.required' => 'Debes indicar el monto total del pago.',
      'monto_total.numeric'  => 'El monto total debe ser numérico.',
      'monto_total.min'      => 'El monto total debe ser mayor a cero.',

      'nivel_usuario.in' => 'No tienes permisos para registrar este pago.',

      'fecha_pago.required' => 'La fecha de pago es obligatoria.',
      'referencia_pago.required' => 'La referencia del pago es obligatoria.',

      'solicitudes.required' => 'Debes seleccionar al menos una solicitud.',
      'solicitudes.*.solicitud_id.exists' => 'Una o más solicitudes no son válidas.',
      'solicitudes.*.monto_pago.required' => 'Falta el monto de una solicitud.',
    ];
  }

  public function attributes(): array
  {
    return [
      'comprobante_pago' => 'comprobante de pago',
      'cuenta_destino_id' => 'cuenta destino',
      'cuenta_destino_terminacion' => 'terminación de cuenta',
      'monto_total'      => 'monto total del pago',
      'fecha_pago'       => 'fecha de pago',
      'referencia_pago'  => 'referencia de pago',
    ];
  }
}
