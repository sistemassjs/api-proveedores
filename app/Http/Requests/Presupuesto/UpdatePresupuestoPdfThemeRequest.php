<?php

namespace App\Http\Requests\Presupuesto;

use App\Services\Presupuesto\PresupuestoThemeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePresupuestoPdfThemeRequest extends FormRequest
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
        return [
            'pdf_theme' => 'required|string|max:64',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pdf_theme.required' => 'Debe indicar el estilo del presupuesto.',
            'pdf_theme.max' => 'La clave del tema no es válida.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $theme = (string) ($v->getData()['pdf_theme'] ?? '');
            if ($theme === '') {
                return;
            }
            if (! app(PresupuestoThemeService::class)->themeExists($theme)) {
                $v->errors()->add('pdf_theme', 'El estilo seleccionado no está disponible.');
            }
        });
    }
}
