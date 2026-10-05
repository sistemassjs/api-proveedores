<?php

namespace App\Http\Controllers\Cotizacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cotizacion\StoreSolicitudCotizacionPublicaRequest;
use App\Http\Resources\Cotizacion\CotizadorProductoSugerenciaResource;
use App\Http\Resources\Cotizacion\SolicitudCotizacionResource;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\Cotizacion\SolicitudCotizacionService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cotizador público: listado de empresas + sugerencias por término + alta de solicitud.
 */
class CotizadorPublicoController extends Controller
{
    public function __construct(
        private readonly SolicitudCotizacionService $service
    ) {}

    public function empresas(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $query = Proveedor::query()
            ->where('is_proveedor_catalogo', true)
            ->whereHas('productos', function ($q) {
                $q->where('activo', true)
                    ->where('mostrar_en_catalogo_publico', true);
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('nombre_comercial', 'like', "%{$search}%");
            });
        }

        $empresas = $query
            ->withCount([
                'productos as total_productos' => function ($q) {
                    $q->where('activo', true)
                        ->where('mostrar_en_catalogo_publico', true);
                },
            ])
            ->orderByRaw('COALESCE(NULLIF(nombre_comercial, ""), razon_social)')
            ->get()
            ->map(function (Proveedor $proveedor) {
                $razonSocial = trim((string) ($proveedor->razon_social ?? ''));
                $nombreComercial = trim((string) ($proveedor->nombre_comercial ?? ''));
                $empresa = $nombreComercial !== ''
                    ? $nombreComercial
                    : ($razonSocial !== '' ? $razonSocial : ('Proveedor #'.$proveedor->id));

                return [
                    'proveedor_id' => (int) $proveedor->id,
                    'empresa' => $empresa,
                    'razon_social' => $razonSocial !== '' ? $razonSocial : null,
                    'nombre_comercial' => $nombreComercial !== '' ? $nombreComercial : null,
                    'logo' => PublicStorageUrl::make($proveedor->logo),
                    'total_productos' => (int) $proveedor->total_productos,
                ];
            })
            ->values()
            ->all();

        return $this->success($empresas, 'Empresas para cotizador.');
    }

    public function sugerencias(Request $request, Proveedor $proveedor): JsonResponse
    {
        if (! $proveedor->is_proveedor_catalogo) {
            return $this->error('La empresa no participa en el catálogo público.', null, 404);
        }

        $q = trim((string) $request->input('q', $request->input('search', '')));
        if (mb_strlen($q) < 2) {
            return $this->success([], 'Escriba al menos 2 caracteres.');
        }

        $limit = max(1, min((int) $request->input('limit', 15), 30));

        $productos = Producto::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('activo', true)
            ->where('mostrar_en_catalogo_publico', true)
            ->with('unidad_medida')
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('codigo_interno', 'like', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit($limit)
            ->get();

        return $this->success(
            CotizadorProductoSugerenciaResource::collection($productos),
            'Sugerencias de productos.'
        );
    }

    public function store(StoreSolicitudCotizacionPublicaRequest $request, Proveedor $proveedor): JsonResponse
    {
        if (! $proveedor->is_proveedor_catalogo) {
            return $this->error('La empresa no participa en el catálogo público.', null, 404);
        }

        try {
            $solicitud = $this->service->crearDesdePublico($proveedor, $request->validated());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('Uno o más productos no pertenecen a la empresa o no son públicos.', null, 422);
        } catch (\Throwable $e) {
            return $this->error('No se pudo crear la solicitud de cotización.', $e->getMessage(), 500);
        }

        return $this->success(
            new SolicitudCotizacionResource($solicitud),
            'Solicitud de cotización enviada correctamente.',
            201
        );
    }
}
