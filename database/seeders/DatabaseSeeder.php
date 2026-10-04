<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta los seeders principales de la aplicación en orden secuencial.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            // LeadSeeder::class,
        ]);
    }
}