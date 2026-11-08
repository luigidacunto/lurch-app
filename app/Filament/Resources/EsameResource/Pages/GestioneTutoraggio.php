<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\HaSubNavigationEsame;
use App\Models\EsameTutoraggio;
use App\Models\Socio;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Gruppi di tutoraggio: discenti + docenti tutor per l'intero percorso. Un discente sta in un solo gruppo per esame,
 * un docente può essere tutor in più gruppi. Configurazione pura: il gruppo decide solo cosa il tutor legge nella PWA
 * («I miei tutorati»), nessuna scheda dipende da qui. Stesso schema e stesse regole di accesso di GestioneLaboratori.
 */
class GestioneTutoraggio extends Page
{
    use HaSubNavigationEsame, InteractsWithRecord {
        HaSubNavigationEsame::getSubNavigation insteadof InteractsWithRecord;
    }

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.gestione-tutoraggio';

    protected static ?string $title = 'Tutoraggio';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'gestione', 'Tutoraggio');
    }

    /** A esame chiuso (o per chi non gestisce) i gruppi non si creano, modificano o eliminano. */
    public function puoConfigurare(): bool
    {
        return $this->record->puoModificareConfigurazione(auth()->user());
    }

    public function tutoraggi()
    {
        return $this->record->tutoraggi()->with(['docenti', 'discenti'])->orderBy('nome')->get();
    }

    /** Id dei discenti non ritirati: nei gruppi i ritirati restano ma si vedono come tali. */
    public function idDiscentiAttivi(): array
    {
        return $this->record->discenti()->pluck('iscrizioni.partecipante_id')->all();
    }

    /** Discenti non ancora in nessun gruppo di tutoraggio (nomi). */
    public function discentiSenzaTutor(): array
    {
        $assegnati = DB::table('esame_tutoraggio_discente as td')
            ->join('esame_tutoraggi as t', 't.id', '=', 'td.tutoraggio_id')
            ->where('t.esame_id', $this->record->id)->pluck('td.socio_id');

        return Socio::whereIn('id', $this->idDiscentiAttivi())->whereNotIn('id', $assegnati)
            ->orderBy('cognome')->orderBy('nome')->get()
            ->map(fn ($s) => $s->cognome . ' ' . $s->nome)->all();
    }

    /**
     * Tutor assegnabili: docenti del corso + direttore, più i tutor già nel gruppo che non lo sono più (il multi-select
     * valida i valori contro le opzioni; senza di loro il gruppo non si salverebbe). Un tutor non più docente non vede nulla.
     */
    protected function docentiOpzioni(?EsameTutoraggio $gruppo): array
    {
        $corso = $this->record->corso;
        $soci  = $corso->docenti->when($corso->direttore, fn ($c) => $c->push($corso->direttore))->unique('id');

        $opzioni = $soci->sortBy(fn ($s) => $s->cognome . ' ' . $s->nome)
            ->mapWithKeys(fn ($s) => [$s->id => $s->cognome . ' ' . $s->nome . ($s->id === $corso->direttore_socio_id ? ' (direttore)' : '')])
            ->all();
        foreach ($gruppo?->docenti ?? [] as $s) {
            $opzioni[$s->id] ??= $s->cognome . ' ' . $s->nome . ' (non più docente del corso)';
        }

        return $opzioni;
    }

    private function gruppo(int $id): EsameTutoraggio
    {
        return $this->record->tutoraggi()->findOrFail($id);
    }

    private function gruppoInModifica($get): ?EsameTutoraggio
    {
        $id = (int) $get('gruppo_id');

        return $id ? $this->gruppo($id) : null;
    }

    private function campi(): array
    {
        return [
            Forms\Components\Hidden::make('gruppo_id'),
            Forms\Components\TextInput::make('nome')->required()->maxLength(255),
            // Elenca solo chi non è già in un altro gruppo di tutoraggio; la regola resta come controllo lato server.
            Forms\Components\Select::make('discenti_ids')
                ->label('Discenti')
                ->multiple()
                ->options(fn ($get) => EsameTutoraggio::opzioniDiscenti($this->record, $this->gruppoInModifica($get)))
                ->rules([fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                    $doppi = EsameTutoraggio::discentiGiaAssegnati($this->record->id, $this->gruppoInModifica($get)?->id, (array) $value);
                    if ($doppi) {
                        $nomi = Socio::whereIn('id', $doppi)->get()->map(fn ($s) => $s->cognome . ' ' . $s->nome)->implode(', ');
                        $fail("Già in un altro gruppo di tutoraggio: {$nomi}.");
                    }
                }]),
            Forms\Components\Select::make('docenti_ids')
                ->label('Tutor')
                ->helperText('Un docente può essere tutor in più gruppi. Nella app vede, in sola lettura, l\'andamento dei discenti del gruppo con le schede di tutti i docenti.')
                ->multiple()
                ->options(fn ($get) => $this->docentiOpzioni($this->gruppoInModifica($get))),
        ];
    }

    private function sincronizza(EsameTutoraggio $gruppo, array $data): void
    {
        $gruppo->discenti()->sync($data['discenti_ids'] ?? []);
        $gruppo->docenti()->sync($data['docenti_ids'] ?? []);
    }

    public function nuovoTutoraggioAction(): Action
    {
        return Action::make('nuovoTutoraggio')
            ->label('Nuovo gruppo di tutoraggio')
            ->color('success')
            ->authorize(fn () => $this->puoConfigurare())
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(fn () => ['gruppo_id' => null, 'nome' => '', 'discenti_ids' => [], 'docenti_ids' => []])
            ->schema($this->campi())
            ->action(function (array $data) {
                DB::transaction(function () use ($data) {
                    $this->sincronizza($this->record->tutoraggi()->create(['nome' => $data['nome']]), $data);
                });
            });
    }

    public function modificaTutoraggioAction(): Action
    {
        return Action::make('modificaTutoraggio')
            ->label('Modifica gruppo di tutoraggio')
            ->color('success')
            ->authorize(fn () => $this->puoConfigurare())
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(function (array $arguments) {
                $g = $this->gruppo($arguments['gruppo_id']);

                return [
                    'gruppo_id'    => $g->id,
                    'nome'         => $g->nome,
                    'discenti_ids' => $g->discenti()->pluck('soci.id')->all(),
                    'docenti_ids'  => $g->docenti()->pluck('soci.id')->all(),
                ];
            })
            ->schema($this->campi())
            ->action(function (array $data, array $arguments) {
                $g = $this->gruppo($arguments['gruppo_id']);
                DB::transaction(function () use ($g, $data) {
                    $g->update(['nome' => $data['nome']]);
                    $this->sincronizza($g, $data);
                });
            });
    }

    public function eliminaTutoraggioAction(): Action
    {
        return Action::make('eliminaTutoraggio')
            ->label('Elimina gruppo di tutoraggio')
            ->color('danger')
            ->authorize(fn () => $this->puoConfigurare())
            ->requiresConfirmation()
            ->modalHeading('Elimina gruppo di tutoraggio')
            ->modalDescription('Il gruppo e le sue assegnazioni vengono eliminati. Le schede e le valutazioni non dipendono dal tutoraggio.')
            ->modalSubmitActionLabel('Elimina')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(fn (array $arguments) => $this->gruppo($arguments['gruppo_id'])->delete());
    }
}
