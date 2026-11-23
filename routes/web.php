<?php

use App\Http\Controllers\ProtocolPdfController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/admin-nfc');
});

Route::middleware(['auth', 'throttle:30,1'])
    ->prefix('admin-nfc')
    ->group(function (): void {
        Route::get('/protocols/{protocol}/pdf', ProtocolPdfController::class)
            ->whereNumber('protocol')
            ->name('admin.protocols.pdf');
    });

Route::get('/admin-nfc-setup', function () {
    if (app()->isProduction()) {
        abort(403, 'Acceso denegado. Esta herramienta de reconstrucción está deshabilitada en entorno de producción.');
    }

    $requiredToken = (string) config('app.setup_token', '');
    $providedToken = (string) request()->query('token', '');

    if ($requiredToken === '' || $providedToken === '' || ! hash_equals($requiredToken, $providedToken)) {
        return response()->json([
            'success' => false,
            'message' => 'Acceso no autorizado. Token de desarrollo no válido o ausente (?token=...).',
        ], 403);
    }

    try {
        Artisan::call('optimize:clear');

        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }

        Artisan::call('migrate:fresh', [
            '--seed'  => true,
            '--force' => true,
        ]);

        return response()->json([
            'success'     => true,
            'message'     => '¡Arquitectura sincronizada! Base de datos construida y poblada con éxito.',
            'environment' => app()->environment(),
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'success' => false,
            'message' => 'Error durante el setup de la base de datos.',
        ], 500);
    }
});