<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Models\AccessoEvento;
use App\Models\Evento;
use App\Models\IscrizioneStaff;
use App\Models\OspiteEvento;
use App\Models\Presenza;
use App\Models\SocioEvento;
use Carbon\Carbon;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;

class GestionePresenzeEffettive extends Page
{
    protected static string $resource = EventoResource::class;

    protected string $view = 'filament.resources.evento-resource.pages.gestione-presenze-effettive';

    #[Locked]
    public Evento $record;

    public static function canAccess(array $parameters = []): bool
    {
        // Filament's own mountCanAuthorizeAccess() hook calls canAccess() with
        // zero arguments on every mount (Filament\Pages\Concerns\CanAuthorizeAccess) —
        // that call must stay a no-op (true), the real per-record check only runs
        // explicitly via abort_unless() in mount() below, which always passes 'record'.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }

        $record = $parameters['record'];

        return $record instanceof Evento && EventoResource::canEdit($record);
    }

    #[Url]
    public string $data = '';

    public string $dayLabel    = '';
    public ?string $prevData   = null;
    public ?string $nextData   = null;
    public int    $totPresenti   = 0;
    public int    $totConCheckin = 0;
    public int    $totPrevisti   = 0;
    public array  $righe         = [];
    public array  $corsiLista    = [];
    public array  $staffLista    = [];

