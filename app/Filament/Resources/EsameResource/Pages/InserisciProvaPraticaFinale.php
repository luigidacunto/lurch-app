<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\FinaleProvaFinale;
use App\Esami\RiepilogoProvaFinale;
use App\Esami\StrutturaSemaforo;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;
use App\Models\Socio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;

/**
 * Scheda FINALE della prova pratica di UN discente: le 28 voci le compila SOLO il direttore, precompilate dalla
 * proposta (valore più frequente tra le schede personali inviate) con il confronto voce × commissario accanto. Salva solo
 * se complete. È l'unica scheda della prova pratica che entra nel calcolo. Pagina dedicata (non un modale) per le 28 voci.
 */
class InserisciProvaPraticaFinale extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.inserisci-prova-pratica-finale';

    protected static ?string $title = 'Prova pratica finale';

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
        // Solo discenti ammessi: ritirati e non ammessi non hanno scheda finale.
        abort_unless($this->record->discentiAmmessi()->where('iscrizioni.partecipante_id', (int) $discente)->exists(), 404);
        $this->discenteId = (int) $discente;
    }

    public function getTitle(): string
    {
        return 'Prova pratica finale — ' . $this->discente()->cognome . ' ' . $this->discente()->nome;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'valutazioni', 'Scheda finale', [
            EsameResource::getUrl('prova-pratica', ['record' => $this->record]) => 'Prova pratica',
        ]);
    }

    public function discente(): Socio
    {
        return Socio::findOrFail($this->discenteId);
    }

    /** Struttura, confronto con i commissari, valori attuali (se la finale esiste) o proposta. */
    public function dati(): array
    {
        $struttura = StrutturaSemaforo::per($this->record);
        $confronto = RiepilogoProvaFinale::provaPraticaDiscente($this->record, $this->discenteId);
        $attuali = RiepilogoProvaFinale::valoriFinaleProvaPratica($this->record, $this->discenteId);
        $esiste = $attuali !== [];

        $iniziali = [];
        foreach ($struttura['codici'] as $codice) {
            $iniziali[$codice] = $esiste
                ? ($attuali[$codice] ?? null)
                : StrutturaSemaforo::livello($struttura['scala'], $confronto['proposta'][$codice] ?? null);
        }

        return [
            'struttura' => $struttura,
            'confronto' => $confronto,
            'iniziali'  => $iniziali,
            'proposta'  => array_map(fn ($v) => StrutturaSemaforo::livello($struttura['scala'], $v), $confronto['proposta']),
            'esiste'    => $esiste,
        ];
    }

    /** @param array<string, ?string> $voci codice voce => chiave della scala */
    public function salva(array $voci): void
    {
        try {
            FinaleProvaFinale::salvaProvaPratica($this->record, auth()->user(), $this->discenteId, $voci);
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }
        Notification::make()->title('Prova pratica finale salvata.')->success()->send();
        $this->redirect(EsameResource::getUrl('prova-pratica', ['record' => $this->record]));
    }
}
