<?php

declare(strict_types=1);

namespace App\Esami\Calcolo;

use InvalidArgumentException;

/**
 * Calcolo del TCRI. Pesi, massimi, scale e voci si leggono SEMPRE dallo snapshot del modello:
 * nel codice non c'è nessuno dei numeri 30/10/40/20, 20/5/28. Il metodo è scelto dalla versione
 * dello snapshot, così gli esami già avviati restano corretti se nasce una versione 2.
 *
 * Contratto: calcola() riceve il finale d'area deciso dalla commissione, mai i voti dei
 * singoli docenti. La media dei voti di una scheda sta in mediaArea(); un valore forzato diventa
 * finale d'area solo se il direttore lo salva come tale (vedi sintesiArea) e qui non è anticipato.
 */
final class CalcoloTcri implements CalcoloEsame
{
    private const PROVE = ['itinere', 'colloquio', 'prova_pratica', 'test_scritto'];

    public function __construct(private readonly CalcoloTestScritto $testScritto)
    {
    }

    /**
     * @param  array  $dati  lap (int|string), itinere (codice area => finale d'area), colloquio (?int|string, al più 1 decimale),
     *                       prova_pratica_finale (?[voce_codice => valore semaforo]),
     *                       test (?['domande' => [...], 'risposte' => [...]]; null = non consegnato)
     */
    public function calcola(array $snapshot, array $dati): array
    {
        return match (self::versione($snapshot)) {
            1 => $this->calcolaV1($snapshot, $dati),
            default => throw new InvalidArgumentException('Versione del modello TCRI non gestita: '.self::versione($snapshot)),
        };
    }

    /**
     * Media delle voci valutate di UNA scheda per un'area (voci vuote ignorate).
     * Nessun voto → LAP × scala.max / 100, forzato = true.
     *
     * @param  array<string, int|string|null>  $voti  voce_codice => voto 1–5 o null
     * @return array{media: Frazione, forzato: bool, voti_contati: int}
     */
    public function mediaArea(array $snapshot, string $codiceArea, array $voti, int|string $lap): array
    {
        $min = Frazione::da(self::chiave($snapshot, 'itinere', 'scala', 'min'));
        $max = Frazione::da(self::chiave($snapshot, 'itinere', 'scala', 'max'));
        $codiciVoci = self::codiciVociArea($snapshot, $codiceArea);

        $somma = Frazione::di(0);
        $contati = 0;
        foreach ($voti as $codice => $voto) {
            if (! in_array($codice, $codiciVoci, true)) {
                throw new InvalidArgumentException("La voce '{$codice}' non appartiene all'area '{$codiceArea}'.");
            }
            if ($voto === null) {
                continue;
            }
            $v = Frazione::da($voto);
            if ($v->den !== 1 || $v->confronta($min) < 0 || $v->confronta($max) > 0) {
                throw new InvalidArgumentException("Voto non valido per '{$codice}': {$min}–{$max} interi.");
            }
            $somma = $somma->somma($v);
            $contati++;
        }

        if ($contati === 0) {
            return [
                'media' => self::lap($lap)->per($max)->diviso(Frazione::di(100)),
                'forzato' => true,
                'voti_contati' => 0,
            ];
        }

        return ['media' => $somma->diviso(Frazione::di($contati)), 'forzato' => false, 'voti_contati' => $contati];
    }