    public function mount(Evento $record): void
    {
        abort_unless(static::canAccess(['record' => $record]), 403);

        $this->record = $record;

        $this->carica();
    }

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return $this->record;
    }

    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    public function updatedData(): void
    {
        $this->carica();
    }

    public function cambiData(string $data): void
    {
        $this->data = $data;
        $this->carica();
    }

    public function carica(): void
    {
        $evento = $this->record->load(['corsi', 'comitatoRegionale']);

        // Solo giorni operativi (reali + giorno prima/dopo di ogni blocco), vedi Evento::giorniOperativi().
        ['data' => $this->data, 'prev' => $this->prevData, 'next' => $this->nextData] = $evento->navigazioneGiorno($this->data ?: null);

        $dataCarbon    = Carbon::parse($this->data);
        $this->dayLabel = $dataCarbon->translatedFormat('l j F');

        // ── Chi era presente nel giorno $data ────────────────────────────────────
        // Logica: l'ultimo accesso registrato fino a fine giornata deve essere 'entrata'.
        // Copre sia chi è entrato quel giorno sia chi è rimasto da giorni precedenti senza uscire.
        $endOfDay = $dataCarbon->copy()->endOfDay();

        $allSeIds = SocioEvento::where('evento_id', $evento->id)->pluck('id');
        $seIdsPresenti = AccessoEvento::where('iscrizioneable_type', SocioEvento::class)
            ->whereIn('iscrizioneable_id', $allSeIds)
            ->where('timestamp_accesso', '<=', $endOfDay)
            ->orderBy('iscrizioneable_id')
            ->orderBy('timestamp_accesso')
            ->orderBy('id')
            ->get(['iscrizioneable_id', 'tipo'])
            ->groupBy('iscrizioneable_id')
            ->filter(fn ($g) => $g->last()->tipo === 'entrata')
            ->keys()
            ->values();

        $allOspIds = OspiteEvento::where('evento_id', $evento->id)->pluck('id');
        $ospIdsPresenti = AccessoEvento::where('iscrizioneable_type', OspiteEvento::class)
            ->whereIn('iscrizioneable_id', $allOspIds)
            ->where('timestamp_accesso', '<=', $endOfDay)
            ->orderBy('iscrizioneable_id')
            ->orderBy('timestamp_accesso')
            ->orderBy('id')
            ->get(['iscrizioneable_id', 'tipo'])
            ->groupBy('iscrizioneable_id')
            ->filter(fn ($g) => $g->last()->tipo === 'entrata')
            ->keys()
            ->values();

        $socioEventi = SocioEvento::whereIn('id', $seIdsPresenti)
            ->with(['partecipante:id,nome,cognome,codice_fiscale', 'rispostaCheckin'])
            ->get();

        $ospiti = OspiteEvento::whereIn('id', $ospIdsPresenti)
            ->with('rispostaCheckin')
            ->get();

        // ── Presenze_evento per fallback ─────────────────────────────────────────
        $partIds     = $socioEventi->pluck('partecipante_id');
        $presenzeMap = Presenza::where('evento_id', $evento->id)
            ->whereIn('partecipante_id', $partIds)
            ->get()
            ->groupBy(fn ($p) => $p->partecipante_id . '_' . $p->tipo . '_' . $p->data->format('Y-m-d'));

        // ── Presenze dichiarate da chi NON è in campo (attesi ma non arrivati) ──────
        $attetiNonArrivatiMap = Presenza::where('evento_id', $evento->id)
            ->whereDate('data', $this->data)
            ->whereNotIn('partecipante_id', $partIds->isNotEmpty() ? $partIds->all() : [0])
            ->with('partecipante:id,nome,cognome,codice_fiscale')
            ->get()
            ->groupBy('tipo')
            ->map(fn ($gruppo) => $gruppo->unique('partecipante_id'));

        // ── Slot pasto del giorno ────────────────────────────────────────────────
        $slotsDelGiorno = [];

        // La colazione del giorno corrente si vede come "colazione domani" del giorno precedente.
        foreach ($evento->corsi as $corso) {
            foreach ($corso->form_sections ?? [] as $s) {
                if (($s['attivo'] ?? true) === false) continue;
                if ($s['data'] === $this->data && in_array($s['tipo'], ['pranzo', 'cena', 'pernotto'], true)) {
                    $slotsDelGiorno[$s['tipo'] . '_' . $s['data']] = $s['tipo'];
                }
            }
        }

        foreach ($socioEventi as $se) {
            foreach (array_keys($se->rispostaCheckin?->risposte ?? []) as $k) {
                if (preg_match('/^(pranzo|cena|pernotto)_(.+)$/', $k, $m) && $m[2] === $this->data) {
                    $slotsDelGiorno[$k] = $m[1];
                }
            }
        }
        foreach ($ospiti as $osp) {
            foreach (array_keys($osp->rispostaCheckin?->risposte ?? []) as $k) {
                if (preg_match('/^(pranzo|cena|pernotto)_(.+)$/', $k, $m) && $m[2] === $this->data) {
                    $slotsDelGiorno[$k] = $m[1];
                }
            }
        }

        $ordTipo = ['colazione' => 0, 'pranzo' => 1, 'cena' => 2, 'pernotto' => 3];
        uksort($slotsDelGiorno, fn ($a, $b) =>
            ($ordTipo[explode('_', $a)[0]] ?? 9) <=> ($ordTipo[explode('_', $b)[0]] ?? 9)
        );

        // ── Conteggi per slot ────────────────────────────────────────────────────
        $tipoLabelMap = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];

        $righe = [];
        foreach ($slotsDelGiorno as $chiave => $tipo) {
            $count                = 0;
            $personeConEsigenze   = [];
            $personeConfermate    = [];
            $personeNonConfermate = [];

            foreach ($socioEventi as $se) {
                $risposte   = $se->rispostaCheckin?->risposte;
                $entry      = [
                    'nome' => trim(($se->partecipante?->cognome ?? '') . ' ' . ($se->partecipante?->nome ?? '')),
                    'cf'   => $se->partecipante?->codice_fiscale ?? '',
                ];
                if (self::resolvePresenza($se->partecipante_id, $chiave, $risposte, $presenzeMap)) {
                    $count++;
                    $personeConfermate[] = $entry;
                    $allergeni = $risposte['allergeni'] ?? [];
                    $diete     = $risposte['diete']     ?? [];
                    $note      = trim($risposte['note_extra'] ?? '');
                    if ($allergeni || $diete || $note) {
                        $personeConEsigenze[] = array_merge($entry, [
                            'allergeni' => $allergeni,
                            'diete'     => $diete,
                            'note'      => $note ?: null,
                        ]);
                    }
                } else {
                    $personeNonConfermate[] = $entry;
                }
            }

            foreach ($ospiti as $osp) {
                $risposte = $osp->rispostaCheckin?->risposte;
                $entry    = ['nome' => $osp->nome_completo, 'cf' => $osp->cf ?? ''];
                if ($risposte[$chiave] ?? false) {
                    $count++;
                    $personeConfermate[] = $entry;
                    $allergeni = $risposte['allergeni'] ?? [];
                    $diete     = $risposte['diete']     ?? [];
                    $note      = trim($risposte['note_extra'] ?? '');
                    if ($allergeni || $diete || $note) {
                        $personeConEsigenze[] = array_merge($entry, [
                            'allergeni' => $allergeni,
                            'diete'     => $diete,
                            'note'      => $note ?: null,
                        ]);
                    }
                } else {
                    $personeNonConfermate[] = $entry;
                }
            }

            usort($personeConEsigenze,   fn ($a, $b) => strcmp($a['nome'], $b['nome']));
            usort($personeConfermate,    fn ($a, $b) => strcmp($a['nome'], $b['nome']));
            usort($personeNonConfermate, fn ($a, $b) => strcmp($a['nome'], $b['nome']));

            $attetiNonArrivati = ($attetiNonArrivatiMap->get($tipo, collect()))
                ->map(fn ($p) => [
                    'nome' => trim(($p->partecipante?->cognome ?? '') . ' ' . ($p->partecipante?->nome ?? '')),
                    'cf'   => $p->partecipante?->codice_fiscale ?? '',
                ])
                ->sortBy('nome')
                ->values()
                ->all();

            $righe[] = [
                'chiave'               => $chiave,
                'tipo'                 => $tipo,
                'label'                => ($tipoLabelMap[$tipo] ?? ucfirst($tipo)) . ' · ' . $this->dayLabel,
                'count'                => $count,
                'personeConEsigenze'   => $personeConEsigenze,
                'personeConfermate'    => $personeConfermate,
                'personeNonConfermate' => $personeNonConfermate,
                'attetiNonArrivati'    => $attetiNonArrivati,
            ];
        }

        // ── Colazione domani derivata dai pernotti di questa notte ───────────────
        // Se c'è pernotto confermato oggi E colazione domani non è già uno slot diretto,
        // mostriamo quante colazioni ci saranno domani mattina (= chi dorme stanotte).
        $chiavePernotto        = 'pernotto_' . $this->data;
        $nextDayStr            = Carbon::parse($this->data)->addDay()->format('Y-m-d');
        $chiaveColazioneDomani = 'colazione_' . $nextDayStr;

        if (isset($slotsDelGiorno[$chiavePernotto]) && ! isset($slotsDelGiorno[$chiaveColazioneDomani])) {
            $countCol             = 0;
            $esigenzeCol          = [];
            $confermatiCol        = [];
            $nonConfermatiCol     = [];

            foreach ($socioEventi as $se) {
                $risposte = $se->rispostaCheckin?->risposte;
                $entry    = [
                    'nome' => trim(($se->partecipante?->cognome ?? '') . ' ' . ($se->partecipante?->nome ?? '')),
                    'cf'   => $se->partecipante?->codice_fiscale ?? '',
                ];
                if (self::resolvePresenza($se->partecipante_id, $chiavePernotto, $risposte, $presenzeMap)) {
                    $countCol++;
                    $confermatiCol[] = $entry;
                    $allergeni = $risposte['allergeni'] ?? [];
                    $diete     = $risposte['diete']     ?? [];
                    $note      = trim($risposte['note_extra'] ?? '');
                    if ($allergeni || $diete || $note) {
                        $esigenzeCol[] = array_merge($entry, [
                            'allergeni' => $allergeni,
                            'diete'     => $diete,
                            'note'      => $note ?: null,
                        ]);
                    }
                } else {
                    $nonConfermatiCol[] = $entry;
                }
            }
            foreach ($ospiti as $osp) {
                $risposte = $osp->rispostaCheckin?->risposte;
                $entry    = ['nome' => $osp->nome_completo, 'cf' => $osp->cf ?? ''];
                if ($risposte[$chiavePernotto] ?? false) {
                    $countCol++;
                    $confermatiCol[] = $entry;
                    $allergeni = $risposte['allergeni'] ?? [];
                    $diete     = $risposte['diete']     ?? [];
                    $note      = trim($risposte['note_extra'] ?? '');
                    if ($allergeni || $diete || $note) {
                        $esigenzeCol[] = array_merge($entry, [
                            'allergeni' => $allergeni,
                            'diete'     => $diete,
                            'note'      => $note ?: null,
                        ]);
                    }
                } else {
                    $nonConfermatiCol[] = $entry;
                }
            }
            usort($esigenzeCol,      fn ($a, $b) => strcmp($a['nome'], $b['nome']));
            usort($confermatiCol,    fn ($a, $b) => strcmp($a['nome'], $b['nome']));
            usort($nonConfermatiCol, fn ($a, $b) => strcmp($a['nome'], $b['nome']));

            // Attesi non arrivati per colazione domani = chi ha dichiarato pernotto stanotte ma non è in campo
            $attetiColDomani = ($attetiNonArrivatiMap->get('pernotto', collect()))
                ->map(fn ($p) => [
                    'nome' => trim(($p->partecipante?->cognome ?? '') . ' ' . ($p->partecipante?->nome ?? '')),
                    'cf'   => $p->partecipante?->codice_fiscale ?? '',
                ])
                ->sortBy('nome')
                ->values()
                ->all();

            $nextDayLabel = Carbon::parse($nextDayStr)->translatedFormat('l j F');
            $righe[] = [
                'chiave'               => $chiaveColazioneDomani,
                'tipo'                 => 'colazione',
                'label'                => 'Colazione · ' . $nextDayLabel . ' (pernottanti stanotte)',
                'count'                => $countCol,
                'personeConEsigenze'   => $esigenzeCol,
                'personeConfermate'    => $confermatiCol,
                'personeNonConfermate' => $nonConfermatiCol,
                'attetiNonArrivati'    => $attetiColDomani,
            ];
        }

        // ── Corsi attivi nel giorno con lista partecipanti e badge check-in ────────
        $corsiDelGiorno = $evento->corsi->filter(
            fn ($c) => $c->giorniInPresenza()->contains(fn ($d) => $d->format('Y-m-d') === $this->data)
        )->sortBy('nome');

        $partIdsConAccesso = $socioEventi->pluck('partecipante_id')->flip()->all();

        $corsiIds = $corsiDelGiorno->pluck('id');
        $iscrizioniPerCorso = \App\Models\Iscrizione::whereIn('corso_id', $corsiIds)
            ->where('stato', '!=', 'ritirato')
            ->with('partecipante:id,nome,cognome,codice_fiscale')
            ->get()
            ->groupBy('corso_id');

        $corsiLista = [];
        foreach ($corsiDelGiorno as $corso) {
            $iscrizioni   = $iscrizioniPerCorso->get($corso->id, collect());
            $partecipanti = $iscrizioni->map(fn ($i) => [
                'nome'    => trim(($i->partecipante?->cognome ?? '') . ' ' . ($i->partecipante?->nome ?? '')),
                'cf'      => $i->partecipante?->codice_fiscale ?? '',
                'checkin' => isset($partIdsConAccesso[$i->partecipante_id]),
            ])->sortBy('nome')->values()->all();

            $corsiLista[] = [
                'nome'        => $corso->nome,
                'totIscritti' => count($partecipanti),
                'totCheckin'  => collect($partecipanti)->where('checkin', true)->count(),
                'partecipanti'=> $partecipanti,
            ];
        }
        $this->corsiLista = $corsiLista;

        // ── Staff in campo quel giorno, deduplica rispetto alle iscrizioni ai corsi ─
        // Esclude chi compare già in un corso attivo oggi (corsista o corsista+staff).
        $corsistiDelGiornoIds = $iscrizioniPerCorso->flatten(1)
            ->pluck('partecipante_id')
            ->flip()
            ->all();

        $staffAttiviInCampo = IscrizioneStaff::where('evento_id', $evento->id)
            ->where(function ($q) {
                $q->whereDoesntHave('rispostaPreregistrazione')
                  ->orWhereHas('rispostaPreregistrazione', fn ($q2) =>
                      $q2->whereRaw("jsonb_array_length(risposte->'disponibilita') > 0")
                  );
            })
            ->with('partecipante:id,nome,cognome,codice_fiscale')
            ->get();

        $staffLista = [];
        foreach ($staffAttiviInCampo as $iscStaff) {
            $pid = $iscStaff->partecipante_id;
            if (! isset($partIdsConAccesso[$pid])) continue;   // non presente questo giorno
            if (isset($corsistiDelGiornoIds[$pid]))   continue; // già nei corsi del giorno
            $staffLista[] = [
                'nome' => trim(($iscStaff->partecipante?->cognome ?? '') . ' ' . ($iscStaff->partecipante?->nome ?? '')),
                'cf'   => $iscStaff->partecipante?->codice_fiscale ?? '',
            ];
        }
        usort($staffLista, fn ($a, $b) => strcmp($a['nome'], $b['nome']));
        $this->staffLista = $staffLista;

        $this->righe        = $righe;
        $this->totPresenti   = $socioEventi->count() + $ospiti->count();
        $this->totConCheckin = $socioEventi->filter(fn ($se) => $se->rispostaCheckin !== null)->count()
                             + $ospiti->filter(fn ($osp) => $osp->rispostaCheckin !== null)->count();
        $this->totPrevisti   = Presenza::where('evento_id', $evento->id)
                             ->whereDate('data', $this->data)
                             ->distinct('partecipante_id')
                             ->count('partecipante_id');
    }

    private static function resolvePresenza(int $pid, string $chiave, ?array $risposte, Collection $presenzeMap): bool
    {
        [$tipo, $dataStr] = explode('_', $chiave, 2);
        $mapKey   = $pid . '_' . $tipo . '_' . $dataStr;
        $presenze = $presenzeMap->get($mapKey, collect());

        if ($presenze->contains('fonte', 'admin')) return true;

        if ($risposte !== null && array_key_exists($chiave, $risposte)) {
            return (bool) $risposte[$chiave];
        }

        if ($presenze->contains('fonte', 'form')) return true;

        return false;
    }

    public function getTitle(): string
    {
        return 'Gestione presenze — ' . $this->record->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventoResource::getUrl('index')                                       => 'Eventi',
            EventoResource::getUrl('gestione', ['record' => $this->record])       => $this->record->nome,
            EventoResource::getUrl('presenze-live', ['record' => $this->record])  => 'Presenze live',
            ''                                                                    => 'Gestione presenze',
        ];
    }

}
