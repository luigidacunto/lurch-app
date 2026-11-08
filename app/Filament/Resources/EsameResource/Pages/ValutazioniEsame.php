<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\CompletezzaEsame;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Panoramica dello svolgimento (primo tab della barra «Valutazioni»): stato complessivo delle prove, sola lettura.
 * Fa per lo svolgimento ciò che GestioneEsame fa per la configurazione; nessuna logica nuova: i numeri vengono da
 * CompletezzaEsame (barra di avanzamento) e dalle relazioni del test scritto, gli stessi usati dalle singole pagine.
 */
class ValutazioniEsame extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.valutazioni';

    protected static ?string $title = 'Valutazioni';

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
        return 'Valutazioni — ' . $this->record->titolo;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'valutazioni');
    }

    /** Avanzamento per prova: [chiave => [etichetta, testo, rotta della pagina di dettaglio]]. */
    public function prove(): array
    {
        $esame = $this->record;
        $completezza = collect(CompletezzaEsame::per($esame));
        $ammessi = $completezza->count();
        $su = fn (int $n) => "{$n} di {$ammessi} ammessi";

        $nDomande = $esame->testDomande()->count();
        $testo = $nDomande === 0
            ? 'Nessuna domanda'
            : $nDomande . ' domande · avviato per ' . $esame->testAvvii()->whereNotNull('avviato_il')->count()
                . ' · inviati ' . $su($completezza->where('test_inviato', true)->count());

        return [
            ['Itinere', 'Aree decise per tutti: ' . $su($completezza->where('itinere', true)->count()), 'itinere'],
            ['Test scritto', $testo, 'test-scritto'],
            ['Prova pratica', 'Scheda finale: ' . $su($completezza->where('prova_pratica', true)->count()), 'prova-pratica'],
            ['Autovalutazione (colloquio)', 'Scheda finale: ' . $su($completezza->where('colloquio', true)->count()), 'colloquio'],
        ];
    }
}
