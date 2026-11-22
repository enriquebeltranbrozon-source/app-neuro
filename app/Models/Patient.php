<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'birthdate'        => 'date',
        'eeg_scheduled_at' => 'datetime',
        'custom_fields'    => 'array',
        'clinic_id'        => 'integer',
    ];

    // Relación con el Lead originario (si vino por pauta digital)
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    // Historial de sesiones / notas de evolución
    public function sessions(): HasMany
    {
        return $this->hasMany(PatientSession::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function eegStudies(): HasMany
    {
        return $this->hasMany(EegStudy::class);
    }
}