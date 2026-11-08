<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\FinaleProvaFinale;
use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\LeggeProvaFinale;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;

/**
 * Autovalutazione = colloquio: schede PERSONALI dei commissari (voto + impressione, non entrano nel calcolo,
 * visibili solo a «Apri la prova finale» aperta) e scheda FINALE del direttore (l'unica che entra nel calcolo).
 * Pagina dedicata, separata dalla prova pratica. Lettura per direttore, commissione, admin e superadmin
 * (Esame::puoVedereProvaFinale); inserimento della finale SOLO per il direttore (Esame::puoInserireFinali).
 * Parte comune nel trait LeggeProvaFinale, dati da App\Esami\RiepilogoProvaFinale, salvataggio da App\Esami\FinaleProvaFinale.
 */
class ColloquioEsame extends Page
{
    use LeggeProvaFinale, InteractsWithRecord {
        LeggeProvaFinale::getSubNavigation insteadof InteractsWithRecord;
    }

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.prova-finale';

    protected static ?string $title = 'Autovalutazione';

    public function sezione(): string
    {
        return 'colloquio';
    }

    public function getTitle(): string
    {
        return 'Autovalutazione — ' . $this->record->titolo;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'valutazioni', 'Autovalutazione');
    }

    public function colloquioFinaleAction(): Action
    {
        return Action::make('colloquioFinale')
            ->label('Inserisci colloquio finale')
            ->color('success')
            ->modalHeading(fn (array $arguments) => 'Colloquio finale — ' . ($arguments['discente_nome'] ?? ''))
            ->modalDescription('Scheda FINALE: entra nel calcolo dell\'idoneità. Non modifica le schede personali dei commissari.')
            ->modalSubmitActionLabel('Salva colloquio finale')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->fillForm(fn (array $arguments) => [
                'punteggio'   => $arguments['punteggio'] ?? $arguments['proposta'] ?? null,
                'proposta'    => $arguments['proposta'] ?? null,
                'motivazione' => $arguments['motivazione'] ?? null,
            ])
            ->schema(fn () => [
                Forms\Components\Hidden::make('proposta'),
                Forms\Components\TextInput::make('punteggio')
                    ->label('Voto finale del colloquio')
                    ->helperText(fn ($get) => 'Da ' . $this->scala()['min'] . ' a ' . $this->scala()['max'] . ', un decimale.'
                        . (filled($get('proposta')) ? ' Proposta: ' . str_replace('.', ',', (string) $get('proposta')) . ' (media dei voti dei commissari, troncata).' : ' Nessuna proposta: scegli tu il voto.'))
                    ->numeric()
                    ->step(0.1)
                    ->minValue($this->scala()['min'])
                    ->maxValue($this->scala()['max'])
                    ->required()
                    ->live(onBlur: true),
                Forms\Components\Textarea::make('motivazione')
                    ->label('Motivazione')
                    ->helperText('Obbligatoria se il voto è diverso dalla proposta.')
                    ->rows(3)
                    ->required(fn ($get) => $this->differisceDallaProposta((string) $get('punteggio'), (string) $get('proposta'))),
            ])
            ->action(function (array $data, array $arguments, Action $action) {
                try {
                    FinaleProvaFinale::salvaColloquio(
                        $this->record,
                        auth()->user(),
                        (int) $arguments['discente_id'],
                        (string) $data['punteggio'],
                        $data['motivazione'] ?? null,
                    );
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                    $action->halt();

                    return;
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }
                Notification::make()->title('Colloquio finale salvato.')->success()->send();
            });
    }

    /** @return array{min: int, max: int} */
    private function scala(): array
    {
        $scala = $this->record->modello_snapshot['colloquio']['scala'];

        return ['min' => (int) $scala['min'], 'max' => (int) $scala['max']];
    }

    private function differisceDallaProposta(string $punteggio, string $proposta): bool
    {
        $a = str_replace(',', '.', trim($punteggio));
        $b = str_replace(',', '.', trim($proposta));
        if ($b === '' || $a === '' || ! preg_match('/^\d+(\.\d+)?$/', $a) || ! preg_match('/^\d+(\.\d+)?$/', $b)) {
            return false;
        }

        return \App\Esami\Calcolo\Frazione::da($a)->confronta(\App\Esami\Calcolo\Frazione::da($b)) !== 0;
    }
}
