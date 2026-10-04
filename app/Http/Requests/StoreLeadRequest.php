<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara, sanitiza y normaliza los datos del payload antes de aplicar las reglas.
     */
    protected function prepareForValidation(): void
    {
        // 1. Normalización de Teléfono (Limpia no-dígitos y recorta prefijos de México +52 / 52 / 521)
        $phone = $this->phone ? preg_replace('/\D/', '', (string) $this->phone) : null;
        if ($phone) {
            if (strlen($phone) === 12 && str_starts_with($phone, '52')) {
                $phone = substr($phone, 2);
            } elseif (strlen($phone) === 13 && str_starts_with($phone, '521')) {
                $phone = substr($phone, 3);
            }
        }

        // 2. Normalización de Correo
        $email = $this->email ? strtolower(trim((string) $this->email)) : null;

        // 3. Sanitizador de cadenas (Remueve HTML, espacios extra y previene conversiones fallidas de Arrays)[cite: 7]
        $cleanString = fn ($value) => is_string($value) && trim($value) !== '' ? trim(strip_tags($value)) : null;

        // 4. Sanitización especial para arreglos de síntomas múltiples
        $mainSymptoms = is_array($this->main_symptoms)
            ? array_values(array_filter(array_map($cleanString, $this->main_symptoms)))
            : null;

        $this->merge([
            'phone'               => $phone,
            'email'               => $email,
            'name'                => $cleanString($this->name),
            'city'                => $cleanString($this->city),
            'patient_type'        => $cleanString($this->patient_type),
            'main_symptom'        => $cleanString($this->main_symptom),
            'main_symptoms'       => $mainSymptoms,
            'symptom_description' => $cleanString($this->symptom_description),
            
            // Fallbacks de atribución para evitar pérdida de prospectos[cite: 7]
            'landing_origin'      => $cleanString($this->landing_origin) ?? 'web_form',
            'source'              => $cleanString($this->source) ?? 'organic',
            'landing_page'        => $cleanString($this->landing_page),
            
            // Sanitización de Parámetros UTM / Ads[cite: 7]
            'utm_source'          => $cleanString($this->utm_source),
            'utm_medium'          => $cleanString($this->utm_medium),
            'utm_campaign'        => $cleanString($this->utm_campaign),
            'utm_term'            => $cleanString($this->utm_term),
            'utm_content'         => $cleanString($this->utm_content),
            'gclid'               => $cleanString($this->gclid),
            'gbraid'              => $cleanString($this->gbraid),
            'wbraid'              => $cleanString($this->wbraid),
        ]);
    }

    public function rules(): array
    {
        return [
            // Datos del Formulario
            'patient_type'        => ['nullable', 'string', 'max:50'],
            'age'                 => ['nullable', 'integer', 'between:1,120'],
            'main_symptom'        => ['nullable', 'string', 'max:255'],
            'main_symptoms'       => ['nullable', 'array'],
            'main_symptoms.*'     => ['string', 'max:255'],
            'symptom_description' => ['nullable', 'string', 'max:2000'],
            'city'                => ['nullable', 'string', 'max:255'],
            'name'                => ['required', 'string', 'min:3', 'max:255'],
            'phone'               => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'email'               => ['nullable', 'email:rfc,dns', 'max:255'],

            // Contexto y Atribución[cite: 7]
            'landing_origin'      => ['required', 'string', 'max:100'],
            'landing_page'        => ['nullable', 'string', 'max:255'],
            'source'              => ['required', 'string', 'max:50'],
            'session_token'       => ['nullable', 'string', 'max:64'],
            'client_timestamp'    => ['nullable', 'date'],

            // Parámetros UTM / Ads[cite: 7]
            'utm_source'          => ['nullable', 'string', 'max:255'],
            'utm_medium'          => ['nullable', 'string', 'max:255'],
            'utm_campaign'        => ['nullable', 'string', 'max:255'],
            'utm_term'            => ['nullable', 'string', 'max:255'],
            'utm_content'         => ['nullable', 'string', 'max:255'],
            'gclid'               => ['nullable', 'string', 'max:255'],
            'gbraid'              => ['nullable', 'string', 'max:255'],
            'wbraid'              => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'El nombre completo es obligatorio.',
            'name.min'                => 'El nombre debe tener al menos 3 caracteres.',
            'phone.required'          => 'El número telefónico es obligatorio.',
            'phone.regex'             => 'El teléfono debe ser un número celular válido de 10 dígitos.',
            'email.email'             => 'Ingresa una dirección de correo electrónico válida.',
            'landing_origin.required' => 'El origen del formulario es requerido.',
            'source.required'         => 'La fuente de tráfico es requerida.',
        ];
    }
}