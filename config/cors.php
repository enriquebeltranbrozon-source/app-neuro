<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://neurofeedback.mx',
        'https://www.neurofeedback.mx',
        'http://localhost:4321', // Pruebas locales Astro/Vite
        'http://localhost:3000',
    ],

    // Expresión regular para autorizar neurofeedback.mx y TODOS sus subdominios
    'allowed_origins_patterns' => [
        '/^https:\/\/(.*\.)?neurofeedback\.mx$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];