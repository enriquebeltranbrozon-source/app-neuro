<?php

namespace App\Http\Controllers;

use App\Filament\Admin\Resources\ProtocolResource;
use App\Models\Protocol;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ProtocolPdfController extends Controller
{
    public function __invoke(Protocol $protocol): Response
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if (! ($user->isAdmin() || $user->isZoneManager() || $user->isTherapist())) {
            abort(403);
        }

        $isAuthorized = ProtocolResource::getEloquentQuery()
            ->whereKey($protocol->getKey())
            ->exists();

        abort_unless($isAuthorized, 403);

        $protocol->load([
            'patient',
            'eegStudy',
            'clinic',
            'creator',
            'sessions' => fn ($query) => $query->orderBy('session_number'),
        ]);

        $pdf = Pdf::loadView('pdf.protocol', [
            'protocol' => $protocol,
        ])->setPaper('letter', 'portrait');

        return $pdf->stream("protocolo-{$protocol->id}.pdf");
    }
}