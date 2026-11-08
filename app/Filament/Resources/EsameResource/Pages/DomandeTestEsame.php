<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\ImportaTest;
use App\Esami\PreparaTest;
use App\Esami\TestScritto;
use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\EseguiAzioneTest;
use App\Models\Esame;
use App\Models\TestDomanda;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Domande e punteggio del test scritto (parte di CONFIGURAZIONE, spostata da TestScrittoEsame): lettura per chi legge
 * il test, preparazione solo con il test in bozza per direttore, admin dell'ambito e superadmin (Esame::puoPreparareTest).
 * Ogni azione ricontrolla i permessi nel servizio (PreparaTest): i pulsanti spenti in Blade non sono la difesa.
 * L'avvio per discente e i risultati restano in TestScrittoEsame.
 */
class DomandeTestEsame extends Page
{
    use EseguiAzioneTest;
    use InteractsWithRecord;

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.domande-test';

    protected static ?string $title = 'Domande del test';

    public static function canAccess(array $parameters = []): bool
    {
        // Filament sonda canAccess() senza argomenti all'avvio: no-op, il controllo vero ha il record.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }
        $record = $parameters['record'];

        return $record instanceof Esame && $record->puoVedereDomandeTest(auth()->user());
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
        return 'Domande del test — ' . $this->record->titolo;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'gestione', 'Domande del test');
    }

    /** Tutto ciò che serve alla vista, calcolato una volta per render. */
    public function dati(): array
    {
        $esame = $this->record;
        $utente = auth()->user();

        return [
            'config'   => TestScritto::configurazione($esame),
            'massimo'  => TestScritto::massimo($esame),
            'domande'  => TestScritto::domandeComplete($esame),
            'stato'    => $esame->statoTest(),
            'puoPreparare' => $esame->puoPreparareTest($utente),
            'puoRefusi'    => $esame->puoCorreggereRefusiTest($utente),
            'motivoModifica' => PreparaTest::motivoNonModificabile($esame, $utente),
        ];
    }

    // ---- Preparazione ----

    public function configurazioneAction(): Action
    {
        return Action::make('configurazione')
            ->label('Punteggio')
            ->modalHeading('Punteggio e ordine del test')
            ->modalDescription('Senza penalità: 1 punto per risposta giusta e tutte le risposte obbligatorie. Con le penalità i tre valori valgono per tutte le domande, il discente può lasciare risposte vuote e il totale non scende mai sotto 0.')
            ->modalSubmitActionLabel('Salva punteggio')
            ->color('success')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(function () {
                $c = TestScritto::configurazione($this->record);

                return [
                    'mescola'  => $c['mescola'],
                    'penalita' => $c['penalita'],
                    'corretta' => TestScritto::mostraDecimale($this->record->test_punti_corretta),
                    'errata'   => TestScritto::mostraDecimale($this->record->test_penalita_errata),
                    'vuota'    => TestScritto::mostraDecimale($this->record->test_penalita_vuota),
                ];
            })
            ->schema([
                Forms\Components\Toggle::make('mescola')->label('Mescola domande e risposte')
                    ->helperText('Ogni discente, a ogni visualizzazione, vede domande e risposte in ordine casuale (le lettere seguono la posizione mostrata). Spento: ordine di origine.'),
                Forms\Components\Toggle::make('penalita')->label('Attiva le penalità')->live(),
                Forms\Components\TextInput::make('corretta')->label('Punti per risposta corretta')->helperText('Con la virgola, al massimo 1 decimale (es. 1,5).')
                    ->visible(fn ($get) => (bool) $get('penalita'))->required(fn ($get) => (bool) $get('penalita')),
                Forms\Components\TextInput::make('errata')->label('Punti sottratti per risposta sbagliata')
                    ->visible(fn ($get) => (bool) $get('penalita'))->required(fn ($get) => (bool) $get('penalita')),
                Forms\Components\TextInput::make('vuota')->label('Punti sottratti per risposta non data')
                    ->visible(fn ($get) => (bool) $get('penalita'))->required(fn ($get) => (bool) $get('penalita')),
            ])
            ->action(function (array $data, Action $action) {
                $this->esegui($action, fn () => PreparaTest::salvaConfigurazione($this->record, auth()->user(), $data), 'Punteggio salvato.');
            });
    }

    /** Aggiungi / modifica una domanda (argomento domanda_id per la modifica). Dopo l'avvio: solo i testi. */
    public function domandaAction(): Action
    {
        return Action::make('domanda')
            ->label('Domanda')
            ->modalHeading(fn (array $arguments) => isset($arguments['domanda_id']) ? 'Modifica domanda' : 'Aggiungi domanda')
            ->modalDescription(fn () => $this->record->puoPreparareTest(auth()->user())
                ? 'Quattro opzioni; indica quella corretta.'
                : 'Il test è già stato avviato: puoi correggere solo i refusi nei testi, non la risposta corretta.')
            ->modalSubmitActionLabel('Salva domanda')
            ->color('success')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(function (array $arguments) {
                $d = isset($arguments['domanda_id']) ? TestDomanda::where('esame_id', $this->record->id)->find($arguments['domanda_id']) : null;
                if (! $d) {
                    return ['testo' => '', 'o0' => '', 'o1' => '', 'o2' => '', 'o3' => '', 'corretta' => null];
                }
                $o = array_values((array) $d->opzioni);

                return ['testo' => $d->testo, 'o0' => $o[0] ?? '', 'o1' => $o[1] ?? '', 'o2' => $o[2] ?? '', 'o3' => $o[3] ?? '', 'corretta' => (string) $d->corretta];
            })
            ->schema([
                Forms\Components\Textarea::make('testo')->label('Testo della domanda')->rows(3)->required(),
                Forms\Components\TextInput::make('o0')->label('Opzione A')->required(),
                Forms\Components\TextInput::make('o1')->label('Opzione B')->required(),
                Forms\Components\TextInput::make('o2')->label('Opzione C')->required(),
                Forms\Components\TextInput::make('o3')->label('Opzione D')->required(),
                Forms\Components\Radio::make('corretta')->label('Risposta corretta')->options(['0' => 'A', '1' => 'B', '2' => 'C', '3' => 'D'])->inline()->required()
                    ->disabled(fn () => ! $this->record->puoPreparareTest(auth()->user()))->dehydrated(),
            ])
            ->action(function (array $data, array $arguments, Action $action) {
                $this->esegui($action, function () use ($data, $arguments) {
                    $opzioni = [$data['o0'], $data['o1'], $data['o2'], $data['o3']];
                    if (isset($arguments['domanda_id'])) {
                        $d = TestDomanda::where('esame_id', $this->record->id)->findOrFail($arguments['domanda_id']);
                        PreparaTest::aggiornaDomanda($this->record, auth()->user(), $d, $data['testo'], $opzioni, (int) $data['corretta']);
                    } else {
                        PreparaTest::creaDomanda($this->record, auth()->user(), $data['testo'], $opzioni, (int) $data['corretta']);
                    }
                }, 'Domanda salvata.');
            });
    }

    public function eliminaDomandaAction(): Action
    {
        return Action::make('eliminaDomanda')
            ->label('Elimina domanda')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Elimina domanda')
            ->modalDescription('La domanda sparisce dal test (solo possibile con il test in bozza). Le altre si rinumerano.')
            ->modalSubmitActionLabel('Elimina')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (array $arguments, Action $action) {
                $this->esegui($action, function () use ($arguments) {
                    $d = TestDomanda::where('esame_id', $this->record->id)->findOrFail($arguments['domanda_id']);
                    PreparaTest::eliminaDomanda($this->record, auth()->user(), $d);
                }, 'Domanda eliminata.');
            });
    }

    /** Importa il test da JSON (file o testo incollato): sostituisce TUTTE le domande, punteggio invariato. Solo con il test in bozza. */
    public function importaTestAction(): Action
    {
        return Action::make('importaTest')
            ->label('Importa il test da JSON')
            ->color('success')
            ->authorize(fn () => $this->record->puoPreparareTest(auth()->user()))
            ->modalHeading('Importa il test da JSON')
            ->modalDescription(fn () => 'Sostituisce TUTTE le domande attuali (' . $this->record->testDomande()->count() . ') con quelle del JSON; il punteggio non cambia. Se qualcosa non è valido non cambia nulla e ti dico cosa correggere.')
            ->modalSubmitActionLabel('Sostituisci il test')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->schema([
                Forms\Components\FileUpload::make('file')->label('File .json')->storeFiles(false)->maxSize(1024)
                    ->acceptedFileTypes(['application/json', 'text/plain', '.json']),
                Forms\Components\Textarea::make('testo')->label('oppure incolla qui il JSON')->rows(8),
            ])
            ->action(function (array $data, Action $action) {
                $file = $data['file'] ?? null;
                $json = $file instanceof TemporaryUploadedFile ? (string) file_get_contents($file->getRealPath()) : trim((string) ($data['testo'] ?? ''));
                if (trim($json) === '') {
                    Notification::make()->title('Carica un file o incolla il JSON.')->danger()->send();
                    $action->halt();

                    return;
                }
                $this->esegui($action, fn () => ImportaTest::importa($this->record, auth()->user(), $json), 'Test importato: le domande sono state sostituite.');
            });
    }
}
