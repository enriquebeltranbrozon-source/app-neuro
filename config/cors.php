<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración optimizada y blindada para la comunicación entre
    | las landings de Astro / Sitio Principal y la API de Laravel.
    |
    */

    // Rutas públicas y endpoints de la API expuestos a CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Métodos HTTP necesarios para la operación de la API
    'allowed_methods' => ['POST', 'GET', 'OPTIONS', 'PUT', 'DELETE'],

    // Orígenes autorizados (Limpia duplicados y permite inyección desde .env)
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
    env('CORS_ALLOWED_ORIGINS') ? explode(',', env('CORS_ALLOWED_ORIGINS')) : [],
        [
            env('FRONTEND_URL', 'http://127.0.0.1:4321'),
            'http://localhost:4321',
            'http://127.0.0.1:4321',
            'http://localhost:3000',
            'https://neurofeedback.mx',
            'https://landings.neurofeedback.mx',
            'https://www.neurofeedback.mx',
        ]
    )))),

    'allowed_origins_patterns' => [
        // Permite previsualizaciones dinámicas en subdominios si es necesario
        // 'https://*.neurofeedback.mx',
    ],

    // Cabeceras permitidas en las peticiones Fetch/AXIOS
    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'X-Session-Token',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [],

    // Caché de preflight OPTIONS (86400 segundos = 24 hrs)
    // Reduce la latencia al evitar peticiones OPTIONS repetitivas
    'max_age' => 86400,

    // Mantiene true si se utilizan cookies de sesión / tokens Sanctum entre subdominios
    'supports_credentials' => true,

];