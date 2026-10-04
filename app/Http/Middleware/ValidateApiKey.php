<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Api-Key') ?? $request->query('api_key');
        $validKey = config('services.leads_webhook.key', env('LEADS_WEBHOOK_KEY'));

        if (empty($validKey) || $apiKey !== $validKey) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso no autorizado. API Key no válida o ausente.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}