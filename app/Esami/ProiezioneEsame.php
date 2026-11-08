<?php

namespace App\Esami;

use App\Esami\Calcolo\CalcoloEsameFactory;
use App\Esami\Calcolo\Frazione;
use App\Models\Esame;

/**
 * Proiezione del punteggio finale per il direttore (pagina Discenti): SOLO lettura e calcolo a video, nulla viene
 * salvato e niente entra nell'esito. Per ogni prova si usa il punteggio fissato (schede finali, test inviato); dove manca,
 * il direttore inserisce valori ipotetici temporanei. Per l'itinere senza finale d'area si propone la media dei docenti
 * («–» se nessuno ha votato). Stessa formula del motore: contributo = peso × valore ÷ massimo, finale troncato a 1 decimale.
 */
class ProiezioneEsame
{
    public const PROVE = ['itinere', 'colloquio', 'prova_pratica', 'test_scritto'];

    /**
     * Dati di partenza per i discenti AMMESSI (ritirati e non ammessi esclusi), indicizzati per id socio.
     * Aree: finale/proposta come stringhe decimali col punto (pronte per il form); altre prove: Frazione, null = non fissato.
     *
     * @param  list<int>|null  $soloDiscenti
     * @return array<int, array{aree: list<array>, colloquio: ?Frazione, prova_pratica: ?Frazione, test_scritto: ?Frazione, massimi: array<string, Frazione>, finale: ?string, idoneo: ?bool}>
     */
    public static function per(Esame $esame, ?array $soloDiscenti = null): array
    {
        $snapshot = $esame->modello_snapshot;
        $calcolo = CalcoloEsameFactory::per($esame->tipo_codice);
        $riepilogo = RiepilogoItinere::per($esame, false, $soloDiscenti);
        $massimoTest = TestScritto::massimo($esame);
        // ponytail: solo il nome di battesimo per stare stretti; due docenti omonimi di nome si distinguono male.
        $nomiDocenti = \App\Models\Socio::query()->whereIn('id', collect($riepilogo['discenti'])->flatMap(fn ($d) => array_keys($d['docenti'])))->pluck('nome', 'id');

        $righe = [];
        foreach ($riepilogo['discenti'] as $d) {
            if ($d['non_ammesso']) {
                continue;
            }
            $id = $d['socio']->id;
            $r = $calcolo->calcola($snapshot, RisultatiEsame::datiPerCalcolo($esame, $id));
            $det = $r['dettaglio'];

            $aree = [];
            foreach ($riepilogo['aree'] as $area) {
                $a = $d['aree'][$area['codice']];
                $aree[] = [
                    'codice'   => $area['codice'],
                    'nome'     => $area['nome'],
                    'finale'   => $a['finale']['punteggio_input'] ?? null,
                    // Proposta = media delle medie dei docenti troncata a 1 decimale; nessun voto → null (mai il valore forzato).
                    'proposta' => $a['forzato'] ? null : $a['proposta_input'],
                    'docenti'  => collect($d['docenti'])->map(fn ($nome, $docId) => isset($a['medie_docenti'][$docId]['media'])
                        ? ['nome' => $nomiDocenti[$docId] ?? $nome, 'media' => $a['medie_docenti'][$docId]['media'], 'voti' => $a['medie_docenti'][$docId]['contati']]
                        : null)->filter()->values()->all(),
                ];
            }

            $righe[$id] = [
                'aree'          => $aree,
                'colloquio'     => self::frazione($det['colloquio']['punti']),
                'prova_pratica' => self::frazione($det['prova_pratica']['punti']),
                'test_scritto'  => self::frazione($det['test_scritto']['punti']),
                'massimi'       => [
                    'colloquio'     => self::frazione($det['colloquio']['massimo']),
                    'prova_pratica' => self::frazione($det['prova_pratica']['massimo']),
                    'test_scritto'  => $det['test_scritto']['punti'] !== null ? self::frazione($det['test_scritto']['massimo']) : $massimoTest,
                ],
                'finale'        => $r['finale'],
                'idoneo'        => $r['idoneo'],
            ];
        }

        return $righe;
    }

