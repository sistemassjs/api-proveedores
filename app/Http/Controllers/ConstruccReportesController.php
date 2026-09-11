<?php

namespace App\Http\Controllers;

use App\Models\PagoSPP;
use App\Models\SolicitudPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;


class ConstruccReportesController extends Controller
{

  public function __construct() {}

  /**
   * Reporte de contabilidad: Se genera con el listado de los pagos realizados, junto con la informaicon de la factura
   * 
   * GET /api/construcc/reportes/contabilidad
   * 
   * Parametros de entrada para filtrado:
   *  - banco_id : Id de la cuenta bancaria registrado en Construcc 
   *  - fecha_pago_desde : Fecha inicio del rango (default: hoy)
   *  - fecha_pago_hasta : Fecha fin del rango (default: hoy)
   *  
   * Generar salida con las columnas: 
   *  - fecha: fecha_pago
   *  - proveedor: RFC, Razon Social
   *  - factura: Folio (un registro por cada factura asociada a la SPP)
   *  - enlace_descarga: URL para descargar la factura PDF
   *  - importe: monto aplicado del pago
   */
  public function reporteContabilidad(Request $request)
  {
    // Validar parámetros de entrada
    $validated = $request->validate([
      'cuenta_id' => 'nullable|integer',
      'fecha_pago_desde' => 'nullable|date',
      'fecha_pago_hasta' => 'nullable|date',
      'origen' => 'nullable|string|in:spp,directo',
    ]);

    // Establecer fechas por defecto (hoy)
    $fechaDesde = $validated['fecha_pago_desde'] ?? now()->format('Y-m-d');
    $fechaHasta = $validated['fecha_pago_hasta'] ?? now()->format('Y-m-d');
    $bancoId = $validated['cuenta_id'] ?? null;

    // Construir consulta de pagos con relaciones
    $query = PagoSPP::with([
      'proveedor:id,rfc,razon_social,nombre_comercial',
      'solicitudesPago' => function ($q) {
        $q->select(
          'solicitudes_pago.id',
          'solicitudes_pago.folio_factura',
          'solicitudes_pago.ruta_archivo_factura_pdf',
          'solicitudes_pago.ruta_archivo_factura_xml',
          'solicitudes_pago.proveedor_id'
        );
      },
      'facturas.complementos',
    ])
      ->whereDate('fecha_pago', '>=', $fechaDesde)
      ->whereDate('fecha_pago', '<=', $fechaHasta);

    if (! empty($validated['origen'])) {
      $query->where('origen', $validated['origen']);
    }

    // Filtrar por banco si se especifica
    if ($bancoId) {
      $query->where('cuenta_bancaria_empresa_construcc_id', $bancoId);
    }

    // Ordenar por fecha de pago
    /** @var \Illuminate\Support\Collection<int, PagoSPP> $pagos */
    $pagos = $query->orderBy('fecha_pago', 'asc')->get();

    // Transformar datos para el reporte
    $data = [];
    $importeTotal = 0;

    /** @var PagoSPP $pago */
    foreach ($pagos as $pago) {
      $SPPsDelPago = $pago->solicitudesPago;
      $totalSppEnPago = $SPPsDelPago->count();

      $urlDescargaMultipleAmbos = null;
      $foliosFacturaString = null;
      $foliosFactura = [];
      $facturasDetalle = [];

      if ($totalSppEnPago > 1) {
        $sppIds = $SPPsDelPago->pluck('id')->toArray();
        $sppIdsString = implode(',', $sppIds);
        $urlDescargaMultipleAmbos = url('/api/construcc/reportes/descargar-facturas-multiple/' . $sppIdsString . '/ambos');
      }

      foreach ($SPPsDelPago as $spp) {
        $foliosFactura[] = $spp->folio_factura;
        $facturasDetalle[] = [
          'fuente' => 'spp',
          'spp_id' => $spp->id,
          'folio_factura' => $spp->folio_factura,
          'tiene_pdf' => ! empty($spp->ruta_archivo_factura_pdf),
          'tiene_xml' => ! empty($spp->ruta_archivo_factura_xml),
          'metodo_pago' => null,
          'documentos_faltantes' => array_values(array_filter([
            empty($spp->ruta_archivo_factura_pdf) ? 'factura_pdf' : null,
            empty($spp->ruta_archivo_factura_xml) ? 'factura_xml' : null,
          ])),
          'complementos' => [],
        ];
      }

      foreach ($pago->facturas as $factura) {
        $foliosFactura[] = $factura->folio_factura;
        $facturasDetalle[] = [
          'fuente' => 'pago',
          'pago_factura_id' => $factura->id,
          'folio_factura' => $factura->folio_factura,
          'tiene_pdf' => ! empty($factura->ruta_archivo_factura_pdf),
          'tiene_xml' => ! empty($factura->ruta_archivo_factura_xml),
          'metodo_pago' => $factura->metodo_pago,
          'url_pdf' => $factura->ruta_archivo_factura_pdf
            ? route('construcc.pagos-spp.facturas.descargar-pdf', ['factura' => $factura->id])
            : null,
          'url_xml' => $factura->ruta_archivo_factura_xml
            ? route('construcc.pagos-spp.facturas.descargar-xml', ['factura' => $factura->id])
            : null,
          'documentos_faltantes' => $factura->documentosFaltantes(),
          'complementos' => $factura->complementos->map(fn ($c) => [
            'id' => $c->id,
            'folio_complemento' => $c->folio_complemento,
            'tiene_pdf' => ! empty($c->ruta_archivo_pdf),
            'tiene_xml' => ! empty($c->ruta_archivo_xml),
            'url_pdf' => $c->ruta_archivo_pdf
              ? route('construcc.pagos-spp.complementos.descargar-pdf', ['complemento' => $c->id])
              : null,
            'url_xml' => $c->ruta_archivo_xml
              ? route('construcc.pagos-spp.complementos.descargar-xml', ['complemento' => $c->id])
              : null,
          ])->values()->all(),
        ];
      }

      $foliosFactura = array_values(array_filter($foliosFactura, fn ($f) => $f !== null && $f !== ''));
      $foliosFacturaString = ! empty($foliosFactura) ? implode(',', $foliosFactura) : null;

      $importeTotal += $pago->monto_total;

      $data[] = [
        'pago_id' => $pago->id . '',
        'origen' => $pago->origen ?? PagoSPP::ORIGEN_SPP,
        'folio_pago' => $pago->folio_pago_spp_consecutivo,
        'fecha_pago' => $pago->fecha_pago ? $pago->fecha_pago->format('Y-m-d H:i:s') : null,
        'proveedor_rfc' => $pago->proveedor->rfc ?? null,
        'proveedor_razon_social' => $pago->proveedor->razon_social ?? $pago->proveedor->nombre_comercial ?? null,
        'folios_factura' => $foliosFactura,
        'facturas' => $facturasDetalle,
        'documentos_faltantes' => $pago->documentosFaltantes(),

        'importe' => number_format($pago->monto_total, 2, '.', ''),
        'referencia_pago' => $pago->referencia_pago,
        'clave_rastreo' => $pago->clave_rastreo,
        'banco_destino' => $pago->banco_destino,
        'titular_cuenta_destino' => $pago->titular_cuenta_destino,

        'comprobante_pago_url' => $pago->comprobante_pago_url,
        'comprobante_pago_descarga' => route('construcc.reportes.descargar-comprobantes-pago', [
          'pago_id' => $pago->id,
        ]),
        'folios_facturas' => $foliosFacturaString,
        'facturas_descarga' => $urlDescargaMultipleAmbos,
      ];
    }

    return $this->success([
      'importe_total' => number_format($importeTotal, 2, '.', ''),
      'total_registros' => count($data),
      'fecha_desde' => $fechaDesde,
      'fecha_hasta' => $fechaHasta,
      'cuenta_id' => $bancoId,
      'registros' => $data,
    ], 'Reporte de contabilidad generado con éxito.');
  }

