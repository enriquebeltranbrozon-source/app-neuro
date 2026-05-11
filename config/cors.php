<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    // Mantenemos las rutas de la API
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Permitimos todos los métodos (GET, POST, PUT, DELETE, OPTIONS)
    'allowed_methods' => ['*'],

    // Ajuste clave: Permitimos los dos orígenes locales comunes.
    // Si sigue fallando, puedes poner temporalmente ['*'] para debuguear.
    'allowed_origins' => [
    env('FRONTEND_URL', 'http://127.0.0.1:4321'),
    'http://localhost:4321', 
],

    'allowed_origins_patterns' => [],

    // Permitimos todos los headers (Content-Type, X-Requested-With, etc.)
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Cambiamos a true por si en el futuro manejas sesiones o cookies con Sanctum
    'supports_credentials' => true,

];