<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Creamos al Master Admin (Tú)
        User::create([
            'name' => 'Enrique Beltran',
            'email' => 'admin@nfc.com',
            'password' => Hash::make('password'), // Cámbiala al entrar
            'clinic' => 'interlomas',
        ]);

        // 2. Definimos los terapeutas reales de NFC
        $terapeutas = [
            [
                'name' => 'Terapeuta Del Valle',
                'email' => 'valle@nfc.com',
                'clinic' => 'del_valle',
            ],
            [
                'name' => 'Terapeuta Satélite',
                'email' => 'satelite@nfc.com',
                'clinic' => 'satelite',
            ],
            [
                'name' => 'Terapeuta Metepec',
                'email' => 'metepec@nfc.com',
                'clinic' => 'metepec',
            ],
            [
                'name' => 'Terapeuta Cuernavaca',
                'email' => 'cuernavaca@nfc.com',
                'clinic' => 'cuernavaca',
            ],
            [
                'name' => 'Terapeuta Monterrey',
                'email' => 'monterrey@nfc.com',
                'clinic' => 'monterrey',
            ],
        ];

        // 3. Los sembramos en la base de datos
        foreach ($terapeutas as $t) {
            User::create([
                'name' => $t['name'],
                'email' => $t['email'],
                'password' => Hash::make('nfc2026'),
                'clinic' => $t['clinic'],
            ]);
        }
        $this->call(LeadSeeder::class);

        // CRÍTICO: Asegúrate de que NO haya ninguna línea abajo que diga:
        // $this->call(UserSeeder::class); <--- Esto es lo que causaba el error.
    }
}