<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\HaSubNavigationEsame;
use App\Esami\AmmissioneDiscente;
use App\Models\Iscrizione;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

/**
 * Gli iscritti non ritirati del corso, letti in diretta. L'unica scrittura è «Segna / Togli non
 * ammesso»: solo il direttore, solo a esame in corso, solo le colonne `non_ammesso` e `non_ammesso_motivo` di iscrizioni.
 * Il servizio (AmmissioneDiscente) ricontrolla tutto: il pulsante spento non è la difesa.
 */
class DiscentiEsame extends ManageRelatedRecords
{
    use HaSubNavigationEsame;

    protected static string $resource = EsameResource::class;

    protected static string $relationship = 'discenti';

    protected static ?string $title = 'Discenti';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partecipante.cognome')->label('Cognome'),
                Tables\Columns\TextColumn::make('partecipante.nome')->label('Nome'),
                Tables\Columns\TextColumn::make('partecipante.comitatoLocale.nome')->label('Comitato'),
                Tables\Columns\TextColumn::make('eta')->label('Età')
                    ->state(fn (Iscrizione $record) => $this->eta($record))
                    ->tooltip(fn () => 'Età compiuta alla data di fine del corso'.($this->getRecord()->corso?->data_fine ? ' ('.$this->getRecord()->corso->data_fine->format('d/m/Y').')' : '').'.'),
                Tables\Columns\TextColumn::make('stato_ammissione')->label('Stato')
                    ->state(fn (Iscrizione $record) => $record->non_ammesso ? 'Non ammesso' : 'Ammesso')
                    ->badge()->color(fn (Iscrizione $record) => $record->non_ammesso ? 'danger' : 'success')
                    ->tooltip(fn (Iscrizione $record) => $record->non_ammesso ? $record->non_ammesso_motivo : null),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('segnaNonAmmesso')
                        ->label('Segna non ammesso')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Iscrizione $record) => ! $record->non_ammesso && $this->getRecord()->isDirettore(auth()->user()))
                        ->disabled(fn () => AmmissioneDiscente::motivoNonDisponibile($this->getRecord(), auth()->user()) !== null)
                        ->tooltip(fn () => AmmissioneDiscente::motivoNonDisponibile($this->getRecord(), auth()->user()))
                        ->modalHeading(fn (Iscrizione $record) => 'Segna non ammesso — '.$record->partecipante?->nome_completo)
                        ->modalDescription(function (Iscrizione $record) {
                            $impatto = AmmissioneDiscente::impatto($this->getRecord(), $record->partecipante_id);

                            return 'Il discente non farà la prova finale: niente scheda finale e niente punteggio. Non si cancella nulla'
                                .($impatto ? ' ma verrà nascosto: '.implode('; ', $impatto) : '').". Puoi toglierlo finché l'esame è in corso.";
                        })
                        ->modalSubmitActionLabel('Segna non ammesso')
                        ->closeModalByClickingAway(false)
                        ->closeModalByEscaping(false)
                        ->schema([
                            Forms\Components\Textarea::make('motivo')->label('Motivo')->required()->maxLength(1000),
                        ])
                        ->action(function (Iscrizione $record, array $data, Action $action) {
                            $this->esegui($action, fn () => AmmissioneDiscente::segnaNonAmmesso($this->getRecord(), auth()->user(), $record->partecipante_id, $data['motivo']), 'Discente segnato come non ammesso.');
                        }),
                    Action::make('togliNonAmmesso')
                        ->label('Togli non ammesso')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Iscrizione $record) => (bool) $record->non_ammesso && $this->getRecord()->isDirettore(auth()->user()))
                        ->disabled(fn () => AmmissioneDiscente::motivoNonDisponibile($this->getRecord(), auth()->user()) !== null)
                        ->tooltip(fn () => AmmissioneDiscente::motivoNonDisponibile($this->getRecord(), auth()->user()))
                        ->requiresConfirmation()
                        ->modalHeading('Togli non ammesso')
                        ->modalDescription('Il discente torna ammesso alla prova finale: schede e test già scritti riappaiono. Il motivo registrato viene cancellato.')
                        ->closeModalByClickingAway(false)
                        ->closeModalByEscaping(false)
                        ->action(function (Iscrizione $record, Action $action) {
                            $this->esegui($action, fn () => AmmissioneDiscente::togliNonAmmesso($this->getRecord(), auth()->user(), $record->partecipante_id), 'Discente di nuovo ammesso.');
                        }),
                ])->label('Altre azioni')->icon('heroicon-o-ellipsis-vertical')->color('danger')->iconButton(),
            ], position: RecordActionsPosition::BeforeColumns)
            ->description('Letti in diretta dagli iscritti del corso (esclusi i ritirati). Il direttore può segnare chi non è ammesso alla prova finale.')
            ->emptyStateHeading('Nessun discente iscritto al corso');
    }

    /** Età compiuta alla data di fine del corso; «—» se manca la data di nascita o di fine corso. */
    private function eta(Iscrizione $record): string
    {
        $nascita = $record->partecipante?->data_nascita;
        $fine = $this->getRecord()->corso?->data_fine;

        if (! $nascita || ! $fine) {
            return '—';
        }
        // Anni compiuti (intero): niente diffInYears, in Carbon 3 restituisce un decimale.
        $anni = $fine->year - $nascita->year - (($fine->month * 100 + $fine->day) < ($nascita->month * 100 + $nascita->day) ? 1 : 0);

        return (string) max($anni, 0);
    }

    private function esegui(Action $action, \Closure $operazione, string $esito): void
    {
        try {
            $operazione();
        } catch (\RuntimeException|\Illuminate\Validation\ValidationException $e) {
            Notification::make()->title($e instanceof \Illuminate\Validation\ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())->danger()->send();
            $action->halt();

            return;
        }
        Notification::make()->title($esito)->success()->send();
    }

    public function getBreadcrumbs(): array
    {
        return static::getResource()::breadcrumbs($this->getRecord(), 'gestione', 'Discenti');
    }
}
