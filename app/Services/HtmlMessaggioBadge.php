<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Filtro dei messaggi anonimi del badge (endpoint pubblico senza login, mostrati nella PWA del
 * socio). Restano solo le formattazioni prodotte dall'editor (paragrafi, grassetto, corsivo,
 * sottolineato, elenchi) senza alcun attributo: niente script, eventi, stili, link o immagini.
 * Gli altri tag vengono tolti ma il loro testo resta, come testo semplice.
 * Più restrittivo di Str::sanitizeHtml() di Filament, che ammette style, link e immagini esterne.
 */
class HtmlMessaggioBadge
{
    private const ELEMENTI = ['p', 'br', 'strong', 'em', 'u', 'ol', 'ul', 'li'];

    public static function pulisci(?string $html): string
    {
        static $sanitizer = null;

        $sanitizer ??= new HtmlSanitizer(array_reduce(
            self::ELEMENTI,
            fn (HtmlSanitizerConfig $config, string $elemento) => $config->allowElement($elemento),
            (new HtmlSanitizerConfig)
                ->defaultAction(HtmlSanitizerAction::Block)
                ->dropElement('script')
                ->dropElement('style')
                ->withMaxInputLength(20000),
        ));

        return $sanitizer->sanitize($html ?? '');
    }

    /** true se il messaggio non contiene testo visibile dopo il filtro. */
    public static function vuoto(string $htmlPulito): bool
    {
        return trim(html_entity_decode(strip_tags($htmlPulito), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '';
    }
}
