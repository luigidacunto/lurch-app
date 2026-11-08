<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GraduatoriaResource\Pages;
use App\Models\CatalogoCorso;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Corso;
use App\Models\Graduatoria;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GraduatoriaResource extends Resource
{
    protected static ?string $model = Graduatoria::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestione operativa';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'graduatorie';

    protected static ?string $modelLabel = 'Graduatoria';

    protected static ?string $pluralModelLabel = 'Graduatorie';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('graduatorie.manage');
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale();
    }

    public static function canEdit(Model $record): bool
    {
        return $record->puoGestire(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        if ($user?->isAdmin()) return $record->comitato_regionale_id === $user->comitato_regionale_id;
        if ($user?->isAdminLocale()) return $record->comitato_locale_id === $user->comitato_locale_id;
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['operatori', 'corso']);
        $user  = auth()->user();

        if ($user?->isAdmin()) {
            $query->where('comitato_regionale_id', $user->comitato_regionale_id);
        } elseif ($user?->isAdminLocale()) {
            $query->where('comitato_locale_id', $user->comitato_locale_id);
        } elseif ($user?->isOperatore()) {
            $query->whereHas('operatori', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query;
    }

    public static function form(Schema $form): Schema
    {
        $authUser = auth()->user();

        $localiQuery = ComitatoLocale::orderBy('nome');
        if ($authUser?->isAdmin()) {
            $localiQuery->where('comitato_regionale_id', $authUser->comitato_regionale_id);
        } elseif ($authUser?->isAdminLocale()) {
            $localiQuery->where('id', $authUser->comitato_locale_id);
        }
        $localiOptions = $localiQuery->pluck('nome', 'id');

        return $form->columns(1)->schema([
            Section::make('Informazioni generali')->columnSpanFull()->schema([
                Forms\Components\Placeholder::make('gaia_paste_btn')
                    ->hiddenLabel()
                    ->content(new \Illuminate\Support\HtmlString(<<<'HTML'
                        <div x-data="{
                            gaiaValido: false,
                            gaiaDati: null,
                            async controlla() {
                                try {
                                    const testo = await navigator.clipboard.readText();
                                    const dati = JSON.parse(testo);
                                    this.gaiaValido = !!(dati && dati.corso);
                                    this.gaiaDati = this.gaiaValido ? dati : null;
                                } catch(e) {
                                    this.gaiaValido = false;
                                    this.gaiaDati = null;
                                }
                            },
                            async incolla() {
                                if (!this.gaiaValido || !this.gaiaDati) return;
                                const datiUsati = JSON.parse(JSON.stringify(this.gaiaDati));
                                await $wire.riempiDaGaia(datiUsati);
                                // Reset locale immediato: disabilita il pulsante
                                this.gaiaValido = false;
                                this.gaiaDati = null;
                                // Se la clipboard contiene ancora gli stessi dati appena incollati, svuotala
                                try {
                                    const clipboardOra = await navigator.clipboard.readText();
                                    const datiOra = JSON.parse(clipboardOra);
                                    if (datiOra && datiOra.protocollo && datiOra.protocollo === datiUsati.protocollo) {
                                        await navigator.clipboard.writeText('');
                                    }
                                } catch(e) { /* clipboard già cambiata o permesso negato: lasciare com'è */ }
                            }
                        }" x-init="controlla()" style="display:flex;align-items:center;gap:10px;padding:2px 0;">
                            <button
                                type="button"
                                @click="incolla()"
                                :disabled="!gaiaValido"
                                :style="{
                                    background:    gaiaValido ? '#2563eb' : '#f1f5f9',
                                    color:         gaiaValido ? '#ffffff' : '#94a3b8',
                                    cursor:        gaiaValido ? 'pointer' : 'not-allowed',
                                    'border-color':gaiaValido ? '#1d4ed8' : '#e2e8f0',
                                    opacity:       gaiaValido ? '1' : '0.6'
                                }"
                                style="flex-shrink:0;padding:6px 14px;border-radius:7px;font-size:13px;font-weight:600;border:1px solid #e2e8f0;background:#f1f5f9;color:#94a3b8;transition:background .15s,color .15s,border-color .15s;"
                            >Incolla da GAIA</button>
                            <span x-show="gaiaValido"
                                  x-text="gaiaDati?.corso ?? ''"
                                  style="font-size:12px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:380px;"></span>
                            <span x-show="!gaiaValido"
                                  style="font-size:12px;color:#94a3b8;">Nessun dato GAIA nella clipboard</span>
                        </div>
                    HTML))
                    ->visible(fn ($record) => $record === null)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('titolo')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Se riproponi ogni anno una graduatoria con lo stesso nome, aggiungi l\'anno (e il periodo, se capita più volte nello stesso anno — es. "Autunno 2026") per distinguerle facilmente in elenco.')
                    ->columnSpanFull(),

                Forms\Components\Select::make('catalogo_corso_diversifica_id')
                    ->label('Corso di riferimento')
                    // Label "[codice] nome" — stessa convenzione di QualificheRelationManager,
                    // la ricerca di ->searchable() filtra sul testo mostrato quindi copre anche
                    // la ricerca per codice.
                    ->options(fn () => CatalogoCorso::attivo()->orderBy('nome')->get()
                        ->mapWithKeys(fn (CatalogoCorso $c) => [$c->id => "[{$c->codice}] {$c->nome}"]))
                    ->searchable()
                    ->required()
                    ->helperText('Qualifica del catalogo di riferimento per questa graduatoria — usata dal criterio "diversifica comitato" (sezione Criteri di selezione) quando attivo.'),

                Forms\Components\Select::make('corso_id')
                    ->label('Corso collegato (opzionale)')
                    ->options(fn () => Corso::where('data_fine', '>=', now())
                        ->orderBy('data_inizio')
                        ->get()
                        ->mapWithKeys(fn (Corso $c) => [
                            $c->id => $c->nome . ($c->numero_protocollo ? ' — ' . $c->numero_protocollo : ''),
                        ]))
                    ->getOptionLabelUsing(fn ($value) => ($c = Corso::find($value))
                        ? $c->nome . ($c->numero_protocollo ? ' — ' . $c->numero_protocollo : '')
                        : null)
                    ->searchable()
                    ->nullable()
                    ->helperText('Collegamento puramente informativo, non obbligatorio. Solo corsi non ancora conclusi.'),

                Forms\Components\Select::make('comitato_regionale_id')
                    ->label('Comitato Regionale')
                    ->options(
                        ($authUser?->isAdmin() || $authUser?->isAdminLocale())
                            ? ComitatoRegionale::where('id', $authUser->comitato_regionale_id)->pluck('nome', 'id')
                            : ComitatoRegionale::orderBy('nome')->pluck('nome', 'id')
                    )
                    ->default($authUser?->comitato_regionale_id ?? null)
                    ->required()
                    ->searchable()
                    ->disabled($authUser?->isAdmin() || $authUser?->isAdminLocale())
                    ->dehydrated(true)
                    ->live(),

                Forms\Components\Select::make('comitato_locale_id')
                    ->label('Comitato Locale (opzionale)')
                    ->options($localiOptions)
                    ->default($authUser?->isAdminLocale() ? $authUser->comitato_locale_id : null)
                    ->nullable()
                    ->searchable()
                    ->disabled($authUser?->isAdminLocale())
                    ->dehydrated(true),

                Forms\Components\DateTimePicker::make('data_apertura')
                    ->label('Apertura iscrizioni')
                    ->required()
                    ->native(false),

                Forms\Components\DateTimePicker::make('data_chiusura')
                    ->label('Chiusura iscrizioni')
                    ->required()
                    ->native(false)
                    ->afterOrEqual('data_apertura'),

                Forms\Components\Textarea::make('testo_email_conferma')
                    ->label('Testo email di conferma (opzionale)')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Testo aggiuntivo mostrato nell\'email di conferma iscrizione alla graduatoria.'),

                Forms\Components\Select::make('operatori')
                    ->label('Operatori abilitati')
                    ->helperText('Potranno confermare/rifiutare candidature e aggiungere candidati fuori termine.')
                    ->relationship(
                        name: 'operatori',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query) use ($authUser) {
                            $query->where('ruolo', 'operatore')->where('attivo', true);
                            if ($authUser?->isAdmin() || $authUser?->isAdminLocale()) {
                                $query->where('comitato_regionale_id', $authUser->comitato_regionale_id);
                            }
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name
                        . ($record->comitatoLocale?->nome ? ' — ' . $record->comitatoLocale->nome : ''))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Criteri di selezione')
                ->columnSpanFull()
                ->collapsed()
                ->description('Impostazioni che determineranno punteggio, ammissibilità e criteri di selezione degli iscritti a questa graduatoria.')
                ->schema([
                    Forms\Components\TextInput::make('posti_disponibili')
                        ->label('Posti disponibili')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->default(30)
                        ->helperText('Usato dal calcolo della classifica per determinare gli ammessi (anche con la rotazione tra comitati). Lascia vuoto per ottenere solo punteggio e ordinamento, senza taglio ammessi/esclusi.')
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('qualifichePreferenziali')
                        ->label('Qualifiche preferenziali')
                        ->relationship()
                        ->helperText('Ogni riga assegna il punteggio indicato a chi possiede almeno uno dei corsi elencati. Se più corsi valgono lo stesso punteggio, inseriscili tutti nella stessa riga (separati da virgola o punto e virgola).')
                        ->schema([
                            Forms\Components\TagsInput::make('codici_corso')
                                ->label('Codici corso')
                                ->placeholder('es. SICPRE, SICDIR, AGGISIC')
                                ->splitKeys([',', ';'])
                                ->live()
                                ->rules([fn () => CatalogoCorso::regolaCodiciValidi()])
                                ->required()
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('punteggio')
                                ->label('Punteggio')
                                ->numeric()
                                ->step(0.01)
                                ->required()
                                ->columnSpan(1),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('Aggiungi criterio a punteggio')
                        ->columnSpanFull(),

                    Forms\Components\TagsInput::make('qualifiche_propedeutiche')
                        ->label('Qualifiche propedeutiche')
                        ->placeholder('es. TSSA, TCRI')
                        ->splitKeys([',', ';'])
                        ->live()
                        ->rules([fn () => CatalogoCorso::regolaCodiciValidi()])
                        ->helperText('Codici dei corsi richiesti come prerequisito. Non bloccano la candidatura: se scaduti o non aggiornati in tempo, al candidato compare un avviso in pagina di conferma e nella lista Candidati appare la colonna "Prerequisiti" in giallo/rosso. Se una qualifica è conosciuta con più codici equivalenti, inserire solo il codice del corso "principale" (target dell\'equivalenza) o dell\'aggregatore che raggruppa i corsi equivalenti tra loro — non tutti i codici insieme, altrimenti il sistema verifica ciascuno separatamente.')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('richiede_corso_sicurezza')
                        ->label('Richiedi formazione Salute e Sicurezza valida (AGGR-SIC)')
                        ->helperText('Verifica direttamente la validità dell\'aggregatore "Salute e Sicurezza" (dove confluiscono tutti i corsi sicurezza accettati). Da regolamento è sempre richiesta: disattivare solo per casi limite.')
                        ->default(true)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('priorita_eta_a_parita_punteggio')
                        ->label('A parità di punteggio, priorità al candidato più giovane')
                        ->default(false),

                    Forms\Components\Toggle::make('preferenza_diversifica_comitato')
                        ->label('Preferenza a chi non ha altri soci in comitato con la stessa qualifica')
                        ->helperText('Se attivo senza un punteggio: criterio di selezione automatica (priorità assoluta). Se attivo con un punteggio: il punteggio viene sommato invece di applicare una priorità assoluta.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('punteggio_diversifica_comitato')
                        ->label('Punteggio (opzionale)')
                        ->numeric()
                        ->step(0.01)
                        ->nullable()
                        ->visible(fn ($get) => (bool) $get('preferenza_diversifica_comitato'))
                        ->helperText('Lascia vuoto per priorità assoluta invece che punteggio aggiuntivo.'),

                    Forms\Components\Toggle::make('selezione_a_rotazione_comitati')
                        ->label('Selezione a rotazione tra comitati')
                        ->helperText('Se attivo, la selezione garantisce un posto a ciascun comitato (in base al punteggio) prima di prenderne un secondo dallo stesso comitato. Un comitato senza candidati abbastanza in alto in classifica può comunque restare fuori dai posti disponibili.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('rotazione_multi_turno')
                        ->label('Rotazione su più turni')
                        ->helperText('Disattivo (default): la garanzia vale solo al primo turno (un posto a testa per comitato); da lì in poi si prosegue in ordine di solo punteggio, indipendentemente dal comitato. Attivo: la garanzia si ripete a ogni turno successivo (2°, 3°... posto per comitato), finché non sono esauriti tutti i candidati di tutti i comitati.')
                        ->default(false)
                        ->visible(fn ($get) => (bool) $get('selezione_a_rotazione_comitati'))
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('richiede_attestazione_presidente')
                        ->label('Consenti upload attestazione del presidente')
                        ->helperText('Se attivo, il candidato può allegare (facoltativamente) un PDF di attestazione del presidente per lo svolgimento regolare di attività. Il punteggio si applica solo dopo verifica manuale da parte di un operatore.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('punteggio_attestazione_presidente')
                        ->label('Punteggio se verificata')
                        ->numeric()
                        ->step(0.01)
                        ->nullable()
                        ->visible(fn ($get) => (bool) $get('richiede_attestazione_presidente'))
                        ->required(fn ($get) => (bool) $get('richiede_attestazione_presidente')),

                    Forms\Components\Toggle::make('richiede_diploma')
                        ->label('Richiedi titolo di studio (diploma superiore)')
                        ->helperText('Se attivo, il candidato deve autodichiarare nel form pubblico il possesso del diploma e di averlo caricato su GAIA (spunta obbligatoria). Un operatore verifica manualmente su GAIA entro la chiusura: chi non risulta verificato viene escluso dal calcolo della classifica.')
                        ->default(false)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('richiede_autocertificazione')
                        ->label('Richiedi upload autocertificazione di attività')
                        ->helperText('Se attivo, il candidato deve allegare obbligatoriamente un PDF di autocertificazione nel form pubblico. Un operatore verifica manualmente il documento: chi non risulta verificato viene escluso dal calcolo della classifica.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('dicitura_autocertificazione')
                        ->label('Dicitura da mostrare sul campo di upload')
                        ->helperText('Testo esatto mostrato al candidato accanto al campo di caricamento (es. tipo di attività/esperienza da autocertificare).')
                        ->rows(2)
                        ->visible(fn ($get) => (bool) $get('richiede_autocertificazione'))
                        ->required(fn ($get) => (bool) $get('richiede_autocertificazione'))
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->color('success'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('titolo')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Graduatoria $record) => $record->corso?->nome),

                Tables\Columns\TextColumn::make('periodo_corso')
                    ->label('Periodo corso')
                    ->toggleable()
                    ->getStateUsing(function (Graduatoria $record) {
                        $corso = $record->corso;
                        if (! $corso || ! $corso->data_inizio || ! $corso->data_fine) {
                            return null;
                        }

                        $inizio = $corso->data_inizio;
                        $fine   = $corso->data_fine;

                        if ($inizio->isSameDay($fine)) {
                            return $inizio->format('d/m/Y');
                        }
                        if ($inizio->isSameMonth($fine) && $inizio->year === $fine->year) {
                            return $inizio->format('d') . '–' . $fine->format('d/m/Y');
                        }
                        if ($inizio->year === $fine->year) {
                            return $inizio->format('d/m') . ' – ' . $fine->format('d/m/Y');
                        }

                        return $inizio->format('d/m/Y') . ' – ' . $fine->format('d/m/Y');
                    })
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('link_iscrizione')
                    ->label('Link iscrizione')
                    ->toggleable()
                    ->getStateUsing(fn (Graduatoria $record) => route('graduatoria.mostra', $record->codice_pubblico))
                    ->icon('heroicon-o-link')
                    ->copyable()
                    ->copyableState(fn (Graduatoria $record) => route('graduatoria.mostra', $record->codice_pubblico))
                    ->copyMessage('Link copiato negli appunti!')
                    ->formatStateUsing(fn () => 'Copia link')
                    ->tooltip(fn (Graduatoria $record) => route('graduatoria.mostra', $record->codice_pubblico))
                    ->color('primary'),

                Tables\Columns\TextColumn::make('comitatoRegionale.nome')
                    ->label('Regione')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('comitatoLocale.nome')
                    ->label('Comitato Locale')
                    ->toggleable()
                    ->default('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('stato_finestra')
                    ->label('Stato')
                    ->toggleable()
                    ->getStateUsing(function (Graduatoria $record) {
                        if (now()->lt($record->data_apertura)) return 'Non ancora aperta';
                        if (now()->gt($record->data_chiusura)) return 'Chiusa';
                        return 'Aperta';
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Aperta'            => 'success',
                        'Non ancora aperta' => 'gray',
                        'Chiusa'            => 'danger',
                    }),

                Tables\Columns\TextColumn::make('candidati_count')
                    ->counts('candidati')
                    ->label('Candidati')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('data_chiusura')
                    ->label('Chiusura')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('comitato_regionale_id')
                    ->label('Regione')
                    ->relationship('comitatoRegionale', 'nome')
                    ->visible(fn () => auth()->user()?->isSuperadmin()),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica'),
                Action::make('gestione')
                    ->iconButton()
                    ->tooltip('Gestione')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->url(fn (Graduatoria $record) => static::getUrl('candidati', ['record' => $record])),
                ActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (Graduatoria $record) => static::canDelete($record))
                        ->disabled(fn (Graduatoria $record) => $record->candidati()->exists())
                        ->tooltip(fn (Graduatoria $record) => $record->candidati()->exists()
                            ? 'Non eliminabile: la graduatoria ha candidati'
                            : null),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: RecordActionsPosition::BeforeColumns)
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'     => Pages\ListGraduatorie::route('/'),
            'create'    => Pages\CreateGraduatoria::route('/create'),
            'edit'      => Pages\EditGraduatoria::route('/{record}/edit'),
            'candidati' => Pages\GestisciCandidati::route('/{record}/candidati'),
        ];
    }
}
