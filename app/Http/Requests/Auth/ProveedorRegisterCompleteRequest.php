<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ProveedorRegisterCompleteRequest",
 *     required={"token", "password", "password_confirmation"},
 *
 *     @OA\Property(
 *         property="token",
 *         type="string",
 *         description="Token proporcionado para completar el registro",
 *         example="abc123def456"
 *     ),
 *     @OA\Property(
 *         property="password",
 *         type="string",
 *         format="password",
 *         description="Nueva contraseña (mínimo 8 caracteres)",
 *         example="MiClaveSegura123"
 *     ),
 *     @OA\Property(
 *         property="password_confirmation",
 *         type="string",
 *         format="password",
 *         description="Confirmación de la nueva contraseña",
 *         example="MiClaveSegura123"
 *     )
 * )
 */
class ProveedorRegisterCompleteRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize()
    {
        // Retornamos true, ya que estamos permitiendo que cualquiera pueda completar el registro
        return true;
    }

    /**
     * Obtén las reglas de validación que se aplican a la solicitud.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'token' => 'required|string', // Token obligatorio, debe ser una cadena de texto
            'password' => 'required|min:8|confirmed', // Contraseña requerida, debe tener al menos 8 caracteres y confirmarse
        ];
    }

    /**
     * Mensajes personalizados para las reglas de validación.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'token.required' => 'El token de registro es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
