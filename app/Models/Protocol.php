<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Protocol extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'eeg_study_id',
        'clinic_id',
        'created_by',
        'title',
        'status',
        'total_sessions',
        'recommended_frequency_per_week',
        'eeg_findings',
        'clinical_goals',
        'electrode_sites',
        'frequency_targets',
        'clinical_notes',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'total_sessions' => 'integer',
            'recommended_frequency_per_week' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Protocol $protocol): void {
            if ($protocol->eeg_study_id) {
                $study = EegStudy::query()->find($protocol->eeg_study_id);

                if (! $study) {
                    throw ValidationException::withMessages([
                        'eeg_study_id' => 'El estudio EEG seleccionado no existe.',
                    ]);
                }

                if ((int) $study->patient_id !== (int) $protocol->patient_id) {
                    throw ValidationException::withMessages([
                        'eeg_study_id' => 'El estudio EEG seleccionado pertenece a otro paciente.',
                    ]);
                }

                if (! $protocol->clinic_id && $study->clinic_id) {
                    $protocol->clinic_id = (int) $study->clinic_id;
                }
            }

            if (! $protocol->clinic_id && $protocol->patient_id) {
                $patient = Patient::query()->find($protocol->patient_id);
                if ($patient?->clinic_id) {
                    $protocol->clinic_id = (int) $patient->clinic_id;
                }
            }

            if (! $protocol->total_sessions || $protocol->total_sessions < 1) {
                $protocol->total_sessions = 45;
            }
        });

        static::created(function (Protocol $protocol): void {
            $protocol->generateSessionsPlan();
        });
    }

    public function generateSessionsPlan(): void
    {
        $total = max(1, (int) $this->total_sessions);
        $phase1End = (int) ceil($total * 0.25); // ~25% fase inicial / adaptación
        $phase2End = (int) ceil($total * 0.75); // ~50% fase central de entrenamiento

        for ($i = 1; $i <= $total; $i++) {
            $phase = match (true) {
                $i <= $phase1End => 'Fase 1: Evaluación inicial y regulación base',
                $i <= $phase2End => 'Fase 2: Entrenamiento activo del protocolo',
                default          => 'Fase 3: Consolidación, generalización y cierre',
            };

            $this->sessions()->firstOrCreate(
                ['session_number' => $i],
                [
                    'phase'  => $phase,
                    'status' => 'pending',
                ]
            );
        }
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function eegStudy(): BelongsTo
    {
        return $this->belongsTo(EegStudy::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ProtocolSession::class)->orderBy('session_number');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $clinicIds = $user->accessibleClinicIds();

        if (empty($clinicIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($clinicIds, $user): void {
            $q->whereIn('clinic_id', $clinicIds)
                ->orWhereHas('patient', fn (Builder $pq) => $pq->whereIn('clinic_id', $clinicIds))
                ->orWhere('created_by', $user->id);
        });
    }
}