    /**
     * Totale di UNA scheda a semaforo (prova pratica finale e schede dei laboratori): unico punto di calcolo.
     * Voci e scala vengono dallo snapshot. Con voci mancanti (assenti o null) `punti` è null e `mancanti` le elenca,
     * nessun conteggio parziale. Voce sconosciuta o valore fuori scala = InvalidArgumentException.
     *
     * @param  array<string, string|int|null>  $scheda  voce_codice => "0"|"0.25"|"1"|null
     * @return array{punti: ?Frazione, massimo: Frazione, mancanti: list<string>}
     */
    public function totaleSemaforo(array $snapshot, array $scheda): array
    {
        $semaforo = self::semaforo($snapshot);
        $sconosciute = array_diff(array_keys($scheda), $semaforo['voci']);
        if ($sconosciute !== []) {
            throw new InvalidArgumentException('Voci della prova pratica sconosciute: '.implode(', ', $sconosciute));
        }
        $punti = Frazione::di(0);
        $mancanti = [];
        foreach ($semaforo['voci'] as $codice) {
            $valore = $scheda[$codice] ?? null;
            if ($valore === null) {
                $mancanti[] = $codice;

                continue;
            }
            $v = Frazione::da($valore);
            if (! self::inScala($v, $semaforo['valori'])) {
                throw new InvalidArgumentException("Valore del semaforo non in scala per '{$codice}'.");
            }
            $punti = $punti->somma($v);
        }

        return ['punti' => $mancanti === [] ? $punti : null, 'massimo' => $semaforo['massimo'], 'mancanti' => $mancanti];
    }

    /**
     * Sintesi di UN'area per UN discente a partire dalle schede personali di tutti i docenti:
     * media di ciascun docente (via mediaArea), «media delle medie» dei docenti che hanno votato e
     * «media di tutti i voti». Se nessun docente ha votato: forzato = true e proposta = LAP × max / 100.
     * Il forzato è solo una proposta: entra nel calcolo solo se il direttore lo salva come finale d'area.
     *
     * @param  array<int|string, array<string, int|string|null>>  $votiPerDocente  docente => (voce_codice => voto|null)
     * @return array{docenti: array<int|string, array{media: ?Frazione, voti_contati: int}>, media_delle_medie: ?Frazione, media_tutti_i_voti: ?Frazione, forzato: bool, proposta: Frazione, n_docenti: int}
     */
    public function sintesiArea(array $snapshot, string $codiceArea, array $votiPerDocente, int|string $lap): array
    {
        $docenti = [];
        $sommaMedie = Frazione::di(0);
        $sommaVoti = Frazione::di(0);
        $nVoti = 0;
        $nDocenti = 0;
        foreach ($votiPerDocente as $docente => $voti) {
            $r = $this->mediaArea($snapshot, $codiceArea, $voti, $lap);
            if ($r['forzato']) {
                $docenti[$docente] = ['media' => null, 'voti_contati' => 0];

                continue;
            }
            $docenti[$docente] = ['media' => $r['media'], 'voti_contati' => $r['voti_contati']];
            $sommaMedie = $sommaMedie->somma($r['media']);
            $nDocenti++;
            foreach ($voti as $voto) {
                if ($voto !== null) {
                    $sommaVoti = $sommaVoti->somma(Frazione::da($voto));
                    $nVoti++;
                }
            }
        }

        $max = Frazione::da(self::chiave($snapshot, 'itinere', 'scala', 'max'));
        $nessunVoto = $nDocenti === 0;

        return [
            'docenti' => $docenti,
            'media_delle_medie' => $nessunVoto ? null : $sommaMedie->diviso(Frazione::di($nDocenti)),
            'media_tutti_i_voti' => $nessunVoto ? null : $sommaVoti->diviso(Frazione::di($nVoti)),
            'forzato' => $nessunVoto,
            'proposta' => $nessunVoto ? self::lap($lap)->per($max)->diviso(Frazione::di(100)) : $sommaMedie->diviso(Frazione::di($nDocenti)),
            'n_docenti' => $nDocenti,
        ];
    }

    /**
     * Proposta per la scheda FINALE del colloquio: media esatta dei voti delle schede personali dei
     * commissari. Il troncamento a 1 decimale è del livello app (come per le aree). Nessun voto → null.
     * Voto fuori scala o non intero = InvalidArgumentException (la scheda personale ha solo interi).
     *
     * @param  list<int|string>  $voti
     */
    public function propostaColloquio(array $snapshot, array $voti): ?Frazione
    {
        if ($voti === []) {
            return null;
        }
        $min = Frazione::da(self::chiave($snapshot, 'colloquio', 'scala', 'min'));
        $max = Frazione::da(self::chiave($snapshot, 'colloquio', 'scala', 'max'));
        $somma = Frazione::di(0);
        foreach ($voti as $voto) {
            $v = Frazione::da($voto);
            if ($v->den !== 1 || $v->confronta($min) < 0 || $v->confronta($max) > 0) {
                throw new InvalidArgumentException("Voto del colloquio: serve un intero da {$min} a {$max}.");
            }
            $somma = $somma->somma($v);
        }

        return $somma->diviso(Frazione::di(count($voti)));
    }

