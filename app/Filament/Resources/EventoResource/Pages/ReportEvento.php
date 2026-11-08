<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Http\Controllers\ExportPernottatiCheckinController;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\OspiteEvento;
use App\Models\RispostaCheckin;
use App\Models\SocioEvento;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;

class ReportEvento extends Page
{
    protected static string $resource = EventoResource::class;

    protected string $view = 'filament.resources.evento-resource.pages.report-evento';

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

    public string  $dayLabel        = '';
    public ?string $prevData        = null;
    public ?string $nextData        = null;
    public array   $righe           = [];
    public array   $pernottatiLista = [];
    public int     $pernottatiCount = 0;

    public int $statDiscentiFormati = 0;
    public int $statDocentiFormati  = 0;
    public int $statLogisticaImpegnata = 0;
    public int $statPersoneUniche   = 0;

    public function mount(Evento $record): void
    {
        abort_unless(static::canAccess(['record' => $record]), 403);

        $this->record = $record;

        $this->carica();
        $this->caricaPernottati();
        $this->caricaStatistiche();
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
        $evento = $this->record->load('corsi');

        // Solo giorni operativi (reali + giorno prima/dopo di ogni blocco), vedi Evento::giorniOperativi().
        ['data' => $this->data, 'prev' => $this->prevData, 'next' => $this->nextData] = $evento->navigazioneGiorno($this->data ?: null);

        $dataCarbon     = Carbon::parse($this->data);
        $this->dayLabel = $dataCarbon->translatedFormat('l j F');

        $allSeIds  = SocioEvento::where('evento_id', $evento->id)->pluck('id');
        $allOspIds = OspiteEvento::where('evento_id', $evento->id)->pluck('id');

        // Snapshot: tutti i check-in dell'evento indipendente dalla data
        $snapshotAll = RispostaCheckin::where('iscrizioneable_type', SocioEvento::class)
            ->whereIn('iscrizioneable_id', $allSeIds)
            ->with('iscrizioneable.partecipante:id,nome,cognome,codice_fiscale')
            ->get()
            ->map(fn ($r) => [
                'nome'     => trim(($r->iscrizioneable?->partecipante?->cognome ?? '') . ' ' . ($r->iscrizioneable?->partecipante?->nome ?? '')),
                'cf'       => $r->iscrizioneable?->partecipante?->codice_fiscale ?? '',
                'risposte' => $r->risposte ?? [],
            ])
            ->merge(
                RispostaCheckin::where('iscrizioneable_type', OspiteEvento::class)
                    ->whereIn('iscrizioneable_id', $allOspIds)
                    ->with('iscrizioneable')
                    ->get()
                    ->map(fn ($r) => [
                        'nome'     => $r->iscrizioneable?->nome_completo ?? '',
                        'cf'       => $r->iscrizioneable?->cf ?? '',
                        'risposte' => $r->risposte ?? [],
                    ])
            );

        // Slot pasto/pernotto del giorno: da form_sections corsi + da chiavi risposte checkin
        $slotsDelGiorno = [];
        foreach ($evento->corsi as $corso) {
            foreach ($corso->form_sections ?? [] as $s) {
                if ($s['data'] === $this->data && in_array($s['tipo'], ['pranzo', 'cena', 'pernotto'], true)) {
                    $slotsDelGiorno[$s['tipo'] . '_' . $s['data']] = $s['tipo'];
                }
            }
        }
        foreach ($snapshotAll as $p) {
            foreach (array_keys($p['risposte']) as $k) {
                if (preg_match('/^(pranzo|cena|pernotto)_(.+)$/', $k, $m) && $m[2] === $this->data) {
                    $slotsDelGiorno[$k] = $m[1];
                }
            }
        }

        $ordTipo = ['colazione' => 0, 'pranzo' => 1, 'cena' => 2, 'pernotto' => 3];
        uksort($slotsDelGiorno, fn ($a, $b) =>
            ($ordTipo[explode('_', $a)[0]] ?? 9) <=> ($ordTipo[explode('_', $b)[0]] ?? 9)
        );

        $tipoLabelMap = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];
        $coloriMap    = [
            'colazione' => ['bg' => '#fffbeb', 'num' => '#92400e', 'bord' => '#fde68a'],
            'pranzo'    => ['bg' => '#eff6ff', 'num' => '#1d4ed8', 'bord' => '#bfdbfe'],
            'cena'      => ['bg' => '#f5f3ff', 'num' => '#5b21b6', 'bord' => '#ddd6fe'],
            'pernotto'  => ['bg' => '#f0fdf4', 'num' => '#15803d', 'bord' => '#bbf7d0'],
        ];

