<?php

namespace App\Filament\Admin\Resources\EegStudyResource\Pages;

use App\Filament\Admin\Resources\EegStudyResource;
use App\Models\Patient;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateEegStudy extends CreateRecord
{
    protected static string $resource = EegStudyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw ValidationException::withMessages([
                'patient_id' => 'Sesión no válida para cargar estudios.',
            ]);
        }

        $patientId = (int) ($data['patient_id'] ?? 0);
        $patient = Patient::query()->find($patientId);

        if (! $patient) {
            throw ValidationException::withMessages([
                'patient_id' => 'Debes seleccionar un paciente válido.',
            ]);
        }

        $clinicId = ! empty($data['clinic_id'])
            ? (int) $data['clinic_id']
            : ($patient->clinic_id ? (int) $patient->clinic_id : null);

        if (! $actor->isAdmin()) {
            $allowedClinics = array_map('intval', $actor->accessibleClinicIds());

            if ($clinicId === null || ! in_array($clinicId, $allowedClinics, true)) {
                throw ValidationException::withMessages([
                    'clinic_id' => 'La clínica seleccionada está fuera de tu alcance autorizado.',
                ]);
            }

            if ($patient->clinic_id && ! in_array((int) $patient->clinic_id, $allowedClinics, true)) {
                throw ValidationException::withMessages([
                    'patient_id' => 'El paciente seleccionado no pertenece a tu alcance clínico.',
                ]);
            }
        }

        $data['clinic_id'] = $clinicId;
        $data['uploaded_by'] = $actor->id;

        if (! empty($data['file_path'])) {
            $disk = Storage::disk('local');

            if ($disk->exists($data['file_path'])) {
                $data['original_filename'] = $data['original_filename'] ?? basename($data['file_path']);
                $data['file_size'] = $data['file_size'] ?? $disk->size($data['file_path']);
                $data['mime_type'] = $data['mime_type'] ?? $disk->mimeType($data['file_path']);
            }
        }

        return $data;
    }
}