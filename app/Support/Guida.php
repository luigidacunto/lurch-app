<?php

namespace App\Support;

use App\Filament\Pages\Guida\GuidaAppSoci;
use App\Filament\Pages\Guida\GuidaModuliPubblici;
use App\Filament\Pages\Guida\GuidaPannello;
use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

/**
 * Guida completa in docs/: una cartella per sezione, dentro file numerati NN-nome.md, eventualmente
 * raggruppati in cartelle NN-gruppo/; immagini in docs/immagini/<argomento>/. Gli stessi file si leggono
 * su GitHub e nel pannello (00-installazione solo su GitHub). Titolo di una pagina = prima riga «# »;
 * titolo di un gruppo = nome della cartella senza numero.
 *
 * Nei .md link e immagini sono relativi (funzionano su GitHub); qui vengono riscritti verso le pagine
 * del pannello, la rotta delle immagini o, per le parti solo su GitHub, config('app.guida_url').
 */
class Guida
{
    /** Percorso di una pagina dentro la sezione, senza .md: «NN-nome» o «NN-gruppo/NN-nome». */
    public const PERCORSO = '#^(\d{2}-[a-z0-9-]+/)?\d{2}-[a-z0-9-]+$#';

    /** Percorso di un'immagine dentro docs/. */
    public const IMMAGINE = '#^immagini/[a-z0-9-]+(/[a-z0-9-]+)*\.(png|jpe?g|webp)$#';

    /** Sezioni lette anche nel pannello → pagina che le mostra. */
    public const PAGINE = [
        '01-pannello'        => GuidaPannello::class,
        '02-app-soci'        => GuidaAppSoci::class,
        '03-moduli-pubblici' => GuidaModuliPubblici::class,
    ];

    public static function radice(): string
    {
        return base_path('docs');
    }

    public static function cartella(string $sezione): string
    {
        return static::radice() . '/' . $sezione;
    }

    /**
     * Indice della sezione: [['titolo' => ?string, 'pagine' => [['percorso' => ..., 'titolo' => ...]]]].
     * Le pagine sciolte della sezione formano un blocco senza titolo, prima dei gruppi.
     */
    public static function indice(string $sezione): array
    {
        $cartella = static::cartella($sezione);
        $blocchi  = [];

        $sciolte = static::pagineIn($cartella, '');
        if ($sciolte) {
            $blocchi[] = ['titolo' => null, 'pagine' => $sciolte];
        }

        foreach (glob($cartella . '/[0-9][0-9]-*', GLOB_ONLYDIR) ?: [] as $gruppo) {
            $nome = basename($gruppo);
            $blocchi[] = [
                'titolo' => Str::ucfirst(str_replace('-', ' ', substr($nome, 3))),
                'pagine' => static::pagineIn($gruppo, $nome . '/'),
            ];
        }

        return $blocchi;
    }

    /**
     * Pagina della sezione (null = README della sezione); null se il percorso non è valido o non esiste.
     * Con $perPdf le immagini puntano al file su disco (dompdf non scarica URL).
     *
     * @return array{percorso: ?string, titolo: string, html: string, paragrafi: list<array{id: string, testo: string}>}|null
     */
    public static function pagina(string $sezione, ?string $percorso, bool $perPdf = false): ?array
    {
        if ($percorso !== null && ! preg_match(self::PERCORSO, $percorso)) {
            return null;
        }

        $relativo = $sezione . '/' . ($percorso ?? 'README') . '.md';
        $file     = static::radice() . '/' . $relativo;
        if (! is_file($file)) {
            return null;
        }

        $markdown = file_get_contents($file);
        $html     = Str::markdown(static::riscriviLink($markdown, $relativo, $perPdf), [
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink'  => [
                'insert' => 'none', 'apply_id_to_heading' => true, 'id_prefix' => '', 'fragment_prefix' => '',
            ],
        ], [new HeadingPermalinkExtension()]);

        preg_match_all('#<h2 id="([^"]+)">(.*?)</h2>#s', $html, $h2, PREG_SET_ORDER);

