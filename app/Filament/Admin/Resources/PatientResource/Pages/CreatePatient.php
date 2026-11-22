<?php

namespace App\Filament\Admin\Resources\PatientResource\Pages;

use App\Filament\Admin\Resources\PatientResource;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return $data;
        }

        $clinicId = ! empty($data['clinic_id'])
            ? (int) $data['clinic_id']
            : PatientResource::resolveDefaultClinicId($actor);

        if ($clinicId && Clinic::query()->whereKey($clinicId)->exists()) {
            if ($actor->isAdmin() || $actor->canAccessClinic($clinicId)) {
                $data['clinic_id'] = $clinicId;

                return $data;
            }
        }

        if (! $actor->isAdmin()) {
            Notification::make()
                ->danger()
                ->title('Sin sucursal válida asignada')
                ->body('No se pudo asignar una sucursal autorizada a tu usuario para registrar al paciente.')
                ->send();

            $this->halt();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Patient $patient */
        $patient = $this->record;

        $origin = $patient->eeg_origin ?? 'schedule_required';

        if ($origin === 'external_prior') {
            $patient->eegStudies()->create([
                'clinic_id'    => $patient->clinic_id,
                'requested_by' => auth()->id(),
                'title'        => 'EEG Externo Previo - ' . $patient->full_name,
                'study_date'   => now()->toDateString(),
                'status'       => 'external',
                'is_external'  => true,
                'notes'        => $patient->eeg_notes ?? 'Estudio externo previo reportado al alta.',
            ]);

            return;
        }

        $patient->eegStudies()->create([
            'clinic_id'    => $patient->clinic_id,
            'requested_by' => auth()->id(),
            'title'        => 'EEG Basal Inicial - ' . $patient->full_name,
            'scheduled_at' => $patient->eeg_scheduled_at,
            'status'       => 'scheduled',
            'is_external'  => false,
            'notes'        => $patient->eeg_notes,
        ]);
    }
}