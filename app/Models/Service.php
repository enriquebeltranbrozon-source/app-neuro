<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'requires_clinical_record' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PatientSession::class);
    }

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'clinic_service')
                    ->withPivot(['price', 'is_active'])
                    ->withTimestamps();
    }
}