        return [
            'percorso'  => $percorso,
            'titolo'    => static::titolo($markdown, $file),
            'html'      => $html,
            'paragrafi' => array_map(fn (array $m) => ['id' => $m[1], 'testo' => html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5)], $h2),
        ];
    }

    /** Tutta la sezione nell'ordine dell'indice (README per primo), per il PDF. */
    public static function sezioneCompleta(string $sezione): array
    {
        $percorsi = [null];
        foreach (static::indice($sezione) as $blocco) {
            foreach ($blocco['pagine'] as $voce) {
                $percorsi[] = $voce['percorso'];
            }
        }

        return array_values(array_filter(array_map(fn (?string $p) => static::pagina($sezione, $p, true), $percorsi)));
    }

    /** File su disco di un'immagine della guida, null se il percorso non è ammesso o non esiste. */
    public static function fileImmagine(string $percorso): ?string
    {
        if (! preg_match(self::IMMAGINE, $percorso)) {
            return null;
        }
        $file = realpath(static::radice() . '/' . $percorso);
        $base = realpath(static::radice() . '/immagini');

        return ($file && $base && str_starts_with($file, $base . DIRECTORY_SEPARATOR) && is_file($file)) ? $file : null;
    }

    private static function pagineIn(string $cartella, string $prefisso): array
    {
        return array_map(fn (string $file) => [
            'percorso' => $prefisso . basename($file, '.md'),
            'titolo'   => static::titolo(file_get_contents($file), $file),
        ], glob($cartella . '/[0-9][0-9]-*.md') ?: []);
    }

    private static function titolo(string $markdown, string $file): string
    {
        return preg_match('/^# (.+)$/m', $markdown, $m) ? trim($m[1]) : Str::headline(basename($file, '.md'));
    }

    /** Link e immagini relativi del file $relativo (percorso dentro docs/) → indirizzi validi nel pannello. */
    private static function riscriviLink(string $markdown, string $relativo, bool $perPdf): string
    {
        $cartella = dirname($relativo);

        return preg_replace_callback('#(!?)\[([^\]]*)\]\(([^)\s]+)\)#', function (array $m) use ($cartella, $perPdf) {
            [$tutto, $immagine, $testo, $link] = $m;
            if (preg_match('#^([a-z][a-z0-9+.-]*:|/|\#)#i', $link)) {
                return $tutto; // esterno, assoluto o ancora nella stessa pagina
            }

            [$percorso, $ancora] = array_pad(explode('#', $link, 2), 2, null);
            $percorso = static::normalizza($cartella . '/' . $percorso);
            $ancora   = $ancora !== null ? '#' . $ancora : '';

            $destinazione = $immagine
                ? static::urlImmagine($percorso, $perPdf)
                : static::urlPagina($percorso, $ancora);

            return $destinazione === null ? $tutto : "{$immagine}[{$testo}]({$destinazione})";
        }, $markdown);
    }

    private static function urlImmagine(string $percorso, bool $perPdf): ?string
    {
        $file = static::fileImmagine($percorso);
        if ($file === null) {
            return null;
        }

        return $perPdf ? $file : route('guida.immagine', ['percorso' => Str::after($percorso, 'immagini/')]);
    }

    /** Pagina del pannello per un .md di una sezione mostrata nel pannello; altrimenti la copia su GitHub. */
    private static function urlPagina(string $percorso, string $ancora): ?string
    {
        if (! str_ends_with($percorso, '.md')) {
            return null;
        }
        $percorso = substr($percorso, 0, -3);
        [$sezione, $resto] = array_pad(explode('/', $percorso, 2), 2, '');

        if (isset(self::PAGINE[$sezione])) {
            $pagina = self::PAGINE[$sezione];
            if ($resto === 'README') {
                return $pagina::getUrl() . $ancora;
            }
            if (preg_match(self::PERCORSO, $resto)) {
                return $pagina::getUrl(['p' => $resto]) . $ancora;
            }
        }

        $github = rtrim((string) config('app.guida_url'), '/');

        return $github !== '' ? "{$github}/{$percorso}.md{$ancora}" : null;
    }

    private static function normalizza(string $percorso): string
    {
        $parti = [];
        foreach (explode('/', $percorso) as $parte) {
            if ($parte === '..') {
                array_pop($parti);
            } elseif ($parte !== '.' && $parte !== '') {
                $parti[] = $parte;
            }
        }

        return implode('/', $parti);
    }
}
