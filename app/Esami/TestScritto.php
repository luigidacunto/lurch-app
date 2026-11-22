<?php

namespace App\Esami;

use App\Esami\Calcolo\CalcoloTestScritto;
use App\Esami\Calcolo\Frazione;
use App\Models\Esame;
use App\Models\Socio;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use App\Models\TestDomanda;
use App\Models\TestRisposta;

/**
 * Test scritto: lettura e punteggio, un solo punto per pannello e PWA. Il motore è CalcoloTestScritto
 * (puro, esatto); qui si costruisce il suo input dalla configurazione dell'esame (3 valori uguali per tutte le domande)
 * e dalle consegne. Nessun punteggio è salvato: si ricalcola (domande e configurazione sono congelate dal primo avvio).
 *
 * ⚠️ Verso il discente passa SOLO domandePerDiscente(): niente `corretta`, niente punteggio. Unica eccezione:
 * correzionePerDiscente(), per il suo test inviato e solo dopo che il direttore gli ha pubblicato i risultati.
 * Indice delle opzioni in base 0 (posizione nell'array), N_OPZIONI = 4.
 * Domande ANNULLATE (AnnullamentoDomandaTest): fuori da domandePerCalcolo(), quindi fuori da punteggio, massimo, giuste
 * e risposteDi(); restano in domandeComplete() (con `annullata`) e nelle risposte salvate.
 */
class TestScritto
{
    public const N_OPZIONI = 4;

    /** Evidenza di una domanda nella statistica (TestScritto::statistica), uguale in pannello, app e PDF. */
    public const EVIDENZA_PEGGIORE = 'peggiore';

    public const EVIDENZA_ALTA = 'alta';

    public const EVIDENZA_PERFETTA = 'perfetta';

    /**
     * Etichette e colori delle evidenze, una sola fonte per pannello, app e PDF: `sfondo` trasparente (leggibile anche
     * nel tema scuro del pannello), `stampa` a tinta piena per il PDF (dompdf non gestisce bene la trasparenza).
     */
    public const EVIDENZE = [
        self::EVIDENZA_PEGGIORE => ['etichetta' => 'La più sbagliata', 'sfondo' => 'rgba(239,68,68,.16)', 'stampa' => '#fee2e2', 'bordo' => '#dc2626'],
        self::EVIDENZA_ALTA     => ['etichetta' => 'Sbagliata da oltre la metà', 'sfondo' => 'rgba(245,158,11,.16)', 'stampa' => '#fef3c7', 'bordo' => '#d97706'],
        // Azzurro come la fascia più alta dei voti (ColoreScala), non verde: il verde è della risposta corretta.
        self::EVIDENZA_PERFETTA => ['etichetta' => 'Giusta per tutti', 'sfondo' => 'rgba(59,130,246,.12)', 'stampa' => '#e0f0fd', 'bordo' => '#2563eb'],
    ];

    /**
     * Configurazione del punteggio. Con le penalità non attive: 1 punto per corretta, tutte le risposte obbligatorie.
     *
     * @return array{penalita: bool, corretta: string, errata: string, vuota: string, obbligatorie: bool, mescola: bool}
     */
    public static function configurazione(Esame $esame): array
    {
        if (! $esame->test_penalita_attive) {
            return ['penalita' => false, 'corretta' => '1.0', 'errata' => '0.0', 'vuota' => '0.0', 'obbligatorie' => true, 'mescola' => (bool) $esame->test_mescola];
        }

        return [
            'penalita'     => true,
            'corretta'     => (string) $esame->test_punti_corretta,
            'errata'       => (string) $esame->test_penalita_errata,
            'vuota'        => (string) $esame->test_penalita_vuota,
            'obbligatorie' => false,
            'mescola'      => (bool) $esame->test_mescola,
        ];
    }

    /** Numero con virgola o punto e al più 1 decimale → "2.5" normalizzato, altrimenti null. */
    public static function normalizzaDecimale(string $valore): ?string
    {
        $v = str_replace(',', '.', trim($valore));

        return preg_match('/^\d{1,3}(\.\d)?$/', $v) ? (str_contains($v, '.') ? $v : $v.'.0') : null;
    }

    /** "2.5" → "2,5"; "2.0" → "2". Per i campi del pannello e il PDF. */
    public static function mostraDecimale(string|float|int $valore): string
    {
        $t = rtrim(rtrim(number_format((float) $valore, 1, '.', ''), '0'), '.');

        return str_replace('.', ',', $t);
    }

