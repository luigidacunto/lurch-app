<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\HaSubNavigationEsame;
use App\Esami\AmmissioneDiscente;
use App\Esami\Calcolo\Frazione;
use App\Esami\ColoreScala;
use App\Esami\Formato;
use App\Esami\ProiezioneEsame;
use App\Models\Iscrizione;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;
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
                Tables\Columns\IconColumn::make('non_ammesso')->label('Non ammesso')->boolean()
                    ->trueIcon('heroicon-o-x-circle')->trueColor('danger')->falseIcon('')
                    ->tooltip(fn ($record) => $record->non_ammesso ? $record->non_ammesso_motivo : null),
                Tables\Columns\TextColumn::make('proiezione_itinere')->label('Itinere (centesimi)')
                    ->visible(fn () => $this->conProiezione())
                    ->state(fn (Iscrizione $record) => $this->cellaItinere($record))->html()
                    ->tooltip('Contributo dell\'itinere su '.($this->getRecord()->modello_snapshot['pesi']['itinere'] ?? '').': punteggi fissati dal direttore, altrimenti media dei docenti (stima). «–» se un\'area non ha né punteggio né voti.'),
                Tables\Columns\TextColumn::make('proiezione_totale')->label('Totale')
                    ->visible(fn () => $this->conProiezione())
                    ->state(fn (Iscrizione $record) => $this->cellaTotale($record))->html(),
            ])
            ->recordActions([
                Action::make('proiezione')
                    ->label('Proiezione')
                    ->tooltip(fn (Iscrizione $record) => $record->non_ammesso ? 'Non ammesso: nessuna prova finale' : 'Proiezione del punteggio finale')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->iconButton()
                    ->visible(fn () => $this->conProiezione())
                    ->disabled(fn (Iscrizione $record) => (bool) $record->non_ammesso)
                    ->slideOver()
                    ->modalWidth(\Filament\Support\Enums\Width::SixExtraLarge)
                    ->modalHeading(fn (Iscrizione $record) => 'Proiezione — '.$record->partecipante?->nome_completo)
                    ->modalDescription('Simulazione: i valori ipotetici non si salvano e non entrano nell\'esito. Tra parentesi accanto ai docenti: numero di voti.')
                    ->fillForm(fn (Iscrizione $record) => $this->valoriIniziali($record))
                    ->schema(fn (Iscrizione $record) => $this->schemaProiezione($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi'),
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

    // ---- Proiezione del punteggio (sola lettura, nulla salvato) ----

    /** @var array<int, array>|null */
    private ?array $proiezioni = null;

    /** Solo a esame attivato (serve lo snapshot del modello). */
    public function conProiezione(): bool
    {
        return (bool) $this->getRecord()->modello_snapshot;
    }

    private function proiezione(int $discenteId): ?array
    {
        $this->proiezioni ??= ProiezioneEsame::per($this->getRecord());

        return $this->proiezioni[$discenteId] ?? null;
    }

    private static function virgola(?string $v): ?string
    {
        return $v === null ? null : str_replace('.', ',', $v);
    }

    /** Pillola colorata rispetto al LAP (stessa convenzione del modulo). */
    private function pillola(Frazione $valore, Frazione $massimo, string $testo): string
    {
        return '<span style="display:inline-block; padding:1px 8px; border-radius:6px; color:#111827; font-weight:600; background:'
            .ColoreScala::perValore($valore, $massimo, (string) $this->getRecord()->lap).';">'.e($testo).'</span>';
    }

    /** Valori dell'itinere: fissati o, in loro assenza, i valori dati (proposte o ipotesi). */
    private function valoriItinere(array $p, \Closure $altrimenti): array
    {
        return collect($p['aree'])->mapWithKeys(fn ($a) => [$a['codice'] => $a['finale'] ?? $altrimenti($a)])->all();
    }

    private function cellaItinere(Iscrizione $record): string
    {
        $p = $this->proiezione($record->partecipante_id);
        if (! $p) {
            return '—';
        }
        $snapshot = $this->getRecord()->modello_snapshot;
        $t = ProiezioneEsame::totale($snapshot, (string) $this->getRecord()->lap, [
            'itinere' => $this->valoriItinere($p, fn ($a) => $a['proposta']),
            'colloquio' => null, 'prova_pratica' => null, 'test_scritto' => null,
        ], $p['massimi']);
        $c = $t['contributi']['itinere'];
        if ($c === null) {
            return '–';
        }
        $stima = collect($p['aree'])->contains(fn ($a) => $a['finale'] === null);

        return $this->pillola($c, Frazione::da((string) $snapshot['pesi']['itinere']), Formato::decimale($c))
            .($stima ? ' <span style="font-size:12px; color:#6b7280;">stima</span>' : '');
    }

    private function cellaTotale(Iscrizione $record): string
    {
        $p = $this->proiezione($record->partecipante_id);
        if (! $p) {
            return '—';
        }
        if ($p['finale'] === null) {
            return '<span style="color:#6b7280;">da completare</span>';
        }

        return $this->pillola(Frazione::da($p['finale']), Frazione::di(100), self::virgola($p['finale'])).' '.($p['idoneo'] ? 'idoneo' : 'non idoneo');
    }

    /** Nel form solo i campi non fissati; le aree itinere partono dalla proposta dei docenti. */
    private function valoriIniziali(Iscrizione $record): array
    {
        $p = $this->proiezione($record->partecipante_id);

        return collect($p['aree'] ?? [])->filter(fn ($a) => $a['finale'] === null)
            ->mapWithKeys(fn ($a) => ['itinere_'.$a['codice'] => self::virgola($a['proposta'])])->all();
    }

    private function schemaProiezione(Iscrizione $record): array
    {
        $p = $this->proiezione($record->partecipante_id);
        if (! $p) {
            return [TextEntry::make('nessuno')->hiddenLabel()->state('Nessun dato per questo discente.')];
        }
        $esame = $this->getRecord();
        $snapshot = $esame->modello_snapshot;
        $pesi = $snapshot['pesi'];
        $calcola = fn ($get) => ProiezioneEsame::totale($snapshot, (string) $esame->lap, [
            'itinere'       => $this->valoriItinere($p, fn ($a) => $get('itinere_'.$a['codice'])),
            'colloquio'     => $p['colloquio'] ?? $get('colloquio'),
            'prova_pratica' => $p['prova_pratica'] ?? $get('prova_pratica'),
            'test_scritto'  => $p['test_scritto'] ?? $get('test_scritto'),
        ], $p['massimi']);
        $contributo = fn (string $prova) => fn ($get) => ($c = $calcola($get)['contributi'][$prova]) === null
            ? ($calcola($get)['errori'][$prova] ?? 'Contributo: –')
            : new HtmlString('Contributo: '.$this->pillola($c, Frazione::da((string) $pesi[$prova]), Formato::decimale($c)).' su '.$pesi[$prova]);

        // Layout compatto (deve stare in un portatile senza scorrere): totale in cima, aree itinere su 4 colonne, le altre
        // tre prove su 3 colonne; i contributi stanno nel sottotitolo della sezione o sotto il campo, non in righe a sé.
        $itinere = [];
        foreach ($p['aree'] as $a) {
            $docenti = $a['docenti']
                ? collect($a['docenti'])->map(fn ($d) => $d['nome'].' '.$d['media'].' ('.$d['voti'].')')->implode(' · ')
                : 'Nessun docente ha votato.';
            $itinere[] = $a['finale'] !== null
                ? TextEntry::make('fissato_'.$a['codice'])->label($a['nome'])->state(self::virgola($a['finale']).' fissato')->helperText($docenti)
                : TextInput::make('itinere_'.$a['codice'])
                    ->label($a['nome'].' · proposta '.(self::virgola($a['proposta']) ?? '–'))
                    ->placeholder($snapshot['itinere']['scala']['min'].'–'.$snapshot['itinere']['scala']['max'])
                    ->helperText($docenti)
                    ->live(debounce: 500);
        }

        $prova = function (string $codice, string $nome, string $aiuto) use ($p, $contributo) {
            $etichetta = $nome.' (su '.Formato::decimale($p['massimi'][$codice]).')';

            return $p[$codice] !== null
                ? TextEntry::make('fissato_'.$codice)->label($etichetta)->state(Formato::decimale($p[$codice]).' fissato')->helperText($contributo($codice))
                : TextInput::make($codice)->label($etichetta)->placeholder($aiuto)->live(debounce: 500)->helperText($contributo($codice));
        };

        // Deve stare senza scorrere anche in un portatile (area utile ~670 px di altezza): una sezione per l'itinere col
        // contributo nel titolo, e una riga unica, senza titolo, con le altre tre prove e il totale.
        $totale = TextEntry::make('totale')->label(fn () => $p['finale'] !== null ? 'Totale effettivo' : 'Totale ipotetico')
            ->state(function ($get) use ($p, $calcola) {
                $finale = $p['finale'] ?? $calcola($get)['finale'];
                if ($finale === null) {
                    return 'completa i valori mancanti';
                }
                $idoneo = $p['finale'] !== null ? $p['idoneo'] : $calcola($get)['idoneo'];

                return new HtmlString($this->pillola(Frazione::da($finale), Frazione::di(100), self::virgola($finale)).' '.($idoneo ? 'idoneo' : 'non idoneo'));
            })
            ->helperText('LAP '.rtrim(rtrim((string) $this->getRecord()->lap, '0'), '.'));

        return [
            Section::make(function ($get) use ($contributo) {
                $c = $contributo('itinere')($get);

                return new HtmlString('Itinere &nbsp;·&nbsp; '.($c instanceof HtmlString ? $c->toHtml() : e($c)));
            })
                ->compact()->columns(4)->schema($itinere),
            Section::make()->compact()->columns(4)->schema([
                $prova('colloquio', 'Colloquio', 'da '.$snapshot['colloquio']['scala']['min'].' a '.$snapshot['colloquio']['scala']['max'].', un decimale'),
                $prova('prova_pratica', 'Prova pratica', 'punti della scheda'),
                $prova('test_scritto', 'Test scritto', 'punti del test'),
                $totale,
            ]),
        ];
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
