<?php

namespace App\Filament\Resources\QualificheSociResource\Pages;

use App\Filament\Resources\QualificheSociResource;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\CatalogoCorso;
use App\Models\QualificaSocio;
use App\Models\Socio;
use App\Services\QualificaSocioImportWriter;
use App\Services\QualificheSociImportParser;
use App\Services\QualificheSociQueryBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListQualificheSoci extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = QualificheSociResource::class;

    protected string $view = 'filament.resources.qualifiche-soci.pages.list-qualifiche-soci';

    protected static ?string $title = 'Qualifiche';

    /**
     * Senza questo override il breadcrumb duplica "Qualifiche" (una volta dal nome della
     * Resource, una volta dal $title sopra). "Qualifiche" da sola, non cliccabile (sei già
     * su questa pagina, non serve un secondo livello "Lista" che porta nello stesso posto).
     */
    public function getBreadcrumbs(): array
    {
        return [static::getResource()::getBreadcrumb()];
    }

    #[Url(as: 'regione', except: null)]
    public ?int $filtroRegione = null;

    #[Url(as: 'locale', except: null)]
    public ?int $filtroLocale = null;

    #[Url(as: 'richieste')]
    public array $qualificheRichieste = [];

    #[Url(as: 'op', except: 'and')]
    public string $operatoreRichieste = 'and';

    #[Url(as: 'escluse')]
    public array $qualificheEscluse = [];

    #[Url(as: 'cerca', except: false)]
    public bool $ricercaAvviata = false;

    #[Url(as: 'estensioni', except: false)]
    public bool $includiEstensioni = false;

    #[Url(as: 'dipendenti', except: false)]
    public bool $includiDipendenti = false;

    #[Url(as: 'scadenza', except: null)]
    public ?string $filtroStatoScadenza = null;

    #[Url(as: 'giovani', except: null)]
    public ?string $filtroGiovani = null;

    private array $corsiIdsCache = [];

    private function getIdsPerQualifica(int $qualId): array
    {
        return $this->corsiIdsCache[$qualId] ??= CatalogoCorso::idsConEquivalenti($qualId);
    }

    /** Chiave cache del preview import (evita PayloadTooLargeException con file grandi). */
    public ?string $importCacheKey = null;
    public ?string $importFilePath = null;

    /** Filtri sulla tabella di anteprima import (client-side, non persistiti in URL). */
    public ?string $filtroAnteprimaStato = null;
    public string  $cercaAnteprima       = '';

    /** Dati preview letti dalla cache — non serializzati nel payload Livewire. */
    #[Computed]
    public function importPreview(): ?array
    {
        if (! $this->importCacheKey) {
            return null;
        }
        return Cache::get($this->importCacheKey);
    }

    /** Stati raggruppati sotto il tab "Errori" (CF non trovato, qualifica non in catalogo, riga saltata). */
    private const STATI_PROBLEMI = ['errore_cf', 'errore_qualifica', 'saltata'];

    /** Righe di anteprima dopo l'applicazione del tab stato attivo e della ricerca testuale. */
    public function righeAnteprimaFiltrate(): \Illuminate\Support\Collection
    {
        $righe = collect($this->importPreview);

        if ($this->filtroAnteprimaStato === 'problemi') {
            $righe = $righe->whereIn('stato', self::STATI_PROBLEMI);
        } elseif ($this->filtroAnteprimaStato !== null) {
            $righe = $righe->where('stato', $this->filtroAnteprimaStato);
        }

        if (trim($this->cercaAnteprima) !== '') {
            $ricerca = mb_strtolower(trim($this->cercaAnteprima));
            $righe = $righe->filter(fn ($r) => str_contains(mb_strtolower($r['cognome'] . ' ' . $r['nome']), $ricerca)
                || str_contains(mb_strtolower($r['cf'] ?? ''), $ricerca)
                || str_contains(mb_strtolower($r['qualifica_nome_raw'] ?? ''), $ricerca));
        }

        return $righe->values();
    }

    /** Seleziona il tab stato attivo (null = "Totali"). I tab sono mutualmente esclusivi. */
    public function impostaFiltroAnteprima(?string $gruppo): void
    {
        $this->filtroAnteprimaStato = $gruppo;
    }

    public function resetFiltriAnteprima(): void
    {
        $this->filtroAnteprimaStato = null;
        $this->cercaAnteprima       = '';
    }

    public function mount(): void
    {
        $user = auth()->user();

        // Pre-seleziona la regione per admin, admin_locale e operatori solo se non già ripristinata dall'URL
        if ($user?->isAdmin() || $user?->isAdminLocale() || $user?->isOperatore()) {
            if ($this->filtroRegione === null) {
                $this->filtroRegione = $user->comitato_regionale_id;
            }
        }

        // Pre-seleziona il CL per admin_locale e per gli operatori con un comitato locale
        // assegnato (stesso criterio di scope usato in QualificheSociQueryBuilder) solo se
        // non già ripristinato dall'URL
        if ($user?->isAdminLocale() || ($user?->isOperatore() && $user->comitato_locale_id)) {
            if ($this->filtroLocale === null) {
                $this->filtroLocale = $user->comitato_locale_id;
            }
        }
    }

    public function form(Schema $form): Schema
    {
        $user = auth()->user();

        $opzioniQualifiche = CatalogoCorso::attivo()->orderBy('nome')
            ->get()
            ->mapWithKeys(fn ($q) => [$q->id => "[{$q->codice}] {$q->nome}"])
            ->toArray();

        return $form->schema([
            Forms\Components\Select::make('qualificheRichieste')
                ->label('Qualifiche richieste')
                ->options($opzioniQualifiche)
                ->placeholder('Seleziona una o più qualifiche')
                ->multiple()
                ->searchable()
                ->live()
                ->columnSpan(2),

            Forms\Components\Select::make('operatoreRichieste')
                ->label('Operatore')
                ->options(['and' => 'Tutte (AND)', 'or' => 'Almeno una (OR)'])
                ->default('and')
                ->selectablePlaceholder(false)
                ->live()
                ->columnSpan(1),

            SchemaActions::make([
                Action::make('avviaRicerca')
                    ->label('Avvia ricerca')
                    ->icon('heroicon-m-magnifying-glass')
                    ->color('brand_pink')
                    ->action(fn () => $this->avviaRicerca()),

                Action::make('azzera')
                    ->label('Azzera')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->action(function () {
                        $this->ricercaAvviata = false;
                        $this->qualificheRichieste = [];
                        $this->operatoreRichieste = 'and';
                        $this->qualificheEscluse = [];
                        $this->filtroStatoScadenza = null;
                        $this->filtroGiovani = null;
                        $this->filtroLocale = null;
                        $user = auth()->user();
                        if ($user?->isAdminLocale() || ($user?->isOperatore() && $user->comitato_locale_id)) {
                            $this->filtroLocale = $user->comitato_locale_id;
                        }
                    })
                    ->visible(fn () => $this->ricercaAvviata),
            ])
            ->columnSpan(1)
            ->extraAttributes(['class' => 'flex flex-col justify-end items-end'])
            ->alignEnd(),

            Forms\Components\Select::make('qualificheEscluse')
                ->label('Qualifiche escluse')
                ->helperText('I soci trovati non devono avere queste qualifiche.')
                ->options($opzioniQualifiche)
                ->placeholder('Nessuna esclusione')
                ->multiple()
                ->searchable()
                ->live()
                ->columnSpan(3),

            Forms\Components\Select::make('filtroStatoScadenza')
                ->label('Stato scadenza')
                ->helperText('Su almeno una delle qualifiche richieste.')
                ->options([
                    'valide'  => 'Solo valide (anche in scadenza)',
                    'scadute' => 'Solo scadute',
                ])
                ->placeholder('Tutte')
                ->disabled(fn () => empty($this->qualificheRichieste))
                ->live()
                ->columnSpan(1),

            Forms\Components\Select::make('filtroGiovani')
                ->label('Giovani')
                ->helperText('Soglia CRI: meno di 32 anni compiuti.')
                ->options([
                    'attuale'  => 'Solo giovani (età attuale)',
                    'ingresso' => 'Solo entrati come giovani (età all\'ingresso)',
                ])
                ->placeholder('Tutti')
                ->live()
                ->columnSpan(1),

            Forms\Components\Select::make('filtroRegione')
                ->label('Comitato Regionale')
                ->options(function () use ($user) {
                    $query = ComitatoRegionale::orderBy('nome');
                    if ($user?->isAdmin() || $user?->isOperatore() || $user?->isAdminLocale()) {
                        $query->where('id', $user->comitato_regionale_id);
                    }
                    return $query->pluck('nome', 'id')->toArray();
                })
                ->placeholder('Tutte le regioni')
                ->disabled($user?->isAdmin() || $user?->isAdminLocale() || $user?->isOperatore())
                ->dehydrated(true)
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => $this->filtroLocale = null)
                ->columnSpan(1),

            Forms\Components\Select::make('filtroLocale')
                ->label('Comitato Locale')
                ->options(function () {
                    if (! $this->filtroRegione) {
                        return [];
                    }
                    return ComitatoLocale::where('comitato_regionale_id', $this->filtroRegione)
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->toArray();
                })
                ->placeholder('Tutti i comitati locali')
                ->disabled(fn () => ! $this->filtroRegione
                    || auth()->user()?->isAdminLocale()
                    || (auth()->user()?->isOperatore() && auth()->user()?->comitato_locale_id))
                ->searchable()
                ->live()
                ->columnSpan(1),

            Forms\Components\Toggle::make('includiEstensioni')
                ->label('Includi soci in estensione')
                ->helperText('Mostra anche i soci assegnati al comitato selezionato per estensione.')
                ->live()
                ->columnSpan(1),

            Forms\Components\Toggle::make('includiDipendenti')
                ->label('Includi dipendenti')
                ->helperText('Mostra anche i soci con contratto di lavoro con il comitato selezionato.')
                ->live()
                ->columnSpan(1),

        ])->columns(4);
    }

    public function avviaRicerca(): void
    {
        if (empty($this->qualificheRichieste) && empty($this->qualificheEscluse)) {
            Notification::make()
                ->title('Condizione obbligatoria')
                ->body('Seleziona almeno una qualifica richiesta o esclusa prima di avviare la ricerca.')
                ->warning()
                ->send();
            return;
        }

        $this->ricercaAvviata = true;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->buildQuery())
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

                Tables\Columns\TextColumn::make('data_nascita')
                    ->label('Nascita')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('eta_attuale')
                    ->label('Età')
                    ->state(fn (Socio $record) => $record->data_nascita ? (int) $record->data_nascita->diffInYears(now()) : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('data_nascita', $direction === 'asc' ? 'desc' : 'asc')),

                Tables\Columns\TextColumn::make('volontario_da')
                    ->label('Ingresso')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('comitato_display')
                    ->label('Comitato / Sottosede')
                    ->state(fn (Socio $record) => $record->comitato_display)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(function ($q) use ($search) {
                        $q->whereHas('comitatoLocale', fn ($q) => $q->where('nome', 'ilike', "%{$search}%"))
                          ->orWhereHas('sottosedeAppartenenza', fn ($q) => $q->where('nome', 'ilike', "%{$search}%"));
                    }))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw(
                        "(SELECT nome FROM comitati_locali WHERE comitati_locali.id = soci.comitato_locale_id LIMIT 1) {$direction} NULLS LAST"
                    )),

                Tables\Columns\TextColumn::make('tipo_relazione')
                    ->label('Tipo relazione')
                    ->state(function (Socio $record) {
                        if (! $this->filtroLocale) {
                            return null;
                        }
                        if ($record->comitato_locale_dipendente_id === $this->filtroLocale) {
                            return 'Dipendente';
                        }
                        if ($record->comitato_locale_estensione_id === $this->filtroLocale) {
                            return 'In estensione';
                        }
                        return 'Volontario';
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Dipendente'   => 'info',
                        'In estensione' => 'warning',
                        'Volontario'   => 'success',
                        default        => 'gray',
                    })
                    ->visible(fn () => (bool) $this->filtroLocale),

                Tables\Columns\TextColumn::make('corsi_trovati')
                    ->label('Corso/i')
                    ->state(function (Socio $record) {
                        if (empty($this->qualificheRichieste)) {
                            return null;
                        }
                        $parts = [];
                        foreach ($this->qualificheRichieste as $qualId) {
                            $ids = $this->getIdsPerQualifica((int) $qualId);
                            $sq = $record->qualificheSoci
                                ->first(fn ($sq) => in_array($sq->catalogo_corso_id, $ids));
                            $parts[] = $sq?->catalogoCorso?->codice ?? '—';
                        }
                        return implode('<br>', $parts);
                    })
                    ->html()
                    ->visible(fn () => ! empty($this->qualificheRichieste)),

                Tables\Columns\TextColumn::make('scadenze_trovate')
                    ->label('Scadenza/e')
                    ->state(function (Socio $record) {
                        if (empty($this->qualificheRichieste)) {
                            return null;
                        }
                        $parts = [];
                        foreach ($this->qualificheRichieste as $qualId) {
                            $ids = $this->getIdsPerQualifica((int) $qualId);
                            $sq = $record->qualificheSoci
                                ->first(fn ($sq) => in_array($sq->catalogo_corso_id, $ids));
                            $parts[] = $this->formatScadenzaHtml($sq);
                        }
                        return implode('<br>', $parts);
                    })
                    ->html()
                    ->visible(fn () => ! empty($this->qualificheRichieste))
                    ->sortable(
                        fn () => count($this->qualificheRichieste) === 1,
                        query: function (Builder $query, string $direction) {
                            $ids          = $this->getIdsPerQualifica((int) $this->qualificheRichieste[0]);
                            $placeholders = implode(',', array_fill(0, count($ids), '?'));
                            $query->orderByRaw(
                                "(SELECT scadenza_calcolata FROM qualifiche_soci
                                  WHERE qualifiche_soci.socio_id = soci.id
                                    AND qualifiche_soci.catalogo_corso_id IN ({$placeholders})
                                  LIMIT 1) {$direction} NULLS LAST",
                                $ids
                            );
                        }
                    ),
            ])
            ->actions([
                Action::make('gestisci')
                    ->label('Gestisci qualifiche')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Gestisci qualifiche')
                    ->url(fn (Socio $record) => QualificheSociResource::getUrl('gestisci', ['record' => $record])),

                Action::make('apriCurriculumGaia')
                    ->label('Apri su GAIA')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->iconButton()
                    ->tooltip(fn (Socio $record) => filled($record->gaia_persona_id)
                        ? 'Apri su GAIA'
                        : 'Nessun ID GAIA collegato a questo socio')
                    ->disabled(fn (Socio $record) => blank($record->gaia_persona_id))
                    ->url(fn (Socio $record) => filled($record->gaia_persona_id)
                        ? "https://gaia.cri.it/profilo/{$record->gaia_persona_id}/curriculum/"
                        : null)
                    ->openUrlInNewTab(),

                Action::make('apriSchedaSocio')
                    ->label('Apri scheda socio')
                    ->icon('heroicon-o-identification')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Apri scheda socio')
                    ->url(fn (Socio $record) => \App\Filament\Resources\SocioResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->emptyStateHeading('Nessun socio trovato')
            ->emptyStateIcon('heroicon-o-users')
            ->defaultSort('cognome')
            ->paginated([25, 50, 100]);
    }

    private function formatScadenzaHtml(?QualificaSocio $sq): string
    {
        if (! $sq?->scadenza_calcolata) {
            return '<span style="color:#9ca3af">—</span>';
        }
        $color = match ($sq->colore_scadenza) {
            'success' => '#16a34a',
            'warning' => '#d97706',
            'danger'  => '#dc2626',
            default   => '#6b7280',
        };
        return '<span style="color:' . $color . '">' . $sq->scadenza_calcolata->format('d/m/Y') . '</span>';
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('importa')
                    ->label('Importa Excel G.A.I.A.')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('brand_pink')
                    ->visible(fn () => $this->canImport())
                    ->form([
                        Forms\Components\Placeholder::make('istruzioni')
                            ->label('')
                            ->content(
                                'Formato atteso: export GAIA per singolo corso. '
                                . 'Colonne rilevanti: Cognome · Nome · Codice Fiscale · '
                                . 'Titoli/Operativita/Data ottenimento. '
                                . 'Il nome del corso nella cella deve corrispondere esattamente a una qualifica in catalogo. '
                                . 'Dopo il caricamento vedrai un\'anteprima prima di confermare.'
                            ),
                        Forms\Components\FileUpload::make('file_xlsx')
                            ->label('File XLSX')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->disk('local')
                            ->directory('imports-tmp')
                            ->required(),
                    ])
                    ->modalHeading('Importa qualifiche da Excel (formato GAIA)')
                    ->modalSubmitActionLabel('Analizza file')
                    ->action(function (array $data): void {
                        $this->analizzaFile($data['file_xlsx']);
                    }),

                Action::make('scarica_excel')
                    ->label('Scarica Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn () => $this->ricercaAvviata)
                    ->url(fn () => route('qualifiche-soci.export-excel', $this->exportParams())),

                Action::make('anteprima_pdf')
                    ->label('Anteprima PDF')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn () => $this->ricercaAvviata)
                    ->url(fn () => route('qualifiche-soci.export-pdf', $this->exportParams()))
                    ->openUrlInNewTab(),

                Action::make('scarica_pdf')
                    ->label('Scarica PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn () => $this->ricercaAvviata)
                    ->url(fn () => route('qualifiche-soci.export-pdf', array_merge($this->exportParams(), ['download' => true]))),
            ])->label('Azioni')->icon('heroicon-o-ellipsis-vertical')->color('info')->button(),

            // Bottone "Installa" per lo userscript "LURCH - GAIA Bridge": sostituito in-place
            // dallo script con il bottone reale di avvio raccolta. Visibile solo con un comitato
            // locale specifico selezionato (mai su "tutti i comitati" o sull'intera regione: il
            // batch va sempre scoping a un comitato alla volta).
            // Resta fuori dall'ActionGroup: lo userscript lo individua via [data-userscript-install]
            // e deve essere presente/cliccabile nel DOM subito, non dentro un dropdown lazy.
            Action::make('importaGaiaComitato')
                ->label('Importa qualifiche GAIA (comitato)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                // Soglia di ruolo puoUsareGaiaBridge() (oltre a canImport()/filtroLocale, che
                // per admin_locale è già bloccato/preselezionato sul proprio comitato).
                ->visible(fn () => $this->canImport() && $this->filtroLocale !== null && auth()->user()?->puoUsareGaiaBridge())
                ->tooltip('Richiede lo userscript Tampermonkey e una sessione GAIA attiva nel browser. Un solo comitato locale alla volta.')
                ->extraAttributes(fn () => [
                    'data-userscript-install' => 'lurch-gaia-batch-comitato',
                    'data-comitato-locale-id' => (string) $this->filtroLocale,
                ])
                ->url(asset('tampermonkey/lurch-gaia-bridge.user.js'))
                ->openUrlInNewTab(),
        ];
    }

    /** Parametri URL per gli export Excel/PDF, coerenti coi filtri e l'ordinamento correnti della tabella. */
    private function exportParams(): array
    {
        $params = array_filter([
            'regione'    => $this->filtroRegione,
            'locale'     => $this->filtroLocale,
            'op'         => $this->operatoreRichieste !== 'and' ? $this->operatoreRichieste : null,
            'estensioni' => $this->includiEstensioni ? 1 : null,
            'dipendenti' => $this->includiDipendenti ? 1 : null,
            'giovani'    => in_array($this->filtroGiovani, ['attuale', 'ingresso']) ? $this->filtroGiovani : null,
            'sort'       => $this->getTableSortColumn() ?: null,
            'dir'        => ($this->getTableSortDirection() && $this->getTableSortDirection() !== 'asc') ? $this->getTableSortDirection() : null,
        ]);

        if (! empty($this->qualificheRichieste)) {
            $params['richieste'] = $this->qualificheRichieste;
        }
        if (! empty($this->qualificheEscluse)) {
            $params['escluse'] = $this->qualificheEscluse;
        }

        return $params;
    }

    public function canImport(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminLocale();
    }

    public function analizzaFile(string $filePath): void
    {
        $fullPath = Storage::disk('local')->path($filePath);

        try {
            $parser = QualificheSociImportParser::fromFile($fullPath);

            $cacheKey = 'import_preview_' . auth()->id() . '_' . now()->timestamp;
            Cache::put($cacheKey, $parser->righe, now()->addMinutes(30));

            $this->importCacheKey = $cacheKey;
            $this->importFilePath = $filePath;
            $this->filtroAnteprimaStato = null;
            $this->cercaAnteprima = '';
            unset($this->importPreview);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Errore durante l\'analisi del file')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Sostituisce il vecchio bottone con wire:confirm (popup nativo del browser) — scrive dati
     * veri (QualificaSocio) su potenzialmente centinaia di righe in un colpo, merita lo stesso
     * peso delle altre conferme "vere" del pannello, non un semplice confirm() di sistema.
     */
    public function confermaImportAction(): Action
    {
        return Action::make('confermaImport')
            ->label(fn () => 'Conferma import (' . $this->totRigheImportabili() . ' righe)')
            ->icon('heroicon-m-check')
            ->color('success')
            ->visible(fn () => $this->totRigheImportabili() > 0)
            ->requiresConfirmation()
            ->modalHeading('Conferma import')
            ->modalDescription(fn () => "Stai per importare {$this->totRigheImportabili()} righe. I dati verranno scritti subito, l'operazione non è reversibile da qui. Continuare?")
            ->modalSubmitActionLabel('Sì, importa')
            ->action(fn () => $this->confermaImport());
    }

    private function totRigheImportabili(): int
    {
        return collect($this->importPreview)->whereIn('stato', ['inserimento', 'aggiornamento'])->count();
    }

    public function confermaImport(): void
    {
        $preview = $this->importPreview;
        if (empty($preview)) {
            return;
        }

        $inseriti    = 0;
        $aggiornati  = 0;
        $ricalcolati = 0;
        $errori      = [];

        foreach ($preview as $riga) {
            try {
                // "Obsoleta" (stessa data già in archivio) non scrive date, ma può comunque
                // promuovere la fonte secondo la gerarchia manuale < excel < gaia — vedi
                // QualificaSocioImportWriter::ricalcolaSeEsistente(). Prima di questo fix
                // l'import Excel saltava del tutto queste righe (`continue`), quindi una
                // qualifica inserita a mano non veniva mai "presa in carico" dall'Excel se la
                // data risultava già identica.
                if ($riga['stato'] === 'obsoleta') {
                    QualificaSocioImportWriter::ricalcolaSeEsistente($riga, 'importazione_excel');
                    $ricalcolati++;
                    continue;
                }

                if (! in_array($riga['stato'], ['inserimento', 'aggiornamento'])) {
                    continue;
                }

                QualificaSocioImportWriter::scriviRiga($riga, 'importazione_excel');
                $riga['stato'] === 'inserimento' ? $inseriti++ : $aggiornati++;
            } catch (\Throwable $e) {
                $errori[] = "Riga {$riga['riga']} ({$riga['cf']}): " . $e->getMessage();
            }
        }

        if ($this->importCacheKey) {
            Cache::forget($this->importCacheKey);
        }
        if ($this->importFilePath) {
            Storage::disk('local')->delete($this->importFilePath);
        }

        $this->importCacheKey = null;
        $this->importFilePath = null;
        $this->filtroAnteprimaStato = null;
        $this->cercaAnteprima = '';
        unset($this->importPreview);

        $body = "Inseriti: {$inseriti}";
        if ($aggiornati > 0) $body .= " · Aggiornati: {$aggiornati}";
        if ($ricalcolati > 0) $body .= " · Ricalcolati: {$ricalcolati}";
        if (count($errori) > 0) $body .= ' · Errori: ' . count($errori);

        if (count($errori) > 0) {
            Notification::make()
                ->title('Import completato con errori')
                ->body($body . "\n" . implode("\n", array_slice($errori, 0, 5)))
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Import completato')
                ->body($body)
                ->success()
                ->send();
        }
    }

    public function annullaImport(): void
    {
        if ($this->importCacheKey) {
            Cache::forget($this->importCacheKey);
        }
        if ($this->importFilePath) {
            Storage::disk('local')->delete($this->importFilePath);
        }
        $this->importCacheKey = null;
        $this->importFilePath = null;
        $this->filtroAnteprimaStato = null;
        $this->cercaAnteprima = '';
        unset($this->importPreview);
    }

    protected function buildQuery(): Builder
    {
        return (new QualificheSociQueryBuilder())->build(
            user: auth()->user(),
            qualificheRichieste: $this->qualificheRichieste,
            qualificheEscluse: $this->qualificheEscluse,
            operatoreRichieste: $this->operatoreRichieste,
            filtroRegione: $this->filtroRegione,
            filtroLocale: $this->filtroLocale,
            includiEstensioni: $this->includiEstensioni,
            includiDipendenti: $this->includiDipendenti,
            filtroStatoScadenza: $this->filtroStatoScadenza,
            filtroGiovani: $this->filtroGiovani,
            with: ['comitatoLocaleDipendente', 'sottosedeAppartenenza'],
        );
    }

}
