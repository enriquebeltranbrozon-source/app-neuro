<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\EegStudy;
use App\Models\Patient;
use App\Models\PatientSession;
use App\Models\Protocol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = Clinic::query()->get();

        if ($clinics->isEmpty()) {
            $this->command?->warn('No hay clínicas registradas. Omitiendo PatientSeeder.');
            return;
        }

        // Excluir explícitamente sedes de España
        $eligibleClinics = $clinics->filter(function (Clinic $clinic): bool {
            $haystack = mb_strtolower($clinic->name . ' ' . ($clinic->code ?? '') . ' ' . ($clinic->address ?? ''));
            return ! str_contains($haystack, 'españa')
                && ! str_contains($haystack, 'espana')
                && ! str_contains($haystack, 'barcelona')
                && ! str_contains($haystack, 'madrid')
                && ! str_starts_with(strtoupper((string) $clinic->code), 'ES-');
        })->values();

        if ($eligibleClinics->isEmpty()) {
            $eligibleClinics = $clinics->values();
        }

        $fallbackClinic = $eligibleClinics->first();

        // Del Valle (25)
        $delValle = $eligibleClinics->first(function (Clinic $c): bool {
            $code = strtoupper((string) $c->code);
            $name = mb_strtolower($c->name);
            return $code === 'CDMX-VALLE' || $code === 'DEL-VALLE' || str_contains($name, 'valle');
        }) ?? $fallbackClinic;

        // Monterrey y sus sedes (50)
        $mtyClinics = $eligibleClinics->filter(function (Clinic $c) use ($delValle): bool {
            if ($delValle && $c->id === $delValle->id) {
                return false;
            }
            $code = strtoupper((string) $c->code);
            $name = mb_strtolower($c->name);
            return str_starts_with($code, 'MTY')
                || str_contains($name, 'monterrey')
                || str_contains($name, 'mty')
                || str_contains($name, 'san pedro');
        })->values();

        if ($mtyClinics->isEmpty()) {
            $mtyClinics = collect([$fallbackClinic]);
        }

        // Metepec y Cuernavaca (25 divididos entre ambas)
        $metepecClinic = $eligibleClinics->first(function (Clinic $c): bool {
            $code = strtoupper((string) $c->code);
            $name = mb_strtolower($c->name);
            return str_contains($code, 'MET') || str_contains($name, 'metepec');
        });

        $cuernavacaClinic = $eligibleClinics->first(function (Clinic $c): bool {
            $code = strtoupper((string) $c->code);
            $name = mb_strtolower($c->name);
            return str_contains($code, 'CUE') || str_contains($name, 'cuernavaca');
        });

        $otherClinics = collect(array_filter([$metepecClinic, $cuernavacaClinic]))->values();
        if ($otherClinics->isEmpty()) {
            $otherClinics = $eligibleClinics
                ->reject(fn (Clinic $c) => $c->id === $delValle?->id || $mtyClinics->contains('id', $c->id))
                ->values();
            if ($otherClinics->isEmpty()) {
                $otherClinics = collect([$fallbackClinic]);
            }
        }

        // 100 pacientes: 50 MTY/sedes, 25 Del Valle, 25 Metepec/Cuernavaca
        $clinicQueue = [];
        for ($i = 0; $i < 50; $i++) {
            $clinicQueue[] = $mtyClinics[$i % $mtyClinics->count()]->id;
        }
        for ($i = 0; $i < 25; $i++) {
            $clinicQueue[] = $delValle->id;
        }
        for ($i = 0; $i < 25; $i++) {
            $clinicQueue[] = $otherClinics[$i % $otherClinics->count()]->id;
        }

        // 10 estudio propio (external), 70 estudio tomado (completed), 20 pendientes (scheduled)
        $studyModes = array_merge(
            array_fill(0, 10, 'external'),
            array_fill(0, 70, 'completed'),
            array_fill(0, 20, 'scheduled'),
        );

        // De los 80 pacientes con estudio (10 external + 70 completed): 40 con protocolo, 40 pendientes
        $protocolFlags = array_merge(
            array_fill(0, 40, true),
            array_fill(0, 40, false),
            array_fill(0, 20, false),
        );

        $firstNames = [
            'Sofía', 'Mateo', 'Valentina', 'Santiago', 'Camila', 'Sebastián', 'Valeria', 'Emiliano',
            'Regina', 'Diego', 'Ximena', 'Leonardo', 'Renata', 'Daniel', 'Fernanda', 'Alejandro',
            'Isabella', 'Gabriel', 'Lucía', 'Samuel', 'Mariana', 'Bruno', 'Victoria', 'Andrés',
            'Natalia', 'David', 'Paula', 'Eduardo', 'Andrea', 'Fernando', 'Miranda', 'Rodrigo',
            'Paola', 'Jorge', 'Carlos', 'Alejandra', 'Miguel', 'María', 'Ricardo', 'Valeria',
        ];

        $lastNames = [
            'Hernández', 'García', 'Martínez', 'López', 'González', 'Pérez', 'Rodríguez', 'Sánchez',
            'Ramírez', 'Cruz', 'Flores', 'Gómez', 'Morales', 'Vázquez', 'Reyes', 'Jiménez',
            'Díaz', 'Torres', 'Gutiérrez', 'Ruiz', 'Mendoza', 'Aguilar', 'Ortiz', 'Castillo',
        ];

        $symptoms = [
            'Ansiedad y estrés crónico',
            'Insomnio y alteración del sueño',
            'Déficit de atención y concentración',
            'Cefaleas tensionales recurrentes',
            'Fatiga mental y niebla cognitiva',
            'Regulación emocional',
        ];

        $staffUserId = User::query()->value('id');
        $hasEegTable = Schema::hasTable('eeg_studies');
        $hasProtocolsTable = Schema::hasTable('protocols');
        $hasSessionsTable = Schema::hasTable('patient_sessions');

        for ($idx = 0; $idx < 100; $idx++) {
            $num = $idx + 1;
            $clinicId = (int) $clinicQueue[$idx];
            $studyMode = $studyModes[$idx];
            $hasProtocol = (bool) $protocolFlags[$idx];

            $firstName = $firstNames[$idx % count($firstNames)];
            $lastName1 = $lastNames[$idx % count($lastNames)];
            $lastName2 = $lastNames[($idx + 7) % count($lastNames)];
            $fullName = "{$firstName} {$lastName1} {$lastName2}";
            $email = "paciente.seed.{$num}@neurofeedback.mx";

            $scheduledAt = $studyMode === 'scheduled'
                ? Carbon::now()->addDays(($idx % 14) + 1)->setTime(10 + ($idx % 7), 0)
                : null;

            $symptom = $symptoms[$idx % count($symptoms)];

            /** @var Patient $patient */
            $patient = Patient::query()->updateOrCreate(
                ['email' => $email],
                [
                    'clinic_id'           => $clinicId,
                    'registration_source' => $studyMode === 'external' ? 'front_desk' : 'digital_lead',
                    'full_name'           => $fullName,
                    'phone'               => sprintf('55%08d', 40000000 + $num),
                    'birthdate'           => Carbon::now()->subYears(12 + ($idx % 45))->subDays($idx * 11)->toDateString(),
                    'gender'              => ['F', 'M', 'O'][$idx % 3],
                    'eeg_origin'          => $studyMode === 'external' ? 'external_prior' : 'schedule_required',
                    'eeg_scheduled_at'    => $scheduledAt,
                    'eeg_notes'           => $symptom . ($hasProtocol ? ' | Protocolo diseñado.' : ' | Pendiente de protocolo.'),
                    'custom_fields'       => [
                        'main_symptom'    => $symptom,
                        'protocol_status' => $hasProtocol ? 'designed' : 'pending',
                        'seeded'          => true,
                    ],
                    'status'              => 'active',
                ]
            );

            $eegStudy = null;

            if ($hasEegTable) {
                $studyTitle = match ($studyMode) {
                    'external'  => "EEG Externo - {$patient->full_name}",
                    'completed' => "EEG Basal Clínico - {$patient->full_name}",
                    default     => "EEG Programado - {$patient->full_name}",
                };

                $studyAttributes = [
                    'clinic_id'    => $clinicId,
                    'status'       => $studyMode,
                    'is_external'  => $studyMode === 'external',
                    'scheduled_at' => $scheduledAt,
                    'study_date'   => $studyMode === 'scheduled'
                        ? null
                        : Carbon::now()->subDays(5 + ($idx % 30))->toDateString(),
                    'completed_at' => $studyMode === 'scheduled'
                        ? null
                        : Carbon::now()->subDays(4 + ($idx % 25)),
                    'notes'        => $studyMode === 'external'
                        ? 'Estudio previo aportado por el paciente.'
                        : ($studyMode === 'completed'
                            ? 'Estudio cuantitativo capturado en sede.'
                            : 'Cita asignada para toma de estudio EEG.'),
                ];

                if ($staffUserId) {
                    $studyAttributes['requested_by'] = $staffUserId;
                    if ($studyMode !== 'scheduled') {
                        $studyAttributes['uploaded_by'] = $staffUserId;
                        $studyAttributes['assigned_technician_id'] = $staffUserId;
                    }
                }

                $eegStudy = EegStudy::query()->updateOrCreate(
                    [
                        'patient_id' => $patient->id,
                        'title'      => $studyTitle,
                    ],
                    $studyAttributes
                );
            }

            if ($hasProtocol && in_array($studyMode, ['external', 'completed'], true)) {
                if ($hasProtocolsTable) {
                    Protocol::query()->updateOrCreate(
                        [
                            'patient_id' => $patient->id,
                            'title'      => "Protocolo Base - {$patient->full_name}",
                        ],
                        [
                            'eeg_study_id'                   => $eegStudy?->id,
                            'clinic_id'                      => $clinicId,
                            'created_by'                     => $staffUserId,
                            'status'                         => 'active',
                            'total_sessions'                 => 40,
                            'recommended_frequency_per_week' => 2,
                            'eeg_findings'                   => "Evaluación asociada a {$symptom}.",
                            'clinical_goals'                 => "Autorregulación y mejora en {$symptom}.",
                            'electrode_sites'                => 'Cz, Fz, C3/C4',
                            'frequency_targets'              => 'Inhibir 4-8 Hz, recompensar 12-15 Hz',
                            'clinical_notes'                 => 'Protocolo inicial diseñado tras revisión del estudio EEG.',
                            'approved_at'                    => Carbon::now()->subDays(2 + ($idx % 10)),
                        ]
                    );
                }

                if ($hasSessionsTable && $staffUserId) {
                    PatientSession::query()->updateOrCreate(
                        [
                            'patient_id'     => $patient->id,
                            'session_number' => 1,
                        ],
                        [
                            'clinic_id'        => $clinicId,
                            'user_id'          => $staffUserId,
                            'modality'         => 'in_person',
                            'started_at'       => Carbon::now()->subDays(1 + ($idx % 10))->setTime(11, 0),
                            'ended_at'         => Carbon::now()->subDays(1 + ($idx % 10))->setTime(11, 50),
                            'session_metadata' => [
                                'protocol_designed' => true,
                                'notes'             => 'Sesión inicial de diseño y apertura de protocolo.',
                            ],
                            'payment_status'   => 'paid',
                            'charged_amount'   => 850.00,
                            'is_signed'        => false,
                        ]
                    );
                }
            }
        }

        $this->command?->info('PatientSeeder completado: 100 pacientes (50 MTY/sedes, 25 Del Valle, 25 Metepec/Cuernavaca; 10 ext, 70 tomados, 20 pend; 40 con protocolo).');
    }
}