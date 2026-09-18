<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apps cliente (GestionPlus / NexProv)
    |--------------------------------------------------------------------------
    | Una sola API. El front identifica la app con el header X-Client-App
    | (o ?app= en redirects OAuth). Si no viene, se asume gestion.
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
