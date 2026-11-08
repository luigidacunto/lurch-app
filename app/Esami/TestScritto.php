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
 * ⚠️ Verso il discente passa SOLO domandePerDiscente(): niente `corretta`, niente punteggio, mai.
 * Indice delle opzioni in base 0 (posizione nell'array), N_OPZIONI = 4.
 */
class TestScritto
{
    public const N_OPZIONI = 4;

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

    public static function massimo(Esame $esame): Frazione
    {
        return Frazione::da(self::configurazione($esame)['corretta'])->per(Frazione::di(self::numeroDomande($esame)));
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
     * Le domande per chi prepara e legge (commissione, admin, direttore, PDF): con la corretta.
     *
     * @return list<array{id: int, ordine: int, testo: string, opzioni: list<string>, corretta: int}>
     */
    public static function domandeComplete(Esame $esame): array
    {
        return TestDomanda::query()->where('esame_id', $esame->id)->orderBy('ordine')->orderBy('id')->get()
            ->makeVisible('corretta')
            ->map(fn (TestDomanda $d) => [
                'id' => $d->id, 'ordine' => $d->ordine, 'testo' => $d->testo,
                'opzioni' => array_values((array) $d->opzioni), 'corretta' => (int) $d->corretta,
            ])->all();
    }

    /** @return array<int, array> id domanda => input di CalcoloTestScritto (config uguale per tutte). */
    public static function domandePerCalcolo(Esame $esame): array
    {
        $c = self::configurazione($esame);
        $domande = [];
        foreach (TestDomanda::query()->where('esame_id', $esame->id)->get(['id', 'corretta']) as $d) {
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

    /** @return array<int, ?int> id domanda => opzione (null = vuota), per tutte le domande dell'esame. */
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
        $consegne = $esame->testConsegne()->with('inseritoDa')->get()->keyBy('discente_socio_id');
        $date = TestRisposta::query()->whereIn('consegna_id', $consegne->pluck('id'))->whereNotNull('opzione')
            ->selectRaw('consegna_id, count(*) as n')->groupBy('consegna_id')->pluck('n', 'consegna_id');
        $totale = self::numeroDomande($esame);

        return $soci->map(function (Socio $s) use ($esame, $avvii, $consegne, $date, $totale) {
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
            ];
        })->all();
    }
}
