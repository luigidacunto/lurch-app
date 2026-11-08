<?php

namespace App\Esami;

use App\Esami\Calcolo\CalcoloEsameFactory;
use App\Esami\Calcolo\Frazione;
use App\Models\Esame;
use App\Models\Socio;
use App\Models\TcriSchedaSemaforo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dati del riepilogo itinere, uguali per pannello e PWA. Nessuna logica di calcolo qui: le medie e il
 * totale vengono da CalcoloTcri (sintesiArea, totaleItinere, totaleSemaforo). Due famiglie sempre separate:
 * 'voti'/'sintesi' = schede PERSONALI dei docenti (appunti, non entrano nel calcolo); 'finale' = scheda finale
 * d'area del direttore (l'unica che entra nel calcolo). Ritirati esclusi (Esame::discenti()); i non ammessi restano
 * in elenco ma senza finale. Tutto è calcolato a lettura, niente è salvato.
 */
class RiepilogoItinere
{
    /** Codice dell'area che «specchia» i laboratori. Codice d'area del modello TCRI v1, non un peso. */
    public const AREA_SPECCHIO_LABORATORI = 'crescita';

    /**
     * Promemoria solo informativo per l'area «Crescita» (schede personali e finale d'area): non è esclusivamente lo
     * specchio dei laboratori, dipende da più elementi del percorso. Nessuna regola di calcolo né vincolo (l'eventuale
     * specifica definitiva si decide con il team di formazione).
     */
    /** Scarto tra docenti nello stesso laboratorio segnalato in rosso: ≥ 15% del massimo della scheda. */
    public const SCARTO_ALTO_PERC = 15;

    public const NOTA_AREA_SPECCHIO = "Quest'area tiene conto anche dell'andamento nei laboratori e di quanto emerso nel resto del percorso del corso.";

