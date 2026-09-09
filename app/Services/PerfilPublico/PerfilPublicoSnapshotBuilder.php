<?php

namespace App\Services\PerfilPublico;

use App\Enums\EstadoCuentaBancaria;
use App\Models\ConfigEmisorReceptorPresupuesto;
use App\Models\CuentaBancaria;
use App\Models\Proveedor;
use Illuminate\Support\Facades\Storage;

/**
 * Arma el snapshot público a partir de la selección del proveedor.
 */
final class PerfilPublicoSnapshotBuilder
{
    public const CONSTANCIA_PRINCIPAL_ID = 'principal';

    /**
     * Secciones por defecto del borrador (nada sensible activo).
     *
     * @return array<string, mixed>
     */
    public static function defaultSections(): array
    {
        return [
            'empresa' => [
                'enabled' => true,
                'fields' => [
                    'logo',
                    'razon_social',
                    'nombre_comercial',
                    'descripcion_giro_empresa',
                    'direccion_empresa',
                ],
            ],
            'contacto' => [
                'enabled' => true,
                'fields' => [
                    'email',
                    'telefono',
                    'celular',
                    'pagina_web',
                    'contacto_nombre',
                    'contacto_cargo',
                    'contacto_telefono',
                    'contacto_correo',
                ],
            ],
            'tarjetas' => [
                'enabled' => false,
                'ids' => [],
            ],
            'bancos' => [
                'enabled' => false,
                'ids' => [],
            ],
            'fiscal' => [
                'enabled' => false,
                'fields' => [
                    'rfc',
                    'tipo_persona',
                    'regimen_fiscal_nombre',
                    'regimen_fiscal_clave',
                    'direccion_fiscal',
                ],
                'include_constancias' => false,
                /** IDs virtuales; hoy solo "principal". Contrato preparado para N. */
                'constancia_ids' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return array<string, mixed>
     */
    public function build(Proveedor $proveedor, array $sections, string $themeKey): array
    {
        $merged = $this->mergeSections($sections);
        $snapshot = [
            'theme_key' => $themeKey,
            'published_label' => $proveedor->nombre_comercial
                ?: $proveedor->razon_social
                ?: 'Empresa',
        ];

        if (! empty($merged['empresa']['enabled'])) {
            $snapshot['empresa'] = $this->buildEmpresa($proveedor, $merged['empresa']['fields'] ?? []);
        }

        if (! empty($merged['contacto']['enabled'])) {
            $snapshot['contacto'] = $this->buildContacto($proveedor, $merged['contacto']['fields'] ?? []);
        }

        if (! empty($merged['tarjetas']['enabled'])) {
            $ids = array_map('intval', $merged['tarjetas']['ids'] ?? []);
            $snapshot['tarjetas'] = $this->buildTarjetas($proveedor, $ids);
        }

        if (! empty($merged['bancos']['enabled'])) {
            $ids = array_map('intval', $merged['bancos']['ids'] ?? []);
            $snapshot['bancos'] = $this->buildBancos($proveedor, $ids);
        }

        if (! empty($merged['fiscal']['enabled'])) {
            $snapshot['fiscal'] = $this->buildFiscal($proveedor, $merged['fiscal']);
        }

        $snapshot['invite'] = $this->buildInvite();

        return $snapshot;
    }

    /**
     * Catálogo de opciones editables (editor autenticado).
     *
     * @return array<string, mixed>
     */
    public function buildOptionsCatalog(Proveedor $proveedor): array
    {
        $tarjetas = ConfigEmisorReceptorPresupuesto::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('tipo', ConfigEmisorReceptorPresupuesto::TIPO_EMISOR)
            ->whereIn('estado', [
                ConfigEmisorReceptorPresupuesto::ESTADO_ACTIVO,
                ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT,
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (ConfigEmisorReceptorPresupuesto $t) => [
                'id' => $t->id,
                'nombre' => $t->nombreCompletoParaDocumento(),
                'puesto' => $t->puesto,
                'correo' => $t->correo,
                'telefono' => $t->telefono,
            ])
            ->values()
            ->all();

        $bancos = CuentaBancaria::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('estatus', EstadoCuentaBancaria::ACTIVA)
            ->orderByDesc('preferida')
            ->orderBy('id')
            ->get()
            ->map(fn (CuentaBancaria $c) => [
                'id' => $c->id,
                'alias' => $c->alias,
                'banco_nombre' => $c->banco_nombre,
                'preferida' => (bool) $c->preferida,
                'mask' => $this->maskCuenta($c),
            ])
            ->values()
            ->all();

        $constancias = [];
        if ($proveedor->constancia_fiscal) {
            $constancias[] = [
                'id' => self::CONSTANCIA_PRINCIPAL_ID,
                'etiqueta' => 'Constancia de Situación Fiscal',
                'tiene_archivo' => true,
            ];
        }

        return [
            'tarjetas' => $tarjetas,
            'bancos' => $bancos,
            'constancias' => $constancias,
            'empresa_fields' => [
                'logo',
                'razon_social',
                'nombre_comercial',
                'descripcion_giro_empresa',
                'direccion_empresa',
            ],
            'contacto_fields' => [
                'email',
                'telefono',
                'celular',
                'pagina_web',
                'contacto_nombre',
                'contacto_cargo',
                'contacto_telefono',
                'contacto_correo',
            ],
            'fiscal_fields' => [
                'rfc',
                'tipo_persona',
                'regimen_fiscal_nombre',
                'regimen_fiscal_clave',
                'direccion_fiscal',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return array<string, mixed>
     */
    public function mergeSections(array $sections): array
    {
        $defaults = self::defaultSections();

        foreach ($defaults as $key => $defaultSection) {
            if (! isset($sections[$key]) || ! is_array($sections[$key])) {
                continue;
            }
            $defaults[$key] = array_merge($defaultSection, $sections[$key]);
        }

        return $defaults;
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function buildEmpresa(Proveedor $proveedor, array $fields): array
    {
        $out = [];
        $map = [
            'logo' => fn () => $this->publicUrl($proveedor->logo),
            'razon_social' => fn () => $proveedor->razon_social,
            'nombre_comercial' => fn () => $proveedor->nombre_comercial,
            'descripcion_giro_empresa' => fn () => $proveedor->descripcion_giro_empresa,
            'direccion_empresa' => fn () => $proveedor->direccion_empresa,
        ];

        foreach ($fields as $field) {
            if (isset($map[$field])) {
                $out[$field] = $map[$field]();
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function buildContacto(Proveedor $proveedor, array $fields): array
    {
        $out = [];
        $map = [
            'email' => fn () => $proveedor->email,
            'telefono' => fn () => $proveedor->telefono,
            'celular' => fn () => $proveedor->celular,
            'pagina_web' => fn () => $proveedor->pagina_web,
            'contacto_nombre' => fn () => $proveedor->contacto_nombre,
            'contacto_cargo' => fn () => $proveedor->contacto_cargo,
            'contacto_telefono' => fn () => $proveedor->contacto_telefono,
            'contacto_correo' => fn () => $proveedor->contacto_correo,
        ];

        foreach ($fields as $field) {
            if (isset($map[$field])) {
                $out[$field] = $map[$field]();
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function buildTarjetas(Proveedor $proveedor, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return ConfigEmisorReceptorPresupuesto::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('tipo', ConfigEmisorReceptorPresupuesto::TIPO_EMISOR)
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (ConfigEmisorReceptorPresupuesto $t) => [
                'id' => $t->id,
                'nombre' => $t->nombreCompletoParaDocumento(),
                'puesto' => $t->puesto,
                'telefono' => $t->telefono,
                'correo' => $t->correo,
                'foto_perfil' => $this->publicUrl($t->foto_perfil),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function buildBancos(Proveedor $proveedor, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return CuentaBancaria::query()
            ->where('proveedor_id', $proveedor->id)
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (CuentaBancaria $c) => [
                'id' => $c->id,
                'alias' => $c->alias,
                'banco_nombre' => $c->banco_nombre,
                'titular_cuenta' => $c->titular_cuenta,
                'clabe' => $c->clabe,
                'cuenta' => $c->cuenta,
                'tarjeta' => $c->tarjeta,
                'referencia' => $c->referencia,
                'sucursal' => $c->sucursal,
                'swift' => $c->swift,
                'preferida' => (bool) $c->preferida,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $fiscalSection
     * @return array<string, mixed>
     */
    private function buildFiscal(Proveedor $proveedor, array $fiscalSection): array
    {
        $fields = $fiscalSection['fields'] ?? [];
        $out = [];
        $map = [
            'rfc' => fn () => $proveedor->rfc,
            'tipo_persona' => fn () => $proveedor->tipo_persona,
            'regimen_fiscal_nombre' => fn () => $proveedor->regimen_fiscal_nombre,
            'regimen_fiscal_clave' => fn () => $proveedor->regimen_fiscal_clave,
            'direccion_fiscal' => fn () => $proveedor->direccion_fiscal,
        ];

        foreach ($fields as $field) {
            if (isset($map[$field])) {
                $out[$field] = $map[$field]();
            }
        }

        $constancias = [];
        if (! empty($fiscalSection['include_constancias'])) {
            $ids = $fiscalSection['constancia_ids'] ?? [];
            if ($ids === [] || in_array(self::CONSTANCIA_PRINCIPAL_ID, $ids, true)) {
                if ($proveedor->constancia_fiscal) {
                    $constancias[] = [
                        'id' => self::CONSTANCIA_PRINCIPAL_ID,
                        'etiqueta' => 'Constancia de Situación Fiscal',
                        'pdf_url' => $this->publicUrl($proveedor->constancia_fiscal),
                    ];
                }
            }
        }
        $out['constancias'] = $constancias;

        return $out;
    }

    /**
     * @return array{headline: string, body: string, cta: string, path: string}
     */
    private function buildInvite(): array
    {
        $appName = config('app.frontend_name', 'GestionPro');

        return [
            'headline' => "Únete gratis a {$appName}",
            'body' => 'Crea y envía presupuestos formales, comparte datos de tu empresa y gestiona solicitudes de pago desde tu celular.',
            'cta' => 'Registrarme gratis',
            'path' => '/reg',
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    private function maskCuenta(CuentaBancaria $c): string
    {
        $raw = $c->clabe ?: $c->cuenta ?: $c->tarjeta ?: '';
        $raw = preg_replace('/\s+/', '', (string) $raw) ?: '';
        if (strlen($raw) <= 4) {
            return $raw !== '' ? $raw : '—';
        }

        return str_repeat('•', max(0, strlen($raw) - 4)).substr($raw, -4);
    }
}
