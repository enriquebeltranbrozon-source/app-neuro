<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicService extends Model
{
    use HasFactory;

    protected $table = 'clinic_service';

    protected $fillable = [
        'clinic_id',
        'service_id',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if (! $user->isZoneManager()) {
            return $query->whereRaw('1 = 0');
        }

        $rawIds = $user->accessibleClinicIds();
        $clinicIds = is_array($rawIds)
            ? array_values(array_unique(array_filter(
                array_map(static fn (mixed $id): int => (int) $id, $rawIds),
                static fn (int $id): bool => $id > 0
            )))
            : [];

        if (empty($clinicIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('clinic_id', $clinicIds);
    }
}