<?php

namespace App\Filament\Admin\Resources\PatientSessionResource\Pages;

use App\Filament\Admin\Resources\PatientSessionResource;
use App\Models\Patient;
use App\Models\PatientSession;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreatePatientSession extends CreateRecord
{
    protected static string $resource = PatientSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        if (empty($data['clinic_id']) && ! empty($data['patient_id'])) {
            $data['clinic_id'] = Patient::query()->whereKey((int) $data['patient_id'])->value('clinic_id');
        }

        if ($actor instanceof User && ! $actor->isAdmin()) {
            $clinicId = isset($data['clinic_id']) ? (int) $data['clinic_id'] : 0;

            if ($clinicId <= 0 || ! $actor->canAccessClinic($clinicId)) {
                throw ValidationException::withMessages([
                    'clinic_id' => 'No tienes permiso para registrar sesiones en la sucursal seleccionada.',
                ]);
            }
        }

        if (empty($data['session_number']) && ! empty($data['patient_id'])) {
            $lastNumber = (int) PatientSession::query()
                ->where('patient_id', (int) $data['patient_id'])
                ->max('session_number');

            $data['session_number'] = $lastNumber + 1;
        }

        return $data;
    }
}