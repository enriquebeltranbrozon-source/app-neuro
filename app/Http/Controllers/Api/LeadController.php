<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Jobs\SendTelegramLeadNotification;
use App\Models\Lead;
use App\Notifications\NewLeadReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class LeadController extends Controller
{
    /**
     * Guarda un lead recibido desde el sitio web o landings.
     * Siempre crea un nuevo registro independiente en la base de datos.
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $sanitized = $this->sanitizeInputs($validated);

            // Valores por defecto y metadata
            $sanitized['source'] = $sanitized['source'] ?? 'web';
            $sanitized['status'] = $sanitized['status'] ?? 'abierto';
            $sanitized['ip_address'] = $request->ip();
            $sanitized['user_agent'] = $request->userAgent();

            // Limpieza estricta de teléfono (solo dígitos)
            if (!empty($sanitized['phone'])) {
                $sanitized['phone'] = preg_replace('/\D/', '', $sanitized['phone']);
            }

            // Generar siempre un token de sesión único para garantizar un registro nuevo
            $sanitized['session_token'] = Lead::generateUniqueToken('NFC');

            // Creación directa del registro (Sin sobrescribir ni buscar stubs anteriores)
            $lead = Lead::create($sanitized);

            // Notificación asíncrona a prueba de fallos
            $this->dispatchNotificationSafely($lead, false);

            return response()->json([
                'success' => true,
                'message' => 'Lead capturado con éxito.',
                'token'   => $lead->session_token,
                'data'    => [
                    'id'            => $lead->id,
                    'session_token' => $lead->session_token,
                    'created_at'    => $lead->created_at?->toISOString(),
                ],
            ], 201);

        } catch (Throwable $e) {
            Log::error('Error crítico al guardar lead: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'payload'   => $request->all(),
                'ip'        => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud.',
            ], 500);
        }
    }

    /**
     * Genera un token de atribución único antes de redirigir al usuario a WhatsApp.
     */
    public function generateWhatsappToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'landing_origin' => 'nullable|string|max:255',
                'landing_page'   => 'nullable|string|max:255',
                'source'         => 'nullable|string|max:255',
                'utm_source'     => 'nullable|string|max:255',
                'utm_medium'     => 'nullable|string|max:255',
                'utm_campaign'   => 'nullable|string|max:255',
                'utm_term'       => 'nullable|string|max:255',
                'gclid'          => 'nullable|string|max:255',
                'gbraid'         => 'nullable|string|max:255',
                'wbraid'         => 'nullable|string|max:255',
            ]);

            $token = Lead::generateUniqueToken('NFC-WA');

            $lead = Lead::create(array_merge($validated, [
                'session_token' => $token,
                'source'        => $validated['source'] ?? 'whatsapp_button',
                'ip_address'    => $request->ip(),
                'user_agent'    => $request->userAgent(),
            ]));

            return response()->json([
                'success' => true,
                'token'   => $lead->session_token,
                'message' => 'Token de WhatsApp generado exitosamente.',
            ], 200);

        } catch (Throwable $e) {
            Log::error('Error en generateWhatsappToken: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'payload'   => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al generar el código de atención.',
            ], 500);
        }
    }

    /**
     * Sanitiza entradas de texto y arreglos de forma recursiva.
     */
    private function sanitizeInputs(array $data): array
    {
        return collect($data)->map(function ($value) {
            if (is_string($value)) {
                return trim(strip_tags($value));
            }
            if (is_array($value)) {
                return $this->sanitizeInputs($value);
            }
            return $value;
        })->toArray();
    }

    /**
     * Ejecuta el envío de notificaciones sin bloquear la respuesta HTTP de la API.
     */
    private function dispatchNotificationSafely(Lead $lead, bool $isMatched = false): void
    {
        // 1. Notificación por Correo
        try {
            $recipient = config('mail.from.address', 'ventas@tudominio.com');
            Notification::route('mail', $recipient)->notify(new NewLeadReceived($lead));
        } catch (Throwable $notifError) {
            Log::warning('No se pudo enviar correo del lead #' . $lead->id . ': ' . $notifError->getMessage());
        }

        // 2. Notificación por Telegram
        try {
            SendTelegramLeadNotification::dispatch($lead, $isMatched);
        } catch (Throwable $telegramError) {
            Log::warning('No se pudo despachar Job de Telegram del lead #' . $lead->id . ': ' . $telegramError->getMessage());
        }
    }
}