<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\HaSubNavigationEsame;
use App\Models\EsameGruppo;
use App\Models\EsameLaboratorio;
use App\Models\Socio;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Laboratori e gruppi, in un'unica pagina: un riquadro per laboratorio con i suoi gruppi dentro (prima due pagine,
 * Laboratori e Gruppi). Laboratori: minimo 1 (nasce con l'esame), nessun massimo; si aggiungono e si tolgono anche a
 * esame attivo, tranne quelli con schede registrate. Gruppi: ogni discente sta in un solo gruppo per laboratorio
 * (regola applicativa); i docenti (docenti del corso + direttore) si assegnano anche all'ultimo momento: un gruppo senza
 * docenti si salva ma è segnalato. Le schede puntano a laboratorio + discente + docente, mai al gruppo.
 * Azioni vere di Filament con argomenti (stesso schema di GestioneStaff); a esame chiuso o per chi non gestisce
 * tutto è spento E ricontrollato (authorize).
 */
class GestioneLaboratori extends Page
{
    use HaSubNavigationEsame, InteractsWithRecord {
        HaSubNavigationEsame::getSubNavigation insteadof InteractsWithRecord;
    }

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.gestione-laboratori';

    protected static ?string $title = 'Laboratori';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'gestione', 'Laboratori');
    }

    /** Motivo per cui il laboratorio non si può eliminare, null se si può. */
    public static function motivoNonEliminabile(EsameLaboratorio $lab): ?string
    {
        if ($lab->schedeSemaforo()->exists()) {
            return 'Ha già schede registrate: non si può eliminare.';
        }
        if ($lab->esame->laboratori()->count() <= 1) {
            return 'Serve almeno un laboratorio.';
        }

        return null;
    }

    /** A esame chiuso (o per chi non gestisce) laboratori e gruppi non si creano, modificano o eliminano. */
    public function puoConfigurare(): bool
    {
        return $this->record->puoModificareConfigurazione(auth()->user());
    }

    /** Laboratori con i loro gruppi (docenti e conteggio discenti già caricati). */
    public function laboratori()
    {
        return $this->record->laboratori()
            ->with(['gruppi' => fn ($q) => $q->with(['docenti', 'discenti'])->orderBy('nome')])
            ->orderBy('numero')->get();
    }

    /** Id dei discenti non ritirati: nei gruppi i ritirati restano ma si vedono come tali. */
    public function idDiscentiAttivi(): array
    {
        return $this->record->discenti()->pluck('iscrizioni.partecipante_id')->all();
    }

    public function gruppiSenzaDocenti(): int
    {
        return $this->record->gruppi()->doesntHave('docenti')->count();
    }

    /** Docenti assegnabili: docenti del corso + direttore. */
    protected function docentiOpzioni(): array
    {
        $corso = $this->record->corso;
        $soci  = $corso->docenti->when($corso->direttore, fn ($c) => $c->push($corso->direttore))->unique('id');

        return $soci->sortBy(fn ($s) => $s->cognome . ' ' . $s->nome)
            ->mapWithKeys(fn ($s) => [$s->id => $s->cognome . ' ' . $s->nome . ($s->id === $corso->direttore_socio_id ? ' (direttore)' : '')])
            ->all();
    }

    private function laboratorio(int $id): EsameLaboratorio
    {
        return $this->record->laboratori()->findOrFail($id);
    }

    private function gruppo(int $id): EsameGruppo
    {
        return EsameGruppo::where('esame_id', $this->record->id)->findOrFail($id);
    }

    // ---- Laboratori ----

    public function creaLaboratorioAction(): Action
    {
        return Action::make('creaLaboratorio')
            ->label('Aggiungi laboratorio')
            ->color('success')
            ->authorize(fn () => $this->puoConfigurare())
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->schema([$this->campoData()])
            ->action(function (array $data) {
                $this->record->laboratori()->create([
                    'numero' => ((int) $this->record->laboratori()->max('numero')) + 1,
                    'data'   => $data['data'] ?? null,
                ]);
            });
    }

    public function modificaLaboratorioAction(): Action
    {
        return Action::make('modificaLaboratorio')
            ->label('Modifica data')
            ->color('success')
            ->authorize(fn () => $this->puoConfigurare())
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(fn (array $arguments) => ['data' => $this->laboratorio($arguments['laboratorio_id'])->data])
            ->schema([$this->campoData()])
            ->action(fn (array $data, array $arguments) => $this->laboratorio($arguments['laboratorio_id'])->update(['data' => $data['data'] ?? null]));
    }

    public function eliminaLaboratorioAction(): Action
    {
        return Action::make('eliminaLaboratorio')
            ->label('Elimina laboratorio')
            ->color('danger')
            ->authorize(fn () => $this->puoConfigurare())
            ->requiresConfirmation()
            ->modalHeading('Elimina laboratorio')
            ->modalDescription('Verranno eliminati anche i gruppi di questo laboratorio.')
            ->modalSubmitActionLabel('Elimina')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (array $arguments, Action $action) {
                $lab = $this->laboratorio($arguments['laboratorio_id']);
                if ($motivo = static::motivoNonEliminabile($lab)) {
                    Notification::make()->title($motivo)->danger()->send();
                    $action->halt();

                    return;
                }
                $lab->delete();
            });
    }

    private function campoData(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('data')->label('Data')->native(false)->displayFormat('d/m/Y');
    }

    // ---- Gruppi ----

    /** Campi del gruppo; laboratorio e gruppo in modifica sono campi nascosti (la pagina li sa già: è il riquadro in cui si è). */
    private function campiGruppo(): array
    {
        return [
            Forms\Components\Hidden::make('laboratorio_id'),
            Forms\Components\Hidden::make('gruppo_id'),
            Forms\Components\TextInput::make('nome')->required()->maxLength(255),
            // Elenca solo chi non è già in un altro gruppo dello stesso laboratorio; la regola sotto resta
            // come controllo lato server (due utenti che salvano insieme).
            Forms\Components\Select::make('discenti_ids')
                ->label('Discenti')
                ->multiple()
                ->options(fn ($get) => EsameGruppo::opzioniDiscenti($this->record, (int) $get('laboratorio_id'), $this->gruppoInModifica($get)))
                ->rules([fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                    $doppi = EsameGruppo::discentiGiaAssegnati((int) $get('laboratorio_id'), $this->gruppoInModifica($get)?->id, (array) $value);
                    if ($doppi) {
                        $nomi = Socio::whereIn('id', $doppi)->get()->map(fn ($s) => $s->cognome . ' ' . $s->nome)->implode(', ');
                        $fail("Già in un altro gruppo di questo laboratorio: {$nomi}.");
                    }
                }]),
            Forms\Components\Select::make('docenti_ids')
                ->label('Docenti del gruppo')
                ->helperText('Possono essere assegnati anche poco prima del laboratorio, ma senza docenti i discenti non avranno schede da compilare.')
                ->multiple()
                ->options(fn () => $this->docentiOpzioni()),
        ];
    }

    private function gruppoInModifica($get): ?EsameGruppo
    {
        $id = (int) $get('gruppo_id');

        return $id ? $this->gruppo($id) : null;
    }

    protected function sincronizza(EsameGruppo $gruppo, array $data): void
    {
        $gruppo->discenti()->sync($data['discenti_ids'] ?? []);
        $gruppo->docenti()->sync($data['docenti_ids'] ?? []);
    }

    public function nuovoGruppoAction(): Action
    {
        return Action::make('nuovoGruppo')
            ->label('Nuovo gruppo')
            ->color('success')
            ->authorize(fn () => $this->puoConfigurare())
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(fn (array $arguments) => [
                'laboratorio_id' => $this->laboratorio($arguments['laboratorio_id'])->id,
                'gruppo_id'      => null, 'nome' => '', 'discenti_ids' => [], 'docenti_ids' => [],
            ])
            ->schema($this->campiGruppo())
            ->action(function (array $data) {
                DB::transaction(function () use ($data) {
                    $lab = $this->laboratorio((int) $data['laboratorio_id']);
                    $gruppo = $this->record->gruppi()->create(['laboratorio_id' => $lab->id, 'nome' => $data['nome']]);
                    $this->sincronizza($gruppo, $data);
                });
            });
    }

    public function modificaGruppoAction(): Action
    {
        return Action::make('modificaGruppo')
            ->label('Modifica gruppo')
            ->color('success')
            ->authorize(fn () => $this->puoConfigurare())
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(function (array $arguments) {
                $g = $this->gruppo($arguments['gruppo_id']);

                return [
                    'laboratorio_id' => $g->laboratorio_id,
                    'gruppo_id'      => $g->id,
                    'nome'           => $g->nome,
                    'discenti_ids'   => $g->discenti()->pluck('soci.id')->all(),
                    'docenti_ids'    => $g->docenti()->pluck('soci.id')->all(),
                ];
            })
            ->schema($this->campiGruppo())
            ->action(function (array $data, array $arguments) {
                $g = $this->gruppo($arguments['gruppo_id']);
                DB::transaction(function () use ($g, $data) {
                    $g->update(['nome' => $data['nome']]);
                    $this->sincronizza($g, $data);
                });
            });
    }

    public function eliminaGruppoAction(): Action
    {
        return Action::make('eliminaGruppo')
            ->label('Elimina gruppo')
            ->color('danger')
            ->authorize(fn () => $this->puoConfigurare())
            ->requiresConfirmation()
            ->modalHeading('Elimina gruppo')
            ->modalDescription('Il gruppo e le sue assegnazioni vengono eliminati. Le schede già compilate non dipendono dai gruppi.')
            ->modalSubmitActionLabel('Elimina')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(fn (array $arguments) => $this->gruppo($arguments['gruppo_id'])->delete());
    }

    /**
     * Copia nome e discenti di tutti i gruppi (o di uno solo) di un altro laboratorio in questo; i docenti si assegnano
     * dopo. Un discente sta in un solo gruppo per laboratorio: se la copia ne metterebbe uno in due gruppi della
     * destinazione, non si copia nulla e si dice chi.
     */
    public function copiaGruppiAction(): Action
    {
        return Action::make('copiaGruppi')
            ->label('Copia i gruppi da un altro laboratorio')
            ->color('success')
            ->authorize(fn () => $this->puoConfigurare())
            ->modalHeading('Copia i gruppi')
            ->modalDescription('Copia nome e discenti, non i docenti (si assegnano dopo). Scegli il laboratorio di partenza e tutti i gruppi o uno solo.')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(fn (array $arguments) => ['a' => $arguments['laboratorio_id'] ?? null, 'da' => null, 'gruppo' => 'tutti'])
            ->schema([
                Forms\Components\Hidden::make('a'),
                Forms\Components\Select::make('da')->label('Copia dal')->required()->live()
                    ->afterStateUpdated(fn ($set) => $set('gruppo', 'tutti'))
                    ->options(fn ($get) => $this->record->laboratori()->orderBy('numero')->get()
                        ->filter(fn ($l) => $l->id !== (int) $get('a') && $l->gruppi()->exists())
                        ->mapWithKeys(fn ($l) => [$l->id => 'Laboratorio ' . $l->numero])->all()),
                Forms\Components\Select::make('gruppo')->label('Gruppi da copiare')->required()
                    ->visible(fn ($get) => (bool) $get('da'))
                    ->options(fn ($get) => ['tutti' => 'Tutti i gruppi'] + $this->record->gruppi()
                        ->where('laboratorio_id', (int) $get('da'))->orderBy('nome')->pluck('nome', 'id')->all()),
            ])
            ->action(function (array $data, Action $action) {
                $da = $this->laboratorio((int) $data['da']);
                $a  = $this->laboratorio((int) $data['a']);
                $gruppi = $da->gruppi()->with('discenti')
                    ->when($data['gruppo'] !== 'tutti', fn ($q) => $q->whereKey((int) $data['gruppo']))->get();
                // I ritirati restano nei gruppi di origine ma non vengono copiati.
                $attivi = $this->record->discenti()->pluck('iscrizioni.partecipante_id');
                $copiati = $gruppi->mapWithKeys(fn ($g) => [$g->id => $g->discenti->pluck('id')->intersect($attivi)]);

                $tutti = $copiati->flatten();
                $doppi = $tutti->duplicates()->merge(EsameGruppo::discentiGiaAssegnati($a->id, null, $tutti->all()))->unique();
                if ($doppi->isNotEmpty()) {
                    $nomi = Socio::whereIn('id', $doppi)->get()->map(fn ($s) => $s->cognome . ' ' . $s->nome)->implode(', ');
                    Notification::make()->title("Non copiato: già in un gruppo del Laboratorio {$a->numero}: {$nomi}.")->danger()->send();
                    $action->halt();

                    return;
                }
                DB::transaction(function () use ($gruppi, $copiati, $a) {
                    foreach ($gruppi as $g) {
                        $nuovo = $a->gruppi()->create(['esame_id' => $a->esame_id, 'nome' => $g->nome]);
                        $nuovo->discenti()->sync($copiati[$g->id]);
                    }
                });
                Notification::make()->title('Gruppi copiati: assegna i docenti.')->success()->send();
            });
    }
}
