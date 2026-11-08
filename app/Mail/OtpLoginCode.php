<?php

namespace App\Mail;

use App\Models\Socio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpLoginCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $codice,
        public Socio $socio,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Il tuo codice di accesso — Lurch',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp-login',
        );
    }
}
