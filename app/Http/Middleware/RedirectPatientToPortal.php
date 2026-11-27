<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectPatientToPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isPatient') && $user->isPatient()) {
            if (! $request->routeIs('patient.portal') && ! $request->is('mi-expediente*')) {
                return redirect()->route('patient.portal');
            }
        }

        return $next($request);
    }
}