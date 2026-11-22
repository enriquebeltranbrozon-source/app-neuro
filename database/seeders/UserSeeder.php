<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $clinicsByCode = Clinic::all()->keyBy('code');
        $delValle      = $clinicsByCode->get('CDMX-VALLE');
        $metepec       = $clinicsByCode->get('EDOMEX-MET');
        $cuernavaca    = $clinicsByCode->get('MOR-CUER');
        $monterrey     = $clinicsByCode->get('MTY-VALLE');

        // 1. Admin / Master local
        $adminNfc = User::updateOrCreate(
            ['email' => 'admin@nfc.com'],
            [
                'name'      => 'Enrique Beltran',
                'password'  => Hash::make('password'),
                'role'      => UserRole::Admin->value,
                'clinic_id' => $delValle?->id,
            ]
        );
        $this->syncClinicMembership($adminNfc, $delValle?->id, isPrimary: true, isClinicAdmin: false);

        // 2. Terapeutas de clínicas (ajustar clinic_code según catálogo real de clínicas)
        $terapeutas = [
            [
                'name'            => 'Terapeuta Del Valle',
                'email'           => 'valle@nfc.com',
                'role'            => UserRole::Therapist->value,
                'clinic_code'     => 'CDMX-VALLE',
                'is_clinic_admin' => false,
            ],
            [
                'name'            => 'Terapeuta Satélite',
                'email'           => 'satelite@nfc.com',
                'role'            => UserRole::Therapist->value,
                'clinic_code'     => null, // Reemplazar por código real si existe sucursal Satélite en ClinicSeeder
                'is_clinic_admin' => false,
            ],
            [
                'name'            => 'Terapeuta Metepec',
                'email'           => 'metepec@nfc.com',
                'role'            => UserRole::Therapist->value,
                'clinic_code'     => 'EDOMEX-MET',
                'is_clinic_admin' => false,
            ],
            [
                'name'            => 'Terapeuta Cuernavaca',
                'email'           => 'cuernavaca@nfc.com',
                'role'            => UserRole::Therapist->value,
                'clinic_code'     => 'MOR-CUER',
                'is_clinic_admin' => false,
            ],
            [
                'name'            => 'Terapeuta Monterrey',
                'email'           => 'monterrey@nfc.com',
                'role'            => UserRole::Therapist->value,
                'clinic_code'     => 'MTY-VALLE',
                'is_clinic_admin' => false,
            ],
        ];

        foreach ($terapeutas as $t) {
            $clinic = ! empty($t['clinic_code']) ? $clinicsByCode->get($t['clinic_code']) : null;

            $user = User::updateOrCreate(
                ['email' => $t['email']],
                [
                    'name'      => $t['name'],
                    'password'  => Hash::make('nfc2026'),
                    'role'      => $t['role'],
                    'clinic_id' => $clinic?->id,
                ]
            );

            if ($clinic) {
                $this->syncClinicMembership(
                    user: $user,
                    clinicId: (int) $clinic->id,
                    isPrimary: true,
                    isClinicAdmin: (bool) $t['is_clinic_admin']
                );
            }
        }

        // 3. Placeholder Marketing
        $marketing = User::updateOrCreate(
            ['email' => 'marketing@nfc.com'],
            [
                'name'      => 'Analista Marketing',
                'password'  => Hash::make('nfc2026'),
                'role'      => UserRole::Marketing->value,
                'clinic_id' => $delValle?->id,
            ]
        );
        $this->syncClinicMembership($marketing, $delValle?->id, isPrimary: true, isClinicAdmin: false);
    }

    private function syncClinicMembership(
        User $user,
        ?int $clinicId,
        bool $isPrimary = true,
        bool $isClinicAdmin = false
    ): void {
        if (! $clinicId) {
            return;
        }

        // Si se marca como admin local de esta clínica, limpiar is_clinic_admin previo del usuario
        // para respetar la regla de máximo 1 clínica administrada por usuario.
        if ($isClinicAdmin && ! $user->isAdmin()) {
            $user->clinics()->newPivotStatement()
                ->where('user_id', $user->id)
                ->update(['is_clinic_admin' => false]);
        }

        $user->clinics()->syncWithoutDetaching([
            $clinicId => [
                'is_primary'      => $isPrimary,
                'is_clinic_admin' => $user->isAdmin() ? false : $isClinicAdmin,
            ],
        ]);
    }
}