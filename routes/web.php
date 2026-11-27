<?php

use App\Http\Controllers\PatientPortalController;
use App\Http\Controllers\ProtocolPdfController;
use Illuminate\Http\Request;
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

Route::middleware(['auth', 'throttle:60,1'])
    ->get('/mi-expediente', [PatientPortalController::class, 'index'])
    ->name('patient.portal');

Route::middleware(['auth', 'throttle:30,1'])
    ->prefix('admin-nfc')
    ->group(function (): void {
        Route::get('/protocols/{protocol}/pdf', ProtocolPdfController::class)
            ->whereNumber('protocol')
            ->name('admin.protocols.pdf');

Route::middleware(['guest', 'throttle:20,1'])
        ->get('/acceso-paciente', [PatientAuthController::class, 'showLoginForm'])
        ->name('patient.login');

Route::middleware(['guest', 'throttle:10,1'])
        ->post('/acceso-paciente', [PatientAuthController::class, 'login'])
        ->name('patient.login.submit');

    Route::middleware(['auth', 'throttle:30,1'])
        ->post('/cerrar-sesion-paciente', [PatientAuthController::class, 'logout'])
        ->name('patient.logout');
});

Route::post('/admin-nfc-setup', function (Request $request) {
    if (! app()->environment(['local', 'development'])) {
        abort(403, 'Acceso denegado. Esta herramienta solo está disponible en entornos de desarrollo.');
    }

    $requiredToken = (string) config('app.setup_token', '');
    $providedToken = (string) ($request->header('X-Setup-Token') ?? $request->query('token', ''));

    if ($requiredToken === '' || $providedToken === '' || ! hash_equals($requiredToken, $providedToken)) {
        return response()->json([
            'success' => false,
            'message' => 'Acceso no autorizado.',
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
            'message'     => 'Arquitectura sincronizada.',
            'environment' => app()->environment(),
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'success' => false,
            'message' => 'Error durante la sincronización de la base de datos.',
        ], 500);
    }
})->middleware('throttle:5,1');