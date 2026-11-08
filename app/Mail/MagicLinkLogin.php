<?php

namespace App\Mail;

use App\Models\UserLoginToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkLogin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $codice, public UserLoginToken $loginToken) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Accedi al pannello — Croce Rossa Italiana',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link-login',
            with: [
                'nomeUtente' => $this->loginToken->user->name,
                'codice'     => $this->codice,
                'linkLogin'  => route('admin-login.magic', $this->loginToken->token),
                'disabilitaPassword' => $this->loginToken->user->password !== null,
            ],
        );
    }
}
