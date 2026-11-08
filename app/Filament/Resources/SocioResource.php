<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualificheSociResource\RelationManagers\QualificheRelationManager;
use App\Filament\Resources\SocioResource\Pages;
use App\Imports\SociImport;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\ComitatoSottosede;
use App\Models\Socio;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SocioResource extends Resource
{
    protected static ?string $model = Socio::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static \UnitEnum|string|null $navigationGroup = 'Archivi';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'soci';

    protected static ?string $modelLabel = 'Socio';

    protected static ?string $pluralModelLabel = 'Soci';

    /** Accede alla sezione chi ha almeno un permesso soci, o è admin/superadmin. */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminLocale()) return true;
        return $user?->hasAnyPermission(['soci.view', 'soci.edit', 'soci.import', 'soci.create'])
            ?? false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('soci.create');
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        if ($user?->isAdmin()) {
            return $record->comitato_regionale_id === $user->comitato_regionale_id
                || $record->comitato_regionale_dipendente_id === $user->comitato_regionale_id;
        }
        if ($user?->isAdminLocale()) {
            return $record->comitato_locale_id === $user->comitato_locale_id
                || $record->comitato_locale_dipendente_id === $user->comitato_locale_id;
        }
        return $user?->can('soci.edit')
            && $record->comitato_regionale_id === $user->comitato_regionale_id;
    }

    /**
     * Gate specifico per la scrittura delle QUALIFICHE del socio dal bridge GAIA (curriculum
     * singolo + batch comitato/graduatoria, incluso il salvataggio di gaia_persona_id che ne è
     * il prerequisito): come canEdit() ma include anche l'appartenenza per ESTENSIONE. Scelta
     * 2026-08-27: in estensione il socio è comunque in parte sotto la direzione del comitato
     * ospitante per la parte formativa, quindi ha senso leggere le qualifiche da GAIA e
     * scriverle; l'anagrafica e i dati salute restano invece gated dal solo canEdit().
     */
    public static function canEditQualifiche(Model $record): bool
    {
        if (static::canEdit($record)) return true;

        $user = auth()->user();
        if ($user?->isAdmin()) {
            return $record->comitato_regionale_estensione_id === $user->comitato_regionale_id;
        }
        if ($user?->isAdminLocale()) {
            return $record->comitato_locale_estensione_id === $user->comitato_locale_id;
        }
        return $user?->can('soci.edit')
            && $record->comitato_regionale_estensione_id === $user->comitato_regionale_id;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        if ($user?->isAdmin()) {
            return $record->comitato_regionale_id === $user->comitato_regionale_id
                || $record->comitato_regionale_dipendente_id === $user->comitato_regionale_id;
        }
        if ($user?->isAdminLocale()) {
            return $record->comitato_locale_id === $user->comitato_locale_id
                || $record->comitato_locale_dipendente_id === $user->comitato_locale_id;
        }
        return false;
    }

    /** Dati sensibili (allergie/diete/note salute): visibili solo a chi ha il permesso esplicito o è admin. */
    public static function canViewSalute(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->can('soci.view_sensitive');
    }

    public static function getRelationManagers(): array
    {
        return [
            QualificheRelationManager::class,
        ];
    }

    public static function canImport(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('soci.import');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScope(SoftDeletingScope::class);
        $user  = auth()->user();

        if ($user?->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('comitato_regionale_id', $user->comitato_regionale_id)
                  ->orWhere('comitato_regionale_dipendente_id', $user->comitato_regionale_id);
            });
        } elseif ($user?->isAdminLocale()) {
            $query->where(function ($q) use ($user) {
                $q->where('comitato_locale_id', $user->comitato_locale_id)
                  ->orWhere('comitato_locale_dipendente_id', $user->comitato_locale_id);
            });
        } elseif ($user?->isOperatore()) {
            $query->where('comitato_regionale_id', $user->comitato_regionale_id);
        }

        return $query;
    }

    public static function form(Schema $form): Schema
    {
        $authUser = auth()->user();

        // Select comitati locali — superadmin vede tutti, admin solo i propri, admin_locale solo il proprio
        $localiQuery = ComitatoLocale::with(['comitatoRegionale', 'sottosedi'])->orderBy('nome');
        if ($authUser?->isAdmin()) {
            $localiQuery->where('comitato_regionale_id', $authUser->comitato_regionale_id);
        } elseif ($authUser?->isAdminLocale()) {
            $localiQuery->where('id', $authUser->comitato_locale_id);
        }
        $locali = $localiQuery->get();

        // Opzioni flat: "l:{id}" → "Nome Comitato" / "s:{id}" → "Nome Comitato: Sottosede"
        $localiOptions = [];
        foreach ($locali as $l) {
            $localiOptions["l:{$l->id}"] = $l->nome;
            foreach ($l->sottosedi as $sub) {
                $localiOptions["s:{$sub->id}"] = $l->nome . ': ' . $sub->nome;
            }
        }

        // Opzioni estensione/dipendente: tutti i comitati (senza scoping)
        $tuttiLocali = ComitatoLocale::with(['comitatoRegionale', 'sottosedi'])->orderBy('nome')->get();
        $tuttiLocaliOptions = [];
        foreach ($tuttiLocali as $l) {
            $tuttiLocaliOptions["l:{$l->id}"] = $l->nome . ' (' . ($l->comitatoRegionale?->codice ?? '?') . ')';
            foreach ($l->sottosedi as $sub) {
                $tuttiLocaliOptions["s:{$sub->id}"] = $l->nome . ': ' . $sub->nome . ' (' . ($l->comitatoRegionale?->codice ?? '?') . ')';
            }
        }

        // Closure riutilizzabile: decodifica "l:{id}" / "s:{id}" e setta i campi correlati
        $afterComitato = function (?string $state, Set $set, string $tipoSottosede, string $tipoLocale, string $tipoRegionale): void {
            if (! $state) {
                $set($tipoLocale, null);
                $set($tipoRegionale, null);
                $set($tipoSottosede, null);
                return;
            }
            if (str_starts_with($state, 's:')) {
                $sub = ComitatoSottosede::with('comitatoLocale')->find((int) substr($state, 2));
                if ($sub) {
                    $set($tipoLocale, $sub->comitato_locale_id);
                    $set($tipoRegionale, $sub->comitatoLocale->comitato_regionale_id);
                    $set($tipoSottosede, $sub->id);
                }
            } else {
                $locale = ComitatoLocale::find((int) substr($state, 2));
                if ($locale) {
                    $set($tipoLocale, $locale->id);
                    $set($tipoRegionale, $locale->comitato_regionale_id);
                    $set($tipoSottosede, null);
                }
            }
        };

        // ->columns(1) sul form: senza, EditRecord::defaultForm() forza columns(2) e le Section
        // finiscono a coppie affiancate (regola layout form).
        return $form->columns(1)->schema([

            // Prima card, sempre aperta: identità + contatti. Sotto-raggruppamenti via Group
            // (2-3 campi corti per riga), mai Section annidate.
            Section::make('Anagrafica e contatti')->schema([
                Group::make([
                    Forms\Components\TextInput::make('cognome')
                        ->label('Cognome')->required()->maxLength(100),
                    Forms\Components\TextInput::make('nome')
                        ->label('Nome')->required()->maxLength(100),
                ])->columns(2),

                Group::make([
                    Forms\Components\TextInput::make('codice_fiscale')
                        ->label('Codice Fiscale')
                        ->required()
                        ->length(16)
                        ->unique(table: 'soci', column: 'codice_fiscale', ignoreRecord: true)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                        ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')->email()->maxLength(255),
                ])->columns(2),

                Group::make([
                    Forms\Components\DatePicker::make('data_nascita')
                        ->label('Data di nascita')->native(false)->displayFormat('d/m/Y'),
                    Forms\Components\DatePicker::make('volontario_da')
                        ->label('Volontario dal')->native(false)->displayFormat('d/m/Y'),
                    Forms\Components\Select::make('ruolo_associativo')
                        ->label('Ruolo associativo')
                        ->options([
                            'volontario'            => 'Volontario',
                            'dipendente'            => 'Dipendente',
                            'infermiere_volontarie' => 'Infermiere Volontarie CRI',
                            'corpo_militare'        => 'Corpo Militare Volontario CRI',
                        ])
                        ->searchable()
                        ->nullable()
                        ->placeholder('Non specificato'),
                ])->columns(3),

                Group::make([
                    Forms\Components\Select::make('prefisso_telefono')
                        ->label('Prefisso')
                        ->options([
                            '+39' => '🇮🇹 +39 — Italia',
                            '+41' => '🇨🇭 +41 — Svizzera',
                            '+33' => '🇫🇷 +33 — Francia',
                            '+34' => '🇪🇸 +34 — Spagna',
                            '+49' => '🇩🇪 +49 — Germania',
                            '+43' => '🇦🇹 +43 — Austria',
                            '+32' => '🇧🇪 +32 — Belgio',
                            '+31' => '🇳🇱 +31 — Paesi Bassi',
                            '+44' => '🇬🇧 +44 — Regno Unito',
                            '+351' => '🇵🇹 +351 — Portogallo',
                            '+352' => '🇱🇺 +352 — Lussemburgo',
                            '+353' => '🇮🇪 +353 — Irlanda',
                            '+355' => '🇦🇱 +355 — Albania',
                            '+385' => '🇭🇷 +385 — Croazia',
                            '+386' => '🇸🇮 +386 — Slovenia',
                            '+380' => '🇺🇦 +380 — Ucraina',
                            '+1'   => '🇺🇸 +1 — USA / Canada',
                        ])
                        ->default('+39')
                        ->searchable(),
                    Forms\Components\TextInput::make('telefono')
                        ->label('Cellulare principale')
                        ->tel()
                        ->maxLength(20)
                        ->columnSpan(2),
                ])->columns(3),

                Forms\Components\TagsInput::make('telefoni_secondari')
                    ->label('Cellulari aggiuntivi')
                    ->helperText('Inserisci o modifica i numeri secondari. Solo cellulari (es. 3331234567).')
                    ->columnSpanFull(),
            ]),

            // Un'unica card "Comitato di riferimento": appartenenza / estensione / rapporto di
            // lavoro sono tutti "quale comitato, con che tipo di legame" — blocchi piccoli, non
            // meritano una Section a testa. Sotto-blocchi con etichetta via Fieldset (Group non
            // ha titolo, Section annidata è vietata dalla regola layout form). Collassata di
            // default: normalmente questi dati arrivano dall'import GAIA, non si toccano a mano.
            Section::make('Comitato di riferimento')
                ->description('Appartenenza come volontario, eventuale estensione presso un altro comitato, ed eventuale rapporto di lavoro.')
                ->collapsed()
                ->schema([
                    Fieldset::make('Appartenenza')->schema([
                        Forms\Components\Select::make('_comitato_appartenenza')
                            ->label('Comitato / Sottosede')
                            ->options($localiOptions)
                            ->default($authUser?->isAdminLocale() ? "l:{$authUser->comitato_locale_id}" : null)
                            ->disabled($authUser?->isAdminLocale())
                            ->dehydrated(false)
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateHydrated(function ($component, $record) {
                                if (! $record) return;
                                if ($record->sottosede_appartenenza_id) {
                                    $component->state("s:{$record->sottosede_appartenenza_id}");
                                } elseif ($record->comitato_locale_id) {
                                    $component->state("l:{$record->comitato_locale_id}");
                                }
                            })
                            ->afterStateUpdated(fn ($state, Set $set) => $afterComitato(
                                $state, $set,
                                'sottosede_appartenenza_id', 'comitato_locale_id', 'comitato_regionale_id'
                            )),

                        Forms\Components\Select::make('comitato_regionale_id')
                            ->label('Comitato Regionale')
                            ->options(ComitatoRegionale::orderBy('nome')->pluck('nome', 'id'))
                            ->default($authUser?->isAdminLocale() ? $authUser->comitato_regionale_id : null)
                            ->searchable()
                            ->nullable()
                            ->disabled(fn (Get $get) => (bool) $get('comitato_locale_id') || $authUser?->isAdminLocale())
                            ->dehydrated(true)
                            ->helperText('Compilato automaticamente dal comitato locale.'),

                        Forms\Components\Hidden::make('comitato_locale_id'),
                        Forms\Components\Hidden::make('sottosede_appartenenza_id'),
                    ])->columns(2),

                    Fieldset::make('Estensione')->schema([
                        Forms\Components\Select::make('_comitato_estensione')
                            ->label('Comitato / Sottosede in Estensione')
                            ->options($tuttiLocaliOptions)
                            ->dehydrated(false)
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->helperText('Solo se il socio è in estensione presso un altro comitato.')
                            ->afterStateHydrated(function ($component, $record) {
                                if (! $record) return;
                                if ($record->sottosede_estensione_id) {
                                    $component->state("s:{$record->sottosede_estensione_id}");
                                } elseif ($record->comitato_locale_estensione_id) {
                                    $component->state("l:{$record->comitato_locale_estensione_id}");
                                }
                            })
                            ->afterStateUpdated(fn ($state, Set $set) => $afterComitato(
                                $state, $set,
                                'sottosede_estensione_id', 'comitato_locale_estensione_id', 'comitato_regionale_estensione_id'
                            )),

                        Forms\Components\Select::make('comitato_regionale_estensione_id')
                            ->label('Comitato Regionale Estensione')
                            ->options(ComitatoRegionale::orderBy('nome')->pluck('nome', 'id'))
                            ->searchable()
                            ->nullable()
                            ->disabled(fn (Get $get) => (bool) $get('comitato_locale_estensione_id'))
                            ->dehydrated(true)
                            ->helperText('Compilato automaticamente.'),

                        Forms\Components\Hidden::make('comitato_locale_estensione_id'),
                        Forms\Components\Hidden::make('sottosede_estensione_id'),
                    ])->columns(2),

                    Fieldset::make('Rapporto di lavoro')->schema([
                        Forms\Components\Select::make('_comitato_dipendente')
                            ->label('Comitato / Sottosede Datore di Lavoro')
                            ->options($tuttiLocaliOptions)
                            ->dehydrated(false)
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->helperText('Solo se ha un contratto di lavoro con un comitato CRI. Imposta il ruolo a "Dipendente".')
                            ->afterStateHydrated(function ($component, $record) {
                                if (! $record) return;
                                if ($record->sottosede_dipendente_id) {
                                    $component->state("s:{$record->sottosede_dipendente_id}");
                                } elseif ($record->comitato_locale_dipendente_id) {
                                    $component->state("l:{$record->comitato_locale_dipendente_id}");
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Set $set) use ($afterComitato) {
                                $afterComitato($state, $set,
                                    'sottosede_dipendente_id', 'comitato_locale_dipendente_id', 'comitato_regionale_dipendente_id'
                                );
                                if ($state) $set('ruolo_associativo', 'dipendente');
                            }),

                        Forms\Components\Select::make('comitato_regionale_dipendente_id')
                            ->label('Comitato Regionale (Lavoro)')
                            ->options(ComitatoRegionale::orderBy('nome')->pluck('nome', 'id'))
                            ->searchable()
                            ->nullable()
                            ->disabled(fn ($get) => (bool) $get('comitato_locale_dipendente_id'))
                            ->dehydrated(true)
                            ->helperText('Compilato automaticamente dal comitato datore di lavoro.'),

                        Forms\Components\Hidden::make('comitato_locale_dipendente_id'),
                        Forms\Components\Hidden::make('sottosede_dipendente_id'),
                    ])->columns(2),
                ]),

            // ⚠️ Sezione "Salute"/alimentazione DISABILITATA nel form di modifica:
            // questi dati devono essere compilati solo dall'interessato o a livello di evento, non
            // corretti a mano da un operatore dalla scheda socio. Codice tenuto (non cancellato):
            // potrebbe servire per un meccanismo futuro. `false` esplicito, non legato al permesso.
            // La visualizzazione read-only resta in ViewSocio ("Gestione pasti in evento").
            Section::make('Salute')->visible(false)->schema([
                Forms\Components\Textarea::make('allergie')
                    ->label('Allergie mediche generali')
                    ->rows(2)
                    ->placeholder('Nessuna')
                    ->helperText('Allergie non alimentari (es. lattice, farmaci, polline…)'),

                Forms\Components\Textarea::make('note_alimentari')
                    ->label('Note alimentari libere')
                    ->rows(2)
                    ->placeholder('Nessuna'),

                Forms\Components\CheckboxList::make('allergeni')
                    ->label('Allergeni alimentari')
                    ->options(\App\Models\OpzioneAlimentare::allergeni()->pluck('etichetta', 'nome'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->helperText('Aggiornato automaticamente dall\'ultimo form compilato.'),

                Forms\Components\CheckboxList::make('diete_intolleranze')
                    ->label('Intolleranze')
                    ->options(\App\Models\OpzioneAlimentare::intolleranze()->pluck('etichetta', 'nome'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->afterStateHydrated(fn ($component, $record) => $component->state(array_values(array_intersect(
                        $record?->diete ?? [],
                        \App\Models\OpzioneAlimentare::intolleranze()->pluck('nome')->all()
                    ))))
                    ->helperText('Aggiornato automaticamente dall\'ultimo form compilato.'),

                Forms\Components\CheckboxList::make('diete')
                    ->label('Diete')
                    ->options(\App\Models\OpzioneAlimentare::diete()->pluck('etichetta', 'nome'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->helperText('Aggiornato automaticamente dall\'ultimo form compilato.'),

                Forms\Components\CheckboxList::make('diete_esclusioni')
                    ->label('Esclusioni alimentari')
                    ->options(\App\Models\OpzioneAlimentare::esclusioni()->pluck('etichetta', 'nome'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn ($component, $record) => $component->state(array_values(array_intersect(
                        $record?->diete ?? [],
                        \App\Models\OpzioneAlimentare::esclusioni()->pluck('nome')->all()
                    ))))
                    ->helperText('Aggiornato automaticamente dall\'ultimo form compilato.'),
            ])->columns(2),

            Section::make('Patenti CRI')->collapsed()->schema([
                Forms\Components\CheckboxList::make('patenti')
                    ->hiddenLabel()
                    ->relationship('patenti', 'tipologia')
                    ->getOptionLabelFromRecordUsing(fn ($record) => 'Tipo ' . $record->tipologia . ' — ' . $record->descrizione)
                    ->options(
                        \App\Models\PatenteCri::attive()->get()
                            ->mapWithKeys(fn ($p) => [$p->id => 'Tipo ' . $p->tipologia . ' — ' . $p->descrizione])
                    )
                    ->columns(1)
                    ->columnSpanFull()
                    ->helperText('Di norma importate dal curriculum GAIA. Modificabili qui a mano se serve — le date/protocollo restano quelle dell\'ultimo import.'),
            ]),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                // Fallback per superadmin: i soci vanno sempre importati da GAIA (dati
                // ufficiali, formato garantito) — la creazione manuale resta disponibile solo
                // come estrema ratio (es. correggere un caso singolo), non come via normale.
                CreateAction::make()
                    ->label('Nuovo')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isSuperadmin()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('cognome')
                    ->label('Cognome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('codice_fiscale')
                    ->label('C.F.')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('CF copiato')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Telefono')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $state) return '—';
                        $prefisso = $record->prefisso_telefono ?? '+39';
                        $primario = $prefisso . ' ' . preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1 $2 $3', $state);
                        $secondari = $record->telefoni_secondari ?? [];
                        if (count($secondari) === 0) return $primario;
                        $altriTxt = count($secondari) === 1 ? '+1 altro' : '+' . count($secondari) . ' altri';
                        return $primario . ' (' . $altriTxt . ')';
                    })
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('comitato_display')
                    ->label('Comitato Locale')
                    ->getStateUsing(fn ($record) => $record->comitato_display ?? '—')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(function ($q) use ($search) {
                        $q->whereHas('comitatoLocale', fn ($q) => $q->where('nome', 'ilike', "%{$search}%"))
                          ->orWhereHas('sottosedeAppartenenza', fn ($q) => $q->where('nome', 'ilike', "%{$search}%"));
                    }))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->leftJoin(
                        'comitati_locali', 'comitati_locali.id', '=', 'soci.comitato_locale_id'
                    )->orderBy('comitati_locali.nome', $direction)),

                Tables\Columns\TextColumn::make('comitatoRegionale.codice')
                    ->label('Regione')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isSuperadmin()),

                Tables\Columns\TextColumn::make('comitatoLocaleEstensione.nome')
                    ->label('Estensione')
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('comitato_lavoro')
                    ->label('Comitato Lavoro')
                    ->getStateUsing(fn ($record) => $record->comitatoLocaleDipendente?->nome
                        ?? $record->comitatoRegionaleDipendente?->nome)
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('data_nascita')
                    ->label('Data di nascita')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('eta_attuale')
                    ->label('Età')
                    ->state(fn (Socio $record) => $record->data_nascita ? (int) $record->data_nascita->diffInYears(now()) : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('data_nascita', $direction === 'asc' ? 'desc' : 'asc'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ruolo_associativo')
                    ->label('Ruolo')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'volontario'            => 'Volontario',
                        'dipendente'            => 'Dipendente',
                        'infermiere_volontarie' => 'Inf. Volontarie',
                        'corpo_militare'        => 'Corpo Militare',
                        default                 => '—',
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'volontario'            => 'success',
                        'dipendente'            => 'info',
                        'infermiere_volontarie' => 'warning',
                        'corpo_militare'        => 'danger',
                        default                 => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('volontario_da')
                    ->label('Volontario dal')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('dimesso_il')
                    ->label('Dimesso il')
                    ->date('d/m/Y')
                    ->badge()
                    ->color('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminato il')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // I filtri vivono ora nel form di ricerca dedicato di ListSoci (stesso schema di
            // ListQualificheSoci) invece che come filtri Filament inline sulla tabella — vedi
            // app/Services/SociQueryBuilder.php.
            ->actions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip('Visualizza')
                    ->color('info'),
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->visible(fn (Model $record) => static::canEdit($record)),
                ActionGroup::make([
                    RestoreAction::make(),
                    DeleteAction::make()
                        ->visible(fn (Model $record) => static::canDelete($record))
                        ->requiresConfirmation()
                        ->modalHeading('Elimina socio')
                        ->modalDescription('Il socio verrà eliminato. I dati correlati verranno preservati. Continuare?')
                        ->modalSubmitActionLabel('Sì, elimina'),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->defaultSort('cognome');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSoci::route('/'),
            'create' => Pages\CreateSocio::route('/create'),
            'view'   => Pages\ViewSocio::route('/{record}'),
            'edit'   => Pages\EditSocio::route('/{record}/edit'),
        ];
    }
}
