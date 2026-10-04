<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramLeadNotification;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LeadIngestionController extends Controller
{
    /**
     * Recibe e ingesta prospectos en tiempo real desde formularios de landing page.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                => ['required', 'string', 'max:255'],
            'phone'               => ['required', 'string', 'max:20'],
            'email'               => ['nullable', 'email', 'max:255'],
            'city'                => ['nullable', 'string', 'max:255'],
            'age'                 => ['nullable', 'integer', 'min:1', 'max:120'],
            'patient_type'        => ['nullable', 'string', 'max:255'],
            'main_symptom'        => ['nullable', 'string', 'max:255'],
            'symptom_description' => ['nullable', 'string', 'max:2000'],
            
            // Atribución Publicitaria y Landing Pages
            'source'              => ['nullable', 'string', 'max:255'],
            'landing_origin'      => ['nullable', 'string', 'max:255'],
            'landing_key'         => ['nullable', 'string', 'max:255'],
            'landing_page'        => ['nullable', 'string', 'max:500'],
            'utm_source'          => ['nullable', 'string', 'max:255'],
            'utm_medium'          => ['nullable', 'string', 'max:255'],
            'utm_campaign'        => ['nullable', 'string', 'max:255'],
            'utm_term'            => ['nullable', 'string', 'max:255'],
            'utm_content'         => ['nullable', 'string', 'max:255'],
            'gclid'               => ['nullable', 'string', 'max:255'],
            'gbraid'              => ['nullable', 'string', 'max:255'],
            'wbraid'              => ['nullable', 'string', 'max:255'],
            'session_token'       => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación en los datos enviados.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            // 1. Sanitización de teléfono
            $cleanPhone = $this->sanitizePhone($data['phone']);

            // 2. Mapeo e inferencia de origen de landing (Soporta landing_origin o landing_key)
            $originKey = $data['landing_origin'] ?? $data['landing_key'] ?? null;
            $landingOrigin = $originKey ?? $this->inferLandingOrigin(
                $data['landing_page'] ?? null, 
                $data['main_symptom'] ?? null
            );

            // 3. Conciliación exclusiva con borradores pendientes de WhatsApp
            $existingLead = null;

            if (!empty($data['session_token'])) {
                $existingLead = Lead::where('session_token', $data['session_token'])
                    ->where('phone', 'LIKE', 'PENDIENTE_%')
                    ->where('created_at', '>=', Carbon::now()->subHours(24))
                    ->latest()
                    ->first();
            }

            // 4. Determinación de fuente
            $utmSource = $data['utm_source'] ?? null;
            $defaultSource = ($utmSource === 'google') ? 'google_ads' : 'web_organico';
            $source = $data['source'] ?? $defaultSource;

            // 5. Transacción de datos
            $result = DB::transaction(function () use ($data, $cleanPhone, $landingOrigin, $source, $existingLead, $request) {
                
                if ($existingLead) {
                    $updatePayload = [
                        'name'                => strip_tags(trim($data['name'])),
                        'phone'               => $cleanPhone,
                        'email'               => $data['email'] ?? $existingLead->email,
                        'city'                => !empty($data['city']) ? strip_tags(trim($data['city'])) : $existingLead->city,
                        'age'                 => $data['age'] ?? $existingLead->age,
                        'patient_type'        => $data['patient_type'] ?? $existingLead->patient_type,
                        'main_symptom'        => $data['main_symptom'] ?? $existingLead->main_symptom,
                        'symptom_description' => !empty($data['symptom_description']) ? strip_tags(trim($data['symptom_description'])) : $existingLead->symptom_description,
                        'landing_origin'      => $landingOrigin ?? $existingLead->landing_origin,
                        'landing_page'        => $data['landing_page'] ?? $existingLead->landing_page,
                        'utm_source'          => $data['utm_source'] ?? $existingLead->utm_source,
                        'utm_medium'          => $data['utm_medium'] ?? $existingLead->utm_medium,
                        'utm_campaign'        => $data['utm_campaign'] ?? $existingLead->utm_campaign,
                        'utm_term'            => $data['utm_term'] ?? $existingLead->utm_term,
                        'utm_content'         => $data['utm_content'] ?? $existingLead->utm_content,
                        'gclid'               => $data['gclid'] ?? $existingLead->gclid,
                        'gbraid'              => $data['gbraid'] ?? $existingLead->gbraid,
                        'wbraid'              => $data['wbraid'] ?? $existingLead->wbraid,
                        'source'              => $source,
                        'whatsapp_matched_at' => now(),
                    ];

                    $existingLead->update($updatePayload);

                    return [
                        'lead'      => $existingLead,
                        'isMatched' => true,
                        'updated'   => true,
                    ];
                }

                // Inserción limpia de un nuevo Lead
                $lead = Lead::create([
                    'name'                => strip_tags(trim($data['name'])),
                    'phone'               => $cleanPhone,
                    'email'               => $data['email'] ?? null,
                    'city'                => !empty($data['city']) ? strip_tags(trim($data['city'])) : null,
                    'age'                 => $data['age'] ?? null,
                    'patient_type'        => $data['patient_type'] ?? null,
                    'main_symptom'        => $data['main_symptom'] ?? null,
                    'symptom_description' => !empty($data['symptom_description']) ? strip_tags(trim($data['symptom_description'])) : null,
                    'status'              => 'abierto',
                    'source'              => $source,
                    'landing_origin'      => $landingOrigin,
                    'landing_page'        => $data['landing_page'] ?? null,
                    'utm_source'          => $data['utm_source'] ?? null,
                    'utm_medium'          => $data['utm_medium'] ?? null,
                    'utm_campaign'        => $data['utm_campaign'] ?? null,
                    'utm_term'            => $data['utm_term'] ?? null,
                    'utm_content'         => $data['utm_content'] ?? null,
                    'gclid'               => $data['gclid'] ?? null,
                    'gbraid'              => $data['gbraid'] ?? null,
                    'wbraid'              => $data['wbraid'] ?? null,
                    'session_token'       => $data['session_token'] ?? Str::uuid()->toString(),
                    'ip_address'          => $request->ip(),
                    'user_agent'          => $request->userAgent(),
                ]);

                return [
                    'lead'      => $lead,
                    'isMatched' => false,
                    'updated'   => false,
                ];
            });

            // 6. Notificación
            $this->dispatchTelegramSafely($result['lead'], $result['isMatched']);

            return response()->json([
                'success' => true,
                'message' => $result['updated'] ? 'Prospecto actualizado exitosamente.' : 'Prospecto registrado exitosamente.',
                'lead_id' => $result['lead']->id,
            ], $result['updated'] ? 200 : 201);

        } catch (\Throwable $e) {
            Log::error('Error registrando Lead: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'payload'   => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error interno al procesar su solicitud.',
            ], 500);
        }
    }

    /**
     * Sanitiza y limpia el número de teléfono a 10 dígitos.
     */
    private function sanitizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($digits) > 10 && str_starts_with($digits, '52')) {
            $digits = substr($digits, 2);
        }

        return substr($digits, -10);
    }

    /**
     * Infiere la clave de la landing page según la URL o el síntoma.
     */
    private function inferLandingOrigin(?string $url, ?string $symptom): string
    {
        if ($url) {
            $path = parse_url($url, PHP_URL_PATH);
            if ($path === '/depresion') return 'landing_depresion';
            if ($path === '/ansiedad') return 'landing_ansiedad';
            if ($path === '/problemas-aprendizaje') return 'landing_aprendizaje';
            if ($path === '/deficit') return 'landing_deficit';
            if ($path === '/' || empty($path)) return 'home-page';
        }

        if ($symptom) {
            $s = strtolower($symptom);
            if (str_contains($s, 'depresi')) return 'landing_depresion';
            if (str_contains($s, 'ansiedad')) return 'landing_ansiedad';
            if (str_contains($s, 'aprendizaje')) return 'landing_aprendizaje';
            if (str_contains($s, 'déficit') || str_contains($s, 'deficit') || str_contains($s, 'tdah')) return 'landing_deficit';
        }

        return 'home-page';
    }

    /**
     * Despacha la notificación por Telegram de forma aislada a la transacción.
     */
    private function dispatchTelegramSafely(Lead $lead, bool $isMatched = false): void
    {
        try {
            SendTelegramLeadNotification::dispatch($lead, $isMatched);
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar la notificación a Telegram del lead #' . $lead->id . ': ' . $e->getMessage());
        }
    }
}