    /** La regola di punteggio spiegata a chi svolge il test (online e PDF cartaceo). Non è il punteggio di nessuno. */
    public static function regola(Esame $esame): string
    {
        $c = self::configurazione($esame);
        if (! $c['penalita']) {
            return 'Tutte le risposte sono obbligatorie: non puoi inviare il test finché non hai risposto a tutte le domande.';
        }
        $m = fn (string $v) => self::mostraDecimale($v);

        return 'Risposta corretta: +'.$m($c['corretta']).' punti · risposta sbagliata: −'.$m($c['errata'])
            .' · risposta non data: −'.$m($c['vuota']).'. Puoi lasciare domande senza risposta.';
    }

    public static function numeroDomande(Esame $esame): int
    {
        return $esame->testDomande()->count();
    }

    /** Domande valide, cioè non annullate: sono quelle che contano nel punteggio. */
    public static function numeroDomandeValide(Esame $esame): int
    {
        return $esame->testDomande()->whereNull('annullata_il')->count();
    }

    public static function massimo(Esame $esame): Frazione
    {
        return Frazione::da(self::configurazione($esame)['corretta'])->per(Frazione::di(self::numeroDomandeValide($esame)));
    }

    /**
     * Le domande per il discente: SOLO id, testo, opzioni. `corretta` non viene nemmeno caricata dal database.
     *
     * `opzioni` è sempre indice originale => testo: l'indice originale è l'identificatore stabile dell'opzione (è ciò che il
     * discente salva e che si confronta con la corretta), la POSIZIONE mostrata (e quindi la lettera) è solo presentazione.
     * Con `$mescola` l'ordine delle domande e quello delle opzioni sono casuali a ogni chiamata (cioè a ogni visualizzazione);
     * senza, l'ordine di origine. Il mescolamento è lato server: al client non arriva mai la corretta.
     *
     * @return list<array{id: int, testo: string, opzioni: array<int, string>}>
     */
    public static function domandePerDiscente(Esame $esame, bool $mescola = false): array
    {
        $domande = TestDomanda::query()->where('esame_id', $esame->id)->orderBy('ordine')->orderBy('id')
            ->get(['id', 'ordine', 'testo', 'opzioni'])
            ->map(function (TestDomanda $d) use ($mescola) {
                $opzioni = array_values((array) $d->opzioni);
                if ($mescola) {
                    $indici = array_keys($opzioni);
                    shuffle($indici);
                    $opzioni = array_combine($indici, array_map(fn ($i) => $opzioni[$i], $indici));
                }

                return ['id' => $d->id, 'testo' => $d->testo, 'opzioni' => $opzioni];
            })
            ->all();
        if ($mescola) {
            shuffle($domande);
        }

        return $domande;
    }

    /**
     * Le domande per chi prepara e legge (commissione, admin, direttore, PDF): con la corretta e, se annullata,
     * data, autore e motivo dell'annullamento.
     *
     * @return list<array{id: int, ordine: int, testo: string, opzioni: list<string>, corretta: int, annullata: ?array{il: \Illuminate\Support\Carbon, da: ?string, motivo: string}}>
     */
    public static function domandeComplete(Esame $esame): array
    {
        return TestDomanda::query()->where('esame_id', $esame->id)->with('annullataDa')->orderBy('ordine')->orderBy('id')->get()
            ->makeVisible('corretta')
            ->map(fn (TestDomanda $d) => [
                'id' => $d->id, 'ordine' => $d->ordine, 'testo' => $d->testo,
                'opzioni' => array_values((array) $d->opzioni), 'corretta' => (int) $d->corretta,
                'annullata' => $d->eAnnullata()
                    ? ['il' => $d->annullata_il, 'da' => $d->annullataDa?->nome_completo, 'motivo' => (string) $d->motivo_annullamento]
                    : null,
            ])->all();
    }

    /** @return array<int, array> id domanda => input di CalcoloTestScritto (config uguale per tutte). */
    public static function domandePerCalcolo(Esame $esame): array
    {
        $c = self::configurazione($esame);
        $domande = [];
        foreach (TestDomanda::query()->where('esame_id', $esame->id)->whereNull('annullata_il')->get(['id', 'corretta']) as $d) {
            $domande[$d->id] = [
                'corretta'        => (int) $d->corretta,
                'punti'           => $c['corretta'],
                'penalita_errata' => $c['errata'],
                'penalita_vuota'  => $c['vuota'],
                'n_opzioni'       => self::N_OPZIONI,
            ];
        }

        return $domande;
    }

