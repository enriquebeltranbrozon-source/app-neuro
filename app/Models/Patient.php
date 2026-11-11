<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'birthdate' => 'date',
        'custom_fields' => 'array',
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
}