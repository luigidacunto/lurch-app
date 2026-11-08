<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\RiepilogoProvaFinale;
use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\LeggeProvaFinale;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Prova pratica (il colloquio/autovalutazione è in ColloquioEsame). Schede PERSONALI dei commissari (appunti, non entrano nel calcolo,
 * visibili solo a «Apri la prova finale» aperta) e schede FINALI del direttore (le uniche che entrano nel calcolo).
 * Lettura per direttore, commissione, admin e superadmin (Esame::puoVedereProvaFinale); inserimento delle finali SOLO per
 * il direttore (Esame::puoInserireFinali). Dati da App\Esami\RiepilogoProvaFinale, salvataggio da App\Esami\FinaleProvaFinale.
 */
class ProvaPraticaEsame extends Page
{
    use LeggeProvaFinale, InteractsWithRecord {
        LeggeProvaFinale::getSubNavigation insteadof InteractsWithRecord;
    }

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.prova-finale';

    public function sezione(): string
    {
        return 'pratica';
    }

    protected static ?string $title = 'Prova pratica';

    /** Dettagli della prova pratica aperti ("discente" => matrice), caricati solo al clic. */
    public array $dettagli = [];

    public function getTitle(): string
    {
        return 'Prova pratica — ' . $this->record->titolo;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'valutazioni', 'Prova pratica');
    }

    /** Matrice voce × commissario (schede inviate) di UN discente, letta solo al clic (e chiusa al secondo clic). */
    public function apriDettaglioProvaPratica(int $discenteId): void
    {
        abort_unless($this->record->puoVedereProvaFinale(auth()->user()), 403);
        // Solo discenti ammessi dell'esame: niente schede di estranei, ritirati o non ammessi.
        abort_unless($this->record->discentiAmmessi()->where('iscrizioni.partecipante_id', $discenteId)->exists(), 404);

        if (isset($this->dettagli[$discenteId])) {
            unset($this->dettagli[$discenteId]);

            return;
        }
        $this->dettagli[$discenteId] = RiepilogoProvaFinale::provaPraticaDiscente($this->record, $discenteId);
    }
}
