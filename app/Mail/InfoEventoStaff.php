<?php

namespace App\Mail;

use App\Models\Evento;
use App\Models\IscrizioneStaff;
use App\Services\BadgeConfigResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InfoEventoStaff extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public IscrizioneStaff $iscrizione) {}

    public function envelope(): Envelope
    {
        $evento = $this->iscrizione->evento;

        return new Envelope(
            subject: 'Informazioni evento — ' . $evento->nome,
        );
    }

    public function content(): Content
    {
        $evento       = $this->iscrizione->evento;
        $corpo        = $evento->note_app ?? '';
        $nomeEvento   = $evento->nome;
        $comitatoNome = $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore';
        $referenti    = $evento->referenti()->where('attivo', true)->get();

        // Nei Mailable usiamo l'URL pubblico: i client email bloccano le data: URI
        $intestazioneSrc = $evento->getFirstMedia('intestazione_app')?->getUrl();

        $dateTesto      = Evento::formattaGiorni($evento->giorniEvento());
        $sede          = $evento->sede;
        $indirizzo      = $evento->indirizzo;
        $coordinateMaps = $evento->coordinate_maps;

        // QR check-in inline nel corpo (immagine CID, vedi emails/info-evento.blade.php).
        $allegaBadge         = BadgeConfigResolver::qrCheckinInEmail($evento);
        $qrCheckinPng        = $allegaBadge
            ? BadgeConfigResolver::qrCheckinPng($evento, $this->iscrizione->badge_token)
            : null;
        $qrCheckinPreviewSrc = null;

        return new Content(
            view: 'emails.info-evento',
            with: compact('corpo', 'nomeEvento', 'comitatoNome', 'referenti', 'intestazioneSrc',
                          'dateTesto', 'sede', 'indirizzo', 'coordinateMaps',
                          'allegaBadge', 'qrCheckinPng', 'qrCheckinPreviewSrc'),
        );
    }

    public function attachments(): array
    {
        $evento = $this->iscrizione->evento;

        return $evento->getMedia('documenti')
            ->filter(fn ($m) => file_exists($m->getPath()))
            ->map(fn ($m) => Attachment::fromPath($m->getPath())
                ->as($m->file_name)
                ->withMime($m->mime_type))
            ->values()
            ->all();
    }
}
