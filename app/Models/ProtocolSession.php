<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProtocolSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'protocol_id',
        'patient_session_id',
        'session_number',
        'phase',
        'scheduled_date',
        'status',
        'session_notes',
    ];

    protected function casts(): array
    {
        return [
            'patient_session_id' => 'integer',
            'session_number' => 'integer',
            'scheduled_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProtocolSession $protocolSession): void {
            if (! $protocolSession->patient_session_id) {
                return;
            }

            $protocolSession->loadMissing('protocol');
            $patientSession = PatientSession::query()->find((int) $protocolSession->patient_session_id);

            if (! $patientSession) {
                throw ValidationException::withMessages([
                    'patient_session_id' => 'La sesión del paciente seleccionada no existe.',
                ]);
            }

            if ($protocolSession->protocol && (int) $patientSession->patient_id !== (int) $protocolSession->protocol->patient_id) {
                throw ValidationException::withMessages([
                    'patient_session_id' => 'La sesión seleccionada pertenece a otro paciente distinto al del protocolo.',
                ]);
            }
        });
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function patientSession(): BelongsTo
    {
        return $this->belongsTo(PatientSession::class);
    }

    /**
     * Crea o vincula una PatientSession del mismo paciente y enlaza esta sesión de protocolo.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function attachPatientSession(array $attributes = []): PatientSession
    {
        $this->loadMissing('protocol');

        $protocol = $this->protocol;
        $patientId = (int) ($attributes['patient_id'] ?? $protocol?->patient_id ?? 0);

        if ($patientId <= 0) {
            throw ValidationException::withMessages([
                'patient_id' => 'No se pudo determinar el paciente para crear la sesión.',
            ]);
        }

        if ($this->patient_session_id) {
            $existing = PatientSession::query()->find((int) $this->patient_session_id);
            if ($existing) {
                return $existing;
            }
        }

        $nextSessionNumber = (int) ($attributes['session_number']
            ?? ((int) PatientSession::query()->where('patient_id', $patientId)->max('session_number') + 1));

        $metadata = is_array($attributes['session_metadata'] ?? null)
            ? $attributes['session_metadata']
            : [];

        $metadata['protocol_id'] = $this->protocol_id;
        $metadata['protocol_session_id'] = $this->id;
        $metadata['protocol_session_number'] = $this->session_number;
        $metadata['protocol_phase'] = $this->phase;

        $patientSession = PatientSession::query()->create([
            'patient_id' => $patientId,
            'clinic_id' => $attributes['clinic_id'] ?? $protocol?->clinic_id,
            'user_id' => $attributes['user_id'] ?? auth()->id(),
            'service_id' => $attributes['service_id'] ?? null,
            'session_number' => max(1, $nextSessionNumber),
            'modality' => $attributes['modality'] ?? 'in_person',
            'remote_platform' => $attributes['remote_platform'] ?? null,
            'started_at' => $attributes['started_at'] ?? now(),
            'ended_at' => $attributes['ended_at'] ?? null,
            'session_metadata' => $metadata,
            'impedance_readings' => $attributes['impedance_readings'] ?? null,
            'payment_status' => $attributes['payment_status'] ?? 'pending',
            'charged_amount' => $attributes['charged_amount'] ?? 0.00,
            'is_signed' => false,
        ]);

        $this->forceFill([
            'patient_session_id' => $patientSession->id,
            'status' => $attributes['protocol_session_status'] ?? 'completed',
        ])->save();

        return $patientSession;
    }
}