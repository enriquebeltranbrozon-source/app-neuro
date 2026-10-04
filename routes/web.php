<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1. Redirección de la ruta raíz al panel administrativo
Route::get('/', function () {
    return redirect('/admin-nfc');
});

// 2. Ruta de Setup / Inicialización de Desarrollo (Blindada)
Route::get('/admin-nfc-setup', function () {
    // BLINDAJE 1: Bloqueo estricto en entorno de Producción
    if (app()->isProduction()) {
        abort(403, 'Acceso denegado. Esta herramienta de reconstrucción está deshabilitada en entorno de producción.');
    }

    // BLINDAJE 2: Validación de Token Secreto por URL (?token=...)
    $requiredToken = config('app.setup_token', env('SETUP_SECRET_TOKEN', 'nfc_dev_setup_2026'));
    
    if (request('token') !== $requiredToken) {
        return response()->json([
            'success' => false,
            'message' => 'Acceso no autorizado. Token de desarrollo no válido o ausente (?token=...).',
        ], 403);
    }

    try {
        // 1. Limpieza total de cachés de configuración y rutas
        Artisan::call('optimize:clear');

        // 2. Creación del enlace simbólico a storage (solo si no existe)
        if (!file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }

        // 3. Ejecución segura de migraciones y seeders
        Artisan::call('migrate:fresh', [
            '--seed'  => true,
            '--force' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => '¡Arquitectura sincronizada! Base de datos construida y poblada con éxito.',
            'environment' => app()->environment(),
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error durante el setup de la base de datos.',
            'error'   => $e->getMessage(),
        ], 500);
    }
});