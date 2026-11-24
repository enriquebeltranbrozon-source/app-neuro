<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EegStudy extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXTERNAL  = 'external';

    protected $fillable = [
        'patient_id',
        'clinic_id',
        'uploaded_by',
        'requested_by',
        'assigned_technician_id',
        'title',
        'status',
        'is_external',
        'scheduled_at',
        'study_date',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'patient_id'             => 'integer',
            'clinic_id'              => 'integer',
            'uploaded_by'            => 'integer',
            'requested_by'           => 'integer',
            'assigned_technician_id' => 'integer',
            'is_external'            => 'boolean',
            'scheduled_at'           => 'datetime',
            'study_date'             => 'date',
            'file_size'              => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EegStudy $study): void {
            if (! $study->clinic_id && $study->patient) {
                $study->clinic_id = (int) $study->patient->clinic_id;
            }

            if (! $study->requested_by && auth()->check()) {
                $study->requested_by = (int) auth()->id();
            }

            if ($study->is_external) {
                $study->status = self::STATUS_EXTERNAL;
            } elseif (blank($study->status)) {
                $study->status = filled($study->file_path)
                    ? self::STATUS_COMPLETED
                    : self::STATUS_SCHEDULED;
            }

            if (
                $study->status === self::STATUS_SCHEDULED
                && ! $study->scheduled_at
                && $study->patient?->eeg_scheduled_at
            ) {
                $study->scheduled_at = $study->patient->eeg_scheduled_at;
            }
        });

        static::saving(function (EegStudy $study): void {
            if ($study->is_external) {
                $study->status = self::STATUS_EXTERNAL;
            } elseif (filled($study->file_path)) {
                $study->status = self::STATUS_COMPLETED;

                if (! $study->uploaded_by && auth()->check()) {
                    $study->uploaded_by = (int) auth()->id();
                }

                if (! $study->study_date) {
                    $study->study_date = now()->toDateString();
                }
            } elseif (blank($study->status)) {
                $study->status = self::STATUS_SCHEDULED;
            }
        });

        static::deleted(function (EegStudy $study): void {
            if (! filled($study->file_path)) {
                return;
            }

            $stillInUse = static::query()
                ->where('id', '!=', $study->getKey())
                ->where('file_path', $study->file_path)
                ->exists();

            if (! $stillInUse && Storage::disk('local')->exists($study->file_path)) {
                Storage::disk('local')->delete($study->file_path);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if (! $user->canAccessEegStudies()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $clinicIds = $user->accessibleClinicIds();

        if (empty($clinicIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($clinicIds): void {
            $q->whereIn('eeg_studies.clinic_id', $clinicIds)
                ->orWhere(function (Builder $fallback) use ($clinicIds): void {
                    $fallback->whereNull('eeg_studies.clinic_id')
                        ->whereHas('patient', fn (Builder $pq) => $pq->whereIn('patients.clinic_id', $clinicIds));
                });
        });
    }
}