    /** @return array<int, ?int> id domanda => opzione (null = vuota), per le domande VALIDE dell'esame. */
    public static function risposteDi(Esame $esame, TestConsegna $consegna): array
    {
        $date = $consegna->risposte()->pluck('opzione', 'domanda_id')->all();
        $risposte = [];
        foreach (array_keys(self::domandePerCalcolo($esame)) as $id) {
            $risposte[$id] = isset($date[$id]) ? (int) $date[$id] : null;
        }

        return $risposte;
    }

    public static function consegna(Esame $esame, int $discenteId): ?TestConsegna
    {
        return TestConsegna::where('esame_id', $esame->id)->where('discente_socio_id', $discenteId)->first();
    }

    public static function avvio(Esame $esame, int $discenteId): ?TestAvvio
    {
        return TestAvvio::where('esame_id', $esame->id)->where('discente_socio_id', $discenteId)->first();
    }

    /** Risposte effettivamente date (non vuote) di una consegna. */
    public static function risposteDate(TestConsegna $consegna): int
    {
        return $consegna->risposte()->whereNotNull('opzione')->count();
    }

    /**
     * Punteggio di una consegna INVIATA; punti null se è ancora in compilazione.
     *
     * @return array{punti: ?Frazione, massimo: Frazione}
     */
    public static function punteggio(Esame $esame, TestConsegna $consegna): array
    {
        $r = (new CalcoloTestScritto)->calcola(
            self::domandePerCalcolo($esame),
            $consegna->eInviata() ? self::risposteDi($esame, $consegna) : null,
        );

        return ['punti' => $r['punti'], 'massimo' => $r['massimo']];
    }

    /**
     * Input pronto per CalcoloTcri (`$dati['test']`): null se il test non è stato inviato.
     * Cablato nella formula: lo usa App\Esami\RisultatiEsame::datiPerCalcolo().
     *
     * @return array{domande: array, risposte: array}|null
     */
    public static function datiPerCalcolo(Esame $esame, int $discenteId): ?array
    {
        $consegna = self::consegna($esame, $discenteId);
        if (! $consegna?->eInviata()) {
            return null;
        }

        return ['domande' => self::domandePerCalcolo($esame), 'risposte' => self::risposteDi($esame, $consegna)];
    }

    /**
     * Correzione di un test INVIATO per il discente stesso, SOLO dopo la pubblicazione (PermessiEsame::vedeRisultatiTest):
     * è l'unico punto in cui le corrette vanno verso un discente.
     *
     * `annullate` = id domanda => motivo: la domanda resta visibile al discente, segnata come annullata.
     *
     * @return array{punteggio: ?string, giuste: int, totale: int, corrette: array<int, int>, annullate: array<int, string>}
     */
    public static function correzionePerDiscente(Esame $esame, TestConsegna $consegna): array
    {
        $corrette = array_map(fn (array $d) => $d['corretta'], self::domandePerCalcolo($esame));
        $annullate = TestDomanda::query()->where('esame_id', $esame->id)->whereNotNull('annullata_il')
            ->pluck('motivo_annullamento', 'id')->map(fn ($m) => (string) $m)->all();
        $risposte = self::risposteDi($esame, $consegna);
        $giuste = count(array_filter($risposte, fn ($r, $id) => $r !== null && $r === ($corrette[$id] ?? null), ARRAY_FILTER_USE_BOTH));

        return [
            'punteggio' => self::mostraPunteggio(self::punteggio($esame, $consegna)),
            'giuste'    => $giuste,
            'totale'    => count($corrette),
            'corrette'  => $corrette,
            'annullate' => $annullate,
        ];
    }

