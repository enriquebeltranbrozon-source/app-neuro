<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramLeadNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public bool $isMatched = false
    ) {}

    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId   = config('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            Log::warning('Notificación de Telegram omitida: Faltan credenciales en config/services.php o .env');
            return;
        }

        $header = $this->isMatched
            ? "⚡ *¡WhatsApp Emparejado con Formulario!*"
            : "🎯 *Nuevo Lead Registrado*";

        $message  = "{$header}\n\n";
        $message .= "👤 *Nombre:* " . $this->escapeMarkdown($this->lead->name ?? 'N/A') . "\n";
        $message .= "📞 *Teléfono:* `" . ($this->lead->phone ?? 'N/A') . "`\n";

        if (!empty($this->lead->email)) {
            $message .= "✉️ *Email:* " . $this->escapeMarkdown($this->lead->email) . "\n";
        }
        if (!empty($this->lead->city)) {
            $message .= "📍 *Ciudad:* " . $this->escapeMarkdown($this->lead->city) . "\n";
        }
        if (!empty($this->lead->main_symptom)) {
            $message .= "🧠 *Síntoma:* " . $this->escapeMarkdown($this->lead->main_symptom) . "\n";
        }

        if (!empty($this->lead->landing_origin)) {
            $message .= "🌐 *Origen:* `" . $this->lead->landing_origin . "`\n";
        }
        if (!empty($this->lead->source)) {
            $message .= "📢 *Fuente:* `" . $this->lead->source . "`\n";
        }
        if (!empty($this->lead->utm_campaign)) {
            $message .= "📊 *Campaña:* " . $this->escapeMarkdown($this->lead->utm_campaign) . "\n";
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id'                  => $chatId,
                'text'                     => $message,
                'parse_mode'               => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::error('Error al enviar notificación a Telegram: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error('Excepción al enviar notificación a Telegram: ' . $e->getMessage());
        }
    }

    private function escapeMarkdown(?string $text): string
    {
        if (!$text) return '';
        return str_replace(['_', '*', '`', '['], ['\_', '\*', '\`', '\['], $text);
    }
}