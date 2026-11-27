<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\PatientSession;
use App\Models\Protocol;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PatientPortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $isPatient = $user !== null
            && (
                (method_exists($user, 'isPatient') && $user->isPatient())
                || ($user->role instanceof UserRole && $user->role === UserRole::Patient)
                || $user->role === UserRole::Patient->value
            );

        abort_unless($isPatient, 403);

        /** @var Patient|null $patient */
        $patient = Patient::query()
            ->with(['clinic'])
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);

                if (! empty($user->email)) {
                    $query->orWhereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $user->email))]);
                }
            })
            ->first();

        if (! $patient) {
            return view('patient-portal.show', [
                'patient'             => null,
                'latestProtocol'      => null,
                'scheduledSessions'   => collect(),
                'completedSessions'   => collect(),
                'paidSessions'        => collect(),
                'attendingTherapists' => collect(),
                'recommendations'     => collect(),
            ]);
        }

        if (! $patient->user_id) {
            $patient->forceFill(['user_id' => $user->id])->saveQuietly();
        }

        $latestProtocol = Protocol::query()
            ->with(['sessions' => fn ($q) => $q->orderBy('session_number')])
            ->where('patient_id', $patient->id)
            ->latest('id')
            ->first();

        $allSessions = PatientSession::query()
            ->with(['therapist', 'user'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        // Sesiones tomadas / realizadas
        $completedSessions = $allSessions;

        // Sesiones pagadas
        $paidSessions = $allSessions->filter(
            fn (PatientSession $s): bool => in_array($s->payment_status, ['paid', 'covered_by_package'], true)
        )->values();

        // Sesiones programadas / pendientes del protocolo vigente
        $scheduledSessions = $latestProtocol
            ? $latestProtocol->sessions->where('status', 'pending')->values()
            : collect();

        // Terapeutas que han atendido
        $attendingTherapists = $allSessions
            ->map(fn (PatientSession $s) => $s->therapist ?? $s->user)
            ->filter()
            ->unique('id')
            ->values();

        // Recomendaciones y comentarios clínicos consolidados
        $recommendations = collect();

        if ($latestProtocol) {
            $protocolFields = [
                'clinical_goals'    => 'Objetivos del protocolo',
                'frequency_targets' => 'Pautas de entrenamiento',
                'clinical_notes'    => 'Recomendaciones clínicas',
            ];

            foreach ($protocolFields as $field => $label) {
                $value = $latestProtocol->{$field};
                if (is_string($value) && trim($value) !== '') {
                    $recommendations->push([
                        'source' => $label,
                        'text'   => trim($value),
                    ]);
                }
            }
        }

        foreach ($allSessions as $session) {
            $meta = is_array($session->session_metadata) ? $session->session_metadata : [];
            $note = $meta['notes'] ?? $meta['observations'] ?? $meta['comments'] ?? null;

            if (is_string($note) && trim($note) !== '') {
                $dateLabel = ($session->started_at ?? $session->created_at)?->format('d/m/Y') ?? 'Sesión';
                $recommendations->push([
                    'source' => "Comentario de sesión ({$dateLabel})",
                    'text'   => trim($note),
                ]);
            }
        }

        if (is_string($patient->eeg_notes) && trim($patient->eeg_notes) !== '') {
            $recommendations->push([
                'source' => 'Observaciones del expediente / EEG',
                'text'   => trim($patient->eeg_notes),
            ]);
        }

        return view('patient-portal.show', [
            'patient'             => $patient,
            'latestProtocol'      => $latestProtocol,
            'scheduledSessions'   => $scheduledSessions,
            'completedSessions'   => $completedSessions,
            'paidSessions'        => $paidSessions,
            'attendingTherapists' => $attendingTherapists,
            'recommendations'     => $recommendations->unique('text')->values(),
        ]);
    }
}