<?php

namespace Tests\Feature\Api;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_captura_exitosamente_un_lead_con_datos_completos_de_atribucion(): void
    {
        $payload = [
            'session_token'  => 'NFC-TEST-100100',
            'name'           => 'Ana Martínez',
            'phone'          => '5598765432',
            'email'          => 'ana@ejemplo.com',
            'main_symptom'   => 'Insomnio',
            'landing_origin' => 'landing-insomnio',
            'source'         => 'web',
            'utm_source'     => 'google',
            'utm_medium'     => 'cpc',
            'utm_campaign'   => 'insomnio_search_2026',
            'gclid'          => 'gclid_test_998877',
        ];

        $response = $this->postJson(route('api.v1.leads.store'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'token'   => 'NFC-TEST-100100',
            ]);

        $this->assertDatabaseHas('leads', [
            'session_token' => 'NFC-TEST-100100',
            'email'         => 'ana@ejemplo.com',
            'gclid'         => 'gclid_test_998877',
        ]);
    }

    public function test_garantiza_la_idempotencia_actualizando_el_lead_si_el_session_token_ya_existe(): void
    {
        $initialPayload = [
            'session_token'  => 'NFC-TEST-200200',
            'name'           => 'Carlos Ruiz',
            'phone'          => '5511223344',
            'email'          => 'carlos@ejemplo.com',
            'source'         => 'web',
            'landing_origin' => 'landing-estres',
        ];

        $this->postJson(route('api.v1.leads.store'), $initialPayload)->assertStatus(201);

        $updatePayload = array_merge($initialPayload, [
            'main_symptom' => 'Estrés Laboral',
            'gclid'        => 'new_gclid_value_123',
        ]);

        $response = $this->postJson(route('api.v1.leads.store'), $updatePayload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'token'   => 'NFC-TEST-200200',
            ]);

        $this->assertEquals(1, Lead::where('session_token', 'NFC-TEST-200200')->count());

        $this->assertDatabaseHas('leads', [
            'session_token' => 'NFC-TEST-200200',
            'main_symptom'  => 'Estrés Laboral',
            'gclid'         => 'new_gclid_value_123',
        ]);
    }

    public function test_retorna_http_422_si_faltan_campos_requeridos_como_source(): void
    {
        $payload = [
            'session_token' => 'NFC-TEST-300300',
            'name'          => 'Usuario Incompleto',
        ];

        $response = $this->postJson(route('api.v1.leads.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    public function test_genera_un_token_de_seguimiento_previo_al_clic_de_whatsapp(): void
    {
        $payload = [
            'landing_origin' => 'landing-estres',
            'source'         => 'whatsapp_button',
        ];

        $response = $this->postJson(route('api.v1.leads.whatsapp-token'), $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
            ]);
    }

    public function test_bloquea_peticiones_cuando_se_excede_el_rate_limit_permitido_por_ip(): void
    {
        $payload = [
            'session_token'  => 'NFC-TEST-400400',
            'source'         => 'web',
            'landing_origin' => 'landing-test',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.v1.leads.store'), $payload);
        }

        $response = $this->postJson(route('api.v1.leads.store'), $payload);

        $response->assertStatus(429);
    }
}