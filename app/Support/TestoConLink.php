<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Testo libero scritto dagli organizzatori (diciture di autocertificazione e documento aggiuntivo) con gli
 * indirizzi web resi cliccabili: al posto dell'indirizzo intero un link «Apri il link (dominio)» e un pulsante
 * «Copia indirizzo». Tutto il resto del testo è escapato; stili inline perché serve sia ai moduli Tailwind sia all'app.
 */
class TestoConLink
{
    public static function html(?string $testo): HtmlString
    {
        $parti = preg_split('~(https?://[^\s<>"]+)~u', (string) $testo, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';

        foreach ($parti as $i => $parte) {
            if ($i % 2 === 0) {
                $html .= e($parte);
                continue;
            }

            // La punteggiatura in coda appartiene alla frase, non all'indirizzo.
            $url  = rtrim($parte, '.,;:!?)]}\'');
            $coda = substr($parte, strlen($url));
            $host = parse_url($url, PHP_URL_HOST) ?: 'link';

            $html .= '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer"'
                . ' style="color:#b91c1c;font-weight:600;text-decoration:underline;">Apri il link (' . e($host) . ')</a>'
                . ' <button type="button" data-url="' . e($url) . '"'
                . ' onclick="var b=this;navigator.clipboard.writeText(b.dataset.url).then(function(){b.textContent=\'Copiato\';setTimeout(function(){b.textContent=\'Copia indirizzo\'},2000)})"'
                . ' style="font-size:11px;font-weight:600;color:#374151;background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:1px 8px;cursor:pointer;white-space:nowrap;">Copia indirizzo</button>'
                . e($coda);
        }

        return new HtmlString($html);
    }
}
