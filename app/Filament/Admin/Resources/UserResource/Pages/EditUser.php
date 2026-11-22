<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        /** @var User|null $actor */
        $actor = auth()->user();

        /** @var User $target */
        $target = $this->record;

        if (! $actor instanceof User || ! $actor->canManageStaffUser($target)) {
            abort(403, 'No tienes permiso para editar este usuario.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function (): bool {
                    /** @var User|null $actor */
                    $actor = auth()->user();

                    /** @var User $target */
                    $target = $this->record;

                    if (! $actor instanceof User || $actor->id === $target->id) {
                        return false;
                    }

                    return $actor->isAdmin() || $actor->canManageStaffUser($target);
                }),
        ];
    }

    protected function resolveManagedClinicId(string $role): ?int
    {
        if ($role === UserRole::Admin->value) {
            return null;
        }

        $raw = $this->data['managed_clinic_id'] ?? null;

        return filled($raw) ? (int) $raw : null;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        /** @var User $record */
        $record = $this->record;

        if (! $actor instanceof User || ! $actor->canManageStaffUser($record)) {
            throw ValidationException::withMessages([
                'role' => 'No tienes permiso para modificar este usuario.',
            ]);
        }

        $role = (string) ($data['role'] ?? $record->role?->value ?? $record->role ?? UserRole::Therapist->value);

        if (! $actor->isAdmin()) {
            $allowedRoles = UserResource::zoneManageableRoles();

            if (! in_array($role, $allowedRoles, true)) {
                throw ValidationException::withMessages([
                    'role' => 'No tienes permiso para asignar este rol.',
                ]);
            }
        }

        $data['role'] = $role;

        $selectedClinicIds = collect($this->data['clinics'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $primaryClinicId = ! empty($data['clinic_id']) ? (int) $data['clinic_id'] : null;
        $managedClinicId = $this->resolveManagedClinicId($role);

        if ($primaryClinicId !== null && ! in_array($primaryClinicId, $selectedClinicIds, true)) {
            $selectedClinicIds[] = $primaryClinicId;
        }

        if ($managedClinicId !== null && ! in_array($managedClinicId, $selectedClinicIds, true)) {
            $selectedClinicIds[] = $managedClinicId;
        }

        if (! $actor->isAdmin()) {
            $allowedClinics = array_map('intval', $actor->accessibleClinicIds());

            if (empty($selectedClinicIds) || array_diff($selectedClinicIds, $allowedClinics) !== []) {
                throw ValidationException::withMessages([
                    'clinics' => 'Solo puedes asignar sucursales dentro de tu alcance autorizado.',
                ]);
            }

            if ($primaryClinicId !== null && ! in_array($primaryClinicId, $allowedClinics, true)) {
                throw ValidationException::withMessages([
                    'clinic_id' => 'La sucursal principal seleccionada está fuera de tu alcance.',
                ]);
            }

            if ($managedClinicId !== null && ! in_array($managedClinicId, $allowedClinics, true)) {
                throw ValidationException::withMessages([
                    'managed_clinic_id' => 'No tienes permiso para asignar administración local sobre esta sucursal.',
                ]);
            }
        }

        if ($managedClinicId !== null) {
            $taken = DB::table('clinic_user')
                ->where('clinic_id', $managedClinicId)
                ->where('is_clinic_admin', true)
                ->where('user_id', '!=', $record->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'managed_clinic_id' => 'Esta clínica ya cuenta con un administrador local asignado.',
                ]);
            }
        }

        $this->data['clinics'] = $selectedClinicIds;

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $record */
        $record = $this->record;

        $role = $record->role instanceof UserRole
            ? $record->role->value
            : (string) $record->role;

        $managedClinicId = $this->resolveManagedClinicId($role);
        $primaryClinicId = $record->clinic_id ? (int) $record->clinic_id : null;

        try {
            UserResource::syncClinicPivot(
                record: $record,
                managedClinicId: $managedClinicId,
                primaryClinicId: $primaryClinicId
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'managed_clinic_id' => $e->getMessage(),
            ]);
        }
    }
}