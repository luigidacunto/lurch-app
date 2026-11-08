<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\HaSubNavigationEsame;
use App\Models\Esame;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Pagina Gestione dell'esame (come Gestione corso/evento): riepilogo + azioni di stato e commissione.
 * Le sezioni di configurazione sono nella sub-nav; i parametri base nella pagina Modifica.
 */
class GestioneEsame extends Page
{
    use HaSubNavigationEsame, InteractsWithRecord {
        HaSubNavigationEsame::getSubNavigation insteadof InteractsWithRecord;
    }

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.gestione-esame';

    protected static ?string $title = 'Gestione esame';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Gestione esame — ' . $this->record->titolo;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'gestione');
    }

    /** Dati del riepilogo: laboratori con numero di gruppi e di docenti (distinti) assegnati ai loro gruppi. */
    public function laboratori(): array
    {
        $labs = $this->record->laboratori()->withCount('gruppi')->orderBy('numero')->get();
        $docenti = DB::table('esame_gruppo_docente')
            ->join('esame_gruppi', 'esame_gruppi.id', '=', 'esame_gruppo_docente.gruppo_id')
            ->whereIn('esame_gruppi.laboratorio_id', $labs->pluck('id'))
            ->groupBy('esame_gruppi.laboratorio_id')
            ->selectRaw('esame_gruppi.laboratorio_id as lab, count(distinct esame_gruppo_docente.socio_id) as n')
            ->pluck('n', 'lab');

        return $labs->map(fn ($l) => [
            'numero'  => $l->numero,
            'gruppi'  => $l->gruppi_count,
            'docenti' => (int) ($docenti[$l->id] ?? 0),
        ])->all();
    }

    /** Docenti del corso: id => "Cognome Nome". */
    protected function docentiDelCorso(): array
    {
        return $this->record->corso->docenti()->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->cognome . ' ' . $s->nome])->all();
    }

    protected function getHeaderActions(): array
    {
        return [ActionGroup::make([
            Action::make('commissione')
                ->label('Commissione')
                ->icon('heroicon-o-user-group')
                ->color('info')
                ->visible(fn () => $this->record->puoImpostareCommissione(auth()->user()))
                ->modalHeading('Commissione')
                ->modalDescription(function () {
                    $corso = $this->record->corso;

                    return 'Direttore: ' . ($corso->direttore?->nome_completo ?? $corso->direttore_corso ?? '—')
                        . ' · Presidente o delegato: ' . ($corso->presidente?->nome_completo ?? 'non indicato (si indica modificando il corso)');
                })
                ->modalSubmitActionLabel('Salva')
                ->fillForm(fn () => ['docenti' => $this->record->docentiCommissione()->pluck('socio_id')->all()])
                ->schema([
                    Forms\Components\Select::make('docenti')
                        ->label('Docenti in commissione')
                        ->helperText('Fino a 3, scelti tra i docenti del corso (si aggiungono modificando il corso).')
                        ->multiple()
                        ->maxItems(3)
                        ->options(fn () => $this->docentiDelCorso()),
                ])
                ->action(function (array $data) {
                    abort_unless($this->record->fresh()->puoImpostareCommissione(auth()->user()), 403);
                    $scelti = collect($data['docenti'] ?? [])->map(fn ($id) => (int) $id);
                    DB::transaction(function () use ($scelti) {
                        $this->record->docentiCommissione()->whereNotIn('socio_id', $scelti)->delete();
                        $presenti = $this->record->docentiCommissione()->pluck('socio_id');
                        foreach ($scelti->diff($presenti) as $socioId) {
                            $this->record->docentiCommissione()->create(['socio_id' => $socioId]);
                        }
                    });
                    Notification::make()->title('Commissione aggiornata.')->success()->send();
                }),

            Action::make('attiva')
                ->label('Attiva esame')
                ->icon('heroicon-o-play')
                ->color('brand_pink')
                ->visible(fn () => $this->record->isBozza())
                ->disabled(fn () => $this->record->motivoNonAttivabile() !== null)
                ->tooltip(fn () => $this->record->motivoNonAttivabile())
                ->requiresConfirmation()
                ->modalHeading('Attiva esame')
                ->modalDescription('Da questo momento il modello d\'esame resta quello attuale, anche se in futuro ne esisteranno versioni nuove. Il resto (LAP, commissione, laboratori, gruppi) resta modificabile.')
                ->modalSubmitActionLabel('Attiva')
                ->action(function () {
                    $this->record->attiva();
                    $this->record->refresh();
                    Notification::make()->title('Esame attivato.')->success()->send();
                }),

            // Apre/richiude la compilazione delle schede PERSONALI di colloquio e prova pratica (le finali non dipendono da qui).
            Action::make('provaFinale')
                ->label(fn () => $this->record->provaFinaleAperta() ? 'Richiudi la prova finale' : 'Apri la prova finale')
                ->icon(fn () => $this->record->provaFinaleAperta() ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                ->color('brand_pink')
                ->visible(fn () => $this->record->puoGestireProvaFinale(auth()->user()))
                ->requiresConfirmation()
                ->modalHeading(fn () => $this->record->provaFinaleAperta() ? 'Richiudi la prova finale' : 'Apri la prova finale')
                ->modalDescription(fn () => $this->record->provaFinaleAperta()
                    ? 'Le schede personali di colloquio e prova pratica restano visibili a commissione, admin e agli stessi commissari (anche dalla PWA) ma in sola lettura: nessuno può più compilarle o modificarle finché non riapri. Le schede finali del direttore non cambiano.'
                    : 'Direttore e docenti di commissione potranno compilare dalla PWA le loro schede personali di colloquio e prova pratica. Potrai richiuderla: le schede già compilate restano visibili in sola lettura, ma non si potranno più modificare finché non riapri.')
                ->modalSubmitActionLabel(fn () => $this->record->provaFinaleAperta() ? 'Richiudi' : 'Apri')
                ->action(function () {
                    abort_unless($this->record->puoGestireProvaFinale(auth()->user()), 403);
                    $eraAperta = $this->record->provaFinaleAperta();
                    $eraAperta ? $this->record->chiudiProvaFinale() : $this->record->apriProvaFinale();
                    $this->record->refresh();
                    Notification::make()->title($eraAperta ? 'Prova finale richiusa.' : 'Prova finale aperta.')->success()->send();
                }),

            Action::make('modifica')
                ->label('Modifica')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn () => $this->record->puoModificareConfigurazione(auth()->user()))
                ->url(fn () => EsameResource::getUrl('edit', ['record' => $this->record])),
        ])->label('Azioni')->icon('heroicon-o-ellipsis-vertical')->color('info')->button()];
    }
}
