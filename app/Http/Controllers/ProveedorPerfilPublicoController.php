<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilPublico\UpdatePerfilPublicoRequest;
use App\Http\Resources\PerfilPublico\ProveedorPerfilPublicoResource;
use App\Models\Proveedor;
use App\Models\ProveedorPerfilPublico;
use App\Services\PerfilPublico\PerfilPublicoSnapshotBuilder;
use App\Services\PerfilPublico\PerfilPublicoThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedorPerfilPublicoController extends Controller
{
    public function __construct(
        private readonly PerfilPublicoThemeService $themeService,
        private readonly PerfilPublicoSnapshotBuilder $snapshotBuilder,
    ) {}

    public function show(Request $request, Proveedor $proveedor): JsonResponse
    {
        if ($denied = $this->denyIfNoAccess($request, $proveedor)) {
            return $denied;
        }

        $perfil = $this->findOrCreateDraft($proveedor);
        $preview = $this->snapshotBuilder->build(
            $proveedor,
            $perfil->sections ?? [],
            $this->themeService->resolveThemeKey($perfil->theme_key)
        );

        return $this->success(
            new ProveedorPerfilPublicoResource(
                $perfil,
                $this->snapshotBuilder->buildOptionsCatalog($proveedor),
                $preview,
                [
                    'themes' => $this->themeService->getThemes(),
                    'default_theme' => $this->themeService->getDefaultThemeKey(),
                ]
            ),
            'Perfil público obtenido correctamente.'
        );
    }

    public function update(UpdatePerfilPublicoRequest $request, Proveedor $proveedor): JsonResponse
    {
        if ($denied = $this->denyIfNoAccess($request, $proveedor)) {
            return $denied;
        }

        $perfil = $this->findOrCreateDraft($proveedor);
        $data = $request->validated();

        if (array_key_exists('theme_key', $data) && $data['theme_key'] !== null) {
            $perfil->theme_key = $this->themeService->resolveThemeKey($data['theme_key']);
        }

        if (array_key_exists('sections', $data) && is_array($data['sections'])) {
            $perfil->sections = $this->snapshotBuilder->mergeSections(
                array_merge($perfil->sections ?? [], $data['sections'])
            );
        }

        $perfil->save();

        $preview = $this->snapshotBuilder->build(
            $proveedor,
            $perfil->sections ?? [],
            $this->themeService->resolveThemeKey($perfil->theme_key)
        );

        return $this->success(
            new ProveedorPerfilPublicoResource(
                $perfil->fresh(),
                $this->snapshotBuilder->buildOptionsCatalog($proveedor),
                $preview,
                [
                    'themes' => $this->themeService->getThemes(),
                    'default_theme' => $this->themeService->getDefaultThemeKey(),
                ]
            ),
            'Configuración del perfil público guardada.'
        );
    }

    public function publicar(Request $request, Proveedor $proveedor): JsonResponse
    {
        if ($denied = $this->denyIfNoAccess($request, $proveedor)) {
            return $denied;
        }

        $perfil = $this->findOrCreateDraft($proveedor);
        $this->applyPublish($perfil, $proveedor);

        $preview = $this->snapshotBuilder->build(
            $proveedor,
            $perfil->sections ?? [],
            $this->themeService->resolveThemeKey($perfil->theme_key)
        );

        return $this->success(
            new ProveedorPerfilPublicoResource(
                $perfil->fresh(),
                $this->snapshotBuilder->buildOptionsCatalog($proveedor),
                $preview,
                [
                    'themes' => $this->themeService->getThemes(),
                    'default_theme' => $this->themeService->getDefaultThemeKey(),
                ]
            ),
            'Perfil público publicado. Cualquiera con el enlace podrá ver la información seleccionada.'
        );
    }

    public function despublicar(Request $request, Proveedor $proveedor): JsonResponse
    {
        if ($denied = $this->denyIfNoAccess($request, $proveedor)) {
            return $denied;
        }

        $perfil = $this->findOrCreateDraft($proveedor);
        $perfil->is_published = false;
        $perfil->snapshot = null;
        $perfil->published_at = null;
        $perfil->save();

        $preview = $this->snapshotBuilder->build(
            $proveedor,
            $perfil->sections ?? [],
            $this->themeService->resolveThemeKey($perfil->theme_key)
        );

        return $this->success(
            new ProveedorPerfilPublicoResource(
                $perfil->fresh(),
                $this->snapshotBuilder->buildOptionsCatalog($proveedor),
                $preview,
                [
                    'themes' => $this->themeService->getThemes(),
                    'default_theme' => $this->themeService->getDefaultThemeKey(),
                ]
            ),
            'El perfil público dejó de estar disponible. El enlace ya no mostrará información.'
        );
    }

    public function themes(): JsonResponse
    {
        return $this->success([
            'themes' => $this->themeService->getThemes(),
            'default_theme' => $this->themeService->getDefaultThemeKey(),
        ], 'Temas de perfil público obtenidos correctamente.');
    }

    private function applyPublish(ProveedorPerfilPublico $perfil, Proveedor $proveedor): void
    {
        $themeKey = $this->themeService->resolveThemeKey($perfil->theme_key);
        $perfil->theme_key = $themeKey;
        $perfil->sections = $this->snapshotBuilder->mergeSections($perfil->sections ?? []);
        $perfil->snapshot = $this->snapshotBuilder->build(
            $proveedor,
            $perfil->sections,
            $themeKey
        );
        $perfil->is_published = true;
        $perfil->published_at = now();
        $perfil->save();
        $perfil->asegurarToken();
    }

    private function findOrCreateDraft(Proveedor $proveedor): ProveedorPerfilPublico
    {
        return ProveedorPerfilPublico::query()->firstOrCreate(
            ['proveedor_id' => $proveedor->id],
            [
                'theme_key' => $this->themeService->getDefaultThemeKey(),
                'is_published' => false,
                'sections' => PerfilPublicoSnapshotBuilder::defaultSections(),
            ]
        );
    }

    private function denyIfNoAccess(Request $request, Proveedor $proveedor): ?JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->tieneAccesoAProveedor((int) $proveedor->id)) {
            return $this->error(
                'El usuario autenticado no tiene acceso al proveedor indicado.',
                null,
                403
            );
        }

        return null;
    }
}
