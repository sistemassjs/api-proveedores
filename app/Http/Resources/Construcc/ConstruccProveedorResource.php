<?php

namespace App\Http\Resources\Construcc;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ConstruccProveedorResource extends JsonResource
{
    /**
     * Convierte un string a mayúsculas (UTF-8). Null o vacío se devuelven tal cual.
     */
    private static function upper(?string $value): ?string
    {
        return $value !== null && $value !== '' ? Str::upper($value) : $value;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_comercial' => self::upper($this->nombre_comercial),
            'razon_social' => self::upper($this->razon_social),
            'rfc' => $this->rfc,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'pagina_web' => $this->pagina_web,

            // Información de contacto
            'contacto' => [
                'nombre' => self::upper($this->contacto_nombre),
                'cargo' => self::upper($this->contacto_cargo),
                'telefono' => $this->contacto_telefono,
                'correo' => $this->contacto_correo,
            ],

            // Información de ubicación
            'ubicacion' => [
                'estado' => self::upper($this->estado),
                'municipio' => self::upper($this->municipio),
                'codigo_postal' => $this->codigo_postal,
                'direccion_fiscal' => self::upper($this->direccion_fiscal),
                'direccion_empresa' => self::upper($this->direccion_empresa),
            ],

            // Logo optimizado
            'logo' => PublicStorageUrl::make($this->logo),

            // // Información empresarial
            // 'empresa' => [
            //     'tipo_persona' => $this->tipo_persona,
            //     'tipos_empresa_id' => $this->tipos_empresa_id,
            //     'tipos_empresa_otro' => $this->tipos_empresa_otro,
            //     'descripcion_giro' => $this->descripcion_giro_empresa,
            //     'nombre_propietario' => $this->nombre_propietario,
            // ],

            // Estado y clasificación
            // 'estatus' => $this->estatus,
            // 'principal' => $this->principal,
            // 'calificacion' => $this->calificacion,
            // 'categoria' => $this->categoria,

            // Estadísticas (solo cuando se cargan las relaciones)
            // 'estadisticas' => $this->when($this->relationLoaded('productos'), function () {
            //     return [
            //         'total_productos' => $this->productos_count ?? $this->productos->count(),
            //         'productos_activos' => $this->productos->where('activo', true)->count(),
            //         'productos_con_stock' => $this->productos->where('activo', true)->where('stock', '>', 0)->count(),
            //         'productos_destacados' => $this->productos->where('activo', true)->where('destacado', true)->count(),
            //     ];
            // }),

            // // Metadatos
            // 'created_at' => $this->created_at,
            // 'updated_at' => $this->updated_at,

            // Relaciones expandidas cuando se soliciten específicamente
            // 'categorias' => ConstruccCategoriaResource::collection($this->whenLoaded('categorias')),
            // 'marcas' => ConstruccMarcaResource::collection($this->whenLoaded('marcas')),
            // 'unidades' => ConstruccUnidadResource::collection($this->whenLoaded('unidades')),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource_type' => 'proveedor',
                'version' => '1.0',
            ],
        ];
    }
}
