<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\DecisioneItinere;
use App\Esami\RiepilogoItinere;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;

/**
 * Riepilogo itinere: schede PERSONALI di tutti i docenti (appunti, non entrano nel calcolo) e scheda
 * FINALE d'area del direttore (l'unica che entra nel calcolo). Lettura per direttore, commissione, admin e
 * superadmin (EsameResource::canView…); inserimento della finale SOLO per il direttore (Esame::puoDecidereItinere).
 * Dati da App\Esami\RiepilogoItinere, salvataggio da App\Esami\DecisioneItinere.
 */
class RiepilogoItinereEsame extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EsameResource::class;

    protected string $view = 'filament.resources.esame-resource.pages.riepilogo-itinere';

    protected static ?string $title = 'Itinere';

    /** Dettagli dei laboratori aperti ("discente-lab" => matrice), caricati solo al clic. */
    public array $dettagli = [];

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
        return 'Itinere — ' . $this->record->titolo;
    }

    public function getBreadcrumbs(): array
    {
        return EsameResource::breadcrumbs($this->record, 'valutazioni', 'Itinere');
    }

    public function riepilogo(): array
    {
        return RiepilogoItinere::per($this->record, $this->record->isDirettore(auth()->user()));
    }

    public function puoDecidere(): bool
    {
        return $this->record->puoDecidereItinere(auth()->user());
    }

    /** Motivo per cui «Decidi» è spento, null se acceso (tooltip). */
    public function motivoNonDecidibile(): ?string
    {
        $user = auth()->user();
        if (! $this->record->isDirettore($user)) {
            return 'Solo il direttore del corso inserisce il punteggio finale.';
        }
        if ($this->record->isChiuso()) {
            return 'L\'esame è chiuso: sola lettura.';
        }
        if ($this->record->stato !== Esame::STATO_IN_CORSO) {
            return 'L\'esame non è in corso.';
        }

        return null;
    }

    /** Matrice voce × docente di UN laboratorio per UN discente, letta solo al clic (e chiusa al secondo clic). */
    public function apriDettaglioLaboratorio(int $discenteId, int $laboratorioId): void
    {
        abort_unless($this->record->puoVedereRiepilogoItinere(auth()->user()), 403);
        // Solo discenti dell'esame (non ritirati): niente schede di estranei.
        abort_unless($this->record->discenti()->where('iscrizioni.partecipante_id', $discenteId)->exists(), 404);

        $chiave = $discenteId . '-' . $laboratorioId;
        if (isset($this->dettagli[$chiave])) {
            unset($this->dettagli[$chiave]);

            return;
        }
        $this->dettagli[$chiave] = RiepilogoItinere::dettaglioLaboratorio($this->record, $discenteId, $laboratorioId, $this->record->isDirettore(auth()->user()));
    }

    public function decidiAction(): Action
    {
        return Action::make('decidi')
            ->label('Decidi punteggio')
            ->color('success')
            ->modalHeading(fn (array $arguments) => 'Punteggio finale — ' . ($arguments['area_nome'] ?? '') . ' · ' . ($arguments['discente_nome'] ?? ''))
            ->modalDescription(fn (array $arguments) => 'Scheda FINALE d\'area: entra nel calcolo dell\'idoneità. Non modifica le schede personali dei docenti.'
                . (($arguments['area'] ?? null) === RiepilogoItinere::AREA_SPECCHIO_LABORATORI ? ' ' . RiepilogoItinere::NOTA_AREA_SPECCHIO : ''))
            ->modalSubmitActionLabel('Salva punteggio finale')
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
                    ->label('Punteggio finale d\'area')
                    ->helperText(fn ($get) => 'Da ' . $this->scala()['min'] . ' a ' . $this->scala()['max'] . ', un decimale. Proposta: ' . str_replace('.', ',', (string) $get('proposta')) . ' (media delle medie dei docenti).')
                    ->numeric()
                    ->step(0.1)
                    ->minValue($this->scala()['min'])
                    ->maxValue($this->scala()['max'])
                    ->required()
                    ->live(onBlur: true),
                Forms\Components\Textarea::make('motivazione')
                    ->label('Motivazione')
                    ->helperText('Obbligatoria se il punteggio è diverso dalla proposta.')
                    ->rows(3)
                    ->required(fn ($get) => $this->differisceDallaProposta((string) $get('punteggio'), (string) $get('proposta'))),
            ])
            ->action(function (array $data, array $arguments, Action $action) {
                try {
                    DecisioneItinere::salva(
                        $this->record,
                        auth()->user(),
                        (int) $arguments['discente_id'],
                        (string) $arguments['area'],
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
                Notification::make()->title('Punteggio finale salvato.')->success()->send();
            });
    }

    /** @return array{min: int, max: int} */
    private function scala(): array
    {
        $scala = $this->record->modello_snapshot['itinere']['scala'];

        return ['min' => (int) $scala['min'], 'max' => (int) $scala['max']];
    }

    private function differisceDallaProposta(string $punteggio, string $proposta): bool
    {
        $a = str_replace(',', '.', trim($punteggio));
        $b = str_replace(',', '.', trim($proposta));
        if ($a === '' || ! preg_match('/^\d+(\.\d+)?$/', $a) || ! preg_match('/^\d+(\.\d+)?$/', $b)) {
            return false;
        }

        return \App\Esami\Calcolo\Frazione::da($a)->confronta(\App\Esami\Calcolo\Frazione::da($b)) !== 0;
    }
}
