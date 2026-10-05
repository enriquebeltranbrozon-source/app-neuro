<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración blindada para la captura de formularios desde landing pages
    | estáticas y subdominios hacia la API de Laravel.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        'https://neurofeedback.mx',
        'https://www.neurofeedback.mx',
        // Entornos de desarrollo local (Astro / Vite / Next)
        'http://localhost:4321',
        'http://127.0.0.1:4321',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        env('FRONTEND_URL'),
    ]),

    // Expresión regular estricta para el dominio principal y CUALQUIER subdominio HTTPS
    'allowed_origins_patterns' => [
        '#^https://.*\.neurofeedback\.mx$#',
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'X-XSRF-TOKEN',
        'X-App-Key',
    ],

    'exposed_headers' => ['X-XSRF-TOKEN'],

    // Caché de peticiones Preflight (OPTIONS): 24 horas (86400 segundos)
    'max_age' => 86400,

    'supports_credentials' => true,

];