    /**
     * Proposta per la scheda FINALE della prova pratica: per ogni voce il valore più frequente tra le
     * schede personali INVIATE dei commissari; in pareggio il più basso (prudenziale). Nessuna scheda → nessuna
     * proposta (voci null). `discordanti[voce]` = i commissari non sono tutti d'accordo. Valori e voci dallo snapshot.
     *
     * @param  list<array<string, string|int|null>>  $schede  ognuna voce_codice => "0"|"0.25"|"1"|null
     * @return array{proposta: array<string, ?string>, discordanti: array<string, bool>}
     */
    public function propostaVociSemaforo(array $snapshot, array $schede): array
    {
        $semaforo = self::semaforo($snapshot);
        $proposta = [];
        $discordanti = [];
        foreach ($semaforo['voci'] as $codice) {
            $conteggio = []; // chiave canonica (stringa Frazione) => [Frazione, n]
            foreach ($schede as $scheda) {
                $valore = $scheda[$codice] ?? null;
                if ($valore === null) {
                    continue;
                }
                $f = Frazione::da($valore);
                if (! self::inScala($f, $semaforo['valori'])) {
                    throw new InvalidArgumentException("Valore del semaforo non in scala per '{$codice}'.");
                }
                $k = (string) $f;
                $conteggio[$k] = [$f, ($conteggio[$k][1] ?? 0) + 1];
            }
            $vincente = null;
            foreach ($conteggio as [$f, $n]) {
                if ($vincente === null || $n > $vincente[1] || ($n === $vincente[1] && $f->confronta($vincente[0]) < 0)) {
                    $vincente = [$f, $n];
                }
            }
            $proposta[$codice] = $vincente === null ? null : $vincente[0]->tronca(2);
            $discordanti[$codice] = count($conteggio) > 1;
        }

        return ['proposta' => $proposta, 'discordanti' => $discordanti];
    }

    /**
     * Somma dei finali d'area decisi dal direttore (unico punto: lo usa anche calcola()). Un'area senza finale
     * è «mancante» e la somma è null (nessun totale parziale). Area sconosciuta o fuori scala = InvalidArgumentException.
     *
     * @param  array<string, int|string|null>  $finali  area_codice => punteggio
     * @return array{somma: ?Frazione, massimo: Frazione, aree: array<string, ?string>, mancanti: list<string>}
     */
    public function totaleItinere(array $snapshot, array $finali): array
    {
        $min = Frazione::da(self::chiave($snapshot, 'itinere', 'scala', 'min'));
        $max = Frazione::da(self::chiave($snapshot, 'itinere', 'scala', 'max'));
        $codiciAree = array_column(self::chiave($snapshot, 'itinere', 'aree'), 'codice');
        $estranee = array_diff(array_keys($finali), $codiciAree);
        if ($estranee !== []) {
            throw new InvalidArgumentException('Aree itinere sconosciute: '.implode(', ', $estranee));
        }
        $somma = Frazione::di(0);
        $mancanti = [];
        $aree = [];
        foreach ($codiciAree as $codice) {
            $finale = $finali[$codice] ?? null;
            if ($finale === null) {
                $mancanti[] = $codice;
                $aree[$codice] = null;

                continue;
            }
            $f = Frazione::da($finale);
            if ($f->confronta($min) < 0 || $f->confronta($max) > 0) {
                throw new InvalidArgumentException("Punteggio d'area '{$codice}' fuori da {$min}–{$max}.");
            }
            $somma = $somma->somma($f);
            $aree[$codice] = (string) $f;
        }

        return [
            'somma' => $mancanti === [] ? $somma : null,
            'massimo' => $max->per(Frazione::di(count($codiciAree))),
            'aree' => $aree,
            'mancanti' => $mancanti,
        ];
    }

