<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Http\Controllers\BadgePortaleController;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\OspiteEvento;
use App\Models\RispostaCheckin;
use App\Models\SocioEvento;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Locked;

class CheckinRisposteEvento extends Page
{
    protected static string $resource = EventoResource::class;

    protected string $view = 'filament.resources.evento-resource.pages.checkin-risposte-evento';

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

    public array  $lista        = [];
    public string $search       = '';
    public string $filtroTipo   = '';

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

    public function carica(): void
    {
        $evento = $this->record->load('corsi');

        $partIscIds = Iscrizione::whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
            ->pluck('partecipante_id')->unique()->flip()->all();

        $partStaffIds = IscrizioneStaff::where('evento_id', $evento->id)
            ->pluck('partecipante_id')->unique()->flip()->all();

        $staffAttiviIds = IscrizioneStaff::where('evento_id', $evento->id)
            ->where(function ($q) {
                $q->whereDoesntHave('rispostaPreregistrazione')
                  ->orWhereHas('rispostaPreregistrazione', fn ($q2) =>
                      $q2->whereRaw("jsonb_array_length(risposte->'disponibilita') > 0")
                  );
            })
            ->pluck('partecipante_id')->unique()->flip()->all();

        $lista = [];

        SocioEvento::where('evento_id', $evento->id)
            ->whereIn('id', function ($sub) use ($evento) {
                $sub->selectRaw('MIN(id)')
                    ->from('socio_evento')
                    ->where('evento_id', $evento->id)
                    ->groupBy('partecipante_id');
            })
            ->whereHas('rispostaCheckin')
            ->with(['partecipante', 'rispostaCheckin'])
            ->get()
            ->each(function ($se) use (&$lista, $partIscIds, $partStaffIds, $staffAttiviIds) {
                $pid            = $se->partecipante_id;
                $hasCors        = isset($partIscIds[$pid]);
                $hasStaffAttivo = isset($partStaffIds[$pid]) && isset($staffAttiviIds[$pid]);
                $tipo           = ($hasCors && $hasStaffAttivo) ? 'corsista+staff'
                                : ($hasStaffAttivo ? 'staff' : 'corsista');

                $risposte  = $se->rispostaCheckin?->risposte ?? [];
                $nPasti    = collect($risposte)->filter(fn ($v, $k) =>
                    preg_match('/^(colazione|pranzo|cena)_/', $k) && $v === true)->count();
                $nPernotti = collect($risposte)->filter(fn ($v, $k) =>
                    str_starts_with($k, 'pernotto_') && $v === true)->count();

                $lista[] = [
                    'tipo_record' => 'se',
                    'isc_id'      => $se->id,
                    'nome'        => $se->partecipante?->nome_completo ?? '',
                    'cf'          => $se->partecipante?->codice_fiscale ?? '',
                    'tipo'        => $tipo,
                    'checkin_at'  => $se->rispostaCheckin?->checkin_at,
                    'n_pasti'     => $nPasti,
                    'n_pernotti'  => $nPernotti,
                ];
            });

        OspiteEvento::where('evento_id', $evento->id)
            ->whereHas('rispostaCheckin')
            ->with('rispostaCheckin')
            ->get()
            ->each(function ($osp) use (&$lista) {
                $risposte  = $osp->rispostaCheckin?->risposte ?? [];
                $nPasti    = collect($risposte)->filter(fn ($v, $k) =>
                    preg_match('/^(colazione|pranzo|cena)_/', $k) && $v === true)->count();
                $nPernotti = collect($risposte)->filter(fn ($v, $k) =>
                    str_starts_with($k, 'pernotto_') && $v === true)->count();

                $lista[] = [
                    'tipo_record' => 'osp',
                    'isc_id'      => $osp->id,
                    'nome'        => $osp->nome_completo ?? '',
                    'cf'          => $osp->cf ?? '',
                    'tipo'        => 'ospite',
                    'checkin_at'  => $osp->rispostaCheckin?->checkin_at,
                    'n_pasti'     => $nPasti,
                    'n_pernotti'  => $nPernotti,
                ];
            });

        usort($lista, fn ($a, $b) => strcmp($a['nome'], $b['nome']));
        $this->lista = $lista;
    }

