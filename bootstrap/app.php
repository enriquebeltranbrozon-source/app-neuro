<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // 1. FIX: Carga explícita de rutas API (Resuelve el 404)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 2. Confiar en encabezados de proxy (Cloudflare + Nginx/aaPanel)
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO |
                     Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // 3. Excepciones CSRF para endpoints API y Livewire
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'livewire/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 4. BLINDAJE DE EXCEPCIONES: Forzar respuestas JSON en la API
        // Previene fugas de HTML/Stack traces crudos si un endpoint API falla
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();