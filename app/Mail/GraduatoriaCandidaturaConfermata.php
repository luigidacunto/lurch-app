<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\GraduatoriaCandidato;
use App\Services\TemplatePlaceholders;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GraduatoriaCandidaturaConfermata extends Mailable
{
    use Queueable, SerializesModels;

    protected ?EmailTemplate $template;

    public function __construct(public GraduatoriaCandidato $candidato)
    {
        $this->template = EmailTemplate::risolvi($candidato->graduatoria->comitato_regionale_id, 'graduatoria');
    }

    public function envelope(): Envelope
    {
        $graduatoria = $this->candidato->graduatoria;

        $soggetto = $this->template
            ? TemplatePlaceholders::risolviPerGraduatoria($this->template->soggetto, $this->candidato)
            : 'Candidatura confermata — ' . $graduatoria->titolo;

        $from = $this->template?->from_address
            ? new Address($this->template->from_address, $this->template->from_name ?? config('mail.from.name'))
            : null;

        $replyTo = $this->template?->reply_to
            ? [new Address($this->template->reply_to)]
            : [];

        return new Envelope(
            subject: $soggetto,
            from:    $from,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        $graduatoria = $this->candidato->graduatoria;

        if ($this->template) {
            $corpo = TemplatePlaceholders::risolviPerGraduatoria($this->template->corpo, $this->candidato);
        } else {
            $corpo = '<p>Gentile <strong>' . e($this->candidato->nome) . ' ' . e($this->candidato->cognome) . '</strong>,</p>'
                . '<p>la tua candidatura alla graduatoria è stata <strong>confermata</strong>.</p>'
                . '<p>Graduatoria: <strong>' . e($graduatoria->titolo) . '</strong></p>'
                . (filled($graduatoria->testo_email_conferma) ? '<p>' . nl2br(e($graduatoria->testo_email_conferma)) . '</p>' : '');
        }

        $comitatoNome = $graduatoria->comitatoLocale?->nome ?? $graduatoria->comitatoRegionale?->nome ?? 'Comitato organizzatore';

        return new Content(
            view: 'emails.template-corpo',
            with: ['corpo' => $corpo, 'comitatoNome' => $comitatoNome, 'referenti' => collect()],
        );
    }
}
