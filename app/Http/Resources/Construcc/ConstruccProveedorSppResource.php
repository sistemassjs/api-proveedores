<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccProveedorSppResource extends JsonResource
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
                // datos generales
                'id' => $this->id,
                'numero_folio_solicitud' => $this->numero_folio_solicitud,
                'folio_sp_consecutivo' => $this->folio_sp_consecutivo,
                'folio_factura' => $this->folio_factura,
                'descripcion_concepto' => $this->descripcion_concepto,
                'observaciones' => $this->observaciones,
                'estado_solicitud' => $this->estado_solicitud,

                // Montos de las solicitudes de pago
                'monto_total' => (float) $this->monto_total,
                'saldo_pendiente' => (float) $this->saldo_pendiente,
                'monto_abonado' => (float) $this->monto_abonado,
                'pago_completo' => (bool) $this->pago_completo,

                // Archivos con URLs correctas
                'url_factura_pdf' => $this->ruta_archivo_factura_pdf ? route('construcc.solicitudes-pago.descargar-factura-pdf', $this->id) : null,
                'url_factura_xml' => $this->ruta_archivo_factura_xml ? route('construcc.solicitudes-pago.descargar-factura-xml', $this->id) : null,
                'url_cotizacion' => $this->ruta_archivo_cotizacion ? route('construcc.solicitudes-pago.descargar-cotizacion', $this->id) : null,

                // Datos de la empresa construcc
                'empresa_construcc_id' => $this->empresaConstrucc->id,
                'empresa_construcc_nombre' => $this->empresaConstrucc->nombre,
                'usuario_id' => $this->usuario_id,
                'usuario_nombre' => $this->usuario_nombre,

                'pagos' => ConstruccPagoResource::collection($this->whenLoaded('pagos')),

                // Campos de validación con monto parcial (si existen)
                'validacion_con_monto' => [
                    'monto' => (float) $this->validacion_monto ?? null,
                    'usuario_id' => $this->validacion_usuario_id ?? null,
                    'usuario_nombre' => $this->validacion_usuario_nombre ?? null,
                    'fecha' => $this->validacion_fecha ?? null,
                    'motivo' => $this->validacion_motivo ?? null,
                ],
                // Fechas
                'fecha_registro_pendiente' => $this->fecha_registro_pendiente?->format('Y-m-d H:i:s'),
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            ],

            // 'proveedor' => $this->whenLoaded('proveedor', function () {
            //     return [
            //         'id' => $this->proveedor->id,
            //         'nombre_comercial' => $this->proveedor->nombre_comercial,
            //         'razon_social' => $this->proveedor->razon_social,
            //         'rfc' => $this->proveedor->rfc,
            //         'email' => $this->proveedor->email,
            //         'telefono' => $this->proveedor->telefono,
            //         'estatus' => $this->proveedor->estatus,
            //         'tipo_alta' => $this->proveedor->tipo_alta,
            //     ];
            // }),

            // 'cuenta_bancaria' => $this->whenLoaded('cuentasBancarias', function () {
            //     $cuenta = $this->cuentasBancarias->first();

            //     if (!$cuenta) {
            //         return null;
            //     }

            //     return [
            //         'id' => $cuenta->id,
            //         'alias' => $cuenta->alias,
            //         'banco_clave' => $cuenta->banco_clave,
            //         'banco_nombre' => $cuenta->banco_nombre,
            //         'tipo_cuenta' => $cuenta->tipo_cuenta,
            //         'campo_dependiente' => $cuenta->campo_dependiente,
            //         'titular_cuenta' => $cuenta->titular_cuenta,
            //         'referencia' => $cuenta->referencia,
            //         'estatus' => $cuenta->estatus,
            //         'sucursal' => $cuenta->sucursal,
            //         'swift' => $cuenta->swift,
            //         'preferida' => (bool) $cuenta->preferida,
            //     ];
            // }),


        ];
    }
}
