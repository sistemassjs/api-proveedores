<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apps cliente y webs que envían el formulario de contacto.
    |--------------------------------------------------------------------------
    | Misma API. El origen se identifica con el header X-Client-App.
    | Si no viene (o es inválido) → gestion (compatibilidad GestionPlus).
    | Google OAuth: query/state `app` → grant + redirect a frontend_url de esa app.
    |
    | mail.* = colores de plantillas (header / CTA / acento). Mismas vistas, tema por app.
    */

    'header' => 'X-Client-App',

    'default' => 'gestion',

    'apps' => [
        'gestion' => [
            'name' => env('CLIENT_APP_GESTION_NAME', 'GestionPlus'),
            'frontend_url' => env('APP_FRONTEND_URL', 'http://localhost:4300'),
            'logo' => 'assets/logos/logo-gestionplus.png',
            'logo_url' => env('CLIENT_APP_GESTION_LOGO_URL'),
            'mail' => [
                'header' => '#006eff',
                'header_end' => '#0052d6',
                'cta' => '#006eff',
                'cta_end' => '#3d8fff',
                'cta_text' => '#ffffff',
                'cta_shadow' => 'rgba(0, 110, 255, 0.4)',
                'accent' => '#006eff',
                'link' => '#8ec5ff',
            ],
        ],
        'nexprov' => [
            'name' => env('CLIENT_APP_NEXPROV_NAME', 'NexProv'),
            'frontend_url' => env('NEXPROV_FRONTEND_URL', 'http://localhost:4400'),
            'logo' => 'assets/logos/logo-nexprov.png',
            'logo_url' => env('CLIENT_APP_NEXPROV_LOGO_URL'),
            'mail' => [
                'header' => '#00a878',
                'header_end' => '#008f66',
                'cta' => '#00a878',
                'cta_end' => '#1ab98a',
                'cta_text' => '#ffffff',
                'cta_shadow' => 'rgba(0, 168, 120, 0.35)',
                'accent' => '#00a878',
                'link' => '#1ab98a',
            ],
        ],
        'construcc' => [
            'name' => 'Construcc',
            'frontend_url' => env('CONSTRUCC_FRONTEND_URL', env('APP_FRONTEND_URL', 'http://localhost:4300')),
            'logo' => 'assets/logos/logo-construcc.png',
            'mail' => [
                'header' => '#111827',
                'header_end' => '#1f2937',
                'cta' => '#F5C500',
                'cta_end' => '#FBBF24',
                'cta_text' => '#111827',
                'cta_shadow' => 'rgba(245, 197, 0, 0.4)',
                'accent' => '#F5C500',
                'link' => '#F5C500',
            ],
        ],
        'industrias' => [
            'name' => 'Industrias SJS',
            'frontend_url' => env('INDUSTRIAS_FRONTEND_URL', env('APP_FRONTEND_URL', 'http://localhost:4300')),
            'logo' => 'assets/logos/logo-industrias.png',
            'mail' => [
                'header' => '#111111',
                'header_end' => '#1f2937',
                'cta' => '#dc2626',
                'cta_end' => '#b91c1c',
                'cta_text' => '#ffffff',
                'cta_shadow' => 'rgba(220, 38, 38, 0.35)',
                'accent' => '#dc2626',
                'link' => '#f87171',
            ],
        ],
        'inmobiliaria' => [
            'name' => 'Inmobiliaria SJS',
            'frontend_url' => env('INMOBILIARIA_FRONTEND_URL', env('APP_FRONTEND_URL', 'http://localhost:4300')),
            'logo' => 'assets/logos/logo-inmobiliaria.png',
            'mail' => [
                'header' => '#dc2626',
                'header_end' => '#991b1b',
                'cta' => '#dc2626',
                'cta_end' => '#ef4444',
                'cta_text' => '#ffffff',
                'cta_shadow' => 'rgba(220, 38, 38, 0.35)',
                'accent' => '#dc2626',
                'link' => '#fecaca',
            ],
        ],
        'sjsconstrucciones' => [
            'name' => 'SJS Construcciones',
            'frontend_url' => env('SJS_CONSTRUCCIONES_FRONTEND_URL', env('APP_FRONTEND_URL', 'http://localhost:4300')),
            'logo' => 'assets/logos/logo-sjsconstrucciones.png',
            'mail' => [
                'header' => '#D71920',
                'header_end' => '#9b1218',
                'cta' => '#D71920',
                'cta_end' => '#ef4444',
                'cta_text' => '#ffffff',
                'cta_shadow' => 'rgba(215, 25, 32, 0.35)',
                'accent' => '#D71920',
                'link' => '#fecaca',
            ],
        ],
        'colibri' => [
            'name' => 'Purificadora Colibrí',
            'frontend_url' => env('COLIBRI_FRONTEND_URL', env('APP_FRONTEND_URL', 'http://localhost:4300')),
            'logo' => 'assets/logos/logo-colibri.png',
            'mail' => [
                'header' => '#2563eb',
                'header_end' => '#1e40af',
                'cta' => '#2563eb',
                'cta_end' => '#3b82f6',
                'cta_text' => '#ffffff',
                'cta_shadow' => 'rgba(37, 99, 235, 0.35)',
                'accent' => '#2563eb',
                'link' => '#93c5fd',
            ],
        ],
    ],
];
