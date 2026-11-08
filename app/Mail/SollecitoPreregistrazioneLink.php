<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Iscrizione;
use App\Services\TemplatePlaceholders;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SollecitoPreregistrazioneLink extends Mailable
{
    use Queueable, SerializesModels;

    protected ?EmailTemplate $template;
    protected string $scadenza;

    public function __construct(public Iscrizione $iscrizione)
    {
        $corso  = $iscrizione->corso;
        $evento = $corso->evento;
        $this->template = EmailTemplate::risolvi($evento->comitato_regionale_id, 'sollecito_iscritti');
        // Scadenza = data di invio di QUESTO sollecito + 1 giorno. Il sollecito può essere reinviato
        // più volte (a differenza del form iniziale): ogni invio dà un nuovo termine breve, non si
        // riusa più la data del form iniziale (+7gg era eccessivo se il sollecito viene inviato tardi
        // o per errore al posto del form).
        $this->scadenza = now()->addDay()->format('d/m/Y');
    }

    public function envelope(): Envelope
    {
        $corso = $this->iscrizione->corso;

        $soggetto = $this->template
            ? TemplatePlaceholders::risolvi($this->template->soggetto, $this->iscrizione, $this->scadenza)
            : 'Sollecito preregistrazione — ' . $corso->nome;

        $from = $this->template?->from_address
            ? new Address($this->template->from_address, $this->template->from_name ?? config('mail.from.name'))
            : null;

        $replyTo = $this->template?->reply_to
            ? [new Address($this->template->reply_to)]
            : [];

        return new Envelope(
            subject:  $soggetto,
            from:     $from,
            replyTo:  $replyTo,
        );
    }

    public function content(): Content
    {
        $iscrizione = $this->iscrizione;
        $corso      = $iscrizione->corso;
        $evento     = $corso->evento;

        if ($this->template) {
            $corpo        = TemplatePlaceholders::risolvi($this->template->corpo, $iscrizione, $this->scadenza);
            $comitatoNome = $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore';
            $referenti    = $evento->referenti()->where('attivo', true)->get();

            return new Content(
                view: 'emails.template-corpo',
                with: compact('corpo', 'comitatoNome', 'referenti'),
            );
        }

        return new Content(
            markdown: 'emails.form-preregistrazione-link',
        );
    }
}
