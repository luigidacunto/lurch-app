<?php

namespace App\Esami;

use App\Esami\Calcolo\CalcoloEsameFactory;
use App\Esami\Calcolo\Frazione;
use App\Models\Esame;
use App\Models\Esito;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\TcriColloquio;
use App\Models\TcriProvaPratica;

/**
 * Risultato finale per discente: un solo lettore per pannello, PDF e CSV. Nessuna aritmetica qui: il calcolo
 * è sempre CalcoloTcri::calcola(); questa classe compone i dati dalle fonti esistenti (finali d'area, colloquio finale,
 * prova pratica finale, test inviato, LAP) e ne normalizza l'uscita.
 *
 *  - esame CHIUSO  → legge SOLO `esiti` (snapshot): mai ricalcolato, nemmeno se cambiano modello, LAP o schede;
 *  - altrimenti    → calcola dal vivo (provvisorio).
 *
 * Riga = ['socio','stato','prove','finale','finale_esatto','idoneo','mancanti','motivo_non_ammesso','lap','ingresso'].
 * stato: 'calcolato' | 'non_ammesso' | 'incompleto' (solo dal vivo: manca almeno un dato, `mancanti` dice cosa).
 * prove[prova] = ['punti' => ?"n/d", 'massimo' => "n/d", 'contributo' => ?"xx.xx"] (contributo = centesimi, troncato a 2 decimali).
 */
class RisultatiEsame
{
    public const PROVE = ['itinere', 'colloquio', 'prova_pratica', 'test_scritto'];

    public const ETICHETTE_PROVE = [
        'itinere' => 'Valutazione in itinere',
        'colloquio' => 'Colloquio',
        'prova_pratica' => 'Prova pratica',
        'test_scritto' => 'Test scritto',
    ];

    /** Codice d'avviso del motore → cosa manca, in italiano. */
    private const MANCA = [
        'itinere_area_mancante' => "punteggi finali d'area",
        'colloquio_mancante' => 'colloquio finale',
        'prova_pratica_mancante' => 'prova pratica finale',
        'semaforo_incompleto' => 'prova pratica finale',
        'test_non_consegnato' => 'test scritto',
        'test_senza_domande' => 'domande del test',
    ];

    /** @return list<array<string, mixed>> */
    public static function per(Esame $esame): array
    {
        return $esame->isChiuso() ? self::daSnapshot($esame) : self::dalVivo($esame);
    }

