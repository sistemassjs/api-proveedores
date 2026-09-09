<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccEmpresaResource extends JsonResource
{
    /**
     * Transforma el recurso en un arreglo JSON.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'razon_social' => $this->razon_social,
            'rfc' => $this->rfc,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'estado' => $this->estado,
            'codigo_postal' => $this->codigo_postal,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'representante_legal' => $this->representante_legal,
            'activo' => (bool) $this->activo,
            'nombre_completo' => $this->nombre_completo,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),

            /**
             * Relaciones
             */
            'proveedores' => $this->whenLoaded('proveedores', function () {
                return $this->proveedores->map(function ($proveedor) {
                    return [
                        'id' => $proveedor->id,
                        'nombre' => $proveedor->nombre,
                        'rfc' => $proveedor->rfc,
                        'telefono' => $proveedor->telefono ?? null,
                        'email' => $proveedor->email ?? null,
                    ];
                });
            }),

            'solicitudes_pago' => $this->whenLoaded('solicitudesPago', function () {
                return $this->solicitudesPago->map(function ($sp) {
                    return [
                        'id' => $sp->id,
                        'folio' => $sp->folio ?? null,
                        'monto' => $sp->monto ?? null,
                        'estado_solicitud' => $sp->estado_solicitud ?? null,
                        'created_at' => optional($sp->created_at)->toDateTimeString(),
                    ];
                });
            }),
        ];
    }
}
