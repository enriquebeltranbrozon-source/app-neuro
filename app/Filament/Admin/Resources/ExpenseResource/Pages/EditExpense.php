<?php

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        if ($actor instanceof User && ! $actor->isAdmin()) {
            $clinicId = isset($data['clinic_id']) ? (int) $data['clinic_id'] : 0;

            if ($clinicId <= 0 || ! $actor->canAccessClinic($clinicId)) {
                throw ValidationException::withMessages([
                    'clinic_id' => 'No tienes permiso para mover o asignar el egreso a la sucursal seleccionada.',
                ]);
            }

            if (! $actor->isZoneManager()) {
                $data['user_id'] = $this->record->user_id ?? $actor->id;
            }
        }

        return $data;
    }
}