<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'recaptcha' => [
        'secret' => env('RECAPTCHA_SECRET_KEY'),
        'site' => env('RECAPTCHA_SITE_KEY'),
    ],
    'frontend' => [
        'url' => env('APP_FRONTEND_URL', 'http://localhost:4300'), // Valor por defecto
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth / Socialite (login social PWA)
    |--------------------------------------------------------------------------
    | Providers habilitados en oauth.providers. Credenciales por driver abajo.
    | Redirect URI debe incluir el prefijo /api (rutas api.php).
    */
    'oauth' => [
        'providers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OAUTH_PROVIDERS', 'google'))
        ))),
        'frontend_callback' => env(
            'OAUTH_FRONTEND_CALLBACK',
            rtrim((string) env('APP_FRONTEND_URL', 'http://localhost:4300'), '/') . '/auth/callback'
        ),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env(
            'GOOGLE_REDIRECT_URI',
            rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/api/auth/google/callback'
        ),
    ],

    /**
     * CONFIGURACION PARA INTERCOMUNICACION CON API_CONSTRUCC
     */
    'api_construcciones' => [
        'url' => env('API_CONSTRUCCIONES_URL', 'http://localhost:8091'),
        'apikey' => env('API_CONSTRUCCIONES_APIKEY'),
    ],

    /**
     * FIREBASE CLOUD MESSAGING (FCM) - PUSH NOTIFICATIONS
     * Nota: FIREBASE_CREDENTIALS debe ser relativa a storage_path()
     * Ejemplo en .env: FIREBASE_CREDENTIALS=app/firebase/service-account.json
     */
    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS', 'app/firebase/service-account.json'),
        'project_id' => env('FCM_PROJECT_ID', 'app-proveedores-notificacion'),
        'sender_id' => env('FCM_SENDER_ID', '989092385974'),
    ],
];
