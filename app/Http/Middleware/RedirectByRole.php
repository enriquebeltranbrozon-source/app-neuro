<?php

namespace App\Http\Middleware;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Pages\EegTechnicianDashboard;
use App\Filament\Admin\Pages\TherapistDashboard;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectByRole
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        // Solo redirigimos cuando entran exactamente a la raíz del panel (/admin-nfc)
        if ($request->path() !== 'admin-nfc') {
            return $next($request);
        }

        if ($user->isAdmin() || $user->isZoneManager()) {
            return $next($request);
        }

        if ($user->isZoneManager()) {
                return redirect()->to(ZoneManagerDashboard::getUrl(panel: 'admin-nfc'));
            }

        if ($user->isEegTechnician()) {
            return redirect()->to(EegTechnicianDashboard::getUrl(panel: 'admin-nfc'));
        }

        if ($user->isTherapist()) {
            return redirect()->to(TherapistDashboard::getUrl(panel: 'admin-nfc'));
        }

        return $next($request);
    }
}