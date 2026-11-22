<?php

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        if ($actor instanceof User && ! $actor->isAdmin()) {
            $clinicId = isset($data['clinic_id']) ? (int) $data['clinic_id'] : 0;

            if ($clinicId <= 0 || ! $actor->canAccessClinic($clinicId)) {
                throw ValidationException::withMessages([
                    'clinic_id' => 'No tienes permiso para registrar egresos en la sucursal seleccionada.',
                ]);
            }

            if (! $actor->isZoneManager()) {
                $data['user_id'] = $actor->id;
            }
        }

        return $data;
    }
}