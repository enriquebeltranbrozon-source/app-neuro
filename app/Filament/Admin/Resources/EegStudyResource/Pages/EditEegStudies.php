<?php

namespace App\Filament\Admin\Resources\EegStudyResource\Pages;

use App\Filament\Admin\Resources\EegStudyResource;
use App\Models\EegStudy;
use App\Models\Patient;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EditEegStudy extends EditRecord
{
    protected static string $resource = EegStudyResource::class;

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

        /** @var EegStudy $record */
        $record = $this->record;

        if (! $actor instanceof User) {
            throw ValidationException::withMessages([
                'patient_id' => 'Sesión no válida para editar estudios.',
            ]);
        }

        $patientId = (int) ($data['patient_id'] ?? $record->patient_id);
        $patient = Patient::query()->find($patientId);

        if (! $patient) {
            throw ValidationException::withMessages([
                'patient_id' => 'Debes seleccionar un paciente válido.',
            ]);
        }

        $clinicId = ! empty($data['clinic_id'])
            ? (int) $data['clinic_id']
            : ($patient->clinic_id ? (int) $patient->clinic_id : $record->clinic_id);

        if (! $actor->isAdmin()) {
            $allowedClinics = array_map('intval', $actor->accessibleClinicIds());

            if ($clinicId === null || ! in_array($clinicId, $allowedClinics, true)) {
                throw ValidationException::withMessages([
                    'clinic_id' => 'La clínica seleccionada está fuera de tu alcance autorizado.',
                ]);
            }
        }

        $data['clinic_id'] = $clinicId;

        if (! empty($data['file_path']) && $data['file_path'] !== $record->file_path) {
            $disk = Storage::disk('local');

            if ($disk->exists($data['file_path'])) {
                $data['original_filename'] = basename($data['file_path']);
                $data['file_size'] = $disk->size($data['file_path']);
                $data['mime_type'] = $disk->mimeType($data['file_path']);
            }
        }

        return $data;
    }
}