    /**
     * Statistica delle risposte, domanda per domanda nell'ordine del test (annullate comprese, segnate): conta solo i test
     * INVIATI (online e cartacei) dei discenti AMMESSI. Calcolata a ogni lettura, nulla è salvato. `errore` = percentuale
     * di chi non ha dato la corretta (sbagliate + non date) sugli inviati, null senza inviati.
     *
     * @return array{inviati: int, domande: list<array>}
     */
    public static function statistica(Esame $esame): array
    {
        $ammessi = $esame->discentiAmmessi()->pluck('iscrizioni.partecipante_id');
        $consegne = $esame->testConsegne()->whereNotNull('consegnato_il')->whereIn('discente_socio_id', $ammessi)->pluck('id');
        $inviati = $consegne->count();
        $conteggi = TestRisposta::query()->whereIn('consegna_id', $consegne)->whereNotNull('opzione')
            ->selectRaw('domanda_id, opzione, count(*) as n')->groupBy('domanda_id', 'opzione')->get()->groupBy('domanda_id');

        $domande = array_map(function (array $d) use ($conteggi, $inviati) {
            $scelte = array_fill(0, self::N_OPZIONI, 0);
            foreach ($conteggi->get($d['id'], collect()) as $c) {
                $scelte[(int) $c->opzione] = (int) $c->n;
            }
            $giuste = $scelte[$d['corretta']] ?? 0;
            $date = array_sum($scelte);

            return $d + [
                'scelte'    => $scelte,
                'giuste'    => $giuste,
                'sbagliate' => $date - $giuste,
                'vuote'     => $inviati - $date,
                'errore'    => $inviati > 0 ? (int) round(100 * ($inviati - $giuste) / $inviati) : null,
            ];
        }, self::domandeComplete($esame));

        // Evidenza sui conteggi esatti (non sulla percentuale arrotondata), solo per le domande valide: «peggiore» = più
        // errori di tutte (pari merito comprese, almeno un errore), «alta» = sbagliata da più della metà (la metà esatta
        // no), «perfetta» = giusta per tutti. Una sola evidenza per domanda, in quest'ordine.
        $errori = fn (array $d) => $inviati - $d['giuste'];
        $valide = array_filter($domande, fn (array $d) => $d['annullata'] === null);
        $peggiore = $valide === [] ? 0 : max(array_map($errori, $valide));
        foreach ($domande as &$d) {
            $e = $errori($d);
            $d['evidenza'] = match (true) {
                $d['annullata'] !== null || $inviati === 0 => null,
                $e > 0 && $e === $peggiore                 => self::EVIDENZA_PEGGIORE,
                2 * $e > $inviati                          => self::EVIDENZA_ALTA,
                $e === 0                                   => self::EVIDENZA_PERFETTA,
                default                                    => null,
            };
        }
        unset($d);

        return ['inviati' => $inviati, 'domande' => $domande];
    }

    /** "x su Tmax" con una virgola. */
    public static function mostraPunteggio(array $punteggio): ?string
    {
        if ($punteggio['punti'] === null) {
            return null;
        }

        return Formato::unDecimale($punteggio['punti']).' su '.Formato::unDecimale($punteggio['massimo']);
    }

    /**
     * Una riga per discente AMMESSO (non ritirato, non «non ammesso»): stato dello svolgimento e, se inviato,
     * punteggio. Una query per tabella. Stati: non_avviato | disattivato | avviato | in_compilazione | inviato.
     *
     * @return list<array<string, mixed>>
     */
    public static function riepilogo(Esame $esame): array
    {
        $ammessi = $esame->discentiAmmessi()->pluck('iscrizioni.partecipante_id');
        $soci = Socio::query()->whereIn('id', $ammessi)->orderBy('cognome')->orderBy('nome')->get();
        $avvii = $esame->testAvvii()->get()->keyBy('discente_socio_id');
        $pubblicazioni = $esame->testPubblicazioni()->whereNotNull('pubblicato_il')->get()->keyBy('discente_socio_id');
        $consegne = $esame->testConsegne()->with('inseritoDa')->get()->keyBy('discente_socio_id');
        $date = TestRisposta::query()->whereIn('consegna_id', $consegne->pluck('id'))->whereNotNull('opzione')
            ->selectRaw('consegna_id, count(*) as n')->groupBy('consegna_id')->pluck('n', 'consegna_id');
        $totale = self::numeroDomande($esame);

        return $soci->map(function (Socio $s) use ($esame, $avvii, $consegne, $date, $totale, $pubblicazioni) {
            $avvio = $avvii->get($s->id);
            $consegna = $consegne->get($s->id);
            $nDate = $consegna ? (int) ($date[$consegna->id] ?? 0) : 0;

            $stato = match (true) {
                $consegna?->eInviata() => 'inviato',
                $avvio?->eAttivo() && $nDate > 0 => 'in_compilazione',
                (bool) $avvio?->eAttivo() => 'avviato',
                $avvio !== null => 'disattivato',
                default => 'non_avviato',
            };

            return [
                'socio'      => $s,
                'stato'      => $stato,
                'modalita'   => $avvio?->modalita,
                'motivo'     => $avvio?->motivo_cartaceo,
                'date'       => $nDate,
                'totale'     => $totale,
                'consegna'   => $consegna,
                'punteggio'  => $consegna?->eInviata() ? self::mostraPunteggio(self::punteggio($esame, $consegna)) : null,
                // risultati pubblicati al discente (solo con test inviato): data della pubblicazione, altrimenti null
                'pubblicato' => $consegna?->eInviata() ? $pubblicazioni->get($s->id)?->pubblicato_il : null,
            ];
        })->all();
    }
}