        $righe = [];
        foreach ($slotsDelGiorno as $chiave => $tipo) {
            $snapshot = $snapshotAll
                ->filter(fn ($p) => ($p['risposte'][$chiave] ?? false) === true)
                ->sortBy('nome')
                ->values()
                ->map(fn ($p) => ['nome' => $p['nome'], 'cf' => $p['cf']])
                ->all();

            $colori = $coloriMap[$tipo] ?? ['bg' => '#f9fafb', 'num' => '#374151', 'bord' => '#e5e7eb'];

            $righe[] = [
                'chiave'        => $chiave,
                'tipo'          => $tipo,
                'label'         => ($tipoLabelMap[$tipo] ?? ucfirst($tipo)) . ' · ' . $this->dayLabel,
                'snapshot'      => $snapshot,
                'snapshotCount' => count($snapshot),
                'coloreBg'      => $colori['bg'],
                'coloreNum'     => $colori['num'],
                'coloreBord'    => $colori['bord'],
            ];
        }

        // Colazione domani derivata dai pernottanti stanotte
        $chiavePernotto        = 'pernotto_' . $this->data;
        $nextDayStr            = Carbon::parse($this->data)->addDay()->format('Y-m-d');
        $chiaveColazioneDomani = 'colazione_' . $nextDayStr;

        if (isset($slotsDelGiorno[$chiavePernotto]) && ! isset($slotsDelGiorno[$chiaveColazioneDomani])) {
            $snapshotCol = $snapshotAll
                ->filter(fn ($p) => ($p['risposte'][$chiavePernotto] ?? false) === true)
                ->sortBy('nome')
                ->values()
                ->map(fn ($p) => ['nome' => $p['nome'], 'cf' => $p['cf']])
                ->all();

            $righe[] = [
                'chiave'        => $chiaveColazioneDomani,
                'tipo'          => 'colazione',
                'label'         => 'Colazione · ' . Carbon::parse($nextDayStr)->translatedFormat('l j F') . ' (pernottanti stanotte)',
                'snapshot'      => $snapshotCol,
                'snapshotCount' => count($snapshotCol),
                'coloreBg'      => '#fffbeb',
                'coloreNum'     => '#92400e',
                'coloreBord'    => '#fde68a',
            ];
        }

        $this->righe = $righe;
    }

    private function caricaStatistiche(): void
    {
        $eventoId = $this->record->id;

        // Partecipanti che hanno fatto almeno un check-in (hanno RispostaCheckin su SocioEvento)
        $partecipantiConCheckin = SocioEvento::where('evento_id', $eventoId)
            ->whereHas('rispostaCheckin')
            ->pluck('partecipante_id');

        // Discenti formati: iscrizioni attive ai corsi dell'evento, con checkin (duplicati per corso contano)
        $this->statDiscentiFormati = Iscrizione::whereHas('corso', fn ($q) => $q->where('evento_id', $eventoId))
            ->where('stato', '!=', 'ritirato')
            ->whereIn('partecipante_id', $partecipantiConCheckin)
            ->count();

        // Docenti / Logistica impegnati: iscrizioni staff all'evento con almeno un checkin, per ruolo
        $this->statDocentiFormati = IscrizioneStaff::where('evento_id', $eventoId)
            ->where('ruolo', 'docenza')
            ->whereIn('partecipante_id', $partecipantiConCheckin)
            ->count();

        $this->statLogisticaImpegnata = IscrizioneStaff::where('evento_id', $eventoId)
            ->where('ruolo', 'logistica')
            ->whereIn('partecipante_id', $partecipantiConCheckin)
            ->count();

        // Persone uniche formate: corsisti distinti con checkin (nessun duplicato per CF)
        $this->statPersoneUniche = Iscrizione::whereHas('corso', fn ($q) => $q->where('evento_id', $eventoId))
            ->where('stato', '!=', 'ritirato')
            ->whereIn('partecipante_id', $partecipantiConCheckin)
            ->distinct('partecipante_id')
            ->count('partecipante_id');
    }

    private function caricaPernottati(): void
    {
        $lista = ExportPernottatiCheckinController::buildRighe($this->record);
        $this->pernottatiLista = $lista;
        $this->pernottatiCount = count($lista);
    }

    public function getTitle(): string
    {
        return 'Report — ' . $this->record->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventoResource::getUrl('index')                                 => 'Eventi',
            EventoResource::getUrl('gestione', ['record' => $this->record]) => $this->record->nome,
            ''                                                              => 'Report',
        ];
    }

}
