<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\ChiusuraEsame;
use App\Esami\RisultatiEsame;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Esito dell'esame: risultato finale per discente (4 prove, punteggio in centesimi, idoneità), chiusura e
 * riapertura. Lettura per chi legge l'Itinere; «Chiudi» solo il direttore, «Riapri» solo il superadmin, entro 15 giorni.
 * Esame chiuso: i numeri vengono SOLO dallo snapshot (`esiti`), mai ricalcolati. Ogni azione ricontrolla nel servizio
 * (ChiusuraEsame): i pulsanti spenti in Blade non sono la difesa.
 */
class EsitoEsame extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.esito';

    protected static ?string $title = 'Esito';

    public static function canAccess(array $parameters = []): bool
    {
        // Filament sonda canAccess() senza argomenti all'avvio: no-op, il controllo vero ha il record.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }
        $record = $parameters['record'];

        return $record instanceof Esame && $record->puoVedereRiepilogoItinere(auth()->user());
    }

    public function getSubNavigation(): array
    {
        return EsameResource::getRecordSubNavigation($this);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::canAccess(['record' => $this->record]), 403);
    }

    public function getTitle(): string
    {
        return 'Esito — ' . $this->record->titolo;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'valutazioni', 'Esito');
    }

    /** Tutto ciò che serve alla vista, calcolato una volta per render. */
    public function dati(): array
    {
        $esame = $this->record;
        $utente = auth()->user();
        $righe = RisultatiEsame::per($esame);

        return [
            'righe' => $righe,
            'chiuso' => $esame->isChiuso(),
            'idonei' => collect($righe)->where('idoneo', true)->count(),
            'nonIdonei' => collect($righe)->where('idoneo', false)->count(),
            'incompleti' => collect($righe)->where('stato', 'incompleto')->count(),
            'nonAmmessi' => collect($righe)->where('stato', 'non_ammesso')->count(),
            'vedeChiudi' => $esame->stato === Esame::STATO_IN_CORSO && EsameResource::canEdit($esame),
            'motivoChiudi' => ChiusuraEsame::motivoNonChiudibile($esame, $utente, $righe),
            'vedeRiapri' => $esame->isChiuso() && (bool) $utente?->isSuperadmin(),
            'motivoRiapri' => ChiusuraEsame::motivoNonRiapribile($esame, $utente),
            'pesi' => $esame->modello_snapshot['pesi'] ?? [],
            'aree' => collect($esame->modello_snapshot['itinere']['aree'] ?? [])->pluck('nome', 'codice')->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $utente = auth()->user();
        $esame = $this->record;

        return [ActionGroup::make([
            Action::make('csv')
                ->label('Esporta CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(fn () => route('esami.esiti-csv', $esame))
                ->openUrlInNewTab(),
            $this->azioneChiudi()
                ->visible(fn () => $esame->stato === Esame::STATO_IN_CORSO && EsameResource::canEdit($esame))
                ->disabled(fn () => ChiusuraEsame::motivoNonChiudibile($this->record, $utente) !== null)
                ->tooltip(fn () => ChiusuraEsame::motivoNonChiudibile($this->record, $utente)),
            $this->azioneRiapri()
                ->visible(fn () => $esame->isChiuso() && (bool) $utente?->isSuperadmin())
                ->disabled(fn () => ChiusuraEsame::motivoNonRiapribile($this->record, $utente) !== null)
                ->tooltip(fn () => ChiusuraEsame::motivoNonRiapribile($this->record, $utente)),
        ])->label('Azioni')->icon('heroicon-o-ellipsis-vertical')->color('info')->button()];
    }

    private function azioneChiudi(): Action
    {
        return Action::make('chiudi')
            ->label('Chiudi esame')
            ->icon('heroicon-o-lock-closed')
            ->color('brand_pink')
            ->requiresConfirmation()
            ->modalHeading('Chiudi l\'esame')
            ->modalDescription(function () {
                $righe = RisultatiEsame::per($this->record);
                $n = fn (callable $f) => collect($righe)->filter($f)->count();

                return 'Gli esiti vengono salvati in modo definitivo: '.$n(fn ($r) => $r['idoneo'] === true).' idonei, '
                    .$n(fn ($r) => $r['idoneo'] === false).' non idonei, '.$n(fn ($r) => $r['stato'] === 'non_ammesso').' non ammessi (i ritirati non compaiono). '
                    .'Dopo la chiusura nessun dato dell\'esame si può più modificare. Solo un superadmin può riaprirlo, con un motivo e entro '
                    .Esame::RIAPERTURA_GIORNI.' giorni dalla chiusura; passato quel termine l\'esame resta chiuso per sempre.';
            })
            ->modalSubmitActionLabel('Chiudi definitivamente')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (Action $action) {
                try {
                    ChiusuraEsame::chiudi($this->record, auth()->user());
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }
                $this->record->refresh();
                Notification::make()->title('Esame chiuso.')->success()->send();
            });
    }

    private function azioneRiapri(): Action
    {
        return Action::make('riapri')
            ->label('Riapri esame')
            ->icon('heroicon-o-lock-open')
            ->color('brand_pink')
            ->modalHeading('Riapri l\'esame')
            ->modalDescription('L\'esame torna in corso e gli esiti salvati vengono cancellati: alla nuova chiusura se ne salveranno di nuovi. Il motivo resta registrato (solo l\'ultima riapertura). Una nuova chiusura riavvia i '.Esame::RIAPERTURA_GIORNI.' giorni.')
            ->modalSubmitActionLabel('Riapri')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->schema([
                Forms\Components\Textarea::make('motivo')->label('Motivo della riapertura')->required()->minLength(ChiusuraEsame::MOTIVO_MIN_CARATTERI)->maxLength(1000),
            ])
            ->action(function (array $data, Action $action) {
                try {
                    ChiusuraEsame::riapri($this->record, auth()->user(), $data['motivo']);
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }
                $this->record->refresh();
                Notification::make()->title('Esame riaperto.')->success()->send();
            });
    }
}
