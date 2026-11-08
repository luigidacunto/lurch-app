<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Esami\Calcolo\Frazione;
use App\Esami\ColoreScala;
use App\Esami\Formato;
use App\Esami\ProiezioneEsame;
use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\HaSubNavigationEsame;
use App\Models\Iscrizione;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Riepilogo dei discenti nella sezione Valutazioni (in fondo alla barra): per ogni discente ammesso i contributi in
 * centesimi di itinere, colloquio, prova pratica e test, il totale e lo stato (idoneo / non idoneo) solo con tutte e quattro
 * le prove fissate, e la finestra «Proiezione» con valori ipotetici temporanei. SOLA lettura: nulla si salva e niente entra
 * nell'esito. La lista di configurazione (comitato, età, ammissione) sta in Gestione → Discenti.
 */
class RiepilogoDiscentiEsame extends ManageRelatedRecords
{
    use HaSubNavigationEsame;

    protected static string $resource = EsameResource::class;

    protected static string $relationship = 'discenti';

    protected static ?string $title = 'Discenti';

    public function table(Table $table): Table
    {
        $snapshot = $this->getRecord()->modello_snapshot;
        $pesi = $snapshot['pesi'] ?? [];

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partecipante.cognome')->label('Cognome'),
                Tables\Columns\TextColumn::make('partecipante.nome')->label('Nome'),
                Tables\Columns\TextColumn::make('proiezione_itinere')->label('Itinere /'.($pesi['itinere'] ?? ''))
                    ->state(fn (Iscrizione $record) => $this->cellaItinere($record))->html()
                    ->tooltip("Itinere: punteggi d'area fissati dal direttore, altrimenti media dei docenti (stima). «–» se un'area non ha né punteggio né voti."),
                // Colloquio, prova pratica, test: contributo in centesimi solo se il punteggio è fissato («–» altrimenti).
                ...collect(['colloquio' => 'Colloquio', 'prova_pratica' => 'Prova pratica', 'test_scritto' => 'Test scritto'])
                    ->map(fn (string $nome, string $prova) => Tables\Columns\TextColumn::make('proiezione_'.$prova)->label($nome.' /'.($pesi[$prova] ?? ''))
                        ->state(fn (Iscrizione $record) => $this->cellaProva($record, $prova))->html()
                        ->tooltip($nome.': contributo solo se il punteggio è già fissato.'))
                    ->values()->all(),
                // Totale e stato solo con tutte e quattro le prove fissate, altrimenti vuoti.
                Tables\Columns\TextColumn::make('proiezione_totale')->label('Totale')
                    ->state(fn (Iscrizione $record) => $this->cellaTotale($record))->html(),
                Tables\Columns\TextColumn::make('proiezione_stato')->label('Stato')
                    ->state(fn (Iscrizione $record) => $this->cellaStato($record))->html(),
            ])
            ->recordActions([
                Action::make('proiezione')
                    ->label('Proiezione')
                    ->tooltip(fn (Iscrizione $record) => $record->non_ammesso ? 'Non ammesso: nessuna prova finale' : 'Proiezione del punteggio finale')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->iconButton()
                    ->disabled(fn (Iscrizione $record) => (bool) $record->non_ammesso)
                    ->slideOver()
                    ->modalWidth(\Filament\Support\Enums\Width::SixExtraLarge)
                    ->modalHeading(fn (Iscrizione $record) => 'Proiezione — '.$record->partecipante?->nome_completo)
                    ->modalDescription("Simulazione: i valori ipotetici non si salvano e non entrano nell'esito. Tra parentesi accanto ai docenti: numero di voti.")
                    ->fillForm(fn (Iscrizione $record) => $this->valoriIniziali($record))
                    ->schema(fn (Iscrizione $record) => $this->schemaProiezione($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi'),
            ], position: RecordActionsPosition::BeforeColumns)
            ->description('Contributi in centesimi delle singole prove (peso dopo la barra), totale e stato solo a prove tutte fissate. Per simulare i punteggi mancanti usa «Proiezione».')
            ->emptyStateHeading('Nessun discente iscritto al corso');
    }

    // ---- Proiezione del punteggio (sola lettura, nulla salvato) ----

    /** @var array<int, array>|null */
    private ?array $proiezioni = null;

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

    /** Contributo in centesimi di UNA prova, solo se il suo punteggio è fissato (le ipotesi stanno nella finestra «Proiezione»). */
    private function cellaProva(Iscrizione $record, string $prova): string
    {
        $p = $this->proiezione($record->partecipante_id);
        if (! $p) {
            return '—';
        }
        $snapshot = $this->getRecord()->modello_snapshot;
        $t = ProiezioneEsame::totale($snapshot, (string) $this->getRecord()->lap, [
            'itinere'       => $this->valoriItinere($p, fn () => null),
            'colloquio'     => $p['colloquio'],
            'prova_pratica' => $p['prova_pratica'],
            'test_scritto'  => $p['test_scritto'],
        ], $p['massimi']);
        $c = $t['contributi'][$prova];

        return $c === null ? '–' : $this->pillola($c, Frazione::da((string) $snapshot['pesi'][$prova]), Formato::decimale($c));
    }

    /** Totale effettivo (motore) solo con tutte le prove fissate; altrimenti cella vuota. */
    private function cellaTotale(Iscrizione $record): string
    {
        $p = $this->proiezione($record->partecipante_id);
        if (! $p) {
            return '—';
        }

        return $p['finale'] === null ? '' : $this->pillola(Frazione::da($p['finale']), Frazione::di(100), self::virgola($p['finale']));
    }

    /** Idoneo / non idoneo solo con tutte le prove fissate; altrimenti cella vuota. */
    private function cellaStato(Iscrizione $record): string
    {
        $p = $this->proiezione($record->partecipante_id);
        if (! $p || $p['finale'] === null) {
            return '';
        }

        return $p['idoneo']
            ? '<span style="color:#15803d; font-weight:600;">Idoneo</span>'
            : '<span style="color:#b91c1c; font-weight:600;">Non idoneo</span>';
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


    public static function canAccess(array $parameters = []): bool
    {
        // Solo con l'esame attivato (serve lo snapshot del modello): prima non c'è nulla da riepilogare.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }

        return ($parameters['record']->modello_snapshot !== null) && EsameResource::canEdit($parameters['record']);
    }

    public function getBreadcrumbs(): array
    {
        return static::getResource()::breadcrumbs($this->getRecord(), 'valutazioni', 'Discenti');
    }
}
