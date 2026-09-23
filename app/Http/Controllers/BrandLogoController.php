<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve logos de marca desde public/assets/logos.
 * Necesario en producción donde Apache solo proxea /gestion/api (no /gestion/assets).
 */
class BrandLogoController extends Controller
{
    public function __invoke(string $file): BinaryFileResponse
    {
        if (! preg_match('/^logo-[a-z0-9_-]+\.(png|webp|jpe?g|svg)$/i', $file)) {
            abort(404);
        }

        $path = public_path('assets/logos/'.$file);

        if (! is_readable($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => mime_content_type($path) ?: 'image/png',
        ]);
    }
}
