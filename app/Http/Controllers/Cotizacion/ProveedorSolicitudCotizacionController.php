<?php

namespace App\Http\Controllers\Cotizacion;

use App\Enums\EstadoSolicitudCotizacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cotizacion\ProcesarSolicitudCotizacionRequest;
use App\Http\Requests\Cotizacion\ResponderSolicitudCotizacionRequest;
use App\Http\Requests\Cotizacion\StoreSolicitudCotizacionNexprovRequest;
use App\Http\Requests\Cotizacion\UpdateSolicitudCotizacionRequest;
use App\Http\Resources\Cotizacion\SolicitudCotizacionArchivoResource;
use App\Http\Resources\Cotizacion\SolicitudCotizacionResource;
use App\Http\Resources\Cotizacion\SolicitudCotizacionRespuestaResource;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use App\Models\SolicitudCotizacionArchivo;
use App\Models\SolicitudCotizacionRespuesta;
use App\Services\Cotizacion\SolicitudCotizacionService;
use App\Support\CotizacionPdf;
use App\Support\PublicStorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProveedorSolicitudCotizacionController extends Controller
{
    public function __construct(
        private readonly SolicitudCotizacionService $service
    ) {}

    public function index(Request $request, Proveedor $proveedor): JsonResponse
    {
        $filters = $request->only(SolicitudCotizacion::getFilters());
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');
        $perPage = max(5, min((int) $request->input('per_page', 15), 100));

        $paginator = SolicitudCotizacion::query()
            ->with(['detalles'])
            ->where('proveedor_id', $proveedor->id)
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = SolicitudCotizacionResource::collection($paginator)->resolve();

        $estatusCounts = collect(EstadoSolicitudCotizacion::values())
            ->mapWithKeys(fn ($estatus) => [
                $estatus => SolicitudCotizacion::query()
                    ->where('proveedor_id', $proveedor->id)
                    ->where('estatus', $estatus)
                    ->count(),
            ])
            ->all();
        $estatusCounts['todas'] = SolicitudCotizacion::query()
            ->where('proveedor_id', $proveedor->id)
            ->count();

        return $this->paginated(
            $paginator->setCollection(collect($data)),
            'Solicitudes de cotización.',
            200,
            ['estatus_counts' => $estatusCounts]
        );
    }

    /**
     * Galería de PDFs archivados del proveedor (envíos + procesadas).
     */
    public function galeria(Request $request, Proveedor $proveedor): JsonResponse
    {
        $tipo = trim((string) $request->input('tipo', ''));
        $search = trim((string) $request->input('search', ''));
        $perPage = max(5, min((int) $request->input('per_page', 20), 100));

        $query = SolicitudCotizacionArchivo::query()
            ->with(['solicitud', 'creadoPor'])
            ->where('proveedor_id', $proveedor->id)
            ->orderByDesc('created_at');

        if ($tipo !== '') {
            $query->whereIn('tipo', explode(',', $tipo));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhereHas('solicitud', function ($sq) use ($search) {
                        $sq->where('folio', 'like', "%{$search}%")
                            ->orWhere('cliente_nombre', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->paginate($perPage);
        $data = SolicitudCotizacionArchivoResource::collection($paginator)->resolve();

        return $this->paginated(
            $paginator->setCollection(collect($data)),
            'Galería de archivos de cotización.'
        );
    }

    public function descargarArchivo(
        Proveedor $proveedor,
        SolicitudCotizacionArchivo $archivo
    ): Response|JsonResponse {
        if ((int) $archivo->proveedor_id !== (int) $proveedor->id) {
            return $this->error('El archivo no pertenece a este proveedor.', null, 403);
        }

        if (! $archivo->pdf_path || ! Storage::disk('private')->exists($archivo->pdf_path)) {
            return $this->error('PDF no disponible.', null, 404);
        }

        return response()->download(
            Storage::disk('private')->path($archivo->pdf_path),
            $archivo->nombre ?: ('Cotizacion_'.$archivo->id.'.pdf')
        );
    }

    public function store(
        StoreSolicitudCotizacionNexprovRequest $request,
        Proveedor $proveedor
    ): JsonResponse {
        try {
            $solicitud = $this->service->crearDesdeNexprov(
                $proveedor,
                $request->validated(),
                $request->user()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->error('No se pudo crear la cotización.', $e->getMessage(), 500);
        }

        return $this->success(
            new SolicitudCotizacionResource($solicitud),
            'Cotización creada.',
            201
        );
    }

    public function show(Proveedor $proveedor, SolicitudCotizacion $solicitudCotizacion): JsonResponse
    {
        if (! $this->pertenece($proveedor, $solicitudCotizacion)) {
            return $this->error('La solicitud no pertenece a este proveedor.', null, 403);
        }

        $solicitudCotizacion->marcarEnRevisionSiRecibida();
        $solicitudCotizacion->load(SolicitudCotizacion::eagerLodable());

        return $this->success(
            new SolicitudCotizacionResource($solicitudCotizacion),
            'Detalle de solicitud de cotización.'
        );
    }

    public function update(
        UpdateSolicitudCotizacionRequest $request,
        Proveedor $proveedor,
        SolicitudCotizacion $solicitudCotizacion
    ): JsonResponse {
        if (! $this->pertenece($proveedor, $solicitudCotizacion)) {
            return $this->error('La solicitud no pertenece a este proveedor.', null, 403);
        }

        try {
            $actualizada = $this->service->actualizar($solicitudCotizacion, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->error('No se pudo actualizar la solicitud.', $e->getMessage(), 500);
        }

        return $this->success(
            new SolicitudCotizacionResource($actualizada),
            'Solicitud actualizada.'
        );
    }

    public function procesar(
        ProcesarSolicitudCotizacionRequest $request,
        Proveedor $proveedor,
        SolicitudCotizacion $solicitudCotizacion
    ): JsonResponse {
        if (! $this->pertenece($proveedor, $solicitudCotizacion)) {
            return $this->error('La solicitud no pertenece a este proveedor.', null, 403);
        }

        try {
            $procesada = $this->service->marcarProcesada(
                $solicitudCotizacion,
                $request->validated(),
                $request->user()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->error('No se pudo marcar como procesada.', $e->getMessage(), 500);
        }

        return $this->success(
            new SolicitudCotizacionResource($procesada),
            'Cotización marcada como procesada y archivada.'
        );
    }

    public function responder(
        ResponderSolicitudCotizacionRequest $request,
        Proveedor $proveedor,
        SolicitudCotizacion $solicitudCotizacion
    ): JsonResponse {
        if (! $this->pertenece($proveedor, $solicitudCotizacion)) {
            return $this->error('La solicitud no pertenece a este proveedor.', null, 403);
        }

        if ($solicitudCotizacion->detalles()->count() < 1) {
            return $this->error('La solicitud no tiene líneas para cotizar.', null, 422);
        }

        try {
            $respuesta = $this->service->responder(
                $solicitudCotizacion,
                $request->validated(),
                $request->user()
            );
        } catch (\Throwable $e) {
            return $this->error('No se pudo enviar la respuesta.', $e->getMessage(), 500);
        }

        $solicitudCotizacion->load(SolicitudCotizacion::eagerLodable());

        return $this->success([
            'solicitud' => new SolicitudCotizacionResource($solicitudCotizacion),
            'respuesta' => new SolicitudCotizacionRespuestaResource($respuesta),
        ], 'Respuesta procesada.');
    }

    public function previewPdf(Proveedor $proveedor, SolicitudCotizacion $solicitudCotizacion): Response|JsonResponse
    {
        if (! $this->pertenece($proveedor, $solicitudCotizacion)) {
            return $this->error('La solicitud no pertenece a este proveedor.', null, 403);
        }

        return CotizacionPdf::descargar($solicitudCotizacion);
    }

    public function descargarRespuestaPdf(
        Proveedor $proveedor,
        SolicitudCotizacion $solicitudCotizacion,
        SolicitudCotizacionRespuesta $respuesta
    ): Response|JsonResponse {
        if (! $this->pertenece($proveedor, $solicitudCotizacion)) {
            return $this->error('La solicitud no pertenece a este proveedor.', null, 403);
        }

        if ((int) $respuesta->solicitud_cotizacion_id !== (int) $solicitudCotizacion->id) {
            return $this->error('La respuesta no pertenece a esta solicitud.', null, 404);
        }

        if (! $respuesta->pdf_path || ! Storage::disk('private')->exists($respuesta->pdf_path)) {
            return $this->error('PDF no disponible.', null, 404);
        }

        return response()->download(
            Storage::disk('private')->path($respuesta->pdf_path),
            'Cotizacion_'.$solicitudCotizacion->folio.'.pdf'
        );
    }

    /**
     * Productos del proveedor (públicos y privados) para agregar líneas en gestión NexProv.
     */
    public function productosParaAgregar(Request $request, Proveedor $proveedor): JsonResponse
    {
        $q = trim((string) $request->input('q', $request->input('search', '')));
        $limit = max(1, min((int) $request->input('limit', 20), 50));
        $soloPublicos = filter_var($request->input('solo_publicos', false), FILTER_VALIDATE_BOOLEAN);

        $query = Producto::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('activo', true)
            ->with('unidad_medida');

        if ($soloPublicos) {
            $query->where('mostrar_en_catalogo_publico', true);
        }

        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo_interno', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderBy('nombre')->limit($limit)->get()->map(function (Producto $p) {
            $unidad = null;
            if ($p->unidad_medida) {
                $unidad = $p->unidad_medida->clave ?: $p->unidad_medida->nombre;
            }

            return [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'codigo' => $p->codigo_interno ?: $p->sku,
                'unidad' => $unidad,
                'precio_base' => $p->precio_base !== null ? (float) $p->precio_base : null,
                'stock' => $p->stock !== null ? (float) $p->stock : null,
                'mostrar_en_catalogo_publico' => (bool) $p->mostrar_en_catalogo_publico,
                'imagen_url' => PublicStorageUrl::make($p->imagen_principal),
            ];
        });

        return $this->success($rows, 'Productos disponibles para la cotización.');
    }

    private function pertenece(Proveedor $proveedor, SolicitudCotizacion $solicitud): bool
    {
        return (int) $solicitud->proveedor_id === (int) $proveedor->id;
    }
}
