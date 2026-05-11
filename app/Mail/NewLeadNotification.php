<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLeadNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $lead; // Esto permite usar $lead en la plantilla Blade

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🚨 Nuevo Prospecto: ' . $this->lead->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.leads.notification',
        );
    }
}
