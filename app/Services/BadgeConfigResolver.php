<?php

namespace App\Services;

use App\Models\Evento;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** Config posizioni/font e sfondo badge, condivisi tra generazione PDF (corsisti/staff)
 *  e anteprima in modale su GestioneEvento — stessa logica, mai duplicata. */
class BadgeConfigResolver
{
    public static function resolveConfig(?Evento $evento): array
    {
        $stored = $evento?->badge_config ?? [];

        $defaults = [
            'titolo_evento' => ['attivo' => false, 'x' => 4, 'y' => 25, 'font_size' => 14],
            'nome_cognome'  => ['x' => 5,  'y' => 50, 'font_size' => 14],
            'corso_ruolo'   => ['x' => 5,  'y' => 40, 'font_size' => 9],
            'qr'            => ['x' => 16, 'y' => 66, 'size'      => 28],
        ];

        $config = array_replace_recursive($defaults, is_array($stored) ? $stored : []);

        $config['titolo_evento']['width'] = max(10, 61 - 2 * ($config['titolo_evento']['x']));
        $config['nome_cognome']['width']  = max(10, 61 - 2 * ($config['nome_cognome']['x']));
        $config['corso_ruolo']['width']   = max(10, 61 - 2 * ($config['corso_ruolo']['x']));

        return $config;
    }

    public static function sfondoBase64(?Evento $evento): ?string
    {
        $media = $evento?->getFirstMedia('badge_sfondo');
        if (! $media || ! file_exists($media->getPath())) {
            return null;
        }

        return 'data:' . $media->mime_type . ';base64,' . base64_encode(file_get_contents($media->getPath()));
    }

    /** Toggle "Includi il QR di check-in nella comunicazione evento via email" (Section Badge di EditEvento). */
    public static function qrCheckinInEmail(?Evento $evento): bool
    {
        return (bool) ($evento?->badge_config['qr_checkin_in_email'] ?? false);
    }

    /**
     * PNG (binario) del QR code di check-in per una riga iscrizione, da embeddare INLINE
     * nel corpo della comunicazione evento via email (immagine CID, non allegato/PDF).
     * `$token` = badge_token della riga `iscrizioni`/`iscrizioni_staff`; l'URL è lo stesso
     * `badge.portale` dei QR stampati sui badge fisici — nessun dato personale in chiaro.
     * chillerlan/php-qrcode (già in stack via Filament) + GD, nessun file su disco.
     */
    public static function qrCheckinPng(Evento $evento, string $token): string
    {
        $url = route('badge.portale', ['evento' => $evento->id, 'token' => $token]);

        // outputType (non outputInterface: quello vale solo con outputType=CUSTOM) —
        // default della lib è MARKUP_SVG, va forzato PNG per l'embed CID nella mail.
        $qr = new QRCode(new QROptions([
            'outputType'       => QROutputInterface::GDIMAGE_PNG,
            'outputBase64'     => false,
            'eccLevel'         => EccLevel::M,
            'scale'            => 8,
            'imageTransparent' => false,
            'quietzoneSize'    => 2,
        ]));

        return $qr->render($url);
    }
}
