<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProveedorPublicResource;
use App\Models\Proveedor;
use App\Models\ProveedorPerfilPublico;
use App\Services\PerfilPublico\PerfilPublicoThemeService;
use App\Support\PublicStorageBase64;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProveedorPublicController extends Controller
{
  use ApiResponse;

  /**
   * Perfil público de empresa por token (sin autenticación).
   */
  public function perfilPublico(string $token, PerfilPublicoThemeService $themeService): JsonResponse
  {
    $perfil = ProveedorPerfilPublico::query()
      ->with(['proveedor:id,logo'])
      ->where('token', $token)
      ->where('is_published', true)
      ->first();

    if (! $perfil || ! is_array($perfil->snapshot)) {
      return $this->error(
        'Perfil no disponible o el enlace ya no es válido.',
        null,
        404
      );
    }

    $themeKey = $themeService->resolveThemeKey($perfil->theme_key);
    $theme = $themeService->getTheme($themeKey);
    $snapshot = $this->enrichSnapshotLogoBase64($perfil->snapshot, $perfil->proveedor?->logo);

    return $this->success([
      'token' => $perfil->token,
      'theme_key' => $themeKey,
      'theme' => $theme,
      'snapshot' => $snapshot,
      'published_at' => $perfil->published_at?->toIso8601String(),
    ], 'Perfil público disponible.');
  }

  /**
   * Sustituye empresa.logo por data URI para que el front público no dependa de /storage.
   * Solo actúa si el snapshot ya incluye logo (campo publicado).
   *
   * @param  array<string, mixed>  $snapshot
   * @return array<string, mixed>
   */
  private function enrichSnapshotLogoBase64(array $snapshot, ?string $proveedorLogoPath): array
  {
    if (! isset($snapshot['empresa']) || ! is_array($snapshot['empresa'])) {
      return $snapshot;
    }

    $snapshotLogo = $snapshot['empresa']['logo'] ?? null;
    if (! is_string($snapshotLogo) || trim($snapshotLogo) === '') {
      return $snapshot;
    }

    // Preferir path del proveedor (más fiable); fallback al valor congelado en snapshot.
    $base64 = PublicStorageBase64::make($proveedorLogoPath)
      ?? PublicStorageBase64::make($snapshotLogo);

    if ($base64 !== null) {
      $snapshot['empresa']['logo'] = $base64;
    }

    return $snapshot;
  }

  /**
   * Ruta pública para compartir constancia fiscal del proveedor.
   */
  public function compartirConstancia(int $id): JsonResponse
  {
    $proveedor = Proveedor::select([
      'id',
      'logo',
      'nombre_comercial',
      'email',
      'telefono',
      'direccion_empresa',
      'constancia_fiscal',
    ])
      ->whereNotNull('constancia_fiscal')
      ->find($id);

    // ❌ No existe o no tiene constancia
    if (!$proveedor) {
      return $this->error(
        'Proveedor no disponible.',
        null,
        404
      );
    }

    // ✅ Proveedor válido con constancia (usa Resource para URLs completas)
    return $this->success(
      new ProveedorPublicResource($proveedor),
      'Proveedor disponible.',
      200
    );
  }
}
