<?php

use App\Http\Controllers\Api\LeadIngestionController;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rate Limiters (Control de Tráfico y Anti-Spam)
|--------------------------------------------------------------------------
*/

// Límite estricto para formularios web y clics de WhatsApp
RateLimiter::for('lead-submission', function (Request $request) {
    return Limit::perMinute(5)
        ->by($request->ip())
        ->response(function (Request $request, array $headers) {
            return response()->json([
                'success' => false,
                'message' => 'Ha superado el límite de intentos permitidos. Por favor, reintente en un minuto.',
            ], 429, $headers);
        });
});

// Límite para llamadas de webhook externas
RateLimiter::for('webhook-limit', function (Request $request) {
    return Limit::perMinute(60)
        ->by($request->ip())
        ->response(function (Request $request, array $headers) {
            return response()->json([
                'success' => false,
                'message' => 'Límite de peticiones excedido para la API Key proporcionada.',
            ], 429, $headers);
        });
});

/*
|--------------------------------------------------------------------------
| Rutas de Captura Directa (/api/leads y /api/leads/whatsapp-click)
|--------------------------------------------------------------------------
*/

Route::middleware(['throttle:lead-submission'])->group(function () {

    // Recepción de formularios desde Landings
    Route::post('/leads', [LeadIngestionController::class, 'store'])
        ->name('api.leads.store');

    // Registro transparente al presionar botón de WhatsApp
    Route::post('/leads/whatsapp-click', [LeadIngestionController::class, 'registerWhatsappClick'])
        ->name('api.leads.whatsapp-click');
});

/*
|--------------------------------------------------------------------------
| API Routes - Versión 1 (/api/v1/...)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // 1. ENDPOINTS PÚBLICOS DE CAPTURA (Formulario Web / Astro)
    Route::middleware(['throttle:lead-submission'])->group(function () {

        Route::post('/leads', [LeadIngestionController::class, 'store'])
            ->name('api.v1.leads.store');

        Route::post('/leads/whatsapp-click', [LeadIngestionController::class, 'registerWhatsappClick'])
            ->name('api.v1.leads.whatsapp-click');

        // Alias de compatibilidad
        Route::post('/leads/whatsapp-token', [LeadIngestionController::class, 'registerWhatsappClick'])
            ->name('api.v1.leads.whatsapp-token');
    });

    // 2. ENDPOINT DE INGESTA EXTERNA / WEBHOOKS (Protegido con API Key + Rate Limit)
    Route::middleware(['throttle:webhook-limit', ValidateApiKey::class])->group(function () {

        Route::post('/leads/webhook', [LeadIngestionController::class, 'store'])
            ->name('api.v1.leads.webhook');
    });

    // Fallback específico de la versión v1
    Route::fallback(function () {
        return response()->json([
            'success' => false,
            'message' => 'El endpoint v1 solicitado no existe o no se encuentra disponible.',
        ], 404);
    });

});

/*
|--------------------------------------------------------------------------
| Fallback Global de la API
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'El recurso o endpoint de API solicitado no existe.',
    ], 404);
});