    private function calcolaV1(array $snapshot, array $dati): array
    {
        $lap = self::lap($dati['lap'] ?? throw new InvalidArgumentException('Manca il LAP.'));
        $pesi = [];
        foreach (self::PROVE as $prova) {
            $pesi[$prova] = Frazione::da(self::chiave($snapshot, 'pesi', $prova));
        }

        $avvisi = [];
        $valori = [];   // prova => Frazione|null
        $massimi = [];  // prova => Frazione
        $dettaglio = [];

        // --- Itinere: finale d'area della commissione, tutte le aree obbligatorie
        $itinere = $this->totaleItinere($snapshot, $dati['itinere'] ?? []);
        foreach ($itinere['mancanti'] as $codice) {
            $avvisi[] = self::avviso('itinere_area_mancante', "Manca il punteggio finale dell'area '{$codice}'.", ['area' => $codice]);
        }
        $massimi['itinere'] = $itinere['massimo'];
        $valori['itinere'] = $itinere['somma'];
        $dettaglio['itinere'] = ['aree' => $itinere['aree'], 'somma' => $valori['itinere']?->__toString(), 'massimo' => (string) $massimi['itinere']];

        // --- Colloquio
        $cMin = Frazione::da(self::chiave($snapshot, 'colloquio', 'scala', 'min'));
        $massimi['colloquio'] = Frazione::da(self::chiave($snapshot, 'colloquio', 'scala', 'max'));
        $colloquio = $dati['colloquio'] ?? null;
        if ($colloquio === null) {
            $valori['colloquio'] = null;
            $avvisi[] = self::avviso('colloquio_mancante', 'Manca il punteggio del colloquio.', []);
        } else {
            // La finale del colloquio ha fino a 1 decimale (media dei commissari troncata).
            $c = Frazione::da($colloquio);
            if (10 % $c->den !== 0 || $c->confronta($cMin) < 0 || $c->confronta($massimi['colloquio']) > 0) {
                throw new InvalidArgumentException("Colloquio: serve un numero con al più 1 decimale da {$cMin} a {$massimi['colloquio']}.");
            }
            $valori['colloquio'] = $c;
        }
        $dettaglio['colloquio'] = ['punti' => $valori['colloquio']?->__toString(), 'massimo' => (string) $massimi['colloquio']];

        // --- Prova pratica: solo la scheda finale
        $massimi['prova_pratica'] = self::semaforo($snapshot)['massimo'];
        $valori['prova_pratica'] = null;
        $scheda = $dati['prova_pratica_finale'] ?? null;
        if ($scheda === null) {
            $avvisi[] = self::avviso('prova_pratica_mancante', 'Manca la scheda finale della prova pratica.', []);
        } else {
            $tot = $this->totaleSemaforo($snapshot, $scheda);
            $valori['prova_pratica'] = $tot['punti'];
            if ($tot['mancanti'] !== []) {
                $avvisi[] = self::avviso('semaforo_incompleto', 'Scheda finale della prova pratica incompleta: '.count($tot['mancanti']).' voci mancanti.', ['voci_mancanti' => $tot['mancanti']]);
            }
        }
        $dettaglio['prova_pratica'] = ['punti' => $valori['prova_pratica']?->__toString(), 'massimo' => (string) $massimi['prova_pratica']];

        // --- Test scritto
        $test = $dati['test'] ?? null;
        $esitoTest = $this->testScritto->calcola($test['domande'] ?? [], $test === null ? null : ($test['risposte'] ?? throw new InvalidArgumentException('Mancano le risposte del test.')));
        $massimi['test_scritto'] = $esitoTest['massimo'];
        $valori['test_scritto'] = $esitoTest['punti'];
        if ($test === null) {
            $avvisi[] = self::avviso('test_non_consegnato', 'Il test scritto non risulta consegnato.', []);
        } elseif ($esitoTest['massimo']->eZero()) {
            $valori['test_scritto'] = null;
            $avvisi[] = self::avviso('test_senza_domande', 'Il test non ha domande: punteggio non calcolabile.', []);
        }
        $dettaglio['test_scritto'] = ['punti' => $valori['test_scritto']?->__toString(), 'massimo' => (string) $massimi['test_scritto']];

        // --- Contributi (peso × valore ÷ massimo) e finale
        $contributi = [];
        $finale = Frazione::di(0);
        $completo = true;
        foreach (self::PROVE as $prova) {
            if ($valori[$prova] === null) {
                $contributi[$prova] = null;
                $completo = false;

                continue;
            }
            $c = $pesi[$prova]->per($valori[$prova])->diviso($massimi[$prova]);
            $finale = $finale->somma($c);
            $contributi[$prova] = ['esatto' => (string) $c, 'troncato2' => $c->tronca(2)];
        }

        $troncato = $completo ? $finale->tronca(1) : null;

        return [
            'dettaglio' => $dettaglio,
            'contributi' => $contributi,
            'finale' => $troncato,
            'finale_esatto' => $completo ? (string) $finale : null,
            'idoneo' => $troncato === null ? null : Frazione::da($troncato)->confronta($lap) >= 0,
            'avvisi' => $avvisi,
        ];
    }

