<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apps cliente (GestionPlus / NexProv)
    |--------------------------------------------------------------------------
    | Misma API. El origen se identifica con el header X-Client-App.
    | Si no viene (o es inválido) → gestion (compatibilidad GestionPlus).
    | Google OAuth: query/state `app` → grant + redirect a frontend_url de esa app.
    |
    | mail.* = colores de plantillas auth (header / CTA). Mismas vistas, tema por app.
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
                'header' => '#2b6cb0',
                'header_end' => '#1d4e89',
                'cta' => '#FFC107',
                'cta_end' => '#FFD54F',
                'cta_text' => '#000000',
                'cta_shadow' => 'rgba(255, 193, 7, 0.4)',
                'accent' => '#FFC107',
                'link' => '#93c5fd',
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
    ],
];
