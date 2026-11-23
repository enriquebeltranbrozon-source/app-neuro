<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Service extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'duration_minutes',
        'requires_clinical_record',
        'is_active',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'duration_minutes' => 'integer',
        'requires_clinical_record' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
            $service->name = trim((string) $service->name);

            if ($service->name === '') {
                throw ValidationException::withMessages([
                    'name' => 'El nombre del servicio es obligatorio.',
                ]);
            }

            $baseSlug = filled($service->slug)
                ? Str::slug((string) $service->slug)
                : Str::slug($service->name);

            if ($baseSlug === '') {
                $baseSlug = 'servicio';
            }

            $slug = $baseSlug;
            $suffix = 2;

            while (
                static::query()
                    ->where('slug', $slug)
                    ->when($service->exists, fn (Builder $q): Builder => $q->whereKeyNot($service->getKey()))
                    ->exists()
            ) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $service->slug = $slug;

            $duration = (int) ($service->duration_minutes ?? 60);
            if ($duration < 1 || $duration > 600) {
                throw ValidationException::withMessages([
                    'duration_minutes' => 'La duración debe estar entre 1 y 600 minutos.',
                ]);
            }

            $service->duration_minutes = $duration;
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PatientSession::class);
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_service')
            ->withPivot(['price', 'is_active'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->whereHas('clinics', function (Builder $q) use ($clinicId): void {
            $q->where('clinics.id', $clinicId)
                ->where('clinic_service.is_active', true);
        });
    }

    public function priceForClinic(?int $clinicId): ?float
    {
        if (! $clinicId || $clinicId <= 0) {
            return null;
        }

        $clinic = $this->clinics()
            ->where('clinics.id', $clinicId)
            ->wherePivot('is_active', true)
            ->first();

        $rawPrice = $clinic?->pivot?->price;

        return $rawPrice !== null ? (float) $rawPrice : null;
    }
}