    private static function versione(array $snapshot): int
    {
        if (($snapshot['tipo_codice'] ?? null) !== 'TCRI') {
            throw new InvalidArgumentException('Lo snapshot non è un modello TCRI.');
        }
        $v = $snapshot['versione'] ?? null;
        if (is_string($v) && ctype_digit($v)) {
            $v = (int) $v;
        }
        if (! is_int($v)) {
            throw new InvalidArgumentException('Versione del modello non valida.');
        }

        return $v;
    }

    private static function lap(mixed $lap): Frazione
    {
        $f = Frazione::da($lap);
        if ($f->confronta(Frazione::di(0)) < 0 || $f->confronta(Frazione::di(100)) > 0) {
            throw new InvalidArgumentException('Il LAP deve essere tra 0 e 100.');
        }

        return $f;
    }

    /** @return array{voci: list<string>, valori: list<Frazione>, massimo: Frazione} */
    private static function semaforo(array $snapshot): array
    {
        $valori = [];
        foreach (self::chiave($snapshot, 'semaforo', 'scala') as $livello) {
            $valori[] = Frazione::da(self::chiave($livello, 'valore'));
        }
        $massimoVoce = $valori[0] ?? throw new InvalidArgumentException('Scala del semaforo vuota.');
        foreach ($valori as $v) {
            if ($v->confronta($massimoVoce) > 0) {
                $massimoVoce = $v;
            }
        }
        $voci = [];
        foreach (self::chiave($snapshot, 'semaforo', 'competenze') as $competenza) {
            foreach (self::chiave($competenza, 'voci') as $voce) {
                $voci[] = self::chiave($voce, 'codice');
            }
        }

        return ['voci' => $voci, 'valori' => $valori, 'massimo' => $massimoVoce->per(Frazione::di(count($voci)))];
    }

    private static function inScala(Frazione $v, array $valori): bool
    {
        foreach ($valori as $ammesso) {
            if ($v->confronta($ammesso) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function codiciVociArea(array $snapshot, string $codiceArea): array
    {
        foreach (self::chiave($snapshot, 'itinere', 'aree') as $area) {
            if (self::chiave($area, 'codice') === $codiceArea) {
                return array_column(self::chiave($area, 'voci'), 'codice');
            }
        }
        throw new InvalidArgumentException("Area itinere sconosciuta: '{$codiceArea}'.");
    }

    private static function chiave(array $array, string ...$percorso): mixed
    {
        $corrente = $array;
        foreach ($percorso as $k) {
            if (! is_array($corrente) || ! array_key_exists($k, $corrente)) {
                throw new InvalidArgumentException("Snapshot del modello: manca '".implode('.', $percorso)."'.");
            }
            $corrente = $corrente[$k];
        }

        return $corrente;
    }

    private static function avviso(string $codice, string $messaggio, array $contesto): array
    {
        return ['codice' => $codice, 'messaggio' => $messaggio, 'contesto' => $contesto];
    }
}
