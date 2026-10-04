<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Determina si el usuario puede ver la lista general de leads.
     */
    public function viewAny(User $user): bool
    {
        // Todos los roles (Administrador, Agente, Marketing) tienen acceso a la vista.
        // La restricción de registros para Agentes se aplica en LeadResource::getEloquentQuery().
        return true; 
    }

    /**
     * Determina si el usuario puede ver el detalle de un lead en particular.
     */
    public function view(User $user, Lead $lead): bool
    {
        if ($user->isAdmin() || $user->isMarketing()) {
            return true;
        }

        // El Agente Comercial solo puede ver leads asignados a él o libres sin asignar
        return $lead->user_id === $user->id || is_null($lead->user_id);
    }

    /**
     * Determina si el usuario puede registrar prospectos manualmente.
     */
    public function create(User $user): bool
    {
        // Solo Administradores y Agentes Comerciales pueden registrar leads manuales
        return $user->isAdmin() || $user->isAgent();
    }

    /**
     * Determina si el usuario puede actualizar datos, notas o estado de un lead.
     */
    public function update(User $user, Lead $lead): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isMarketing()) {
            return false; // El rol de Marketing es estrictamente de solo lectura
        }

        // El Agente Comercial edita sus propios leads asignados o prospectos libres
        return $lead->user_id === $user->id || is_null($lead->user_id);
    }

    /**
     * Determina si el usuario puede borrar un lead de la base de datos.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determina si el usuario puede restaurar un lead eliminado.
     */
    public function restore(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determina si el usuario puede forzar el borrado permanente de un lead.
     */
    public function forceDelete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }
}