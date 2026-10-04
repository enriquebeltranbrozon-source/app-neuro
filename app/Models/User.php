<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Atributos asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 
        'email', 
        'password',
        'role',
    ];

    /**
     * Atributos ocultos para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password', 
        'remember_token',
    ];

    /**
     * Castings de atributos del modelo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Control de Acceso a Filament (Panel Security)
    |--------------------------------------------------------------------------
    */

    /**
     * Determina si el usuario puede acceder al panel de administración.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() || $this->isAgent() || $this->isMarketing();
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos de Verificación de Roles
    |--------------------------------------------------------------------------
    */

    /**
     * Confirma si el usuario es un Administrador del sistema.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Confirma si el usuario es un Agente / Terapeuta comercial.
     */
    public function isAgent(): bool
    {
        return $this->role === UserRole::Agent;
    }

    /**
     * Alias de compatibilidad para verificar si es Terapeuta.
     */
    public function isTherapist(): bool
    {
        return $this->isAgent() || ($this->role?->value === 'therapist');
    }

    /**
     * Confirma si el usuario pertenece al equipo de Marketing.
     */
    public function isMarketing(): bool
    {
        // Si existe el caso Marketing en el Enum UserRole se valida contra él,
        // o contra el valor string por compatibilidad.
        if (defined(UserRole::class . '::Marketing')) {
            return $this->role === UserRole::Marketing || $this->isAdmin();
        }

        return $this->role?->value === 'marketing' || $this->isAdmin();
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones Eloquent
    |--------------------------------------------------------------------------
    */

    /**
     * Obtiene los leads asignados al agente.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}