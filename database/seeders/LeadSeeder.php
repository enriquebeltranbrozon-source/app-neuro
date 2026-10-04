<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_MX');

        // Agentes/Terapeutas activos para asignación
        $agents = User::where('role', UserRole::Agent)->get();

        // 1. Única campaña oficial de Google Ads
        $campaignName = 'nfc_google_ads_search_2026';

        // 2. Mapeo de Landings oficiales del proyecto y sus padecimientos asociados
        $landings = [
            '/' => [
                'landing_origin' => 'index_principal',
                'symptom'        => 'Evaluación General / Estrés',
                'url'            => 'https://neurofeedback.com.mx/',
            ],
            '/depresion' => [
                'landing_origin' => 'landing_depresion',
                'symptom'        => 'Depresión',
                'url'            => 'https://neurofeedback.com.mx/depresion',
            ],
            '/ansiedad' => [
                'landing_origin' => 'landing_ansiedad',
                'symptom'        => 'Ansiedad',
                'url'            => 'https://neurofeedback.com.mx/ansiedad',
            ],
            '/problemas-aprendizaje' => [
                'landing_origin' => 'landing_aprendizaje',
                'symptom'        => 'Problemas de Aprendizaje',
                'url'            => 'https://neurofeedback.com.mx/problemas-aprendizaje',
            ],
            '/deficit' => [
                'landing_origin' => 'landing_deficit',
                'symptom'        => 'TDAH / Déficit de Atención',
                'url'            => 'https://neurofeedback.com.mx/deficit',
            ],
        ];

        // 3. Fuentes de tráfico permitidas
        $sources = ['google_ads', 'whatsapp_directo', 'web_organico'];

        // Catálogos auxiliares
        $cityLadas = [
            'CDMX - Del Valle'          => '55',
            'Naucalpan (Satélite)'      => '55',
            'Huixquilucan (Interlomas)' => '55',
            'Metepec'                   => '722',
            'Cuernavaca'                => '777',
            'Monterrey'                 => '81',
        ];

        $patientTypes = ['Para mí', 'Para mi hijo/a', 'Para un familiar', 'Para mi pareja'];
        $statuses = ['abierto', 'contactado', 'cita_programada', 'cliente_nuevo', 'no_concretado'];

        $rejectionReasons = [
            'No cuenta con presupuesto para el tratamiento completo.',
            'Buscaba atención psiquiátrica de urgencia.',
            'Ubicación lejana a nuestras sedes.',
            'No volvió a responder llamadas ni mensajes de WhatsApp.',
        ];

        $successComments = [
            'Asistió a valoración inicial y contrató paquete de 20 sesiones.',
            'Cita de diagnóstico completada con éxito. Inicia tratamiento esta semana.',
            'Evaluación realizada con éxito. Agendó primera sesión.',
        ];

        // Generación de los 150 leads
        for ($i = 0; $i < 150; $i++) {
            // Distribución de tiempo en los últimos 180 días
            $createdAt = Carbon::now()->subDays(rand(0, 180))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            
            // Selección de landing y fuente
            $landingKey = $faker->randomElement(array_keys($landings));
            $landingData = $landings[$landingKey];
            $source = $faker->randomElement($sources);

            // Teléfono con LADA real según la ciudad
            $city = $faker->randomElement(array_keys($cityLadas));
            $lada = $cityLadas[$city];
            $phoneDigits = strlen($lada) === 3 ? 7 : 8;
            $phone = $lada . $faker->numerify(str_repeat('#', $phoneDigits));

            $status = $faker->randomElement($statuses);
            
            // Asignación lógica de agente
            $isAssigned = ($status === 'abierto') ? $faker->boolean(25) : $faker->boolean(90);
            $assignedAgent = ($isAssigned && $agents->isNotEmpty()) ? $agents->random() : null;

            // Fechas y bitácora según estado comercial
            $contactedAt = null;
            $followUpDate = null;
            $agentComments = null;

            if ($status !== 'abierto') {
                $contactedAt = (clone $createdAt)->addHours(rand(1, 24));

                if (in_array($status, ['contactado', 'cita_programada'])) {
                    $followUpDate = (clone $contactedAt)->addDays(rand(1, 5));
                    $agentComments = $faker->optional(0.7)->sentence(8);
                } elseif ($status === 'cliente_nuevo') {
                    $agentComments = $faker->randomElement($successComments);
                } elseif ($status === 'no_concretado') {
                    $agentComments = $faker->randomElement($rejectionReasons);
                }
            }

            // Parámetros UTM según la fuente de tráfico
            $isGoogleAds = ($source === 'google_ads');
            $isWhatsApp = ($source === 'whatsapp_directo');

            Lead::create([
                // Atribución de WhatsApp y Sesión
                'session_token'       => $faker->boolean(65) ? Str::uuid()->toString() : null,
                'whatsapp_matched_at' => ($isWhatsApp || $faker->boolean(20)) ? (clone $createdAt)->addMinutes(rand(1, 15)) : null,

                // Datos del Prospecto
                'name'         => $faker->name(),
                'phone'        => $phone,
                'email'        => $faker->unique()->safeEmail(),
                'city'         => $city,
                'age'          => $faker->numberBetween(6, 70),
                'patient_type' => $faker->randomElement($patientTypes),
                'main_symptom' => $landingData['symptom'],

                // Detalles de Síntomas
                'main_symptoms'       => [$landingData['symptom']],
                'symptom_description' => $faker->optional(0.85)->sentence(10),

                // Gestión Comercial y Estado
                'status'         => $status,
                'user_id'        => $assignedAgent?->id,
                'contacted_at'   => $contactedAt,
                'follow_up_date' => $followUpDate,
                'agent_comments' => $agentComments,

                // Atribución de Canal y Landing Page
                'source'         => $source,
                'landing_origin' => $landingData['landing_origin'],
                'landing_page'   => $landingData['url'],

                // Parámetros de Publicidad
                'utm_source'   => $isGoogleAds ? 'google' : ($isWhatsApp ? 'whatsapp' : 'organic'),
                'utm_medium'   => $isGoogleAds ? 'cpc' : ($isWhatsApp ? 'social' : 'organic'),
                'utm_campaign' => $isGoogleAds ? $campaignName : null,
                'utm_term'     => $isGoogleAds ? strtolower(explode(' ', $landingData['symptom'])[0]) : null,
                'utm_content'  => $isGoogleAds ? 'ad_responsive_v' . rand(1, 3) : null,
                'gclid'        => $isGoogleAds ? 'TeSt_GCLID_' . Str::random(25) : null,

                // Auditoría y Metadatos
                'client_timestamp' => $createdAt,
                'ip_address'       => $faker->ipv4(),
                'user_agent'       => $faker->userAgent(),

                'created_at' => $createdAt,
                'updated_at' => $contactedAt ?? $createdAt,
            ]);
        }
    }
}