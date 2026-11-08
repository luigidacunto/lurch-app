<?php

namespace App\Mail;

use App\Models\EmailVerifica;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificaEmailSocio extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $codice, public EmailVerifica $verifica) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Conferma il tuo indirizzo email — Croce Rossa Italiana',
        );
    }

    public function content(): Content
    {
        $target = $this->verifica->target();

        return new Content(
            view: 'emails.verifica-email-socio',
            with: [
                'nomeSocio'    => trim($target->nome . ' ' . $target->cognome),
                'codice'       => $this->codice,
                'linkVerifica' => route('email.verifica', $this->verifica->token),
            ],
        );
    }
}