    /**
     * $conAnnotazioni: solo per il direttore — le annotazioni libere dei docenti, mai a presidente,
     * commissione o admin: 'annotazioni' (schede itinere) e 'laboratori[].docenti[].nota'. Per gli altri restano vuote.
     * $soloDiscenti: id dei soci da includere (pagina del tutor); null = tutti.
     *
     * @return array{aree: list<array>, discenti: list<array>, lap: string}
     */
    public static function per(Esame $esame, bool $conAnnotazioni = false, ?array $soloDiscenti = null): array
    {
        $snapshot = $esame->modello_snapshot;
        $calcolo = CalcoloEsameFactory::tcri();
        $lap = (string) $esame->lap;
        $maxItinere = Frazione::da((string) $snapshot['itinere']['scala']['max']);

        $iscrizioni = $esame->discenti()->with('partecipante')->get()
            ->filter(fn ($i) => $i->partecipante && ($soloDiscenti === null || in_array($i->partecipante_id, $soloDiscenti)))
            ->sortBy(fn ($i) => mb_strtolower($i->partecipante->cognome.' '.$i->partecipante->nome));

        $schede = $esame->schedeItinere()->with('voci')->get()->groupBy('discente_socio_id');
        $nomi = Socio::query()->whereIn('id', $schede->flatten()->pluck('docente_socio_id')->unique())->get()->keyBy('id');
        $decisioni = $esame->decisioniItinere()->with('decisoDa')->get()->keyBy(fn ($d) => $d->discente_socio_id.'|'.$d->area_codice);
        $laboratori = self::laboratori($esame, $snapshot, $conAnnotazioni);
        $annotazioni = $conAnnotazioni ? self::annotazioniItinere($schede->flatten(), $nomi) : collect();

        $aree = array_map(fn ($a) => [
            'codice' => $a['codice'],
            'nome'   => $a['nome'],
            'voci'   => $a['voci'],
            'specchio_laboratori' => $a['codice'] === self::AREA_SPECCHIO_LABORATORI,
        ], $snapshot['itinere']['aree']);

        $discenti = [];
        foreach ($iscrizioni as $iscrizione) {
            $socio = $iscrizione->partecipante;
            $dellaPersona = ($schede->get($socio->id) ?? collect())
                ->sortBy(fn ($s) => mb_strtolower(($nomi[$s->docente_socio_id]->cognome ?? '').' '.($nomi[$s->docente_socio_id]->nome ?? '')));
            $docenti = $dellaPersona->mapWithKeys(fn ($s) => [
                $s->docente_socio_id => ($nomi[$s->docente_socio_id]->cognome ?? '?').' '.($nomi[$s->docente_socio_id]->nome ?? ''),
            ])->all();

            $perArea = [];
            $finali = [];
            foreach ($snapshot['itinere']['aree'] as $area) {
                $votiPerDocente = self::votiArea($dellaPersona, array_column($area['voci'], 'codice'));
                $sintesi = $calcolo->sintesiArea($snapshot, $area['codice'], $votiPerDocente, $lap);
                $decisione = $decisioni->get($socio->id.'|'.$area['codice']);
                $finali[$area['codice']] = $decisione ? (string) $decisione->punteggio : null;

                $perArea[$area['codice']] = [
                    'voti'               => $votiPerDocente,
                    'medie_docenti'      => array_map(fn ($d) => ['media' => Formato::decimale($d['media']), 'contati' => $d['voti_contati']], $sintesi['docenti']),
                    'media_delle_medie'  => Formato::decimale($sintesi['media_delle_medie']),
                    // Solo ausilio visivo rispetto al LAP; null senza voti (il valore forzato vale solo se il direttore lo conferma).
                    'colore_media'       => $sintesi['media_delle_medie'] ? ColoreScala::perValore($sintesi['media_delle_medie'], $maxItinere, $lap) : null,
                    'media_tutti_i_voti' => Formato::decimale($sintesi['media_tutti_i_voti']),
                    'forzato'            => $sintesi['forzato'],
                    'proposta'           => Formato::unDecimale($sintesi['proposta']),
                    'proposta_input'     => $sintesi['proposta']->tronca(1),
                    'finale'             => $decisione ? [
                        'punteggio'   => Formato::unDecimale(Frazione::da((string) $decisione->punteggio)),
                        'punteggio_input' => Frazione::da((string) $decisione->punteggio)->tronca(1),
                        'motivazione' => $decisione->motivazione,
                        'autore'      => $decisione->decisoDa?->nome_completo,
                        'data'        => $decisione->updated_at,
                    ] : null,
                ];
            }

            $totale = $calcolo->totaleItinere($snapshot, array_filter($finali, fn ($v) => $v !== null));
            $labDiscente = self::laboratoriDiscente($laboratori, $socio->id);
            $discenti[] = [
                'socio'        => $socio,
                'non_ammesso'  => (bool) $iscrizione->non_ammesso,
                'motivo_non_ammesso' => $iscrizione->non_ammesso_motivo,
                'docenti'      => $docenti,
                'aree'         => $perArea,
                'decise'       => count($totale['aree']) - count($totale['mancanti']),
                'n_aree'       => count($totale['aree']),
                'mancanti'     => $totale['mancanti'],
                'totale'       => Formato::unDecimale($totale['somma']),
                'massimo'      => Formato::decimale($totale['massimo']),
                'laboratori'   => $labDiscente,
                // Sintesi per il titolo dell'accordion: solo laboratori già svolti (data passata o non indicata).
                'stato_lab'    => array_values(array_filter($labDiscente, fn ($l) => $l['svolto'])),
                'annotazioni'  => $annotazioni->get($socio->id, collect())->values()->all(),
            ];
        }

        return ['aree' => $aree, 'discenti' => $discenti, 'lap' => $lap];
    }

