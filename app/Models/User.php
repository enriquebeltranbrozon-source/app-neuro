<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'clinic_id',
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
            'clinic_id'         => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Control de Acceso a Filament (Panel Security)
    |--------------------------------------------------------------------------
    */

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin()
            || $this->isZoneManager()
            || $this->isTherapist()
            || $this->isAgent()
            || $this->isMarketing()
            || $this->isEegTechnician();
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos de Verificación de Roles y Capacidades
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role?->isAdmin() ?? false;
    }

    public function isAgent(): bool
    {
        return $this->role?->isAgent() ?? false;
    }

    public function isTherapist(): bool
    {
        return $this->role?->isTherapist() ?? false;
    }

    public function isMarketing(): bool
    {
        return $this->role?->isMarketing() ?? false;
    }

    public function isZoneManager(): bool
    {
        return $this->role?->isZoneManager() ?? false;
    }

    public function isEegTechnician(): bool
    {
        return $this->role?->isEegTechnician() ?? false;
    }

    public function canManageInfrastructure(): bool
    {
        return $this->role?->canManageInfrastructure() ?? false;
    }

    public function canAccessClinicalOperations(): bool
    {
        return $this->role?->canAccessClinicalOperations() ?? false;
    }

    public function canAccessMarketing(): bool
    {
        return $this->role?->canAccessMarketing() ?? false;
    }

    public function canManageAssignedClinics(): bool
    {
        return $this->role?->canManageAssignedClinics() ?? false;
    }

    /**
     * Devuelve la única clínica administrada localmente por este usuario (si aplica).
     */
    public function managedClinic(): ?Clinic
    {
        if ($this->isAdmin()) {
            return null;
        }

        $clinic = $this->clinics()
            ->wherePivot('is_clinic_admin', true)
            ->first();

        return $clinic instanceof Clinic ? $clinic : null;
    }

    /**
     * ID de la clínica administrada localmente por este usuario, o null si no administra ninguna.
     */
    public function managedClinicId(): ?int
    {
        $clinic = $this->managedClinic();

        return $clinic ? (int) $clinic->id : null;
    }

    /**
     * Indica si el usuario administra localmente una clínica específica.
     */
    public function isClinicAdminOf(int $clinicId): bool
    {
        if ($clinicId <= 0) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return $this->managedClinicId() === $clinicId;
    }

    /**
     * Indica si el usuario tiene facultad de administración local sobre alguna clínica.
     */
    public function canAdministerAnyClinic(): bool
    {
        return $this->isAdmin() || $this->managedClinicId() !== null;
    }

    /**
     * Determina si el usuario actual puede gestionar (ver/crear/editar)
     * a un usuario objetivo dentro del alcance de sus sucursales.
     */
    public function canManageStaffUser(self $target): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $targetClinicIds = $target->clinics()
            ->pluck('clinics.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($target->clinic_id) {
            $targetClinicIds[] = (int) $target->clinic_id;
        }

        $targetClinicIds = array_values(array_unique($targetClinicIds));

        if ($this->isZoneManager()) {
            if ($target->isAdmin() || $target->isZoneManager()) {
                return false;
            }

            $managerClinics = $this->accessibleClinicIds();
            if (empty($managerClinics)) {
                return false;
            }

            return count(array_intersect($managerClinics, $targetClinicIds)) > 0;
        }

        // Si un usuario tiene administración local de una clínica, solo puede gestionar
        // personal no-admin / no-zone-manager de esa misma clínica.
        $managedId = $this->managedClinicId();
        if ($managedId !== null) {
            if ($target->isAdmin() || $target->isZoneManager() || $target->id === $this->id) {
                return false;
            }

            return in_array($managedId, $targetClinicIds, true);
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones Eloquent
    |--------------------------------------------------------------------------
    */

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_user')
            ->withPivot(['is_primary', 'is_clinic_admin'])
            ->withTimestamps();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PatientSession::class, 'user_id');
    }

    public function eegStudies(): HasMany
    {
        return $this->hasMany(EegStudy::class, 'uploaded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers Operativos Multiclínica
    |--------------------------------------------------------------------------
    */

    public function primaryClinic(): ?Clinic
    {
        $pivotClinic = $this->clinics()->wherePivot('is_primary', true)->first();

        if ($pivotClinic instanceof Clinic) {
            return $pivotClinic;
        }

        $directClinic = $this->clinic()->first();

        if ($directClinic instanceof Clinic) {
            return $directClinic;
        }

        if ($this->clinic_id) {
            return Clinic::query()->find((int) $this->clinic_id);
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    public function accessibleClinicIds(): array
    {
        if ($this->isAdmin()) {
            return Clinic::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $pivotIds = $this->clinics()->pluck('clinics.id')->map(fn ($id) => (int) $id)->all();

        if ($this->clinic_id) {
            $pivotIds[] = (int) $this->clinic_id;
        }

        return array_values(array_unique(array_filter(array_map('intval', $pivotIds))));
    }

    public function canAccessClinic(int $clinicId): bool
    {
        if ($clinicId <= 0) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return in_array($clinicId, $this->accessibleClinicIds(), true);
    }
}