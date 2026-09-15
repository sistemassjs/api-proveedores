<?php

namespace App\Http\Controllers;

use App\Http\Resources\SolicitudPago\SolicitudPagoResource;
use App\Models\SolicitudPago;
use App\Support\PrivateFileDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminSolicitudPagoController extends Controller
{
    /**
     * Listado global de SPP para el panel administrador.
     * Filtros: proveedor_id, search/folio, fechas, empresa_construcc_id, estado_solicitud, etc.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(SolicitudPago::getFilters());
        $sortBy = $request->input('sort_by', 'fecha_registro_pendiente');
        $order = $request->input('order', 'desc');
        $perPage = min(max(1, (int) $request->input('per_page', 15)), 100);

        $allowedSort = [
            'fecha_registro_pendiente',
            'created_at',
            'updated_at',
            'numero_folio_solicitud',
            'monto_total',
            'estado_solicitud',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'fecha_registro_pendiente';
        }
        $order = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';

        $query = SolicitudPago::query()
            ->with(SolicitudPago::eagerLodable())
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = SolicitudPagoResource::collection($query)->resolve();

        return $this->paginated(
            $query->setCollection(collect($data)),
            'Solicitudes de pago listadas.',
            200
        );
    }

    public function show(SolicitudPago $solicitudPago): JsonResponse
    {
        $solicitudPago->load(SolicitudPago::eagerLodable());

        return $this->success(
            (new SolicitudPagoResource($solicitudPago))->resolve(),
            'Solicitud de pago obtenida.'
        );
    }

    public function descargarFacturaPdf(SolicitudPago $solicitudPago)
    {
        return $this->descargarArchivoPrivado(
            $solicitudPago->ruta_archivo_factura_pdf,
            'Factura PDF no disponible'
        );
    }

    public function descargarFacturaXml(SolicitudPago $solicitudPago)
    {
        return $this->descargarArchivoPrivado(
            $solicitudPago->ruta_archivo_factura_xml,
            'Factura XML no disponible'
        );
    }

    public function descargarComprobantePago(SolicitudPago $solicitudPago)
    {
        $ruta = $solicitudPago->resolverRutaComprobantePago();

        if (! $ruta || ! Storage::disk('private')->exists($ruta)) {
            return $this->success(['archivo_no_disponible' => true], 'Comprobante no disponible', 200);
        }

        return PrivateFileDownload::download(
            $ruta,
            'comprobante_'.$solicitudPago->numero_folio_solicitud
        );
    }

    public function descargarCotizacion(SolicitudPago $solicitudPago)
    {
        return $this->descargarArchivoPrivado(
            $solicitudPago->ruta_archivo_cotizacion,
            'Cotización no disponible'
        );
    }

    /**
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function descargarArchivoPrivado(?string $ruta, string $mensajeNoDisponible)
    {
        if (! $ruta || ! Storage::disk('private')->exists($ruta)) {
            return $this->success(['archivo_no_disponible' => true], $mensajeNoDisponible, 200);
        }

        return response()->download(Storage::disk('private')->path($ruta));
    }
}
