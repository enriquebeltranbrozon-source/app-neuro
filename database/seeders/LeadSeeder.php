<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Faker\Factory as Faker;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_MX');
        
        // Obtenemos a los terapeutas que ya sembramos (excepto al admin si quieres)
        $terapeutas = User::where('email', '!=', 'admin@nfc.com')->get();

        // Configuraciones para datos realistas
        $sintomas = ['TDAH', 'Ansiedad', 'Insomnio', 'Depresión', 'Estrés Postraumático', 'Autismo', 'Migraña'];
        $ciudades = ['CDMX', 'Metepec', 'Cuernavaca', 'Monterrey', 'Naucalpan'];
        $estados = ['abierto', 'cliente_nuevo', 'no_concretado'];

        for ($i = 0; $i < 60; $i++) {
            $terapeutaAsignado = $terapeutas->random();
            
            Lead::create([
                'name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'phone' => $faker->phoneNumber(),
                'patient_type' => $faker->randomElement(['Para mí', 'Para mi hijo/a', 'Para un familiar']),
                'age' => $faker->numberBetween(6, 75),
                'main_symptom' => $faker->randomElement($sintomas),
                'symptom_description' => $faker->sentence(12),
                'city' => $faker->randomElement($ciudades),
                'source' => 'nfc_center',
                
                // Gestión Interna
                'status' => $faker->randomElement($estados),
                'user_id' => $terapeutaAsignado->id, // Lo asignamos a un terapeuta real
                'contacted_at' => $faker->dateTimeBetween('-1 month', 'now'),
                'follow_up_date' => $faker->dateTimeBetween('now', '+1 week'),
                'agent_comments' => $faker->optional()->realText(100),
                'created_at' => $faker->dateTimeBetween('-2 months', 'now'),
            ]);
        }
    }
}