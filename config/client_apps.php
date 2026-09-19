<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apps cliente (GestionPlus / NexProv)
    |--------------------------------------------------------------------------
    | Misma API. El origen se identifica con el header X-Client-App.
    | Si no viene (o es inválido) → gestion (compatibilidad GestionPlus).
    | Google OAuth multi-app: pendiente.
    */

    'header' => 'X-Client-App',

    'default' => 'gestion',

    'apps' => [
        'gestion' => [
            'name' => env('CLIENT_APP_GESTION_NAME', 'GestionPlus'),
            'frontend_url' => env('APP_FRONTEND_URL', 'http://localhost:4300'),
            'logo' => 'assets/logos/logo-gestionplus.png',
        ],
        'nexprov' => [
            'name' => env('CLIENT_APP_NEXPROV_NAME', 'NexProv'),
            'frontend_url' => env('NEXPROV_FRONTEND_URL', 'http://localhost:4400'),
            'logo' => 'assets/logos/logo-nexprov.png',
        ],
    ],
];
