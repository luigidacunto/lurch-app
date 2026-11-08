<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\AvvioTest;
use App\Esami\TestScritto;
use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\EseguiAzioneTest;
use App\Models\Esame;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Test scritto. Lettura per chi legge l'esame (direttore, commissione, admin, superadmin); preparazione
 * (domande e punteggio, solo con il test in bozza) per direttore, admin dell'ambito e superadmin; avvio per discente,
 * interruttore cartaceo, disattivazione e inserimento a mano SOLO per il direttore (Esame::puoAvviareTest).
 * Ogni azione ricontrolla i permessi nel servizio (PreparaTest / AvvioTest): i pulsanti spenti in Blade non sono la difesa.
 */
class TestScrittoEsame extends Page
{
    use EseguiAzioneTest;
    use InteractsWithRecord;

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.test-scritto';

    protected static ?string $title = 'Test scritto';

    /** Discenti spuntati per «Avvia per i selezionati». */
    public array $selezionati = [];

    /** Risposte di un test inviato aperte con «Vedi risposte» (discente => [domanda => opzione]), lette solo al clic. */
    public array $dettagli = [];

    public static function canAccess(array $parameters = []): bool
    {
        // Filament sonda canAccess() senza argomenti all'avvio: no-op, il controllo vero ha il record.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }
        $record = $parameters['record'];

        return $record instanceof Esame && $record->puoVedereTestScritto(auth()->user());
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
        return 'Test scritto — ' . $this->record->titolo;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'valutazioni', 'Test scritto');
    }

    /** Tutto ciò che serve alla vista, calcolato una volta per render. */
    public function dati(): array
    {
        $esame = $this->record;
        $utente = auth()->user();

        return [
            'massimo'  => TestScritto::massimo($esame),
            'domande'  => TestScritto::domandeComplete($esame),
            'righe'    => TestScritto::riepilogo($esame),
            'stato'    => $esame->statoTest(),
            'eDirettore'   => (bool) $esame->isDirettore($utente),
            'puoScaricare' => $esame->puoScaricareTestCartaceo($utente),
            'motivoAvvio'    => $this->motivoAvvio(),
            'motivoGestione' => $this->motivoGestione(),
            'motivoBozza'    => AvvioTest::motivoNonRiportabileInBozza($esame, $utente),
        ];
    }

    /** Perché «Avvia» e «Cartaceo» sono spenti (tooltip), null se accesi. */
    private function motivoAvvio(): ?string
    {
        if (! $this->record->isDirettore(auth()->user())) {
            return 'Solo il direttore del corso avvia il test.';
        }

        return $this->record->motivoTestNonAvviabile();
    }

    /** Perché «Disattiva» è spento, null se acceso. */
    private function motivoGestione(): ?string
    {
        if (! $this->record->isDirettore(auth()->user())) {
            return 'Solo il direttore del corso avvia e gestisce il test.';
        }
        if ($this->record->isChiuso()) {
            return "L'esame è chiuso: sola lettura.";
        }

        return $this->record->stato !== Esame::STATO_IN_CORSO ? "L'esame non è in corso." : null;
    }

    /** Risposte date da un discente il cui test è inviato, lette solo al clic (e chiuse al secondo clic). */
    public function apriDettaglio(int $discenteId): void
    {
        abort_unless($this->record->puoVedereTestScritto(auth()->user()), 403);
        abort_unless($this->record->discentiAmmessi()->where('iscrizioni.partecipante_id', $discenteId)->exists(), 404);

        if (isset($this->dettagli[$discenteId])) {
            unset($this->dettagli[$discenteId]);

            return;
        }
        $consegna = TestScritto::consegna($this->record, $discenteId);
        if ($consegna?->eInviata()) {
            $this->dettagli[$discenteId] = TestScritto::risposteDi($this->record, $consegna);
        }
    }

    // ---- Svolgimento (solo il direttore) ----

    public function avviaTuttiAction(): Action
    {
        return Action::make('avviaTutti')
            ->label('Avvia per tutti')
            ->color('brand_pink')
            ->requiresConfirmation()
            ->modalHeading('Avvia il test per tutti i discenti ammessi')
            ->modalDescription('Da questo momento i discenti ammessi vedono il test nella app e lo possono svolgere; domande e punteggio non sono più modificabili (potrai riportare il test in bozza solo finché nessuno ha inviato). Chi ha già inviato o è già avviato non cambia.')
            ->modalSubmitActionLabel('Avvia')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (Action $action) {
                $this->esegui($action, fn () => AvvioTest::avvia($this->record, auth()->user()), 'Test avviato.');
            });
    }

    public function avviaSelezionatiAction(): Action
    {
        return Action::make('avviaSelezionati')
            ->label('Avvia per i selezionati')
            ->color('brand_pink')
            ->requiresConfirmation()
            ->modalHeading('Avvia il test per i discenti selezionati')
            ->modalDescription('Solo i discenti spuntati vedranno il test nella app (utile, per esempio, per una seconda data). I non ammessi e i ritirati non possono essere avviati.')
            ->modalSubmitActionLabel('Avvia')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (Action $action) {
                $ids = array_map('intval', $this->selezionati);
                if ($ids === []) {
                    Notification::make()->title('Seleziona almeno un discente.')->warning()->send();
                    $action->halt();

                    return;
                }
                $this->esegui($action, fn () => AvvioTest::avvia($this->record, auth()->user(), $ids), 'Test avviato per i discenti selezionati.');
                $this->selezionati = [];
            });
    }

    public function avviaUnoAction(): Action
    {
        return Action::make('avviaUno')
            ->label('Avvia')
            ->color('brand_pink')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Avvia il test — ' . ($arguments['discente_nome'] ?? ''))
            ->modalDescription('Da questo momento il discente vede il test nella app. Finché non ha inviato puoi disattivarlo.')
            ->modalSubmitActionLabel('Avvia')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (array $arguments, Action $action) {
                $this->esegui($action, fn () => AvvioTest::avvia($this->record, auth()->user(), [(int) $arguments['discente_id']]), 'Test avviato.');
            });
    }

    public function disattivaAction(): Action
    {
        return Action::make('disattiva')
            ->label('Disattiva')
            ->color('brand_pink')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Disattiva il test — ' . ($arguments['discente_nome'] ?? ''))
            ->modalDescription('Il discente non vedrà più il test. Le risposte già salvate restano in attesa: se lo riattivi senza cambiare le domande le ritrova; un modulo che avesse già aperto non potrà più inviare. Possibile solo finché non ha inviato.')
            ->modalSubmitActionLabel('Disattiva')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (array $arguments, Action $action) {
                $this->esegui($action, fn () => AvvioTest::disattiva($this->record, auth()->user(), (int) $arguments['discente_id']), 'Test disattivato.');
            });
    }

    public function riportaInBozzaAction(): Action
    {
        return Action::make('riportaInBozza')
            ->label('Riporta il test in bozza')
            ->color('brand_pink')
            ->requiresConfirmation()
            ->modalHeading('Riporta il test in bozza')
            ->modalDescription('Disattiva il test per tutti i discenti e riapre la modifica di domande e punteggio. Le risposte già salvate restano in attesa finché non cambi le domande (poi vengono cancellate). Possibile solo se nessun discente ha inviato.')
            ->modalSubmitActionLabel('Riporta in bozza')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (Action $action) {
                $this->esegui($action, fn () => AvvioTest::riportaInBozza($this->record, auth()->user()), 'Test riportato in bozza.');
            });
    }

    /** Interruttore «lo farà su carta»: motivo obbligatorio; abilita l'inserimento a mano del direttore. */
    public function cartaceoAction(): Action
    {
        return Action::make('cartaceo')
            ->label('Cartaceo')
            ->color('success')
            ->modalHeading(fn (array $arguments) => 'Test su carta — ' . ($arguments['discente_nome'] ?? ''))
            ->modalDescription('Il discente non vedrà il test nella app: lo svolge su carta (scarica il PDF) e tu ne inserisci le risposte dal pannello. Possibile finché non c\'è un test inviato.')
            ->modalSubmitActionLabel('Conferma')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->schema([
                Forms\Components\Textarea::make('motivo')->label('Motivo (es. BES/DSA)')->rows(3)->required(),
            ])
            ->action(function (array $data, array $arguments, Action $action) {
                $this->esegui($action, fn () => AvvioTest::impostaCartaceo($this->record, auth()->user(), (int) $arguments['discente_id'], $data['motivo']), 'Test su carta impostato.');
            });
    }
}
