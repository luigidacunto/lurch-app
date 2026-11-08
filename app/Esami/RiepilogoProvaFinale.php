<?php

namespace App\Esami;

use App\Esami\Calcolo\CalcoloEsameFactory;
use App\Esami\Calcolo\Frazione;
use App\Models\Esame;
use App\Models\Socio;
use App\Models\TcriColloquio;
use App\Models\TcriProvaPratica;
use Illuminate\Support\Collection;

/**
 * Dati del riepilogo della prova finale (colloquio e prova pratica), uguali per pannello e PWA. Nessuna
 * logica di calcolo qui: totali e proposte vengono da CalcoloTcri. Due famiglie sempre separate:
 * 'personali' = appunti dei commissari (non entrano nel calcolo; visibili anche a prova finale chiusa, solo lettura);
 * 'finale' = scheda del direttore (l'unica che entra nel calcolo, sempre visibile alla commissione).
 * Solo i discenti AMMESSI (Esame::discentiAmmessi()): ritirati e non ammessi non compaiono. Tutto calcolato a lettura.
 */
class RiepilogoProvaFinale
{
    /**
     * @return array{discenti: list<array>, aperta: bool, massimo_pratica: string, completezza: array}
     */
    public static function per(Esame $esame): array
    {
        $snapshot = $esame->modello_snapshot;
        $calcolo = CalcoloEsameFactory::tcri();
        $aperta = $esame->provaFinaleAperta();

        $iscrizioni = $esame->discentiAmmessi()->with('partecipante')->get()
            ->filter(fn ($i) => $i->partecipante)
            ->sortBy(fn ($i) => mb_strtolower($i->partecipante->cognome.' '.$i->partecipante->nome));

        $colloqui = $esame->colloqui()->with('inseritoDa')->get()->groupBy('discente_socio_id');
        $prove = $esame->provePratiche()->with(['voci', 'inseritoDa'])->get()->groupBy('discente_socio_id');
        $idMembri = $esame->colloqui()->pluck('membro_socio_id')->merge($esame->provePratiche()->pluck('membro_socio_id'))->filter()->unique();
        $nomi = Socio::query()->whereIn('id', $idMembri)->get()->mapWithKeys(fn ($s) => [$s->id => $s->cognome.' '.$s->nome]);
        $completezza = CompletezzaEsame::per($esame);
        $massimoPratica = Formato::decimale($calcolo->totaleSemaforo($snapshot, [])['massimo']);

        $discenti = [];
        foreach ($iscrizioni as $iscrizione) {
            $socio = $iscrizione->partecipante;
            $dellaPersonaC = $colloqui->get($socio->id, collect());
            $dellaPersonaP = $prove->get($socio->id, collect());

            // ---- Colloquio
            $personaliC = $dellaPersonaC->where('tipo', TcriColloquio::TIPO_PERSONALE)
                ->sortBy(fn ($c) => mb_strtolower($nomi[$c->membro_socio_id] ?? ''))->values();
            $propostaC = $calcolo->propostaColloquio($snapshot, $personaliC->map(fn ($c) => (int) $c->punteggio)->all());
            $propostaC = $propostaC === null ? null : Frazione::da($propostaC->tronca(1));
            $finaleC = $dellaPersonaC->firstWhere('tipo', TcriColloquio::TIPO_FINALE);

            // ---- Prova pratica
            $personaliP = $dellaPersonaP->where('tipo', TcriProvaPratica::TIPO_PERSONALE)
                ->sortBy(fn ($p) => mb_strtolower($nomi[$p->membro_socio_id] ?? ''))->values();
            $finaleP = $dellaPersonaP->firstWhere('tipo', TcriProvaPratica::TIPO_FINALE);
            $proposteP = self::propostaDaSchede($snapshot, $personaliP);

            $discenti[] = [
                'socio'    => $socio,
                'colloquio' => [
                    'personali' => $personaliC->map(fn ($c) => [
                        'membro'      => $nomi[$c->membro_socio_id] ?? '?',
                        'voto'        => (string) (int) $c->punteggio,
                        'impressione' => $c->impressione,
                    ])->all(),
                    'proposta'       => Formato::unDecimale($propostaC),
                    'proposta_input' => $propostaC?->tronca(1),
                    'finale'         => $finaleC ? [
                        'punteggio'       => Formato::unDecimale(Frazione::da((string) $finaleC->punteggio)),
                        'punteggio_input' => Frazione::da((string) $finaleC->punteggio)->tronca(1),
                        'motivazione'     => $finaleC->motivazione,
                        'autore'          => $finaleC->inseritoDa?->nome_completo,
                        'data'            => $finaleC->updated_at,
                    ] : null,
                ],
                'prova_pratica' => [
                    'personali' => $personaliP->map(function ($p) use ($snapshot, $nomi) {
                        $t = $p->totale($snapshot);

                        return [
                            'membro'  => $nomi[$p->membro_socio_id] ?? '?',
                            'inviata' => $p->eInviata(),
                            'voci'    => $p->voci->whereNotNull('valore')->count(),
                            'totale'  => $t['punti'] === null ? null : Formato::decimale($t['punti']),
                        ];
                    })->all(),
                    'n_inviate'       => $personaliP->filter->eInviata()->count(),
                    'proposta_totale' => $proposteP['totale'] ?? null,
                    'finale'          => $finaleP ? self::finaleProva($finaleP, $snapshot) : null,
                ],
                'completezza' => $completezza[$socio->id] ?? null,
            ];
        }

        return ['discenti' => $discenti, 'aperta' => $aperta, 'massimo_pratica' => $massimoPratica, 'completezza' => $completezza];
    }