  /**
   * Descargar facturas de múltiples SPP en un archivo ZIP
   * 
   * GET /api/construcc/reportes/descargar-facturas-multiple/{spp_ids}/{tipo?}
   * 
   * Parámetros:
   *  - spp_ids: IDs de solicitudes de pago separados por comas (ej: 1,2,3,4)
   *  - tipo: 'pdf', 'xml' o 'ambos' (default: 'pdf', opcional)
   * 
   * Ejemplos:
   *  - /api/construcc/reportes/descargar-facturas-multiple/1,2,3
   *  - /api/construcc/reportes/descargar-facturas-multiple/1,2,3/pdf
   *  - /api/construcc/reportes/descargar-facturas-multiple/1,2,3/xml
   *  - /api/construcc/reportes/descargar-facturas-multiple/1,2,3/ambos
   */
  public function descargarFacturasMultiple(Request $request, string $spp_ids, string $tipo = 'pdf')
  {
    // Convertir string de IDs separados por comas a array
    $sppIdsArray = array_filter(array_map('intval', explode(',', $spp_ids)));

    // Validar que hay IDs
    if (empty($sppIdsArray)) {
      return $this->error('Debe proporcionar al menos un ID de solicitud de pago.', 400);
    }

    // Validar tipo
    if (!in_array($tipo, ['pdf', 'xml', 'ambos'])) {
      return $this->error('Tipo inválido. Use: pdf, xml o ambos', 400);
    }

    // Validar que los IDs existen
    $solicitudes = SolicitudPago::whereIn('id', $sppIdsArray)->get();

    if ($solicitudes->isEmpty()) {
      return $this->error('No se encontraron solicitudes de pago.', 404);
    }

    // Verificar que los IDs proporcionados existen todos
    $idsEncontrados = $solicitudes->pluck('id')->toArray();
    $idsNoEncontrados = array_diff($sppIdsArray, $idsEncontrados);

    if (!empty($idsNoEncontrados)) {
      return $this->error('Algunos IDs no existen: ' . implode(', ', $idsNoEncontrados), 404);
    }

    // Crear nombre único para el archivo ZIP
    $zipFileName = 'facturas_' . date('YmdHis') . '_' . uniqid() . '.zip';
    $zipPath = storage_path('app/temp/' . $zipFileName);

    // Asegurar que el directorio existe
    if (!file_exists(storage_path('app/temp'))) {
      mkdir(storage_path('app/temp'), 0755, true);
    }

    // Crear archivo ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
      return $this->error('No se pudo crear el archivo ZIP.', 500);
    }

