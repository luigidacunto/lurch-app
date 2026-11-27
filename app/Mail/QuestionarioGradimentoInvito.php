<?php

namespace App\Mail;

use App\Models\Iscrizione;
use App\Models\QuestionarioGradimento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invito al questionario di gradimento: testo fisso (nessun template modificabile). Il link porta all'app dei soci,
 * dove si compila dopo il login: nessun token nell'indirizzo.
 */
class QuestionarioGradimentoInvito extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Iscrizione $iscrizione,
        public QuestionarioGradimento $questionario,
        public bool $fraseEsame,
    ) {}

    public function envelope(): Envelope
    {
        $corso = $this->iscrizione->corso;

        return new Envelope(subject: 'Questionario di gradimento – ' . ($corso->numero_protocollo ?: $corso->nome));
    }

    public function content(): Content
    {
        $corso  = $this->iscrizione->corso;
        $evento = $corso->evento;

        return new Content(markdown: 'emails.questionario-gradimento', with: [
            'partecipante' => $this->iscrizione->partecipante,
            'corso'        => $corso,
            'evento'       => $evento,
            'chiudeIl'     => $this->questionario->chiude_il,
            'link'         => \App\Services\PortaleRegionale::route($evento->comitato_regionale_id, 'app.questionario', $this->questionario->id),
            'linkApp'      => \App\Services\PortaleRegionale::route($evento->comitato_regionale_id, 'app.home'),
            'fraseEsame'   => $this->fraseEsame,
        ]);
    }
}
