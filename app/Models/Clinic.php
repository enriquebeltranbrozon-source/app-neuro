<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_virtual' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relación con los terapeutas/usuarios asignados a esta clínica
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Relación con los pacientes registrados en esta sucursal
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'clinic_service')
                    ->withPivot(['price', 'is_active'])
                    ->withTimestamps();
    }

    
}