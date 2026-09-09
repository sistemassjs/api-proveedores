<?php

namespace App\Http\Requests\PerfilPublico;

use App\Services\PerfilPublico\PerfilPublicoThemeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePerfilPublicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme_key' => ['nullable', 'string', 'max:64'],
            'sections' => ['nullable', 'array'],
            'sections.empresa' => ['nullable', 'array'],
            'sections.empresa.enabled' => ['nullable', 'boolean'],
            'sections.empresa.fields' => ['nullable', 'array'],
            'sections.empresa.fields.*' => ['string', 'max:64'],
            'sections.contacto' => ['nullable', 'array'],
            'sections.contacto.enabled' => ['nullable', 'boolean'],
            'sections.contacto.fields' => ['nullable', 'array'],
            'sections.contacto.fields.*' => ['string', 'max:64'],
            'sections.tarjetas' => ['nullable', 'array'],
            'sections.tarjetas.enabled' => ['nullable', 'boolean'],
            'sections.tarjetas.ids' => ['nullable', 'array'],
            'sections.tarjetas.ids.*' => ['integer'],
            'sections.bancos' => ['nullable', 'array'],
            'sections.bancos.enabled' => ['nullable', 'boolean'],
            'sections.bancos.ids' => ['nullable', 'array'],
            'sections.bancos.ids.*' => ['integer'],
            'sections.fiscal' => ['nullable', 'array'],
            'sections.fiscal.enabled' => ['nullable', 'boolean'],
            'sections.fiscal.fields' => ['nullable', 'array'],
            'sections.fiscal.fields.*' => ['string', 'max:64'],
            'sections.fiscal.include_constancias' => ['nullable', 'boolean'],
            'sections.fiscal.constancia_ids' => ['nullable', 'array'],
            'sections.fiscal.constancia_ids.*' => ['string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $themeKey = $this->input('theme_key');
            if ($themeKey === null || $themeKey === '') {
                return;
            }
            $themes = app(PerfilPublicoThemeService::class);
            if (! $themes->isValidThemeKey((string) $themeKey)) {
                $v->errors()->add('theme_key', 'El estilo seleccionado no está disponible.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'theme_key.max' => 'La clave del tema no es válida.',
        ];
    }
}
