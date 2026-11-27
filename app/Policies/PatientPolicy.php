<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientPolicy
{
    use HandlesAuthorization;

    /**
     * Listado general de pacientes (panel clínico).
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessClinicalOperations();
    }

    /**
     * Consulta de ficha o tarjeta individual de paciente.
     */
    public function view(User $user, Patient $patient): bool
    {
        if (! $user->canAccessClinicalOperations()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $patient->clinic_id !== null
            && $user->canAccessClinic((int) $patient->clinic_id);
    }

    /**
     * Alta de nuevos expedientes de paciente.
     */
    public function create(User $user): bool
    {
        if (! $user->canAccessClinicalOperations()) {
            return false;
        }

        return $user->isAdmin() || ! empty($user->accessibleClinicIds());
    }

    /**
     * Edición de expediente de paciente.
     */
    public function update(User $user, Patient $patient): bool
    {
        if (! $user->canAccessClinicalOperations()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $patient->clinic_id !== null
            && $user->canAccessClinic((int) $patient->clinic_id);
    }

    /**
     * Eliminación individual o en lote (solo Admin global o Gerente de Zona con alcance).
     */
    public function delete(User $user, Patient $patient): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isZoneManager()) {
            return $patient->clinic_id !== null
                && $user->canAccessClinic((int) $patient->clinic_id);
        }

        return false;
    }

    /**
     * Habilita la acción masiva de eliminación en Filament (cada registro pasa luego por delete()).
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdmin() || $user->isZoneManager();
    }

    /**
     * Restauración de expedientes eliminados con soft-delete.
     */
    public function restore(User $user, Patient $patient): bool
    {
        return $this->delete($user, $patient);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    /**
     * Borrado físico definitivo reservado exclusivamente al Administrador Global.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}