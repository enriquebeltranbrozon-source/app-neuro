<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    /**
     * Lista blanca explícita contra asignación masiva no controlada.
     */
    protected $fillable = [
        'region_id',
        'name',
        'code',
        'is_virtual',
        'address',
        'phone',
        'is_active',
    ];

    /**
     * Valores por defecto seguros al instanciar el modelo.
     */
    protected $attributes = [
        'is_virtual' => false,
        'is_active'  => true,
    ];

    /**
     * Casteo estricto de atributos.
     */
    protected function casts(): array
    {
        return [
            'region_id'  => 'integer',
            'is_virtual' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    // --- MUTADORES DE NORMALIZACIÓN ---

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value
            ? strtoupper(trim(strip_tags($value)))
            : null;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value
            ? trim(strip_tags($value))
            : null;
    }

    // --- RELACIONES ---

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    // Relación legacy (usuarios cuya sucursal por defecto es users.clinic_id)
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Relación multiclínica (tabla pivote clinic_user)
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clinic_user')
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

    // Pacientes registrados en esta sucursal
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    // Tarifario y servicios diferenciados por clínica (tabla pivote clinic_service)
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'clinic_service')
            ->withPivot(['price', 'is_active'])
            ->withTimestamps();
    }

    // --- SCOPES OPERATIVOS ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVirtual(Builder $query): Builder
    {
        return $query->where('is_virtual', true);
    }

    public function scopePhysical(Builder $query): Builder
    {
        return $query->where('is_virtual', false);
    }
}