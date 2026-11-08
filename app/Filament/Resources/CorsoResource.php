<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CorsoResource\Pages;
use App\Filament\Resources\CorsoResource\RelationManagers\IscrizioniRelationManager;
use App\Imports\IscrittiImport;
use App\Models\ComitatoRegionale;
use App\Models\Corso;
use App\Models\Evento;
use App\Models\Graduatoria;
use App\Models\Presenza;
use App\Models\Socio;
use App\Models\RispostaPreregistrazione;
use App\Services\FormSectionsGenerator;
use Filament\Actions\Action as FilamentAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class CorsoResource extends Resource
{
    protected static ?string $model = Corso::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestione operativa';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'corsi';

    protected static ?string $modelLabel = 'Corso';

    protected static ?string $pluralModelLabel = 'Corsi';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('corsi.view')
            || static::eDirettoreDiQualcheCorso($user);
    }

    /**
     * Esami: chi è direttore di un corso (qualsiasi ruolo, via users.socio_id) vede quel corso
     * e la sua pagina Gestione, anche senza permessi sui corsi. Non ottiene la modifica del corso
     * (canEdit/canDelete restano invariati). Audit dei permessi: rimandato (scelta di progetto).
     */
    public static function eDirettoreDiQualcheCorso(?\App\Models\User $user): bool
    {
        $socioId = \App\Services\SocioCorrente::perUtente($user);

        return $socioId && Corso::where('direttore_socio_id', $socioId)->exists();
    }

    public static function eDirettoreDelCorso(?\App\Models\User $user, Corso $corso): bool
    {
        $socioId = \App\Services\SocioCorrente::perUtente($user);

        return $socioId && $corso->direttore_socio_id === $socioId;
    }

    /**
     * Accesso alle pagine Gestione del corso (pagine custom: nessuno scope automatico).
     * Chi aveva accesso prima lo conserva identico; il solo caso nuovo (direttore senza permessi
     * sui corsi) è limitato ai corsi che dirige.
     */
    public static function puoGestireCorso(Corso $corso): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminLocale() || $user?->can('corsi.view')) {
            return true;
        }

        return static::eDirettoreDelCorso($user, $corso);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('corsi.create');
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        if ($user?->isAdmin()) {
            return $record->evento?->comitato_regionale_id === $user->comitato_regionale_id;
        }
        if ($user?->isAdminLocale()) {
            return $record->evento?->comitato_locale_id === $user->comitato_locale_id;
        }
        return $user?->can('corsi.edit')
            && $record->evento?->operatori->contains($user->id);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        if ($user?->isAdmin()) {
            return $record->evento?->comitato_regionale_id === $user->comitato_regionale_id;
        }
        if ($user?->isAdminLocale()) {
            return $record->evento?->comitato_locale_id === $user->comitato_locale_id;
        }
        return false;
    }

    /** Scope: admin vede solo i corsi degli eventi della propria regione. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('evento.operatori');
        $user  = auth()->user();

        // Il direttore vede sempre i corsi che dirige, in aggiunta al resto dello scope del ruolo.
        $socioId       = \App\Services\SocioCorrente::perUtente($user);
        $soloDirezione = fn ($q) => $socioId
            ? $q->orWhere('direttore_socio_id', $socioId)
            : $q;

        if ($user?->isAdmin()) {
            $query->where(fn ($q) => $soloDirezione($q->whereHas('evento', fn ($e) => $e->where('comitato_regionale_id', $user->comitato_regionale_id))));
        } elseif ($user?->isAdminLocale()) {
            $query->where(fn ($q) => $soloDirezione($q->whereHas('evento', fn ($e) => $e->where('comitato_locale_id', $user->comitato_locale_id))));
        } elseif ($user?->isOperatore()) {
            $query->where(function ($q) use ($user, $soloDirezione) {
                if ($user->can('corsi.view')) {
                    $q->whereHas('evento.operatori', fn ($e) => $e->where('users.id', $user->id));
                } else {
                    $q->whereRaw('1 = 0');
                }
                $soloDirezione($q);
            });
        }

        return $query;
    }

    public static function form(Schema $form): Schema
    {
        $authUser = auth()->user();

        // Query eventi filtrata per ruolo
        $eventiOptions = function () use ($authUser) {
            $eventiQuery = Evento::query()->orderBy('data_inizio', 'desc');
            if ($authUser?->isAdmin()) {
                $eventiQuery->where('comitato_regionale_id', $authUser->comitato_regionale_id);
            } elseif ($authUser?->isAdminLocale()) {
                $eventiQuery->where('comitato_locale_id', $authUser->comitato_locale_id);
            } elseif ($authUser?->isOperatore()) {
                $eventiQuery->whereHas('operatori', fn ($q) => $q->where('users.id', $authUser->id));
            }
            return $eventiQuery->get()->mapWithKeys(fn ($e) => [
                $e->id => $e->nome . ' (' . $e->data_inizio?->format('d/m/Y') . ')',
            ])->toArray();
        };

        return $form->schema([

            Section::make('Informazioni corso')->schema([
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

                Forms\Components\Select::make('evento_id')
                    ->label('Evento')
                    ->options($eventiOptions)
                    ->getOptionLabelUsing(fn ($value) => Evento::find($value)?->nome)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),

                SchemaActions::make([
                    FilamentAction::make('crea_evento_singolo')
                        ->label('Crea evento singolo per questo corso')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->modalHeading('Crea evento singolo')
                        ->modalDescription('Crea un evento dedicato a questo corso con sede e indirizzo da completare in seguito. Utile per corsi autonomi che necessitano comunque di gestione pasti/pernotti/presenze.')
                        ->schema([
                            ...EventoResource::giorniEventoSchema(),
                            Forms\Components\Select::make('comitato_regionale_id')
                                ->label('Comitato Regionale')
                                ->options(ComitatoRegionale::orderBy('nome')->pluck('nome', 'id'))
                                ->required()
                                ->searchable()
                                ->visible(fn () => $authUser?->isSuperadmin()),
                        ])
                        ->fillForm(fn (Get $get) => [
                            // il range del corso precompila l'intervallo: un click per aggiungerlo
                            'intervallo_da' => $get('data_inizio'),
                            'intervallo_a'  => $get('data_fine'),
                        ])
                        ->action(function (array $data, Get $get, Set $set) use ($authUser) {
                            // data_inizio/data_fine e ordinamento dei giorni li ricava Evento::saving
                            $evento = Evento::create([
                                'nome'                  => $get('numero_protocollo') ?: $get('nome') ?: 'Evento corso',
                                'sede'                  => 'Da definire',
                                'indirizzo'             => 'Da definire',
                                'giorni_evento'         => $data['giorni_evento'],
                                'comitato_regionale_id' => $authUser?->isSuperadmin()
                                    ? $data['comitato_regionale_id']
                                    : $authUser?->comitato_regionale_id,
                            ]);

                            $set('evento_id', $evento->id);
                            $set('giorni_presenza', $evento->giorni_evento);

                            Notification::make()
                                ->title('Evento creato: ' . $evento->nome)
                                ->body('Ricorda di completare sede e indirizzo dalla pagina dell\'evento.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (?Model $record, Get $get) => $record === null && blank($get('evento_id'))),
                ])->columnSpanFull(),

                Forms\Components\TextInput::make('nome')
                    ->label('Nome corso')
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 400)
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        if (! $state) return;
                        // Riconosce il protocollo in coda: REGIONE/ANNO/CODICE/NUMERO
                        if (preg_match('/^(.+?)\s+([A-Z]{2,6}\/\d{4}\/[^\/]+\/\d+)\s*$/u', trim($state), $m)) {
                            $set('nome', trim($m[1]));
                            $set('numero_protocollo', trim($m[2]));
                        }
                    }),

                Forms\Components\TextInput::make('numero_protocollo')
                    ->label('N. protocollo attivazione')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Es. TOS/2026/TLC 1/3935'),

                Forms\Components\Textarea::make('descrizione')
                    ->label('Descrizione')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Select::make('direttore_socio_id')
                    ->label('Direttore del corso (cerca tra i soci)')
                    ->helperText('Scrivi almeno ' . Socio::RICERCA_MIN_CARATTERI . ' caratteri di nome, cognome o codice fiscale.')
                    ->searchable()
                    ->searchPrompt('Scrivi almeno ' . Socio::RICERCA_MIN_CARATTERI . ' caratteri')
                    ->getSearchResultsUsing(fn (string $search) => Socio::cercaPerSelezione($search))
                    ->getOptionLabelUsing(fn ($value) => Socio::find($value)?->etichettaSelezione())
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $state
                        ? $set('direttore_corso', Socio::find($state)?->nome_completo)
                        : null)
                    // Obbligatorio solo nei corsi nuovi: un corso vecchio con il solo testo si salva com'è.
                    ->required(fn (string $operation) => $operation === 'create'),

                // Testo storico letto da liste e altre funzioni: non si scrive a mano, lo valorizza il socio scelto.
                Forms\Components\TextInput::make('direttore_corso')
                    ->label('Direttore del corso')
                    ->readOnly()
                    ->maxLength(255),

                Forms\Components\Select::make('presidente_socio_id')
                    ->label('Presidente o delegato (facoltativo)')
                    ->helperText('Serve alla scheda d\'esame; si può indicare anche dopo.')
                    ->searchable()
                    ->searchPrompt('Scrivi almeno ' . Socio::RICERCA_MIN_CARATTERI . ' caratteri')
                    ->getSearchResultsUsing(fn (string $search) => Socio::cercaPerSelezione($search))
                    ->getOptionLabelUsing(fn ($value) => Socio::find($value)?->etichettaSelezione())
                    ->nullable(),

                Forms\Components\Select::make('catalogo_corso_id')
                    ->label('Tipo di corso (catalogo)')
                    ->helperText('Facoltativo. Collega il corso al suo tipo (es. TCRI): il codice di riferimento arriva da qui, ad esempio per creare l\'esame.')
                    ->searchable()
                    ->options(fn () => \App\Models\CatalogoCorso::attivo()->orderBy('nome')->get()
                        ->mapWithKeys(fn ($c) => [$c->id => ($c->nome_visibile ?: $c->nome) . ' (' . $c->codice . ')'])
                        ->all())
                    ->nullable(),

                Forms\Components\Select::make('docenti')
                    ->label('Docenti del corso (facoltativi)')
                    ->multiple()
                    ->relationship('docenti', 'cognome')
                    ->searchable()
                    ->searchPrompt('Scrivi almeno ' . Socio::RICERCA_MIN_CARATTERI . ' caratteri')
                    // Dopo relationship(): sovrascrive la ricerca di default (globale sui soci, con soglia).
                    ->getSearchResultsUsing(fn (string $search) => Socio::cercaPerSelezione($search))
                    ->getOptionLabelsUsing(fn (array $values) => Socio::whereIn('id', $values)->get()
                        ->mapWithKeys(fn (Socio $s) => [$s->id => $s->etichettaSelezione()])->all())
                    ->rules([fn (?Model $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        $rimossi = $record?->docentiNonRimovibili()->diff(collect($value ?? [])->map(fn ($v) => (int) $v));
                        if ($rimossi?->isNotEmpty()) {
                            $nomi = Socio::whereIn('id', $rimossi)->get()->pluck('nome_completo')->join(', ');
                            $fail("Non puoi togliere dal corso: {$nomi}. Ha già schede compilate o fa parte della commissione d'esame.");
                        }
                    }])
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('link_gaia')
                    ->label('Link corso su GAIA')
                    ->placeholder('https://gaia.cri.it/...')
                    ->url()
                    ->maxLength(500)
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('link_gaia_apri')
                    ->hiddenLabel()
                    ->content(fn ($record) => $record && filled($record->link_gaia)
                        ? new \Illuminate\Support\HtmlString(
                            '<a href="' . e($record->link_gaia) . '" target="_blank" style="color:#2563eb;text-decoration:underline;font-size:13px;">↗ Apri corso su GAIA</a>'
                        )
                        : null
                    )
                    ->visible(fn ($record) => $record && filled($record->link_gaia))
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('scadenza_domande')
                    ->label('Scadenza domande')
                    ->seconds(false)
                    ->helperText('Se il corso ha una graduatoria collegata, la data di chiusura iscrizioni resta sempre allineata a questa.')
                    ->columnSpanFull(),
            ])->columns(2),

            Group::make([
                Section::make('Giorni in presenza al campo')
                    ->description('Date in cui il corso svolge attività in presenza presso il campo di questo evento.')
                    ->schema([
                        Forms\Components\DatePicker::make('data_inizio')
                            ->label('Prima giornata')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->live(onBlur: true),

                        Forms\Components\DatePicker::make('data_fine')
                            ->label('Ultima giornata')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('data_inizio')
                            ->live(onBlur: true),

                        Forms\Components\TimePicker::make('ora_inizio')
                            ->label('Ora inizio')
                            ->required()
                            ->seconds(false)
                            ->live(onBlur: true),

                        Forms\Components\TimePicker::make('ora_fine')
                            ->label('Ora fine')
                            ->required()
                            ->seconds(false)
                            ->live(onBlur: true),
                    ])->columns(2),

                Section::make('Automazioni')
                    ->description('Genera rapidamente le entità correlate a questo corso, usando i dati già inseriti.')
                    ->visible(fn (?Model $record) => $record !== null)
                    ->schema([
                        SchemaActions::make([
                            FilamentAction::make('crea_graduatoria')
                                ->label('Crea Graduatoria per il corso')
                                ->icon('heroicon-o-list-bullet')
                                ->color('success')
                                ->requiresConfirmation()
                                ->modalDescription('Genera una graduatoria collegata a questo corso con titolo, protocollo e comitato già compilati. Data apertura/chiusura, testo email e operatori si configurano dopo dalla pagina dedicata.')
                                ->visible(fn (?Model $record) => $record !== null && $record->graduatorie()->doesntExist())
                                ->action(function (Corso $record) {
                                    $chiusura = $record->scadenza_domande
                                        ?? ($record->data_inizio && $record->data_inizio->isFuture()
                                            ? $record->data_inizio
                                            : now()->addDays(30));

                                    try {
                                        Graduatoria::create([
                                            'titolo'                => $record->nome,
                                            'codice_protocollo'     => $record->numero_protocollo,
                                            'corso_id'               => $record->id,
                                            'comitato_regionale_id' => $record->evento->comitato_regionale_id,
                                            'comitato_locale_id'    => $record->evento->comitato_locale_id,
                                            'data_apertura'          => now(),
                                            'data_chiusura'          => $chiusura,
                                        ]);
                                    } catch (\Illuminate\Database\QueryException $e) {
                                        Notification::make()
                                            ->title('Impossibile creare la graduatoria')
                                            ->body('Esiste già una graduatoria con protocollo "' . $record->numero_protocollo . '". Modifica il protocollo o crea la graduatoria manualmente da Graduatorie → Nuovo.')
                                            ->danger()
                                            ->send();
                                        return;
                                    }

                                    Notification::make()
                                        ->title('Graduatoria creata')
                                        ->body('Configura date, testo email e operatori dalla pagina dedicata.')
                                        ->success()
                                        ->send();
                                }),
                        ]),

                        Forms\Components\Placeholder::make('graduatorie_esistenti')
                            ->hiddenLabel()
                            ->content(function (?Model $record) {
                                if (! $record) return null;
                                $graduatorie = $record->graduatorie;
                                if ($graduatorie->isEmpty()) return null;

                                $html = '<div style="display:flex;flex-direction:column;gap:6px;">';
                                foreach ($graduatorie as $g) {
                                    $url = \App\Filament\Resources\GraduatoriaResource::getUrl('edit', ['record' => $g]);
                                    $html .= '<a href="' . e($url) . '" style="color:#2563eb;text-decoration:underline;font-size:13px;">↗ Vai alla graduatoria: ' . e($g->titolo) . '</a>';
                                }
                                $html .= '</div>';
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->visible(fn (?Model $record) => $record !== null && $record->graduatorie()->exists()),
                    ]),
            ]),

            Section::make('Configurazione')->schema([
                Forms\Components\Toggle::make('residenziale')
                    ->label('Corso residenziale (pernotto previsto)')
                    ->default(false)
                    ->live()
                    ->inline(false)
                    ->disabled(fn (?Model $record) =>
                        $record && $record->iscrizioni()->whereHas('rispostaPreregistrazione')->exists()
                    ),

                Forms\Components\Toggle::make('pagamento_previsto')
                    ->label('Richiede pagamento')
                    ->default(false)
                    ->helperText('Se attivo, nel form di preregistrazione apparirà la sezione di upload ricevuta pagamento.')
                    ->inline(false)
                    ->live()
                    ->disabled(fn (?Model $record) =>
                        $record && $record->iscrizioni()->whereHas('rispostaPreregistrazione')->exists()
                    ),

                Forms\Components\TextInput::make('quota_partecipazione')
                    ->label('Quota di partecipazione (€)')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->placeholder('es. 25.00')
                    ->prefix('€')
                    ->visible(fn (Get $get) => (bool) $get('pagamento_previsto'))
                    ->required(fn (Get $get) => (bool) $get('pagamento_previsto')),

                // A differenza di "Richiede pagamento" resta modificabile anche a form già compilati:
                // chi ha già compilato carica il documento dal link/PWA.
                Forms\Components\Toggle::make('richiede_documento_aggiuntivo')
                    ->label('Richiedi un documento PDF aggiuntivo')
                    ->default(false)
                    ->helperText('Se attivo, nel form di preregistrazione compare un upload PDF obbligatorio (es. titolo di studio, curriculum). Si può attivare anche dopo che alcuni iscritti hanno già compilato: potranno caricarlo dal link di conferma.')
                    ->inline(false)
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('dicitura_documento_aggiuntivo')
                    ->label('Dicitura da mostrare sul campo di upload')
                    ->helperText('Testo mostrato al discente accanto al campo di caricamento: scrivi quale documento ti aspetti (es. «Titolo di studio» o «Curriculum vitae»).')
                    ->rows(2)
                    ->maxLength(500)
                    ->visible(fn (Get $get) => (bool) $get('richiede_documento_aggiuntivo'))
                    ->required(fn (Get $get) => (bool) $get('richiede_documento_aggiuntivo'))
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Giorni in presenza e pasti')
                ->description('Spunta i giorni in cui questo corso è al campo dell\'evento: pranzi, cene e pernotti del form di preregistrazione si generano da soli in base a questi giorni e al flag "residenziale".')
                ->schema([
                    Forms\Components\ToggleButtons::make('giorni_presenza')
                        ->label('Giorni in presenza da gestire sull\'evento')
                        ->multiple()
                        ->grouped()
                        ->options(function (Get $get) {
                            $evento = ($id = $get('evento_id')) ? Evento::find($id) : null;

                            return collect($evento?->giorni_evento ?? [])->sort()
                                ->mapWithKeys(fn ($d) => [$d => \Carbon\Carbon::parse($d)->translatedFormat('D j F')])
                                ->all();
                        })
                        ->colors(fn (Get $get) => collect($get('giorni_presenza') ?? [])
                            ->mapWithKeys(fn ($d) => [$d => 'success'])->all())
                        ->icons(fn (Get $get) => collect($get('giorni_presenza') ?? [])
                            ->mapWithKeys(fn ($d) => [$d => 'heroicon-o-check-circle'])->all())
                        ->helperText(function (Get $get, ?Model $record) {
                            if ($record && $record->haRisposte()) {
                                return 'Form già compilato da almeno un iscritto: i giorni sono bloccati. Per modificarli azzera prima tutte le risposte (solo superadmin).';
                            }

                            return $get('evento_id')
                                ? 'Ogni pillola è un giorno dell\'evento: seleziona quelli in cui questo corso è al campo.'
                                : 'Seleziona prima un evento.';
                        })
                        ->live()
                        ->disabled(fn (?Model $record) => $record && $record->haRisposte()),

                    Forms\Components\Placeholder::make('anteprima_form_sections')
                        ->label('Voci pasto che verranno generate')
                        ->content(function (Get $get) {
                            $sezioni = app(FormSectionsGenerator::class)->calcola(new Corso([
                                'giorni_presenza' => $get('giorni_presenza') ?: [],
                                'residenziale'    => (bool) $get('residenziale'),
                            ]));

                            if (empty($sezioni)) {
                                return new \Illuminate\Support\HtmlString('<span style="color:#6b7280;">Nessuna voce — seleziona almeno un giorno.</span>');
                            }

                            $rows = collect($sezioni)->map(fn ($s) => '<li>' . e($s['label']) . '</li>')->implode('');

                            return new \Illuminate\Support\HtmlString('<ul style="margin:0;padding-left:18px;line-height:1.7;">' . $rows . '</ul>');
                        }),

                    SchemaActions::make([
                        FilamentAction::make('azzera_risposte')
                            ->label('Azzera tutte le risposte')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->visible(fn (?Model $record) =>
                                auth()->user()?->isSuperadmin()
                                && $record
                                && $record->haRisposte()
                            )
                            ->requiresConfirmation()
                            ->modalHeading('Azzera tutte le risposte')
                            ->modalDescription(fn (?Model $record) =>
                                'Verranno eliminate definitivamente tutte le risposte al form di preregistrazione per il corso "'
                                . ($record?->nome ?? '')
                                . '", comprese le eventuali ricevute di pagamento e i nullaosta e i documenti aggiuntivi allegati (i file vengono cancellati dal disco) e le presenze già registrate da questi form. Gli iscritti dovranno ricompilare il form da capo. Questa operazione è irreversibile.'
                            )
                            ->modalSubmitActionLabel('Sì, azzera tutto')
                            ->action(function (?Model $record) {
                                if (! $record) return;

                                // ⚠️ Fix 2026-09-23: prima scriveva stato=>'iscritto', valore
                                // inesistente nel CHECK constraint di iscrizioni.stato — l'update
                                // falliva DOPO che le risposte erano già state cancellate (nessuna
                                // transazione), lasciando stato incoerente (form cancellato ma
                                // stato ancora 'form_completato') + file/presenze orfani. Ora:
                                // transazione atomica, stato valido 'pending' (stesso usato da
                                // GestioneCorso::resetFormIscrittoAction per il reset singolo),
                                // pulizia file fisici e presenze collegate.
                                \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                    $iscrizioniIds = $record->iscrizioni()->pluck('id');

                                    $risposte = RispostaPreregistrazione::where('iscrizioneable_type', \App\Models\Iscrizione::class)
                                        ->whereIn('iscrizioneable_id', $iscrizioniIds)
                                        ->get();

                                    foreach ($risposte as $risposta) {
                                        if ($risposta->ricevuta_path) {
                                            Storage::disk('local')->delete($risposta->ricevuta_path);
                                        }
                                        if ($risposta->nullaosta_path) {
                                            Storage::disk('local')->delete($risposta->nullaosta_path);
                                        }
                                        if ($risposta->documento_aggiuntivo_path) {
                                            Storage::disk('local')->delete($risposta->documento_aggiuntivo_path);
                                        }
                                    }

                                    Presenza::whereIn('iscrizione_id', $iscrizioniIds)->delete();

                                    RispostaPreregistrazione::where('iscrizioneable_type', \App\Models\Iscrizione::class)
                                        ->whereIn('iscrizioneable_id', $iscrizioniIds)
                                        ->delete();

                                    $record->iscrizioni()->update(['stato' => 'pending']);
                                });

                                Notification::make()
                                    ->title('Risposte azzerate — le voci pasto verranno rigenerate al prossimo salvataggio.')
                                    ->success()
                                    ->send();
                            }),
                    ]),
                ]),

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
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50)
                    ->extraAttributes(fn ($record) => [
                        'data-row-tooltip' => $record->nome . ($record->numero_protocollo ? ' — ' . $record->numero_protocollo : ''),
                    ]),

                Tables\Columns\TextColumn::make('numero_protocollo')
                    ->label('Protocollo')
                    ->searchable()
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->copyable(),

                Tables\Columns\TextColumn::make('annullato_at')
                    ->label('Stato')
                    ->badge()
                    ->state(fn (Corso $record) => $record->isAnnullato() ? 'Annullato' : 'Attivo')
                    ->color(fn (Corso $record) => $record->isAnnullato() ? 'danger' : 'gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('evento.nome')
                    ->label('Evento')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('data_inizio')
                    ->label('Dal')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('data_fine')
                    ->label('Al')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('direttore_corso')
                    ->label('Direttore')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('residenziale')
                    ->boolean()
                    ->label('Residenziale')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('pagamento_previsto')
                    ->boolean()
                    ->label('Pagamento')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('iscrizioni_attive_count')
                    ->counts('iscrizioniAttive')
                    ->label('Iscritti')
                    ->sortable(),

                Tables\Columns\TextColumn::make('form_sections')
                    ->label('Form')
                    ->badge()
                    ->state(fn (Corso $record) => $record->form_sections && count($record->form_sections) > 0
                        ? 'Generato'
                        : 'Non generato')
                    ->color(fn (string $state) => $state === 'Generato' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('iscrizioni_attive_con_risposta_count')
                    ->counts('iscrizioniAttiveConRisposta')
                    ->label('Risposte')
                    ->state(fn (Corso $record) => ($record->iscrizioni_attive_con_risposta_count ?? 0)
                        . ' / '
                        . ($record->iscrizioni_attive_count ?? 0))
                    ->badge()
                    ->color(fn (Corso $record) => match (true) {
                        ($record->iscrizioni_attive_count ?? 0) === 0                                                        => 'gray',
                        ($record->iscrizioni_attive_con_risposta_count ?? 0) === ($record->iscrizioni_attive_count ?? 0)     => 'success',
                        ($record->iscrizioni_attive_con_risposta_count ?? 0) > 0                                             => 'warning',
                        default                                                                                               => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('evento_id')
                    ->label('Evento')
                    ->relationship('evento', 'nome')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('residenziale')
                    ->label('Residenziale'),

                Tables\Filters\TernaryFilter::make('pagamento_previsto')
                    ->label('Con pagamento'),

                Tables\Filters\TernaryFilter::make('annullato')
                    ->label('Annullato')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('annullato_at'),
                        false: fn (Builder $query) => $query->whereNull('annullato_at'),
                    ),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica'),

                FilamentAction::make('gestione_quote')
                    ->label('Gestione quote')
                    ->tooltip(fn (Corso $record) => $record->pagamento_previsto
                        ? 'Gestione quote'
                        : 'Nessun pagamento previsto per questo corso')
                    ->icon('heroicon-o-banknotes')
                    ->color(fn (Corso $record) => $record->pagamento_previsto ? 'info' : 'gray')
                    ->iconButton()
                    ->url(fn (Corso $record) => $record->pagamento_previsto
                        ? static::getUrl('gestione-quote', ['record' => $record])
                        : null)
                    ->disabled(fn (Corso $record) => ! $record->pagamento_previsto),

                FilamentAction::make('gaia')
                    ->label('GAIA')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color(fn (Corso $record) => filled($record->link_gaia) ? 'info' : 'gray')
                    ->tooltip(fn (Corso $record) => filled($record->link_gaia) ? null : 'Nessun link GAIA collegato')
                    ->url(fn (Corso $record) => $record->link_gaia ?: null)
                    ->openUrlInNewTab()
                    ->disabled(fn (Corso $record) => blank($record->link_gaia)),

                \Filament\Actions\ActionGroup::make([
                    DeleteAction::make()
                        ->disabled(fn (Corso $record) => $record->iscrizioni()->exists())
                        ->tooltip(fn (Corso $record) => $record->iscrizioni()->exists()
                            ? 'Non eliminabile: il corso ha iscritti'
                            : null)
                        ->modalDescription(fn (Corso $record) => 'Stai per eliminare: ' . $record->nome . ($record->numero_protocollo ? ' — ' . $record->numero_protocollo : ''))
                        ->before(function (DeleteAction $action, Corso $record) {
                            if ($record->hasDatiCorrelati()) {
                                Notification::make()
                                    ->title('Eliminazione non consentita')
                                    ->body('Questo corso ha dati compilati (form di preregistrazione, consensi o checkin). Per eliminarlo definitivamente contatta un amministratore di sistema.')
                                    ->danger()
                                    ->persistent()
                                    ->send();
                                $action->halt();
                            }
                        }),

                    FilamentAction::make('annulla_corso')
                        ->label('Annulla corso')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Corso $record) => ! $record->isAnnullato())
                        ->requiresConfirmation()
                        ->modalHeading('Annullare il corso?')
                        ->modalDescription(fn (Corso $record) => 'Il corso "' . $record->nome . '" verrà escluso dai conteggi previsti (pasti/pernotti) dell\'evento. Iscrizioni, form e pagamenti restano invariati e "Gestione quote" continuerà a funzionare normalmente. L\'operazione è reversibile.')
                        ->modalSubmitActionLabel('Annulla corso')
                        ->action(fn (Corso $record) => $record->update(['annullato_at' => now()])),

                    FilamentAction::make('ripristina_corso')
                        ->label('Ripristina corso')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->visible(fn (Corso $record) => $record->isAnnullato())
                        ->requiresConfirmation()
                        ->modalHeading('Ripristinare il corso?')
                        ->modalDescription('Il corso tornerà a essere conteggiato normalmente nei previsti dell\'evento.')
                        ->modalSubmitActionLabel('Ripristina')
                        ->action(fn (Corso $record) => $record->update(['annullato_at' => null])),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->recordUrl(fn (Corso $record) => static::getUrl('gestione', ['record' => $record]))
            ->defaultSort('data_inizio', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            IscrizioniRelationManager::class,
        ];
    }

    /**
     * Tab bar (Top) per le pagine legate allo stesso corso — stesso pattern di
     * EventoResource::getRecordSubNavigation(), convenzione
     * "Navigazione a pagine legate allo stesso record".
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        $routeBase = static::getRouteBaseName();
        $record    = $page->getRecord();

        $tab = fn (string $route, string $label, string $icon) => NavigationItem::make($label)
            ->icon($icon)
            ->url(static::getUrl($route, ['record' => $record]))
            ->isActiveWhen(fn () => request()->routeIs("{$routeBase}.{$route}"));

        $tabs = [
            $tab('gestione', 'Gestione', 'heroicon-o-chart-bar'),
        ];

        if ($record->pagamento_previsto) {
            $tabs[] = $tab('gestione-quote', 'Gestione quote', 'heroicon-o-banknotes');
        }

        // Un solo tab ("Gestione") punterebbe alla pagina corrente — niente da navigare,
        // stessa regola già applicata alle breadcrumb (mai un livello autoreferenziale).
        return count($tabs) > 1 ? $tabs : [];
    }

    public static function getPages(): array
    {
        return [
            'index'    => Pages\ListCorsi::route('/'),
            'create'   => Pages\CreateCorso::route('/create'),
            'edit'     => Pages\EditCorso::route('/{record}/edit'),
            'gestione'       => Pages\GestioneCorso::route('/{record}/gestione'),
            'gestione-quote' => Pages\GestioneQuote::route('/{record}/gestione-quote'),
        ];
    }
}