    /** Griglia pasti/pernotti universale per un record (SocioEvento o OspiteEvento): cena+pernotto giorno
     *  prima di ogni blocco, poi colazione+pranzo+cena+pernotto per ogni giorno evento — uguale per
     *  corsisti/staff/ospiti (correzione operatore, sempre sbloccata indipendentemente da Evento::residenziale). */
    private function slotsPastiPer(SocioEvento|OspiteEvento $model): array
    {
        $slots = [];
        foreach (\App\Services\EventoSezioniGenerator::perGiorni($this->record, bypassResidenziale: true, includeColazione: true) as $s) {
            $slots[$s['chiave']] = ['tipo' => $s['tipo'], 'data' => $s['data']];
        }

        $risposte = $model->rispostaCheckin?->risposte ?? [];
        foreach ($risposte as $k => $v) {
            if (preg_match('/^(colazione|pranzo|cena|pernotto)_(\d{4}-\d{2}-\d{2})$/', $k, $m) && ! isset($slots[$k])) {
                $slots[$k] = ['tipo' => $m[1], 'data' => $m[2]];
            }
        }

        return $slots;
    }

    private function trovaRecord(string $tipoRecord, int $id): SocioEvento|OspiteEvento|null
    {
        return $tipoRecord === 'se'
            ? SocioEvento::with('rispostaCheckin')->find($id)
            : OspiteEvento::with('rispostaCheckin')->find($id);
    }

    public function modificaCheckinAction(): Action
    {
        $labelTipo = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];

        return Action::make('modificaCheckin')
            ->label('Modifica')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->modalHeading(fn (array $arguments) => 'Modifica dati check-in — ' . ($arguments['nome'] ?? ''))
            ->modalSubmitActionLabel('Salva modifiche')
            ->modalSubmitAction(fn ($action) => $action->color('success'))
            ->schema(function (array $arguments) use ($labelTipo) {
                $model = $this->trovaRecord($arguments['tipoRecord'], $arguments['id']);
                $slots = $model ? $this->slotsPastiPer($model) : [];

                $pastiOptions = collect($slots)->mapWithKeys(fn ($s, $chiave) =>
                    [$chiave => \Carbon\Carbon::parse($s['data'])->translatedFormat('j/m') . ' — ' . ($labelTipo[$s['tipo']] ?? ucfirst($s['tipo']))]
                )->all();

                return [
                    Forms\Components\CheckboxList::make('pasti')
                        ->label('Pasti e pernotti')
                        ->options($pastiOptions)
                        ->columns(2)
                        ->columnSpanFull(),
                ];
            })
            ->fillForm(function (array $arguments) {
                $model = $this->trovaRecord($arguments['tipoRecord'], $arguments['id']);
                if (! $model) return [];

                $slots    = $this->slotsPastiPer($model);
                $risposte = $model->rispostaCheckin?->risposte ?? [];

                $pasti = [];
                foreach ($slots as $chiave => $slot) {
                    if ($risposte[$chiave] ?? false) $pasti[] = $chiave;
                }

                return ['pasti' => $pasti];
            })
            ->action(function (array $data, array $arguments) {
                if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $this->record)) {
                    Notification::make()->title('Non autorizzato')->danger()->send();
                    return;
                }

                $model = $this->trovaRecord($arguments['tipoRecord'], $arguments['id']);
                if (! $model) return;

                $slots     = $this->slotsPastiPer($model);
                $morphType = $arguments['tipoRecord'] === 'se' ? SocioEvento::class : OspiteEvento::class;

                $rispostaCheckin = $model->rispostaCheckin ?? new RispostaCheckin([
                    'iscrizioneable_type' => $morphType,
                    'iscrizioneable_id'   => $model->id,
                    'checkin_at'          => now(),
                    'operatore_id'        => auth()->id(),
                ]);

                // Aggiorna solo i pasti/pernotti; preserva allergeni, diete, note e consensi esistenti
                $risposte         = $rispostaCheckin->risposte ?? [];
                $pastiSelezionati = collect($data['pasti'] ?? []);
                foreach ($slots as $chiave => $slot) {
                    $risposte[$chiave] = $pastiSelezionati->contains($chiave);
                }
                $rispostaCheckin->risposte = $risposte;
                $rispostaCheckin->save();

                $this->carica();

                Notification::make()->title('Dati check-in aggiornati')->success()->send();
            });
    }

    public function getTitle(): string
    {
        return 'Tutti i check-in — ' . $this->record->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventoResource::getUrl('index')                                        => 'Eventi',
            EventoResource::getUrl('gestione', ['record' => $this->record])        => $this->record->nome,
            EventoResource::getUrl('presenze-live', ['record' => $this->record])   => 'Presenze live',
            ''                                                                     => 'Tutti i check-in',
        ];
    }

}
