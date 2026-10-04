<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Master Admin
        User::create([
            'name'     => 'Enrique Beltran',
            'email'    => 'admin@nfc.com',
            'password' => Hash::make('password'),
            'clinic'   => 'interlomas',
            'role'     => UserRole::Admin,
        ]);

        // 2. Terapeutas Reales de NFC (Agentes Comerciales)
        $terapeutas = [
            [
                'name'   => 'Terapeuta Del Valle',
                'email'  => 'valle@nfc.com',
                'clinic' => 'del_valle',
            ],
            [
                'name'   => 'Terapeuta Satélite',
                'email'  => 'satelite@nfc.com',
                'clinic' => 'satelite',
            ],
            [
                'name'   => 'Terapeuta Metepec',
                'email'  => 'metepec@nfc.com',
                'clinic' => 'metepec',
            ],
            [
                'name'   => 'Terapeuta Cuernavaca',
                'email'  => 'cuernavaca@nfc.com',
                'clinic' => 'cuernavaca',
            ],
            [
                'name'   => 'Terapeuta Monterrey',
                'email'  => 'monterrey@nfc.com',
                'clinic' => 'monterrey',
            ],
        ];

        foreach ($terapeutas as $t) {
            User::create([
                'name'     => $t['name'],
                'email'    => $t['email'],
                'password' => Hash::make('nfc2026'),
                'clinic'   => $t['clinic'],
                'role'     => UserRole::Agent,
            ]);
        }

        // 3. Usuario de Análisis y Pauta
        User::create([
            'name'     => 'Analista Marketing',
            'email'    => 'marketing@nfc.com',
            'password' => Hash::make('nfc2026'),
            'clinic'   => 'interlomas',
            'role'     => UserRole::Marketing,
        ]);
    }
}