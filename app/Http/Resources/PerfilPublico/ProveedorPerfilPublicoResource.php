<?php

namespace App\Http\Resources\PerfilPublico;

use App\Services\PerfilPublico\PerfilPublicoThemeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProveedorPerfilPublicoResource extends JsonResource
{
    /**
     * @param  array<string, mixed>|null  $optionsCatalog
     * @param  array<string, mixed>|null  $previewSnapshot
     */
    public function __construct(
        $resource,
        private readonly ?array $optionsCatalog = null,
        private readonly ?array $previewSnapshot = null,
        private readonly ?array $themesPayload = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $themeService = app(PerfilPublicoThemeService::class);
        $themeKey = $themeService->resolveThemeKey($this->theme_key);

        return [
            'id' => $this->id,
            'proveedor_id' => $this->proveedor_id,
            'token' => $this->token,
            'theme_key' => $themeKey,
            'is_published' => (bool) $this->is_published,
            'sections' => $this->sections ?? [],
            'snapshot' => $this->when(
                (bool) $this->is_published,
                $this->snapshot
            ),
            'preview' => $this->previewSnapshot,
            'options' => $this->optionsCatalog,
            'themes' => $this->themesPayload['themes'] ?? $themeService->getThemes(),
            'default_theme' => $this->themesPayload['default_theme']
                ?? $themeService->getDefaultThemeKey(),
            'published_at' => $this->published_at?->toIso8601String(),
            'public_path' => $this->token
                ? '/public/perfil/'.$this->token
                : null,
        ];
    }
}
