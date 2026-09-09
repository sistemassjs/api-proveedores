<?php

namespace App\Http\Resources;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ProveedorResource extends JsonResource
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
     */
    public function toArray(Request $request): array
    {
        return [

            /* =========================
             * Identificación
             * ========================= */
            'id' => $this->id,
            'nombre' => self::upper($this->nombre_comercial),
            'razon_social' => self::upper($this->razon_social),
            'nombre_comercial' => self::upper($this->nombre_comercial),
            'rfc' => $this->rfc,

            /* =========================
             * Contacto general
             * ========================= */
            'telefono_codigo_pais' => $this->telefono_codigo_pais,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'pagina_web' => $this->pagina_web,

            /* =========================
             * Archivos
             * ========================= */
            'logo' => PublicStorageUrl::make($this->logo),

            'constancia_fiscal' => PublicStorageUrl::make($this->constancia_fiscal),

            /* =========================
             * Datos fiscales
             * ========================= */
            'tipo_persona' => $this->tipo_persona,
            'regimen_fiscal_clave' => $this->regimen_fiscal_clave,
            'regimen_fiscal_nombre' => self::upper($this->regimen_fiscal_nombre),
            'regimenes_fiscales' => (function () {
                if (! $this->relationLoaded('regimenesFiscales')) {
                    $this->resource->loadMissing('regimenesFiscales');
                }

                return $this->regimenesFiscales
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'clave' => $r->clave,
                        // Sin upper: el front necesita coincidir con el catálogo del select
                        'nombre' => $r->nombre,
                        'fecha_alta' => optional($r->fecha_alta)?->format('Y-m-d'),
                        'fecha_fin' => optional($r->fecha_fin)?->format('Y-m-d'),
                        'es_principal' => (bool) $r->es_principal,
                        'origen' => $r->origen,
                    ])
                    ->values();
            })(),

            'direccion' => self::upper($this->direccion_empresa),

            'direccion_fiscal' => [
                'calle' => self::upper($this->calle),
                'numero_exterior' => $this->numero_exterior,
                'numero_interior' => $this->numero_interior,
                'colonia' => self::upper($this->colonia),
                'ciudad' => self::upper($this->ciudad),
                'estado' => self::upper($this->estado),
                'codigo_postal' => $this->codigo_postal,
                'pais' => self::upper($this->pais),
            ],

            /* =========================
             * Información de la empresa
             * ========================= */
            'tipos_empresa_id' => $this->tipos_empresa_id,
            'tipos_empresa_otro' => self::upper($this->tipos_empresa_otro),
            'descripcion_giro_empresa' => self::upper($this->descripcion_giro_empresa),
            'categoria' => $this->categoria ?? null,
            'ciudad' => self::upper($this->ciudad ?? null),

            /* =========================
             * Contacto principal
             * ========================= */
            'contacto_nombre' => self::upper($this->contacto_nombre),
            'contacto_cargo' => self::upper($this->contacto_cargo),
            'contacto_telefono' => $this->contacto_telefono,
            'contacto_correo' => $this->contacto_correo,

            /* =========================
             * Metadatos / estado
             * ========================= */
            'estatus' => $this->estatus,
            'notas' => $this->notas,
            'validado_por' => $this->validado_por,
            'nombre_propietario' => self::upper($this->nombre_propietario),
            'nombre_de_quien_registra' => self::upper($this->nombre_de_quien_registra),

            'principal' => $this->principal ?? null,
            'calificacion' => $this->calificacion ?? null,

            'is_proveedor_sp' => $this->is_proveedor_sp ?? null,
            'is_proveedor_catalogo' => $this->is_proveedor_catalogo ?? null,
            'es_cuenta_de_pruebas' => (bool) ($this->es_cuenta_de_pruebas ?? false),
            'perfil_empresa_completo' => $this->perfil_empresa_completo ?? null,
            'fecha_registro' => $this->fecha_registro?->toDateTimeString(),
            'registro_completado_at' => $this->registro_completado_at?->toDateTimeString(),
            'consecutivo_presupuesto_siguiente' => $this->consecutivo_presupuesto_siguiente,

            'tipo_alta' => $this->tipo_alta !== null ? (int) $this->tipo_alta : 1,
            'oauth_providers' => $this->resolveOwnerOauthProviders(),
            'auth_google' => in_array('google', $this->resolveOwnerOauthProviders(), true),

            'alta_construcc' => $this->when((int) ($this->tipo_alta ?? 1) === 2, function () {
                $empresaAlta = $this->relationLoaded('empresaConstruccAlta') ? $this->empresaConstruccAlta : null;

                return [
                    'user_construcc_alta' => $this->user_construcc_alta,
                    'user_construcc_nombre' => $this->nombreUsuarioConstruccAlta(),
                    'empresa_construcc_alta' => $this->empresa_construcc_alta,
                    'empresa_construcc_nombre' => self::upper($empresaAlta?->nombre),
                    'empresa_construcc_rfc' => $empresaAlta?->rfc,
                    'empresa_construcc_razon_social' => self::upper($empresaAlta?->razon_social),
                ];
            }),

            /* =========================
             * Relaciones
             * ========================= */
            'cuentas_bancarias' => $this->whenLoaded('cuentasBancarias', function () {
                return $this->cuentasBancarias->map(fn ($c) => [
                    'id' => $c->id,
                    'alias' => $c->alias,
                    'banco_nombre' => $c->banco_nombre,
                    'estatus' => $c->estatus,
                ]);
            }),

            'tipos_empresa' => $this->whenLoaded('tipos_empresa', function () {
                return [
                    'id' => $this->tipos_empresa->id,
                    'nombre' => self::upper($this->tipos_empresa->nombre),
                    'estatus' => $this->tipos_empresa->estatus,
                ];
            }),

            /* =========================
             * Timestamps
             * ========================= */
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Providers OAuth del usuario PRINCIPAL activo de la empresa.
     *
     * @return list<string>
     */
    private function resolveOwnerOauthProviders(): array
    {
        if ($this->relationLoaded('users')) {
            $owner = $this->users->first(function ($user) {
                $pivot = $user->pivot;

                return $pivot
                    && (bool) $pivot->activo
                    && ($pivot->tipo_relacion ?? null) === 'PRINCIPAL';
            }) ?? $this->users->first();

            if (! $owner) {
                return [];
            }

            if ($owner->relationLoaded('oauthAccounts')) {
                return $owner->oauthAccounts->pluck('provider')->unique()->values()->all();
            }

            return $owner->oauthAccounts()->pluck('provider')->unique()->values()->all();
        }

        $owner = $this->users()
            ->wherePivot('tipo_relacion', 'PRINCIPAL')
            ->wherePivot('activo', true)
            ->with('oauthAccounts')
            ->first();

        if (! $owner) {
            return [];
        }

        return $owner->oauthAccounts->pluck('provider')->unique()->values()->all();
    }
}