    /** docente => (voce_codice => voto|null) per le sole voci di un'area, dalle schede itinere di un discente. */
    private static function votiArea(Collection $schedeDelDiscente, array $codiciVoci): array
    {
        $votiPerDocente = [];
        foreach ($schedeDelDiscente as $scheda) {
            $perVoce = $scheda->voci->mapWithKeys(fn ($v) => [$v->voce_codice => $v->voto])->all();
            $votiPerDocente[$scheda->docente_socio_id] = array_intersect_key($perVoce, array_flip($codiciVoci));
        }

        return $votiPerDocente;
    }

    /**
     * Proposta di partenza (media delle medie, o LAP × max se nessun voto) per UN'area di UN discente, come
     * Frazione. Lato server, usata per decidere se la motivazione serve: il valore del form non è mai fidato.
     */
    public static function proposta(Esame $esame, int $discenteId, string $codiceArea): Frazione
    {
        $snapshot = $esame->modello_snapshot;
        $area = collect($snapshot['itinere']['aree'])->firstWhere('codice', $codiceArea)
            ?? throw new \InvalidArgumentException("Area itinere sconosciuta: '{$codiceArea}'.");
        $schede = $esame->schedeItinere()->where('discente_socio_id', $discenteId)->with('voci')->get();

        return CalcoloEsameFactory::tcri()
            ->sintesiArea($snapshot, $codiceArea, self::votiArea($schede, array_column($area['voci'], 'codice')), (string) $esame->lap)['proposta'];
    }

    /**
     * Andamento dei laboratori di UN discente: una riga per laboratorio con il totale di ogni docente (solo schede
     * INVIATE: il totale esiste solo a 28 voci), la media tra docenti e lo scarto min–max. Colori (ColoreScala::perValore,
     * solo ausilio visivo) su media e totali rispetto al LAP; scarto_alto = scarto ≥ SCARTO_ALTO_PERC % del massimo.
     * 'nota' per docente solo col flag del direttore. 'inviate'/'attesi' = schede inviate sui docenti che devono
     * valutare il discente in quel laboratorio (docenti dei suoi gruppi ∪ chi ha già una scheda, anche in bozza);
     * 'svolto' = laboratorio senza data o con data non futura.
     *
     * @return list<array{id: int, numero: int, data: mixed, docenti: list<array>, media: ?string, colore_media: ?string, minimo: ?string, massimo: ?string, scarto: ?string, scarto_alto: bool, massimo_scheda: string}>
     */
    private static function laboratoriDiscente(Collection $laboratori, int $discenteId): array
    {
        $righe = [];
        foreach ($laboratori['labs'] as $lab) {
            $totali = [];
            $punti = [];
            $stato = [
                'inviate' => $laboratori['schede']->get($lab->id, collect())->where('discente_socio_id', $discenteId)->count(),
                'attesi'  => count($laboratori['attesi'][$lab->id.'|'.$discenteId] ?? []),
                'svolto'  => $lab->data === null || ! $lab->data->isFuture(),
            ];
            foreach ($laboratori['schede']->get($lab->id, collect())->where('discente_socio_id', $discenteId) as $scheda) {
                $t = $scheda->totale($laboratori['snapshot']);
                if ($t['punti'] === null) {
                    continue;
                }
                $punti[] = $t['punti'];
                $totali[] = [
                    'docente' => $laboratori['nomi'][$scheda->docente_socio_id] ?? '?',
                    'totale'  => Formato::decimale($t['punti']),
                    'colore'  => ColoreScala::perValore($t['punti'], $laboratori['massimo_f'], $laboratori['lap']),
                    'nota'    => $laboratori['note'] ? Formato::testo($scheda->annotazione) : null,
                ];
            }
            if ($punti === []) {
                $righe[] = $stato + ['id' => $lab->id, 'numero' => $lab->numero, 'data' => $lab->data, 'docenti' => [], 'media' => null, 'colore_media' => null, 'minimo' => null, 'massimo' => null, 'scarto' => null, 'scarto_alto' => false, 'massimo_scheda' => $laboratori['massimo']];

                continue;
            }
            $somma = array_reduce($punti, fn ($c, $p) => $c->somma($p), Frazione::di(0));
            $min = $max = $punti[0];
            foreach ($punti as $p) {
                $min = $p->confronta($min) < 0 ? $p : $min;
                $max = $p->confronta($max) > 0 ? $p : $max;
            }
            $media = $somma->diviso(Frazione::di(count($punti)));
            $scarto = $max->sottrai($min);
            $righe[] = $stato + [
                'id' => $lab->id, 'numero' => $lab->numero, 'data' => $lab->data, 'docenti' => $totali,
                'media' => Formato::decimale($media),
                'colore_media' => ColoreScala::perValore($media, $laboratori['massimo_f'], $laboratori['lap']),
                'minimo' => Formato::decimale($min), 'massimo' => Formato::decimale($max),
                'scarto' => Formato::decimale($scarto),
                'scarto_alto' => $scarto->per(Frazione::di(100))->confronta($laboratori['massimo_f']->per(Frazione::di(self::SCARTO_ALTO_PERC))) >= 0,
                'massimo_scheda' => $laboratori['massimo'],
            ];
        }

        return $righe;
    }