    $archivosAgregados = 0;

    foreach ($solicitudes as $spp) {
      $folioSanitizado = preg_replace('/[^a-zA-Z0-9_-]/', '_', $spp->folio_factura ?? 'sin_folio');

      // Agregar PDF si se solicita
      if (($tipo === 'pdf' || $tipo === 'ambos') && $spp->ruta_archivo_factura_pdf) {
        // Buscar en disco 'private' de Laravel Storage
        $rutaPdf = storage_path('app/private/' . $spp->ruta_archivo_factura_pdf);
        if (file_exists($rutaPdf)) {
          $nombreArchivo = "SPP_{$spp->id}_{$folioSanitizado}.pdf";
          $zip->addFile($rutaPdf, $nombreArchivo);
          $archivosAgregados++;
        }
      }

      // Agregar XML si se solicita
      if (($tipo === 'xml' || $tipo === 'ambos') && $spp->ruta_archivo_factura_xml) {
        // Buscar en disco 'private' de Laravel Storage
        $rutaXml = storage_path('app/private/' . $spp->ruta_archivo_factura_xml);
        if (file_exists($rutaXml)) {
          $nombreArchivo = "SPP_{$spp->id}_{$folioSanitizado}.xml";
          $zip->addFile($rutaXml, $nombreArchivo);
          $archivosAgregados++;
        }
      }
    }

    $zip->close();

    if ($archivosAgregados === 0) {
      // Eliminar el ZIP vacío
      if (file_exists($zipPath)) {
        unlink($zipPath);
      }
      return $this->error('No se encontraron archivos de facturas para las solicitudes especificadas.', 404);
    }

    // Descargar el archivo y eliminarlo después
    return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
  }



  public function descargarComprobantesPago(PagoSPP $pago)
  {

    if (! $pago->comprobante_pago || ! Storage::disk('private')->exists($pago->comprobante_pago)) {
      return $this->error('Comprobante de pago no disponible.', null, 404);
    }

    return response()->download(
      Storage::disk('private')->path($pago->comprobante_pago)
    );
  }
}