    /**
     * Totale con i valori dati (fissati o ipotetici). Una prova con un valore mancante o fuori scala non ha contributo e
     * il finale resta null; 'errori' dice quale campo non va. Valori con virgola o punto.
     *
     * @param  array{itinere: array<string, ?string>, colloquio: Frazione|string|null, prova_pratica: Frazione|string|null, test_scritto: Frazione|string|null}  $valori
     * @param  array<string, Frazione>  $massimi  colloquio, prova_pratica, test_scritto
     * @return array{contributi: array<string, ?Frazione>, itinere_somma: ?Frazione, finale: ?string, idoneo: ?bool, errori: array<string, string>}
     */
    public static function totale(array $snapshot, string $lap, array $valori, array $massimi): array
    {
        $calcolo = CalcoloEsameFactory::tcri();
        $errori = [];
        $punti = [];
        $max = [];

        // Itinere: tutte le aree, ognuna nella scala del modello (controllo del motore, che vuole decimali col punto).
        $aree = array_map(fn ($v) => self::numero($v) === null ? null : str_replace(',', '.', trim($v)), $valori['itinere']);
        $punti['itinere'] = null;
        $max['itinere'] = null;
        if (! in_array(null, $aree, true)) {
            try {
                $t = $calcolo->totaleItinere($snapshot, $aree);
                $punti['itinere'] = $t['somma'];
                $max['itinere'] = $t['massimo'];
            } catch (\InvalidArgumentException) {
                $errori['itinere'] = 'Punteggi d\'area da '.$snapshot['itinere']['scala']['min'].' a '.$snapshot['itinere']['scala']['max'].'.';
            }
        }

        $minColloquio = Frazione::da((string) $snapshot['colloquio']['scala']['min']);
        foreach (['colloquio' => $minColloquio, 'prova_pratica' => Frazione::di(0), 'test_scritto' => Frazione::di(0)] as $prova => $min) {
            $max[$prova] = $massimi[$prova];
            $v = self::numero($valori[$prova]);
            $punti[$prova] = null;
            if ($v === null) {
                continue;
            }
            if ($v->confronta($min) < 0 || $v->confronta($max[$prova]) > 0) {
                $errori[$prova] = 'Da '.Formato::decimale($min).' a '.Formato::decimale($max[$prova]).'.';

                continue;
            }
            $punti[$prova] = $v;
        }

        $contributi = [];
        $somma = Frazione::di(0);
        $completo = true;
        foreach (self::PROVE as $prova) {
            if ($punti[$prova] === null || $max[$prova] === null || $max[$prova]->eZero()) {
                $contributi[$prova] = null;
                $completo = false;

                continue;
            }
            $contributi[$prova] = Frazione::da((string) $snapshot['pesi'][$prova])->per($punti[$prova])->diviso($max[$prova]);
            $somma = $somma->somma($contributi[$prova]);
        }
        $finale = $completo ? $somma->tronca(1) : null;

        return [
            'contributi'    => $contributi,
            'itinere_somma' => $punti['itinere'],
            'finale'        => $finale,
            'idoneo'        => $finale === null ? null : Frazione::da($finale)->confronta(Frazione::da($lap)) >= 0,
            'errori'        => $errori,
        ];
    }

    /** "3,5" / "3.5" → Frazione; vuoto o non numerico → null. Una Frazione passa così com'è. */
    private static function numero(Frazione|string|null $v): ?Frazione
    {
        if ($v instanceof Frazione) {
            return $v;
        }
        $v = str_replace(',', '.', trim((string) $v));
        if ($v === '' || ! preg_match('/^\d+(\.\d+)?$/', $v)) {
            return null;
        }

        return Frazione::da($v);
    }

    /** "num/den" del dettaglio del motore → Frazione; null resta null. */
    private static function frazione(?string $nd): ?Frazione
    {
        if ($nd === null) {
            return null;
        }
        [$num, $den] = explode('/', $nd);

        return Frazione::di((int) $num)->diviso(Frazione::di((int) $den));
    }
}
