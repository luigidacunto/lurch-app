<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\SvolgimentoTest;
use App\Esami\TestException;
use App\Esami\TestScritto;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;
use App\Models\Socio;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use App\Services\SocioCorrente;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Inserimento a mano del test su carta di UN discente (BES/DSA): solo il direttore, dopo aver dichiarato
 * «fa il test su carta» (con motivo) dalla tab Test scritto. Stesso ordine delle domande del foglio stampato, stesso servizio
 * dell'online (SvolgimentoTest): salvataggio a ogni tocco, «Invia» con le stesse condizioni (tutte le risposte se le
 * penalità non sono attive). Dopo l'invio sola lettura, con l'indicazione «cartaceo» e l'autore. Qui NON si mostrano le corrette.
 */
class InserisciTestCartaceo extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.inserisci-test-cartaceo';

    protected static ?string $title = 'Test cartaceo';

    public int $discenteId = 0;

    public static function canAccess(array $parameters = []): bool
    {
        // Filament sonda canAccess() senza argomenti all'avvio: no-op, il controllo vero ha il record.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }
        $record = $parameters['record'];

        return $record instanceof Esame && $record->puoInserireFinali(auth()->user());
    }

    public function mount(int|string $record, int|string $discente): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::canAccess(['record' => $this->record]), 403);
        abort_unless($this->record->discentiAmmessi()->where('iscrizioni.partecipante_id', (int) $discente)->exists(), 404);
        $this->discenteId = (int) $discente;
        // Si arriva qui solo dopo l'interruttore «fa il test su carta» (o a test già inviato, in sola lettura).
        abort_unless($this->avvio()?->modalita === TestAvvio::CARTACEO, 404);
    }

    public function getTitle(): string
    {
        return 'Test cartaceo — ' . $this->discente()->cognome . ' ' . $this->discente()->nome;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'valutazioni', 'Test cartaceo', [
            EsameResource::getUrl('test-scritto', ['record' => $this->record]) => 'Test scritto',
        ]);
    }

    public function discente(): Socio
    {
        return Socio::findOrFail($this->discenteId);
    }

    private function avvio(): ?TestAvvio
    {
        return TestScritto::avvio($this->record, $this->discenteId);
    }

    public function dati(): array
    {
        $consegna = TestScritto::consegna($this->record, $this->discenteId);
        $inviata = (bool) $consegna?->eInviata();
        $scelte = $consegna ? $consegna->risposte()->whereNotNull('opzione')->pluck('opzione', 'domanda_id')->map(fn ($o) => (int) $o)->all() : [];
        $config = TestScritto::configurazione($this->record);
        $totale = TestScritto::numeroDomande($this->record);

        $motivoOff = null;
        if ($inviata) {
            $motivoOff = 'Il test è già stato inviato.';
        } elseif (! $this->avvio()?->eAttivo()) {
            $motivoOff = 'Il test su carta è stato disattivato per questo discente.';
        }

        return [
            'domande'   => TestScritto::domandePerDiscente($this->record), // niente corrette
            'scelte'    => $scelte,
            'date'      => count($scelte),
            'totale'    => $totale,
            'inviata'   => $inviata,
            'consegna'  => $consegna,
            'motivo'    => $this->avvio()?->motivo_cartaceo,
            'obbligatorie' => $config['obbligatorie'],
            'regola'    => TestScritto::regola($this->record),
            'modificabile' => $motivoOff === null,
            'motivoOff' => $motivoOff,
            'puoInviare' => $motivoOff === null && (! $config['obbligatorie'] || count($scelte) >= $totale) && $totale > 0,
        ];
    }

    /** Una risposta: stessa opzione di nuovo = toglierla (solo con le penalità attive, il servizio lo impone). */
    public function scegli(int $domandaId, int $opzione): void
    {
        $attuale = TestScritto::consegna($this->record, $this->discenteId)?->risposte()->where('domanda_id', $domandaId)->value('opzione');
        $nuova = $attuale !== null && (int) $attuale === $opzione ? null : $opzione;

        try {
            abort_unless($this->record->puoInserireFinali(auth()->user()), 403);
            SvolgimentoTest::salvaRisposta(
                $this->record, $this->discenteId, (string) $this->avvio()?->token, TestConsegna::ORIGINE_CARTACEO,
                SocioCorrente::perUtente(auth()->user()), $domandaId, $nuova,
            );
        } catch (TestException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function inviaAction(): Action
    {
        return Action::make('invia')
            ->label('Invia il test')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Inviare il test cartaceo?')
            ->modalDescription('Dopo l\'invio il test di questo discente è concluso e non si può più modificare.')
            ->modalSubmitActionLabel('Sì, invia')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->action(function (Action $action) {
                try {
                    abort_unless($this->record->puoInserireFinali(auth()->user()), 403);
                    SvolgimentoTest::invia(
                        $this->record, $this->discenteId, (string) $this->avvio()?->token, TestConsegna::ORIGINE_CARTACEO,
                        SocioCorrente::perUtente(auth()->user()),
                    );
                } catch (TestException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }
                Notification::make()->title('Test cartaceo inviato.')->success()->send();
                $this->redirect(EsameResource::getUrl('test-scritto', ['record' => $this->record]));
            });
    }
}