    /**
     * Annotazioni non vuote delle schede itinere, per discente (ordine per docente). Quelle dei laboratori stanno
     * accanto al docente nella riga del laboratorio ('docenti[].nota').
     *
     * @return Collection<int, Collection<int, array{docente: string, testo: string}>>
     */
    private static function annotazioniItinere(Collection $schedeItinere, Collection $nomi): Collection
    {
        return $schedeItinere->filter(fn ($s) => Formato::testo($s->annotazione) !== null)
            ->map(fn ($s) => [
                'discente' => $s->discente_socio_id,
                'docente'  => ($nomi[$s->docente_socio_id]->cognome ?? '?').' '.($nomi[$s->docente_socio_id]->nome ?? ''),
                'testo'    => Formato::testo($s->annotazione),
            ])
            ->sortBy(fn ($a) => mb_strtolower($a['docente']))
            ->groupBy('discente');
    }

    /** Laboratori e schede semaforo inviate dell'esame, caricati una volta sola. */
    private static function laboratori(Esame $esame, array $snapshot, bool $conAnnotazioni): Collection
    {
        $labs = $esame->laboratori()->orderBy('numero')->get();
        $schede = TcriSchedaSemaforo::query()->whereIn('laboratorio_id', $labs->pluck('id'))->whereNotNull('inviata_il')
            ->with('voci')->get()->groupBy('laboratorio_id');
        $nomi = Socio::query()->whereIn('id', $schede->flatten()->pluck('docente_socio_id')->unique())->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->cognome.' '.$s->nome]);
        $massimo = CalcoloEsameFactory::tcri()->totaleSemaforo($snapshot, [])['massimo'];

        // Docenti attesi per (laboratorio, discente): quelli dei suoi gruppi più chi ha già una scheda (gruppo cambiato).
        $attesi = [];
        $coppie = DB::table('esame_gruppo_discente as gd')
            ->join('esame_gruppi as g', 'g.id', '=', 'gd.gruppo_id')
            ->join('esame_gruppo_docente as gc', 'gc.gruppo_id', '=', 'g.id')
            ->whereIn('g.laboratorio_id', $labs->pluck('id'))
            ->get(['g.laboratorio_id as lab', 'gd.socio_id as discente', 'gc.socio_id as docente'])
            ->concat(TcriSchedaSemaforo::query()->whereIn('laboratorio_id', $labs->pluck('id'))
                ->get(['laboratorio_id as lab', 'discente_socio_id as discente', 'docente_socio_id as docente']));
        foreach ($coppie as $c) {
            $attesi[$c->lab.'|'.$c->discente][$c->docente] = true;
        }

        return collect([
            'labs' => $labs, 'schede' => $schede, 'nomi' => $nomi, 'snapshot' => $snapshot,
            'massimo' => Formato::decimale($massimo), 'massimo_f' => $massimo, 'lap' => (string) $esame->lap, 'note' => $conAnnotazioni,
            'attesi' => $attesi,
        ]);
    }

    /**
     * Dettaglio asincrono: matrice voce × docente di UN laboratorio per UN discente (schede inviate). Le voci in cui
     * i docenti non concordano sono marcate: 'discordante' = risposte diverse, 'salto' = almeno due docenti distanti
     * ≥ 1 sulla scala (rosso vs verde). Letto solo al clic (pannello) o a scheda propria inviata (PWA, confronto tra
     * docenti). 'note' (allineate a 'docenti') solo col flag del direttore.
     *
     * @return array{docenti: list<string>, note: list<?string>, totali: list<?array{totale: string, colore: string}>, massimo: string, competenze: list<array>}
     */
    public static function dettaglioLaboratorio(Esame $esame, int $discenteId, int $laboratorioId, bool $conAnnotazioni = false): array
    {
        $snapshot = $esame->modello_snapshot;
        $lab = $esame->laboratori()->whereKey($laboratorioId)->firstOrFail();
        $schede = $lab->schedeSemaforo()->where('discente_socio_id', $discenteId)->whereNotNull('inviata_il')
            ->with(['voci', 'docente'])->get()
            ->sortBy(fn ($s) => mb_strtolower($s->docente->cognome.' '.$s->docente->nome))->values();

        $scala = $snapshot['semaforo']['scala'];
        $livello = function ($valore) use ($scala) {
            if ($valore === null) {
                return null;
            }
            foreach ($scala as $chiave => $l) {
                if (Frazione::da((string) $valore)->confronta(Frazione::da($l['valore'])) === 0) {
                    return $chiave;
                }
            }

            return null;
        };

        $competenze = [];
        foreach ($snapshot['semaforo']['competenze'] as $competenza) {
            $voci = [];
            foreach ($competenza['voci'] as $voce) {
                $valori = $schede->map(fn ($s) => $livello($s->voci->firstWhere('voce_codice', $voce['codice'])?->valore))->all();
                $numeri = array_map(fn ($k) => Frazione::da($scala[$k]['valore']), array_values(array_filter($valori)));
                usort($numeri, fn ($a, $b) => $a->confronta($b));
                $voci[] = [
                    'testo'      => $voce['testo'],
                    'valori'     => $valori,
                    'discordante' => count(array_unique($valori)) > 1,
                    'salto'      => count($numeri) > 1 && end($numeri)->sottrai($numeri[0])->confronta(Frazione::di(1)) >= 0,
                ];
            }
            $competenze[] = ['nome' => $competenza['nome'], 'voci' => $voci];
        }

        return [
            'docenti'    => $schede->map(fn ($s) => $s->docente->cognome.' '.$s->docente->nome)->all(),
            'note'       => $schede->map(fn ($s) => $conAnnotazioni ? Formato::testo($s->annotazione) : null)->all(),
            // Totale di ogni scheda (allineato a 'docenti'), colorato rispetto al LAP come nel riepilogo.
            'totali'     => $schede->map(function ($s) use ($snapshot, $esame) {
                $t = $s->totale($snapshot);

                return $t['punti'] === null ? null : [
                    'totale' => Formato::decimale($t['punti']),
                    'colore' => ColoreScala::perValore($t['punti'], $t['massimo'], (string) $esame->lap),
                ];
            })->all(),
            'massimo'    => Formato::decimale(CalcoloEsameFactory::tcri()->totaleSemaforo($snapshot, [])['massimo']),
            'scala'      => array_map(fn ($l) => ['nome' => $l['nome']], $scala),
            'competenze' => $competenze,
        ];
    }
}
