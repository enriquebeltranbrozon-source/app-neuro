<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Todos pueden ver el listado general en Filament
        return true; 
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lead $lead): bool
    {
        // Todos pueden abrir el detalle de un lead
        return true; 
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Todos pueden registrar un lead manual (teléfono/WhatsApp)
        return true; 
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lead $lead): bool
    {
        // Todos pueden cambiar estados, asignar y poner comentarios
        return true; 
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->is_admin === true; 
    }

    public function restore(User $user, Lead $lead): bool
    {
        return $user->is_admin === true; 
    }

    public function forceDelete(User $user, Lead $lead): bool
    {
        return $user->is_admin === true; 
    }
}