    /** Dati d'ingresso del motore per UN discente, letti dalle sole schede finali (un solo punto di composizione). */
    public static function datiPerCalcolo(Esame $esame, int $discenteId, ?array $caricati = null): array
    {
        $c = $caricati ?? self::carica($esame);

        $itinere = [];
        foreach ($c['decisioni']->get($discenteId) ?? [] as $d) {
            $itinere[$d->area_codice] = (string) $d->punteggio;
        }
        $colloquio = $c['colloqui']->get($discenteId);
        $prova = $c['prove']->get($discenteId);

        return [
            'lap' => (string) $esame->lap,
            'itinere' => $itinere,
            'colloquio' => $colloquio === null ? null : (string) $colloquio->punteggio,
            'prova_pratica_finale' => $prova === null ? null
                : $prova->voci->mapWithKeys(fn ($v) => [$v->voce_codice => $v->valore === null ? null : (string) $v->valore])->all(),
            'test' => TestScritto::datiPerCalcolo($esame, $discenteId),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function dalVivo(Esame $esame): array
    {
        $snapshot = $esame->modello_snapshot;
        $calcolo = CalcoloEsameFactory::per($esame->tipo_codice);
        $caricati = self::carica($esame);
        $righe = [];

        foreach (self::iscrizioni($esame) as $isc) {
            $socio = $isc->partecipante;
            if ($isc->non_ammesso) {
                $righe[] = self::rigaNonAmmessa($socio, (string) $isc->non_ammesso_motivo, (string) $esame->lap);

                continue;
            }
            $dati = self::datiPerCalcolo($esame, $socio->id, $caricati);
            $r = $calcolo->calcola($snapshot, $dati);

            $righe[] = self::rigaDaMotore($socio, $r, $dati, (string) $esame->lap);
        }

        return self::ordina($righe);
    }

    /** @return list<array<string, mixed>> */
    private static function daSnapshot(Esame $esame): array
    {
        $righe = [];
        foreach ($esame->esiti()->with('discente')->get() as $e) {
            $t = $e->totali ?? [];
            $righe[] = [
                'socio' => $e->discente,
                'stato' => $e->stato,
                'prove' => $t['prove'] ?? [],
                'finale' => $e->punteggio_finale === null ? null : (string) $e->punteggio_finale,
                'finale_esatto' => $t['finale_esatto'] ?? null,
                'idoneo' => $e->idoneo,
                'mancanti' => [],
                'motivo_non_ammesso' => $e->motivo_non_ammesso,
                'lap' => (string) $e->lap,
                'ingresso' => $t['ingresso'] ?? null,
            ];
        }

        return self::ordina($righe);
    }

    /** Riga da scrivere in `esiti` per un discente (calcolato o non ammesso). Solo righe complete: lancia se manca qualcosa. */
    public static function perEsito(array $riga, Esame $esame): array
    {
        if ($riga['stato'] === 'incompleto') {
            throw new \RuntimeException('Esito incompleto per '.$riga['socio']->nome_completo.': manca '.implode(', ', $riga['mancanti']).'.');
        }

        return [
            'esame_id' => $esame->id,
            'discente_socio_id' => $riga['socio']->id,
            'stato' => $riga['stato'],
            'totali' => ['prove' => $riga['prove'], 'finale_esatto' => $riga['finale_esatto'], 'ingresso' => $riga['ingresso']],
            'punteggio_finale' => $riga['finale'],
            'idoneo' => $riga['idoneo'],
            'motivo_non_ammesso' => $riga['motivo_non_ammesso'],
            'lap' => $riga['lap'],
            'tipo_codice' => $esame->tipo_codice,
            'modello_versione' => $esame->modello_versione,
        ];
    }

    public static function daFrazione(?string $nd): ?Frazione
    {
        return $nd === null ? null : Frazione::daFrazione($nd);
    }

    private static function rigaNonAmmessa(Socio $socio, string $motivo, string $lap): array
    {
        return [
            'socio' => $socio, 'stato' => Esito::STATO_NON_AMMESSO, 'prove' => [], 'finale' => null, 'finale_esatto' => null,
            'idoneo' => null, 'mancanti' => [], 'motivo_non_ammesso' => $motivo, 'lap' => $lap, 'ingresso' => null,
        ];
    }

    private static function rigaDaMotore(Socio $socio, array $r, array $dati, string $lap): array
    {
        $d = $r['dettaglio'];
        $prove = [];
        foreach (self::PROVE as $p) {
            $punti = match ($p) {
                'itinere' => $d['itinere']['somma'],
                default => $d[$p]['punti'],
            };
            $prove[$p] = [
                'punti' => $punti,
                'massimo' => $d[$p]['massimo'],
                'contributo' => $r['contributi'][$p]['troncato2'] ?? null,
            ];
        }
        $mancanti = array_values(array_unique(array_filter(array_map(fn ($a) => self::MANCA[$a['codice']] ?? null, $r['avvisi']))));

        return [
            'socio' => $socio,
            'stato' => $r['finale'] === null ? 'incompleto' : Esito::STATO_CALCOLATO,
            'prove' => $prove,
            'finale' => $r['finale'],
            'finale_esatto' => $r['finale_esatto'],
            'idoneo' => $r['idoneo'],
            'mancanti' => $mancanti,
            'motivo_non_ammesso' => null,
            'lap' => $lap,
            'ingresso' => [
                'itinere' => $dati['itinere'],
                'colloquio' => $dati['colloquio'],
                'prova_pratica_finale' => $dati['prova_pratica_finale'],
                'test' => $dati['test'] === null ? null : ['domande' => count($dati['test']['domande']), 'risposte_date' => count(array_filter($dati['test']['risposte'], fn ($x) => $x !== null))],
            ],
        ];
    }

    /** Iscrizioni non ritirate con il socio (non ammessi inclusi). */
    private static function iscrizioni(Esame $esame)
    {
        return $esame->discenti()->with('partecipante')->get()->filter(fn (Iscrizione $i) => $i->partecipante !== null);
    }

    /** Una query per tabella: finali d'area, colloqui finali, prove pratiche finali (con voci). */
    private static function carica(Esame $esame): array
    {
        return [
            'decisioni' => $esame->decisioniItinere()->get()->groupBy('discente_socio_id'),
            'colloqui' => $esame->colloqui()->where('tipo', TcriColloquio::TIPO_FINALE)->get()->keyBy('discente_socio_id'),
            'prove' => $esame->provePratiche()->where('tipo', TcriProvaPratica::TIPO_FINALE)->with('voci')->get()->keyBy('discente_socio_id'),
        ];
    }

    private static function ordina(array $righe): array
    {
        usort($righe, fn ($a, $b) => [mb_strtolower($a['socio']->cognome), mb_strtolower($a['socio']->nome)] <=> [mb_strtolower($b['socio']->cognome), mb_strtolower($b['socio']->nome)]);

        return $righe;
    }
}
