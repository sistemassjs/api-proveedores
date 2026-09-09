<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccGenerarSppResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'solicitud_pago' => [
                'id' => $this->id,
                'numero_folio_solicitud' => $this->numero_folio_solicitud,
                'folio_sp_consecutivo' => $this->folio_sp_consecutivo,
                'folio_factura' => $this->folio_factura,
                'descripcion_concepto' => $this->descripcion_concepto,
                'observaciones' => $this->observaciones,
                'estado_solicitud' => $this->estado_solicitud,
                'monto_total' => (float) $this->monto_total,
                'saldo_pendiente' => (float) $this->saldo_pendiente,
                'monto_abonado' => (float) $this->monto_abonado,
                'pago_completo' => (bool) $this->pago_completo,
                'tiene_factura' => (bool) $this->tiene_factura,
                'verificada' => (bool) $this->verificada,

                // Archivos
                'ruta_archivo_factura_pdf' => $this->ruta_archivo_factura_pdf,
                'ruta_archivo_factura_xml' => $this->ruta_archivo_factura_xml,
                'ruta_archivo_cotizacion' => $this->ruta_archivo_cotizacion,

                // Construcción
                'empresa_construcc_id' => $this->empresa_construcc_id,
                'usuario_id' => $this->usuario_id,
                'usuario_nombre' => $this->usuario_nombre,

                // Fechas
                'fecha_registro_pendiente' => $this->fecha_registro_pendiente?->format('Y-m-d H:i:s'),
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            ],

            'proveedor' => $this->whenLoaded('proveedor', function () {
                return [
                    'id' => $this->proveedor->id,
                    'nombre_comercial' => $this->proveedor->nombre_comercial,
                    'razon_social' => $this->proveedor->razon_social,
                    'rfc' => $this->proveedor->rfc,
                    'email' => $this->proveedor->email,
                    'telefono' => $this->proveedor->telefono,
                    'estatus' => $this->proveedor->estatus,
                    'tipo_alta' => $this->proveedor->tipo_alta, // 1: Proveedor  2: UserConstrucc
                ];
            }),

            'cuenta_bancaria' => $this->whenLoaded('cuentasBancarias', function () {
                $cuenta = $this->cuentasBancarias->first();

                if (!$cuenta) {
                    return null;
                }

                return [
                    'id' => $cuenta->id,
                    'alias' => $cuenta->alias,
                    'banco_clave' => $cuenta->banco_clave,
                    'banco_nombre' => $cuenta->banco_nombre,
                    'tipo_cuenta' => $cuenta->tipo_cuenta,
                    'campo_dependiente' => $cuenta->campo_dependiente,
                    'titular_cuenta' => $cuenta->titular_cuenta,
                    'referencia' => $cuenta->referencia,
                    'estatus' => $cuenta->estatus,
                    'sucursal' => $cuenta->sucursal,
                    'swift' => $cuenta->swift,
                    'preferida' => (bool) $cuenta->preferida,
                ];
            }),

            'empresa_construcc' => $this->whenLoaded('empresaConstrucc', function () {
                return [
                    'id' => $this->empresaConstrucc->id,
                    'nombre' => $this->empresaConstrucc->nombre,
                    'razon_social' => $this->empresaConstrucc->razon_social,
                    'rfc' => $this->empresaConstrucc->rfc,
                ];
            }),
        ];
    }
}
