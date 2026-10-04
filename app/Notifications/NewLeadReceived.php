<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLeadReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $token = $this->lead->session_token ?? 'Sin Token';
        
        // Formateo seguro de síntomas (soporta array de checkboxes o string único)
        $symptomsList = !empty($this->lead->main_symptoms) && is_array($this->lead->main_symptoms)
            ? implode(', ', $this->lead->main_symptoms)
            : ($this->lead->main_symptom ?? 'No especificado');

        $mail = (new MailMessage)
            ->subject("🚨 Lead [{$token}]: " . ($this->lead->name ?? 'Contacto Web'))
            ->greeting("¡Hola! Se ha recibido un nuevo registro de prospecto.")
            ->line("**Token de Atención:** {$token}")
            ->line("**Nombre:** " . ($this->lead->name ?? 'No especificado'))
            ->line("**Teléfono:** " . ($this->lead->phone ?? 'Pendiente WhatsApp'))
            ->line("**Correo:** " . ($this->lead->email ?? 'No especificado'))
            ->line("**Ciudad / Ubicación:** " . ($this->lead->city ?? 'No especificado'))
            ->line("**Tipo de Paciente:** " . ($this->lead->patient_type ?? 'No especificado'))
            ->line("**Síntomas:** {$symptomsList}");

        if (!empty($this->lead->symptom_description)) {
            $mail->line("**Descripción del síntoma:** " . $this->lead->symptom_description);
        }

        $mail->line("---")
            ->line("**Canal:** " . strtoupper($this->lead->source ?? 'web'))
            ->line("**Landing de Origen:** " . ($this->lead->landing_origin ?? 'N/A') . " (" . ($this->lead->landing_page ?? '/') . ")")
            ->line("**Campaña / UTM:** " . ($this->lead->utm_campaign ?? 'Orgánico / Directo'));

        $dashboardUrl = config('app.url') . ($this->lead->id ? "/admin/leads/{$this->lead->id}" : '/admin/leads');

        return $mail->action('Ver Lead en Dashboard', $dashboardUrl);
    }
}