    /** Totale e dati della finale della prova pratica (28 voci → "punti su massimo"). */
    private static function finaleProva(TcriProvaPratica $finale, array $snapshot): array
    {
        $t = $finale->totale($snapshot);

        return [
            'totale'  => $t['punti'] === null ? null : Formato::decimale($t['punti']),
            'massimo' => Formato::decimale($t['massimo']),
            'autore'  => $finale->inseritoDa?->nome_completo,
            'data'    => $finale->updated_at,
        ];
    }

    /**
     * Proposta voce per voce dalle schede personali INVIATE + il suo totale (null finché manca anche una sola voce).
     *
     * @return array{proposta: array<string, ?string>, discordanti: array<string, bool>, totale: ?string}
     */
    private static function propostaDaSchede(array $snapshot, Collection $personali): array
    {
        $calcolo = CalcoloEsameFactory::tcri();
        $inviate = $personali->filter->eInviata()
            ->map(fn ($p) => $p->voci->mapWithKeys(fn ($v) => [$v->voce_codice => $v->valore === null ? null : (string) $v->valore])->all())
            ->values()->all();
        $r = $calcolo->propostaVociSemaforo($snapshot, $inviate);
        $t = $calcolo->totaleSemaforo($snapshot, $r['proposta']);

        return $r + ['totale' => $t['punti'] === null ? null : Formato::decimale($t['punti'])];
    }

    /**
     * Proposta lato server per UN discente (colloquio): media dei voti personali troncata a 1 decimale (nessuna scheda
     * personale = nessuna proposta). Il valore del form non è mai fidato.
     */
    public static function propostaColloquio(Esame $esame, int $discenteId): ?Frazione
    {
        $voti = $esame->colloqui()->where('tipo', TcriColloquio::TIPO_PERSONALE)->where('discente_socio_id', $discenteId)
            ->pluck('punteggio')->map(fn ($v) => (int) $v)->all();
        $media = CalcoloEsameFactory::tcri()->propostaColloquio($esame->modello_snapshot, $voti);

        return $media === null ? null : Frazione::da($media->tronca(1));
    }

    /**
     * Proposta voce per voce, voci discordanti e totali dei commissari per UN discente (prova pratica): usata dalla
     * pagina della finale per precompilare e confrontare (anche a prova finale chiusa).
     *
     * @return array{commissari: list<array>, competenze: list<array>, proposta: array<string, ?string>, discordanti: array<string, bool>}
     */
    public static function provaPraticaDiscente(Esame $esame, int $discenteId): array
    {
        $snapshot = $esame->modello_snapshot;
        $struttura = StrutturaSemaforo::per($esame);
        $personali = $esame->provePratiche()->where('tipo', TcriProvaPratica::TIPO_PERSONALE)->where('discente_socio_id', $discenteId)
                ->whereNotNull('inviata_il')->with(['voci', 'membro'])->get()
                ->sortBy(fn ($p) => mb_strtolower($p->membro->cognome.' '.$p->membro->nome))->values();

        $calcolo = self::propostaDaSchede($snapshot, $personali);
        $livelloDi = function ($p, string $codice) use ($struttura) {
            $v = $p->voci->firstWhere('voce_codice', $codice)?->valore;

            return StrutturaSemaforo::livello($struttura['scala'], $v === null ? null : (string) $v);
        };

        $competenze = [];
        foreach ($struttura['competenze'] as $competenza) {
            $voci = [];
            foreach ($competenza['voci'] as $voce) {
                $voci[] = [
                    'codice'      => $voce['codice'],
                    'testo'       => $voce['testo'],
                    'valori'      => $personali->map(fn ($p) => $livelloDi($p, $voce['codice']))->all(),
                    'proposta'    => StrutturaSemaforo::livello($struttura['scala'], $calcolo['proposta'][$voce['codice']] ?? null),
                    'discordante' => $calcolo['discordanti'][$voce['codice']] ?? false,
                ];
            }
            $competenze[] = ['nome' => $competenza['nome'], 'voci' => $voci];
        }

        return [
            'commissari'  => $personali->map(function ($p) use ($snapshot) {
                $t = $p->totale($snapshot)['punti'];

                return ['membro' => $p->membro->cognome.' '.$p->membro->nome, 'totale' => $t === null ? null : Formato::decimale($t)];
            })->all(),
            'competenze'  => $competenze,
            'proposta'    => $calcolo['proposta'],
            'discordanti' => $calcolo['discordanti'],
        ];
    }

    /** Valori attuali della finale della prova pratica di UN discente: voce_codice => chiave della scala (o null). */
    public static function valoriFinaleProvaPratica(Esame $esame, int $discenteId): array
    {
        $scala = StrutturaSemaforo::per($esame)['scala'];
        $finale = $esame->provePratiche()->where('tipo', TcriProvaPratica::TIPO_FINALE)
            ->where('discente_socio_id', $discenteId)->with('voci')->first();

        return $finale ? $finale->voci->mapWithKeys(fn ($v) => [
            $v->voce_codice => StrutturaSemaforo::livello($scala, $v->valore === null ? null : (string) $v->valore),
        ])->all() : [];
    }
}
