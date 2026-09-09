<?php

namespace App\Http\Requests\Presupuesto;

class UpdatePresupuestoPlantillaRequest extends StorePresupuestoPlantillaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->plantillaRules();
        $rules['nombre'] = ['sometimes', 'required', 'string', 'max:120'];

        return $rules;
    }
}
