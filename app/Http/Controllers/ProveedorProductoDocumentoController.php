<?php

namespace App\Http\Controllers;

use App\Exceptions\Api\Crud\ResourceNotFoundException;
use App\Http\Requests\Producto\StoreProductoDocumentoRequest;
use App\Http\Resources\Producto\ProductoDocumentoResource;
use App\Models\Producto;
use App\Models\ProductoDocumento;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProveedorProductoDocumentoController extends Controller
{
    public function index(Proveedor $proveedor, Producto $producto): JsonResponse
    {
        $this->assertProducto($proveedor, $producto);

        $docs = $producto->documentos()->orderBy('orden')->get();

        return $this->success(
            ProductoDocumentoResource::collection($docs),
            'Documentos del producto.'
        );
    }

    public function store(
        StoreProductoDocumentoRequest $request,
        Proveedor $proveedor,
        Producto $producto
    ): JsonResponse {
        $this->assertProducto($proveedor, $producto);

        $data = [
            'tipo' => $request->input('tipo', 'ficha_tecnica'),
            'nombre' => $request->input('nombre'),
            'orden' => (int) $request->input('orden', 0),
        ];

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $filename = "producto_{$producto->id}_doc_".time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('uploads/productos/documentos', $filename, 'public');
            $data['url'] = $path;
            $data['mime'] = $file->getMimeType();
            $data['nombre'] = $data['nombre'] ?: $file->getClientOriginalName();
        } else {
            $data['url'] = $request->input('url');
        }

        $doc = $producto->documentos()->create($data);

        return $this->success(new ProductoDocumentoResource($doc), 'Documento creado.', 201);
    }

    public function destroy(
        Proveedor $proveedor,
        Producto $producto,
        ProductoDocumento $documento
    ): JsonResponse {
        $this->assertProducto($proveedor, $producto);

        if ((int) $documento->producto_id !== (int) $producto->id) {
            throw new ResourceNotFoundException('El documento no pertenece al producto.');
        }

        if ($documento->url && ! preg_match('/^https?:\/\//', $documento->url)) {
            Storage::disk('public')->delete($documento->url);
        }

        $documento->delete();

        return $this->success(null, 'Documento eliminado.');
    }

    private function assertProducto(Proveedor $proveedor, Producto $producto): void
    {
        if ((int) $producto->proveedor_id !== (int) $proveedor->id) {
            throw new ResourceNotFoundException('Producto no relacionado al proveedor.');
        }
    }
}
