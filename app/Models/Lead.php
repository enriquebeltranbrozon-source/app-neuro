<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory;

    /**
     * Valores por defecto para atributos del modelo.
     */
    protected $attributes = [
        'status' => 'nuevo',
        'source' => 'web',
    ];

    protected $guarded = []; // Permite la asignación masiva de todos los campos validados por el controlador

    /**
     * Campos habilitados para asignación masiva[cite: 3, 6].
     */
    protected $fillable = [
        // Datos del Prospecto[cite: 3, 6]
        'name',
        'phone',
        'email',
        'city',
        'age',
        'patient_type',
        'main_symptom',       // Categoría Principal (string)[cite: 3, 6]
        'main_symptoms',      // Arreglo de padecimientos (JSON)[cite: 3, 6]
        'symptom_description',
        
        // Estado y Asignación Comercial[cite: 3, 6]
        'status',
        'user_id',            // ID del Agente asignado[cite: 3, 6]
        'contacted_at',
        'follow_up_date',
        'agent_comments',
        
        // Origen y Atribución Publicitaria (Google Ads / Meta / Astro)[cite: 3, 6]
        'source',
        'landing_origin',
        'landing_page',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'gbraid',
        'wbraid',
        
        // Atribución de WhatsApp y Sesión[cite: 3, 6]
        'session_token',
        'whatsapp_matched_at',

        // Auditoría y Metadatos de Red[cite: 3, 6]
        'client_timestamp',
        'ip_address',
        'user_agent',
    ];

    /**
     * Casteo automático de tipos para Eloquent[cite: 3, 6].
     */
    protected function casts(): array
    {
        return [
            'age'                 => 'integer',
            'user_id'             => 'integer',
            'main_symptoms'       => 'array',
            'contacted_at'        => 'datetime',
            'whatsapp_matched_at' => 'datetime',
            'client_timestamp'    => 'datetime',
            'follow_up_date'      => 'date',
        ];
    }

    /**
     * Genera un session_token único garantizado (Ej: NFC-A8X92K)[cite: 3, 6].
     */
    public static function generateUniqueToken(string $prefix = 'NFC'): string
    {
        do {
            $token = $prefix . '-' . strtoupper(Str::random(6));
        } while (static::where('session_token', $token)->exists());

        return $token;
    }

    // --- MUTADORES (Sanitización y Blindaje)[cite: 3, 6] ---

    /**
     * Normaliza el teléfono dejando solo 10 dígitos o preservando 'PENDIENTE_'[cite: 3, 6].
     */
    public function setPhoneAttribute(?string $value): void
    {
        if (is_null($value) || trim($value) === '') {
            $this->attributes['phone'] = null;
            return;
        }

        $trimmed = trim(strip_tags($value));

        // Excepción explícita: Preservar identificadores de WhatsApp no asociados
        if (str_starts_with(strtoupper($trimmed), 'PENDIENTE')) {
            $this->attributes['phone'] = $trimmed;
            return;
        }

        // Sanitización telefónica a 10 dígitos nacionales
        $digitsOnly = preg_replace('/\D/', '', $trimmed);

        if (strlen($digitsOnly) > 10 && str_starts_with($digitsOnly, '52')) {
            $digitsOnly = substr($digitsOnly, 2);
        }

        $this->attributes['phone'] = !empty($digitsOnly) ? substr($digitsOnly, -10) : null;
    }

    /**
     * Normaliza el correo a minúsculas sin espacios[cite: 3, 6].
     */
    public function setEmailAttribute(?string $value): void
    {
        $cleaned = $value ? strtolower(trim($value)) : null;
        $this->attributes['email'] = !empty($cleaned) ? $cleaned : null;
    }

    /**
     * Saneamiento de XSS en el nombre del prospecto[cite: 3, 6].
     */
    public function setNameAttribute(?string $value): void
    {
        $cleaned = $value ? trim(strip_tags($value)) : null;
        $this->attributes['name'] = !empty($cleaned) ? $cleaned : null;
    }

    // --- HELPER METHODS / ACCESORES ---

    /**
     * Determina si el registro es un clic de WhatsApp pendiente de teléfono real.
     */
    public function isPendingWhatsapp(): bool
    {
        return empty($this->phone) || str_starts_with($this->phone, 'PENDIENTE');
    }

    // --- RELACIONES[cite: 3, 6] ---

    /**
     * Relación con el Agente asignado[cite: 3, 6].
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias de compatibilidad para $lead->user[cite: 3, 6].
     */
    public function user(): BelongsTo
    {
        return $this->agent();
    }

    // --- SCOPES DE CONSULTA Y REPORTE[cite: 3, 6] ---

    public function scopeBySessionToken(Builder $query, string $token): Builder
    {
        return $query->where('session_token', $token);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeFromPaidCampaigns(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNotNull('gclid')
              ->orWhereNotNull('gbraid')
              ->orWhereNotNull('wbraid')
              ->orWhereNotNull('utm_campaign');
        });
    }

    public function scopeUncontacted(Builder $query): Builder
    {
        return $query->whereNull('contacted_at')
                     ->whereIn('status', ['nuevo', 'abierto']);
    }
}