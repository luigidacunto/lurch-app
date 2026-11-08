<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Http\Controllers\BadgePortaleController;
use App\Http\Controllers\CheckinAccessoController;
use App\Models\AccessoEvento;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\OspiteEvento;
use App\Models\RispostaCheckin;
use App\Models\SocioEvento;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class PresenzeLive extends Page
{
    protected static string $resource = EventoResource::class;

    protected string $view = 'filament.resources.evento-resource.pages.presenze-live';

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

    /** Il polling ha senso solo nei giorni reali dell'evento (anche non contigui) — vedi Evento::giorniEvento() */
    public function pollAttivo(): bool
    {
        return $this->record->giorniEvento()->contains(fn ($g) => $g->isToday());
    }

    /** Persone attualmente dentro al campo */
    public array $dentro = [];

    /** Ricerca live nella tabella "in campo" */
    public string $searchDentro     = '';
    public string $filtroDentroTipo = '';
    public int    $dentroPerPage    = 25;
    public int    $dentroPage       = 1;

    /** Storico accessi */
    public array $storicoOggi = [];

    /** Paginazione e filtri storico */
    public int    $storicoPage       = 1;
    public int    $storicoPerPage    = 25;
    public int    $storicoTotale     = 0;
    public string $storicoDataFiltro = '';
    public string $storicoSearchNome = '';

    /** Contatori */
    public int $totCorsisti = 0;
    public int $totStaff    = 0;
    public int $totOspiti   = 0;

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

    public function updatedSearchDentro(): void      { $this->dentroPage = 1; }
    public function updatedFiltroDentroTipo(): void  { $this->dentroPage = 1; }
    public function updatedDentroPerPage(): void     { $this->dentroPage = 1; }

    public function cambiaPaginaDentro(int $page): void { $this->dentroPage = $page; }

    public function updatedStoricoPerPage(): void    { $this->storicoPage = 1; $this->carica(); }
    public function updatedStoricoDataFiltro(): void { $this->storicoPage = 1; $this->carica(); }
    public function updatedStoricoSearchNome(): void { $this->storicoPage = 1; $this->carica(); }

    public function cambiaPaginaStorico(int $page): void { $this->storicoPage = $page; $this->carica(); }

    public function carica(): void
    {
        $evento = $this->record;

        // Carica SocioEvento primary (id minore) per ogni partecipante
        $primaries = SocioEvento::where('evento_id', $evento->id)
            ->whereIn('id', function ($sub) use ($evento) {
                $sub->selectRaw('MIN(id)')
                    ->from('socio_evento')
                    ->where('evento_id', $evento->id)
                    ->groupBy('partecipante_id');
            })
            ->with(['partecipante', 'ultimoAccesso'])
            ->get();

        // Carica info ruolo per tipo badge
        $partIscIds   = Iscrizione::whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
            ->pluck('partecipante_id')->unique()->flip()->all(); // set di partecipante_id corsisti
        $partStaffIds = IscrizioneStaff::where('evento_id', $evento->id)
            ->pluck('partecipante_id')->unique()->flip()->all(); // set di partecipante_id staff registrati

        // Staff "attivi": hanno dichiarato disponibilità (formazione/logistica) nel form preregistrazione.
        // Se il form non è ancora stato compilato, si considera staff per default.
        // Solo chi ha compilato il form con disponibilita[] vuoto viene trattato come discente.
        $staffAttiviIds = IscrizioneStaff::where('evento_id', $evento->id)
            ->where(function ($q) {
                $q->whereDoesntHave('rispostaPreregistrazione')
                  ->orWhereHas('rispostaPreregistrazione', fn ($q2) =>
                      $q2->whereRaw("jsonb_array_length(risposte->'disponibilita') > 0")
                  );
            })
            ->pluck('partecipante_id')->unique()->flip()->all();

        $dentro = [];

        foreach ($primaries as $se) {
            if ($se->ultimoAccesso?->tipo !== 'entrata') continue;

            $partId          = $se->partecipante_id;
            $hasCors         = isset($partIscIds[$partId]);
            $hasEffettivoStaff = isset($partStaffIds[$partId]) && isset($staffAttiviIds[$partId]);
            $tipo            = ($hasCors && $hasEffettivoStaff) ? 'corsista+staff'
                             : ($hasEffettivoStaff             ? 'staff'
                             : 'corsista');

            $dentro[] = [
                'tipo'              => $tipo,
                'nome'              => $se->partecipante->nome_completo,
                'cf'                => $se->partecipante->codice_fiscale,
                'badge_token'       => $se->badge_token,
                'entrato_alle'      => $se->ultimoAccesso->timestamp_accesso,
                'ultimo_accesso_id' => $se->ultimoAccesso->id,
                'tipo_isc_class'    => SocioEvento::class,
                'isc_id'            => $se->id,
            ];
        }

        // Ospiti
        $ospiti = OspiteEvento::where('evento_id', $evento->id)
            ->with('ultimoAccesso')
            ->get();

        foreach ($ospiti as $osp) {
            if ($osp->ultimoAccesso?->tipo !== 'entrata') continue;
            $dentro[] = [
                'tipo'              => 'ospite',
                'nome'              => $osp->nome_completo,
                'cf'                => $osp->cf,
                'badge_token'       => null,
                'entrato_alle'      => $osp->ultimoAccesso->timestamp_accesso,
                'ultimo_accesso_id' => $osp->ultimoAccesso->id,
                'tipo_isc_class'    => OspiteEvento::class,
                'isc_id'            => $osp->id,
            ];
        }

        usort($dentro, fn ($a, $b) => $a['entrato_alle'] <=> $b['entrato_alle']);

        $this->dentro      = $dentro;
        $this->totCorsisti = collect($dentro)->whereIn('tipo', ['corsista', 'corsista+staff'])->count();
        $this->totStaff    = collect($dentro)->whereIn('tipo', ['staff', 'corsista+staff'])->count();
        $this->totOspiti   = collect($dentro)->where('tipo', 'ospite')->count();

        // Storico — filtro ricerca nome/CF
        $searchNome = mb_strtolower(trim($this->storicoSearchNome));

        $primariesFiltered = $searchNome
            ? $primaries->filter(fn ($se) =>
                str_contains(mb_strtolower($se->partecipante->nome_completo), $searchNome) ||
                str_contains(mb_strtolower($se->partecipante->codice_fiscale ?? ''), $searchNome)
              )
            : $primaries;

        $ospitiFiltered = $searchNome
            ? $ospiti->filter(fn ($o) =>
                str_contains(mb_strtolower($o->nome_completo), $searchNome) ||
                str_contains(mb_strtolower($o->cf ?? ''), $searchNome)
              )
            : $ospiti;

        $seIds  = $primariesFiltered->pluck('id')->all();
        $ospIds = $ospitiFiltered->pluck('id')->all();

        $whereFn = function ($q) use ($seIds, $ospIds) {
            $q->where(function ($q2) use ($seIds) {
                $q2->where('iscrizioneable_type', SocioEvento::class)
                   ->whereIn('iscrizioneable_id', $seIds ?: [0]);
            })->orWhere(function ($q2) use ($ospIds) {
                $q2->where('iscrizioneable_type', OspiteEvento::class)
                   ->whereIn('iscrizioneable_id', $ospIds ?: [0]);
            });
        };

        $baseQuery = AccessoEvento::query();
        if ($this->storicoDataFiltro) {
            $baseQuery->whereDate('timestamp_accesso', $this->storicoDataFiltro);
        }

        $this->storicoTotale = (clone $baseQuery)->where($whereFn)->count();

        $accessi = (clone $baseQuery)
            ->with('operatore')
            ->where($whereFn)
            ->orderByDesc('timestamp_accesso')
            ->offset(($this->storicoPage - 1) * $this->storicoPerPage)
            ->limit($this->storicoPerPage)
            ->get();

        // Mappa ID → nome per storico
        $nomiSe  = $primaries->mapWithKeys(fn ($se) => [$se->id => [
            'nome' => $se->partecipante->nome_completo,
            'cf'   => $se->partecipante->codice_fiscale,
        ]]);
        $nomiOsp = $ospiti->mapWithKeys(fn ($o) => [$o->id => [
            'nome' => $o->nome_completo,
            'cf'   => $o->cf,
        ]]);

        $this->storicoOggi = $accessi->map(function ($acc) use ($nomiSe, $nomiOsp) {
            $dati = match ($acc->iscrizioneable_type) {
                SocioEvento::class  => $nomiSe[$acc->iscrizioneable_id] ?? ['nome' => '—', 'cf' => null],
                OspiteEvento::class => $nomiOsp[$acc->iscrizioneable_id] ?? ['nome' => '—', 'cf' => null],
                default             => ['nome' => '—', 'cf' => null],
            };
            return [
                'orario'    => $acc->timestamp_accesso->format($this->storicoDataFiltro ? 'H:i' : 'd/m H:i'),
                'nome'      => $dati['nome'],
                'cf'        => $dati['cf'],
                'tipo'      => $acc->tipo,
                'operatore' => $acc->operatore->name ?? '—',
            ];
        })->values()->all();
    }

    public function registraUscitaAction(): Action
    {
        return Action::make('registraUscita')
            ->label('Registra uscita')
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->color('success')
            ->iconButton()
            ->requiresConfirmation()
            ->modalDescription(fn (array $arguments) => 'Registrare uscita manuale per ' . ($arguments['nome'] ?? 'questa persona') . '?')
            ->action(function (array $arguments) {
                if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $this->record)) {
                    Notification::make()->title('Non autorizzato')->danger()->send();
                    return;
                }

                AccessoEvento::create([
                    'iscrizioneable_type' => $arguments['tipoClass'],
                    'iscrizioneable_id'   => $arguments['iscId'],
                    'tipo'                => 'uscita',
                    'timestamp_accesso'   => now(),
                    'operatore_id'        => auth()->id(),
                    'note'                => 'Uscita manuale da dashboard',
                ]);

                $this->carica();

                Notification::make()->title('Uscita registrata')->success()->send();
            });
    }

    public function checkoutMassaAction(): Action
    {
        return Action::make('checkoutMassa')
            ->label(fn () => 'Checkout di massa (' . count($this->dentro) . ')')
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->color('danger')
            ->visible(fn () => count($this->dentro) > 0)
            ->requiresConfirmation()
            ->modalHeading('Checkout di massa')
            ->modalDescription(fn () => count($this->dentro) . ' ' . (count($this->dentro) === 1 ? 'persona ancora in campo' : 'persone ancora in campo')
                . '. Verrà registrata un\'uscita per tutte all\'orario indicato. L\'operazione non può essere annullata.')
            ->modalSubmitActionLabel('Conferma checkout')
            ->schema([
                Forms\Components\DateTimePicker::make('timestamp')
                    ->label('Orario checkout')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (array $data) {
                if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $this->record)) {
                    Notification::make()->title('Non autorizzato')->danger()->send();
                    return;
                }

                if (empty($this->dentro)) return;

                $timestamp   = \Carbon\Carbon::parse($data['timestamp']);
                $operatoreId = auth()->id();
                $count       = 0;

                foreach ($this->dentro as $p) {
                    AccessoEvento::create([
                        'iscrizioneable_type' => $p['tipo_isc_class'],
                        'iscrizioneable_id'   => $p['isc_id'],
                        'tipo'                => 'uscita',
                        'timestamp_accesso'   => $timestamp,
                        'operatore_id'        => $operatoreId,
                        'note'                => 'Checkout di massa da dashboard',
                    ]);
                    $count++;
                }

                $this->carica();

                Notification::make()
                    ->title("Checkout completato: {$count} " . ($count === 1 ? 'persona registrata' : 'persone registrate') . ' in uscita')
                    ->success()
                    ->send();
            });
    }

    /** Slot pasti/pernotti disponibili per un SocioEvento: giorni reali dell'evento (EventoSezioniGenerator,
     *  stessa fonte usata da CheckinRisposteEvento) + slot già presenti in risposte_checkin ma non più in griglia. */
    private function slotsPastiPer(SocioEvento $se): array
    {
        $slots = [];
        foreach (\App\Services\EventoSezioniGenerator::perGiorni($this->record, bypassResidenziale: true, includeColazione: true) as $s) {
            $slots[$s['chiave']] = ['tipo' => $s['tipo'], 'data' => $s['data']];
        }

        $risposte = $se->rispostaCheckin?->risposte ?? [];
        foreach ($risposte as $k => $v) {
            if (preg_match('/^(colazione|pranzo|cena|pernotto)_(\d{4}-\d{2}-\d{2})$/', $k, $m) && ! isset($slots[$k])) {
                $slots[$k] = ['tipo' => $m[1], 'data' => $m[2]];
            }
        }

        return $slots;
    }

    /** Le 4 categorie del catalogo OpzioneAlimentare, dinamiche in base a cosa è configurato. Allergeni ha
     *  un campo risposte_checkin dedicato ('allergeni'); intolleranze/diete/esclusioni condividono lo stesso
     *  campo aggregato ('diete') — stessa scelta già fatta nel form pubblico (resources/views/checkin/accesso.blade.php). */
    private static function campiAlimentariMappa(Evento $evento): array
    {
        $datiForm = CheckinAccessoController::datiForm($evento);

        return [
            'allergeni'    => ['label' => 'Allergeni',           'options' => $datiForm['allergeniList']->pluck('nome', 'nome')->all(),    'campo' => 'allergeni'],
            'intolleranze' => ['label' => 'Intolleranze',        'options' => $datiForm['intolleranzeList']->pluck('nome', 'nome')->all(), 'campo' => 'diete'],
            'diete'        => ['label' => 'Diete particolari',   'options' => $datiForm['dieteList']->pluck('nome', 'nome')->all(),        'campo' => 'diete'],
            'esclusioni'   => ['label' => 'Esclusioni alimentari','options' => $datiForm['esclusioniList']->pluck('nome', 'nome')->all(),  'campo' => 'diete'],
        ];
    }

    /** @return Forms\Components\CheckboxList[] */
    private static function campiAlimentari(Evento $evento): array
    {
        return collect(static::campiAlimentariMappa($evento))
            ->map(fn (array $c, string $chiave) => Forms\Components\CheckboxList::make($chiave)
                ->label($c['label'])
                ->options($c['options'])
                ->columns(3)
                ->columnSpanFull())
            ->values()
            ->all();
    }

    /** Ricostruisce i 4 campi form dai 2 campi salvati (allergeni + diete aggregato). */
    private static function splitCampiAlimentari(Evento $evento, array $risposte): array
    {
        $allergeniSel = collect($risposte['allergeni'] ?? []);
        $dieteSel     = collect($risposte['diete'] ?? []);

        return collect(static::campiAlimentariMappa($evento))
            ->map(fn (array $c) => ($c['campo'] === 'allergeni' ? $allergeniSel : $dieteSel)
                ->intersect($c['options'])
                ->values()
                ->all())
            ->all();
    }

    public function modificaCheckinAction(): Action
    {
        $labelTipo = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];

        return Action::make('modificaCheckin')
            ->label('Modifica dati')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->iconButton()
            ->modalHeading(fn (array $arguments) => 'Modifica dati check-in — ' . ($arguments['nome'] ?? ''))
            ->modalSubmitActionLabel('Salva modifiche')
            ->modalSubmitAction(fn ($action) => $action->color('success'))
            ->schema(function (array $arguments) use ($labelTipo) {
                $se    = SocioEvento::find($arguments['seId']);
                $slots = $se ? $this->slotsPastiPer($se) : [];

                $pastiOptions = collect($slots)->mapWithKeys(fn ($s, $chiave) =>
                    [$chiave => \Carbon\Carbon::parse($s['data'])->translatedFormat('j/m') . ' — ' . ($labelTipo[$s['tipo']] ?? ucfirst($s['tipo']))]
                )->all();

                return [
                    Forms\Components\CheckboxList::make('pasti')
                        ->label('Pasti e pernotti')
                        ->options($pastiOptions)
                        ->columns(2)
                        ->columnSpanFull(),

                    ...static::campiAlimentari($this->record),

                    Forms\Components\Textarea::make('note')
                        ->label('Note extra')
                        ->maxLength(50)
                        ->rows(2)
                        ->columnSpanFull(),
                ];
            })
            ->fillForm(function (array $arguments) {
                $se = SocioEvento::with('rispostaCheckin')->find($arguments['seId']);
                if (! $se) return [];

                $slots    = $this->slotsPastiPer($se);
                $risposte = $se->rispostaCheckin?->risposte ?? [];

                $pasti = [];
                foreach ($slots as $chiave => $slot) {
                    if ($risposte[$chiave] ?? false) $pasti[] = $chiave;
                }

                return [
                    'pasti' => $pasti,
                    ...static::splitCampiAlimentari($this->record, $risposte),
                    'note'  => $risposte['note_extra'] ?? '',
                ];
            })
            ->action(function (array $data, array $arguments) {
                if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $this->record)) {
                    Notification::make()->title('Non autorizzato')->danger()->send();
                    return;
                }

                $se = SocioEvento::with('rispostaCheckin')->find($arguments['seId']);
                if (! $se) return;

                $slots = $this->slotsPastiPer($se);

                $rispostaCheckin = $se->rispostaCheckin ?? new RispostaCheckin([
                    'iscrizioneable_type' => SocioEvento::class,
                    'iscrizioneable_id'   => $se->id,
                    'checkin_at'          => now(),
                    'operatore_id'        => auth()->id(),
                ]);

                // Merge: preserva campi anagrafici e staff, aggiorna solo i campi editabili
                $risposte = $rispostaCheckin->risposte ?? [];

                $pastiSelezionati = collect($data['pasti'] ?? []);
                foreach ($slots as $chiave => $slot) {
                    $risposte[$chiave] = $pastiSelezionati->contains($chiave);
                }

                $risposte['allergeni']  = array_values($data['allergeni'] ?? []);
                $risposte['diete']      = array_values(array_unique(array_merge(
                    $data['intolleranze'] ?? [],
                    $data['diete'] ?? [],
                    $data['esclusioni'] ?? [],
                )));
                $risposte['note_extra'] = trim($data['note'] ?? '');
                // I consensi (privacy/foto-video/salute) non sono modificabili da qui:
                // restano quelli dati dalla persona stessa al check-in, non li tocca l'operatore.

                $rispostaCheckin->risposte = $risposte;
                $rispostaCheckin->save();

                $this->carica();

                Notification::make()->title('Dati check-in aggiornati')->success()->send();
            });
    }

    public function getTitle(): string
    {
        return 'Presenze live — ' . $this->record->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventoResource::getUrl('index')                                        => 'Eventi',
            EventoResource::getUrl('gestione', ['record' => $this->record])        => $this->record->nome,
            ''                                                                     => 'Presenze live',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('cercaCF')
                    ->label('Cerca per CF')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->url(fn () => route('checkin.ricerca', $this->record))
                    ->openUrlInNewTab(),

                $this->checkoutMassaAction(),
            ])
                ->label('Azioni')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('info')
                ->button(),
        ];
    }
}
