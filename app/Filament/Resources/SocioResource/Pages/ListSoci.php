<?php

namespace App\Filament\Resources\SocioResource\Pages;

use App\Filament\Resources\SocioResource;
use App\Imports\SociImport;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Services\SociAllineamentoService;
use App\Services\SociQueryBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListSoci extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = SocioResource::class;

    protected string $view = 'filament.resources.socio-resource.pages.list-soci';

    protected static ?string $title = 'Soci';

    /**
     * Senza questo override il breadcrumb duplica "Soci" (una volta dal nome della Resource,
     * una volta dal $title sopra). "Soci" da sola, non cliccabile (sei già su questa pagina,
     * non serve un secondo livello "Lista" che porta nello stesso posto) — stesso fix di
     * ListQualificheSoci.
     */
    public function getBreadcrumbs(): array
    {
        return [static::getResource()::getBreadcrumb()];
    }

    // --- Filtri di ricerca (estratti dalla tabella in un form dedicato, stesso schema di
    //     ListQualificheSoci) ------------------------------------
    #[Url(as: 'regione', except: null)]
    public ?int $filtroRegione = null;

    #[Url(as: 'locale', except: null)]
    public ?int $filtroLocale = null;

    #[Url(as: 'ruolo', except: null)]
    public ?string $filtroRuolo = null;

    #[Url(as: 'estensioni', except: false)]
    public bool $filtroIncludiEstensioni = false;

    #[Url(as: 'dipendenti', except: false)]
    public bool $filtroIncludiDipendenti = false;

    #[Url(as: 'giovani', except: null)]
    public ?string $filtroGiovani = null;

    #[Url(as: 'stato', except: null)]
    public ?string $filtroStatoAssociativo = null;

    #[Url(as: 'cestinati', except: null)]
    public ?string $filtroCestinati = null;

    #[Url(as: 'cerca', except: false)]
    public bool $ricercaAvviata = false;

    public function mount(): void
    {
        $this->applicaPreselezioniRuolo();

        // Auto-popola solo quando anche il comitato locale risulta già bloccato (admin_locale,
        // o operatore scoped a un comitato locale specifico) — ambito piccolo, safe da mostrare
        // subito. Admin regionale e superadmin restano sul click esplicito di "Avvia ricerca",
        // anche quando la regione è già preimpostata.
        if (! $this->ricercaAvviata && $this->filtroLocale !== null) {
            $this->ricercaAvviata = true;
        }
    }

    /** Preseleziona regione/locale in base al ruolo — usata da mount() e da "Azzera". */
    private function applicaPreselezioniRuolo(): void
    {
        $user = auth()->user();

        if ($user?->isAdmin() || $user?->isAdminLocale() || $user?->isOperatore()) {
            if ($this->filtroRegione === null) {
                $this->filtroRegione = $user->comitato_regionale_id;
            }
        }

        if ($user?->isAdminLocale() || ($user?->isOperatore() && $user->comitato_locale_id)) {
            if ($this->filtroLocale === null) {
                $this->filtroLocale = $user->comitato_locale_id;
            }
        }
    }

    public ?string $importFilePath = null;
    public bool    $fileXlsxPronto = false; // true quando un file è selezionato nel modal

    // --- Analisi (dry-run) del file: elaborazione a chunk pilotata dal browser, niente coda —
    //     stesso motivo dell'import vero sotto: nessun worker da tenere allineato al codice, e
    //     "Annulla"/chiusura scheda fermano davvero anche questa fase (prima era un job in coda,
    //     indipendente dal browser).
    public bool    $analisiInCorso     = false;
    public bool    $analisiPronta      = false; // true quando l'anteprima è pronta, in attesa di conferma
    public ?string $analisiErrore      = null;
    public int     $analisiOffset      = 0;
    public int     $analisiTotaleRighe = 0; // conteggio veloce dai metadati del foglio, solo per la barra di avanzamento
    public int     $analisiRigheLette  = 0; // righe dati effettivamente lette durante il dry-run — fonte di verità per l'import vero
    public int     $analisiCreati      = 0;
    public int     $analisiAggiornati  = 0;
    public int     $analisiEstensioni  = 0;
    public int     $analisiDipendenti  = 0;
    public int     $analisiSaltati     = 0;
    /** @var string[] */
    public array $analisiErroriRun  = [];
    /** @var string[] */
    public array $analisiTipiSconosciutiRun = [];

    // --- Import vero (post-conferma): elaborazione a chunk pilotata dal browser, niente coda.
    //     Se la scheda si chiude, l'elaborazione
    //     si ferma qui: deliberato, nessuna ripresa al ricaricamento pagina.
    public bool  $importInCorso    = false;
    public bool  $importCompletato = false; // true a fine elaborazione, finché l'utente non chiude il riepilogo
    public int   $importOffset     = 0;
    public int   $importTotale     = 0;
    public int   $importCreati     = 0;
    public int   $importAggiornati = 0;
    public int   $importEstensioni = 0;
    public int   $importDipendenti = 0;
    public int   $importSaltati    = 0;
    /** @var string[] */
    public array $importErroriRun  = [];
    /** @var string[] */
    public array $importTipiSconosciutiRun = [];

    // --- Allineamento (dimessi / pulizia estensioni): opzioni scelte nel modal di import,
    //     eseguita DOPO l'import vero.
    public bool    $allineaSegnaDimessi         = false;
    public bool    $allineaPuliziaEstensioni    = false;
    public ?string $allineaAmbitoTipo           = 'locale'; // 'locale' | 'regionale' (ignorato per admin_locale)
    public ?int    $allineaComitatoLocaleId     = null;
    public ?int    $allineaRegioneId            = null;
    /** @var string[] CF accumulati durante i chunk (dedup) */
    public array $allineaCfAppartenenti = [];
    /** @var string[] */
    public array $allineaCfEstensioni   = [];
    public bool  $allineaFileContieneEstensioni = false;

    public bool  $allineaAnteprimaPronta = false;
    public bool  $allineaCompletato      = false;
    /** @var array<int,array{cognome:string,nome:string,cf:string,comitato:string,attive:bool}> */
    public array $allineaPreviewDimessi    = [];
    /** @var array<int,array{cognome:string,nome:string,cf:string,comitato:string}> */
    public array $allineaPreviewEstensioni = [];
    /** @var array<int,array{cognome:string,nome:string,cf:string,comitato:string}> */
    public array $allineaPreviewRientri    = [];
    /** @var array{dimessi:int,estensioni:int,rientri:int} */
    public array $allineaRisultato = ['dimessi' => 0, 'estensioni' => 0, 'rientri' => 0];

    /** Almeno un toggle di allineamento attivo per questo import. */
    private function allineamentoAttivo(): bool
    {
        return $this->allineaSegnaDimessi || $this->allineaPuliziaEstensioni;
    }

    /** @return array{0: ?int, 1: ?int} [comitatoLocaleId, comitatoRegionaleId] dell'ambito scelto. */
    private function allineaAmbitoIds(): array
    {
        $user = auth()->user();

        if ($user?->isAdminLocale()) {
            return [$user->comitato_locale_id, null];
        }
        if ($this->allineaAmbitoTipo === 'regionale') {
            $rid = $user?->isSuperadmin() ? $this->allineaRegioneId : $user?->comitato_regionale_id;
            return [null, $rid];
        }
        return [$this->allineaComitatoLocaleId, null];
    }

    public function form(Schema $form): Schema
    {
        $user = auth()->user();

        return $form->schema([
            Select::make('filtroRegione')
                ->label('Comitato Regionale')
                ->options(function () use ($user) {
                    $query = ComitatoRegionale::orderBy('nome');
                    if ($user?->isAdmin() || $user?->isOperatore() || $user?->isAdminLocale()) {
                        $query->where('id', $user->comitato_regionale_id);
                    }
                    return $query->pluck('nome', 'id')->toArray();
                })
                ->placeholder('Seleziona un comitato regionale')
                ->disabled($user?->isAdmin() || $user?->isAdminLocale() || $user?->isOperatore())
                ->dehydrated(true)
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => $this->filtroLocale = null)
                ->columnSpan(1),

            Select::make('filtroLocale')
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

            Select::make('filtroRuolo')
                ->label('Ruolo associativo')
                ->options([
                    'volontario'            => 'Volontario',
                    'dipendente'            => 'Dipendente',
                    'infermiere_volontarie' => 'Infermiere Volontarie CRI',
                    'corpo_militare'        => 'Corpo Militare Volontario CRI',
                ])
                ->placeholder('Tutti')
                ->searchable()
                ->live()
                ->columnSpan(1),

            Toggle::make('filtroIncludiEstensioni')
                ->label('Includi soci in estensione')
                ->helperText('Mostra anche i soci assegnati al comitato selezionato per estensione.')
                ->live()
                ->columnSpan(1),

            Toggle::make('filtroIncludiDipendenti')
                ->label('Includi dipendenti')
                ->helperText('Mostra anche i soci con contratto di lavoro con il comitato selezionato.')
                ->live()
                ->columnSpan(1),

            // Soglia CRI "Giovani": meno di 32 anni compiuti — stesso filtro/soglia della
            // ricerca Qualifiche.
            Select::make('filtroGiovani')
                ->label('Giovani')
                ->helperText('Soglia CRI: meno di 32 anni compiuti.')
                ->options([
                    'attuale'  => 'Solo giovani (età attuale)',
                    'ingresso' => 'Solo entrati come giovani (età all\'ingresso)',
                ])
                ->placeholder('Tutti')
                ->live()
                ->columnSpan(1),

            Select::make('filtroStatoAssociativo')
                ->label('Stato associativo')
                ->options([
                    'dimessi' => 'Solo dimessi',
                    'tutti'   => 'Attivi e dimessi',
                ])
                ->placeholder('Solo attivi')
                ->live()
                ->columnSpan(1),

            Select::make('filtroCestinati')
                ->label('Cestinati')
                ->options([
                    'con'  => 'Con eliminati',
                    'solo' => 'Solo eliminati',
                ])
                ->placeholder('Senza eliminati')
                ->live()
                ->columnSpan(1),

            SchemaActions::make([
                Action::make('avviaRicerca')
                    ->label('Avvia ricerca')
                    ->icon('heroicon-m-magnifying-glass')
                    ->color('brand_pink')
                    ->disabled(fn () => empty($this->filtroRegione))
                    ->action(fn () => $this->avviaRicerca()),

                Action::make('azzera')
                    ->label('Azzera')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->action(function () {
                        $this->ricercaAvviata = false;
                        $this->filtroRegione = null;
                        $this->filtroLocale = null;
                        $this->filtroRuolo = null;
                        $this->filtroIncludiEstensioni = false;
                        $this->filtroIncludiDipendenti = false;
                        $this->filtroGiovani = null;
                        $this->filtroStatoAssociativo = null;
                        $this->filtroCestinati = null;

                        $this->applicaPreselezioniRuolo();
                        if ($this->filtroLocale !== null) {
                            $this->ricercaAvviata = true;
                        }
                    })
                    ->visible(fn () => $this->ricercaAvviata),
            ])
                ->columnSpan(1)
                ->extraAttributes(['class' => 'flex flex-col justify-end items-end'])
                ->alignEnd(),
        ])->columns(4);
    }

    public function avviaRicerca(): void
    {
        if (empty($this->filtroRegione)) {
            Notification::make()
                ->title('Comitato regionale obbligatorio')
                ->body('Seleziona almeno un comitato regionale prima di avviare la ricerca.')
                ->warning()
                ->send();
            return;
        }

        $this->ricercaAvviata = true;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return SocioResource::table(
            $table->query(fn () => $this->buildQuery())
        );
    }

    private function buildQuery(): Builder
    {
        return (new SociQueryBuilder())->build(
            user: auth()->user(),
            filtroRegione: $this->filtroRegione,
            filtroLocale: $this->filtroLocale,
            filtroRuolo: $this->filtroRuolo,
            includiEstensioni: $this->filtroIncludiEstensioni,
            includiDipendenti: $this->filtroIncludiDipendenti,
            filtroGiovani: $this->filtroGiovani,
            filtroStatoAssociativo: $this->filtroStatoAssociativo,
            filtroCestinati: $this->filtroCestinati,
        );
    }

    protected function getHeaderActions(): array
    {
        // "Nuovo" (CreateAction) vive ora nell'header della tabella (SocioResource::table()),
        // non qui — stesso standard applicato a ogni altra Resource.
        $azioni = [];
        if (SocioResource::canImport()) {
            $azioni[] = $this->importaExcelAction();
        }
        $azioni[] = $this->esportaExcelAction();

        // 2+ azioni → un unico ActionGroup (convenzione header actions).
        if (count($azioni) === 1) {
            return $azioni;
        }

        return [
            ActionGroup::make($azioni)
                ->label('Azioni')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('info')
                ->button(),
        ];
    }

    private function esportaExcelAction(): Action
    {
        return Action::make('esporta_soci')
            ->label('Esporta Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('info')
            ->visible(fn () => $this->ricercaAvviata)
            ->url(fn () => route('soci.export-excel', $this->exportParams()))
            ->openUrlInNewTab();
    }

    /** Parametri URL per l'export Excel, coerenti coi filtri/ordinamento correnti di ricerca. */
    private function exportParams(): array
    {
        return array_filter([
            'regione'    => $this->filtroRegione,
            'locale'     => $this->filtroLocale,
            'ruolo'      => $this->filtroRuolo,
            'estensioni' => $this->filtroIncludiEstensioni ? 1 : null,
            'dipendenti' => $this->filtroIncludiDipendenti ? 1 : null,
            'giovani'    => $this->filtroGiovani,
            'stato'      => $this->filtroStatoAssociativo,
            'cestinati'  => $this->filtroCestinati,
            'sort'       => $this->getTableSortColumn() ?: null,
            'dir'        => ($this->getTableSortDirection() && $this->getTableSortDirection() !== 'asc') ? $this->getTableSortDirection() : null,
        ]);
    }

    private function importaExcelAction(): Action
    {
        return Action::make('importa_soci')
                ->label('Importa da Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('brand_pink')
                ->mountUsing(function ($schema) {
                    $schema->fill(['file_xlsx' => null]);
                    $this->fileXlsxPronto = false;
                })
                ->form([
                    Placeholder::make('istruzioni')
                        ->label('')
                        ->content(
                            'Formato atteso: export GAIA (CRI). '
                            . 'Colonne rilevanti: Cognome · Nome · Codice Fiscale · Data di Nascita · '
                            . 'Email · Numeri di telefono · Ingresso in CRI · Tipo Attuale · Elenco. '
                            . 'Importa prima il file base (Volontari), poi il file estensioni. '
                            . 'Il file verrà analizzato subito dopo: l\'anteprima comparirà automaticamente.'
                        ),

                    FileUpload::make('file_xlsx')
                        ->label('File XLSX')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('imports-tmp')
                        ->live()
                        ->afterStateUpdated(fn ($state, $livewire) => $livewire->fileXlsxPronto = filled($state))
                        ->validationMessages(['required' => 'Seleziona un file XLSX prima di procedere.'])
                        ->required(),

                    Toggle::make('segna_dimessi')
                        ->label('Segna come dimessi i volontari mancanti da questa lista')
                        ->helperText('I volontari del comitato scelto che non compaiono nel file vengono segnati come dimessi (data di oggi). Non vengono cancellati e spariscono solo da elenco Soci e ricerca Qualifiche. Vedrai l\'elenco prima di confermare. NON usare un file che include i volontari in estensione: GAIA in quel caso nasconde il comitato di appartenenza.')
                        ->live()
                        ->disabled(fn ($get) => ! self::currentUserIsAdminLocale()
                            && $get('allinea_ambito_tipo') === 'regionale'
                            && $get('file_con_estensioni'))
                        ->dehydrated(),

                    Toggle::make('file_con_estensioni')
                        ->label('Il file contiene anche i volontari in estensione')
                        ->helperText('Attivalo se l\'export GAIA include le righe "Volontario in Estensione": i soci non più in estensione verso il comitato scelto perderanno la sola estensione. Puoi usarlo da solo, senza segnare i dimessi.')
                        ->live(),

                    Radio::make('allinea_ambito_tipo')
                        ->label('Ambito del confronto')
                        ->options([
                            'locale'    => 'Un comitato locale specifico',
                            'regionale' => 'Intero comitato regionale',
                        ])
                        ->default('locale')
                        ->inline()
                        ->live()
                        ->visible(fn ($get) => ! self::currentUserIsAdminLocale()
                            && ($get('segna_dimessi') || $get('file_con_estensioni'))),

                    Select::make('allinea_regione_id')
                        ->label('Comitato Regionale')
                        ->options(fn () => ComitatoRegionale::orderBy('nome')->pluck('nome', 'id'))
                        ->searchable()
                        ->live()
                        ->required(fn ($get) => $get('segna_dimessi') || $get('file_con_estensioni'))
                        ->visible(fn ($get) => self::currentUserIsSuperadmin()
                            && ($get('segna_dimessi') || $get('file_con_estensioni'))),

                    Select::make('allinea_comitato_locale_id')
                        ->label('Comitato Locale')
                        ->options(function ($get) {
                            $regioneId = self::currentUserIsSuperadmin()
                                ? $get('allinea_regione_id')
                                : auth()->user()?->comitato_regionale_id;
                            if (! $regioneId) {
                                return [];
                            }
                            return ComitatoLocale::where('comitato_regionale_id', $regioneId)
                                ->orderBy('nome')->pluck('nome', 'id');
                        })
                        ->searchable()
                        ->required(fn ($get) => ($get('segna_dimessi') || $get('file_con_estensioni'))
                            && $get('allinea_ambito_tipo') !== 'regionale')
                        ->visible(fn ($get) => ! self::currentUserIsAdminLocale()
                            && ($get('segna_dimessi') || $get('file_con_estensioni'))
                            && $get('allinea_ambito_tipo') !== 'regionale'),
                ])
                ->modalHeading('Importa soci da Excel (formato GAIA)')
                ->modalSubmitActionLabel('Avvia analisi')
                ->modalSubmitAction(fn ($action) => $action->disabled(! $this->fileXlsxPronto))
                ->action(function (array $data): void {
                    $this->impostaOpzioniAllineamento($data);
                    $this->avviaAnalisi($data['file_xlsx']);
                });
    }

    private static function currentUserIsAdminLocale(): bool
    {
        return (bool) auth()->user()?->isAdminLocale();
    }

    private static function currentUserIsSuperadmin(): bool
    {
        return (bool) auth()->user()?->isSuperadmin();
    }

    /** Legge le opzioni di allineamento dal form e le normalizza secondo il ruolo. */
    private function impostaOpzioniAllineamento(array $data): void
    {
        $user = auth()->user();

        $this->allineaSegnaDimessi      = (bool) ($data['segna_dimessi'] ?? false);
        $this->allineaPuliziaEstensioni = (bool) ($data['file_con_estensioni'] ?? false);
        $this->allineaAmbitoTipo        = $data['allinea_ambito_tipo'] ?? 'locale';
        $this->allineaRegioneId         = $data['allinea_regione_id'] ?? null;
        $this->allineaComitatoLocaleId  = $data['allinea_comitato_locale_id'] ?? null;

        if ($user?->isAdminLocale()) {
            $this->allineaAmbitoTipo       = 'locale';
            $this->allineaComitatoLocaleId = $user->comitato_locale_id;
        }

        // Vincolo GAIA: file regionale con estensioni oscura il comitato di appartenenza,
        // quindi non si possono dedurre i dimessi. Solo pulizia estensioni.
        if ($this->allineaAmbitoTipo === 'regionale' && $this->allineaPuliziaEstensioni) {
            $this->allineaSegnaDimessi = false;
        }
    }

    public function avviaAnalisi(string $filePath): void
    {
        $this->importFilePath = $filePath;

        try {
            $fullPath = Storage::disk('local')->path($filePath);
            $info     = IOFactory::createReaderForFile($fullPath)->listWorksheetInfo($fullPath);
            $this->analisiTotaleRighe = max(0, ($info[0]['totalRows'] ?? 1) - 1); // -1 = riga di intestazione
        } catch (\Throwable $e) {
            $this->analisiErrore = $e->getMessage();
            return;
        }

        $this->analisiInCorso    = true;
        $this->analisiPronta     = false;
        $this->analisiOffset     = 0;
        $this->analisiRigheLette = 0;
        $this->analisiCreati     = 0;
        $this->analisiAggiornati = 0;
        $this->analisiEstensioni = 0;
        $this->analisiDipendenti = 0;
        $this->analisiSaltati    = 0;
        $this->analisiErroriRun  = [];
        $this->analisiTipiSconosciutiRun = [];

        $this->allineaCfAppartenenti = [];
        $this->allineaCfEstensioni   = [];
        $this->allineaFileContieneEstensioni = false;
        $this->allineaAnteprimaPronta = false;
        $this->allineaCompletato      = false;
    }

    /** Chiamato dal wire:poll scoped dentro la modale di analisi — un chunk di 200 righe a chiamata, in dry-run. */
    public function elaboraChunkAnalisi(): void
    {
        if (! $this->analisiInCorso || ! $this->importFilePath) {
            return;
        }

        [$clId, $crId] = $this->allineamentoAttivo() ? $this->allineaAmbitoIds() : [null, null];

        $fullPath = Storage::disk('local')->path($this->importFilePath);
        $import   = new SociImport(auth()->user(), dryRun: true, chunkOffset: $this->analisiOffset, chunkLimit: 200,
            ambitoComitatoLocaleId: $clId, ambitoComitatoRegionaleId: $crId);

        try {
            Excel::import($import, $fullPath);
        } catch (\Throwable $e) {
            $this->analisiInCorso = false;
            $this->analisiErrore  = $e->getMessage();
            return;
        }

        $this->accumulaCfAllineamento($import);

        // Il file contiene righe "Volontario in Estensione" ma l'utente ha chiesto di segnare i
        // dimessi senza dichiararle: in quel caso GAIA oscura il comitato di appartenenza dei
        // soci in estensione e la deduzione dei dimessi non è affidabile. Stop.
        if ($this->allineaSegnaDimessi && ! $this->allineaPuliziaEstensioni && $this->allineaFileContieneEstensioni) {
            $this->annullaImport();
            $this->analisiErrore = 'Il file contiene volontari in estensione, ma hai scelto di segnare i dimessi senza dichiararlo. '
                . 'Con le estensioni GAIA nasconde il comitato di appartenenza: usa un export SENZA estensioni per segnare i dimessi, '
                . 'oppure attiva anche "Il file contiene anche i volontari in estensione" (in quel caso, su ambito regionale, i dimessi non vengono toccati).';
            return;
        }

        $this->analisiRigheLette += $import->totale;
        $this->analisiCreati     += $import->creati;
        $this->analisiAggiornati += $import->aggiornati;
        $this->analisiEstensioni += $import->estensioni;
        $this->analisiDipendenti += $import->dipendenti;
        $this->analisiSaltati    += $import->saltati;
        $this->analisiErroriRun   = array_merge($this->analisiErroriRun, $import->errori);
        foreach ($import->tipiSconosciuti as $tipo) {
            if (! in_array($tipo, $this->analisiTipiSconosciutiRun, true)) {
                $this->analisiTipiSconosciutiRun[] = $tipo;
            }
        }

        $this->analisiOffset += 200;

        // Chunk più corto del richiesto = fine file. Più affidabile del conteggio dai metadati
        // (che può includere righe vuote finali), usato solo per la barra di avanzamento.
        if ($import->totale < 200) {
            $this->analisiInCorso = false;
            $this->analisiPronta  = true;
        }
    }

    public function confermaImport(): void
    {
        if (! $this->importFilePath || ! $this->analisiPronta) {
            return;
        }

        $this->importTotale = $this->analisiRigheLette;

        $this->analisiPronta      = false;
        $this->analisiOffset      = 0;
        $this->analisiTotaleRighe = 0;
        $this->analisiRigheLette  = 0;
        $this->analisiCreati      = 0;
        $this->analisiAggiornati  = 0;
        $this->analisiEstensioni  = 0;
        $this->analisiDipendenti  = 0;
        $this->analisiSaltati     = 0;
        $this->analisiErroriRun   = [];
        $this->analisiTipiSconosciutiRun = [];

        // $this->importFilePath resta valorizzato: serve a elaboraChunkImport().
        $this->importInCorso    = true;
        $this->importOffset     = 0;
        $this->importCreati     = 0;
        $this->importAggiornati = 0;
        $this->importEstensioni = 0;
        $this->importDipendenti = 0;
        $this->importSaltati    = 0;
        $this->importErroriRun  = [];
        $this->importTipiSconosciutiRun = [];

        // I CF vengono raccolti di nuovo durante i chunk dell'import vero (rilettura completa).
        $this->allineaCfAppartenenti = [];
        $this->allineaCfEstensioni   = [];
    }

    /** Accumula (dedup) i CF di ambito raccolti da un chunk di SociImport. */
    private function accumulaCfAllineamento(SociImport $import): void
    {
        if ($import->fileContieneEstensioni) {
            $this->allineaFileContieneEstensioni = true;
        }
        if (! $this->allineamentoAttivo()) {
            return;
        }
        $this->allineaCfAppartenenti = array_values(array_unique(array_merge($this->allineaCfAppartenenti, $import->cfAppartenenti)));
        $this->allineaCfEstensioni   = array_values(array_unique(array_merge($this->allineaCfEstensioni, $import->cfEstensioni)));
    }

    /** Chiamato dal wire:poll scoped dentro la modale di avanzamento — un chunk di 200 righe a chiamata. */
    public function elaboraChunkImport(): void
    {
        if (! $this->importInCorso || ! $this->importFilePath) {
            return;
        }

        [$clId, $crId] = $this->allineamentoAttivo() ? $this->allineaAmbitoIds() : [null, null];

        $fullPath = Storage::disk('local')->path($this->importFilePath);
        $import   = new SociImport(auth()->user(), dryRun: false, chunkOffset: $this->importOffset, chunkLimit: 200,
            ambitoComitatoLocaleId: $clId, ambitoComitatoRegionaleId: $crId);

        try {
            Excel::import($import, $fullPath);
        } catch (\Throwable $e) {
            $this->importInCorso = false;
            Storage::disk('local')->delete($this->importFilePath);
            $this->importFilePath = null;

            Notification::make()
                ->title('Import soci fallito')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->sendToDatabase(auth()->user());
            return;
        }

        $this->importCreati     += $import->creati;
        $this->importAggiornati += $import->aggiornati;
        $this->importEstensioni += $import->estensioni;
        $this->importDipendenti += $import->dipendenti;
        $this->importSaltati    += $import->saltati;
        $this->importErroriRun   = array_merge($this->importErroriRun, $import->errori);
        foreach ($import->tipiSconosciuti as $tipo) {
            if (! in_array($tipo, $this->importTipiSconosciutiRun, true)) {
                $this->importTipiSconosciutiRun[] = $tipo;
            }
        }

        $this->accumulaCfAllineamento($import);

        $this->importOffset += 200;

        if ($this->importOffset >= $this->importTotale) {
            $this->completaImport();
        }
    }

    /** Import terminato: pulizia file, notifica DB persistente — stesso riepilogo di sempre. */
    private function completaImport(): void
    {
        $this->importInCorso    = false;
        $this->importCompletato = true;

        if ($this->importFilePath) {
            Storage::disk('local')->delete($this->importFilePath);
        }
        $this->importFilePath = null;

        $erroriCount  = count($this->importErroriRun);
        $saltatiCount = $this->importSaltati;

        $body = "Creati: {$this->importCreati}";
        if ($this->importAggiornati > 0) $body .= " · Aggiornati: {$this->importAggiornati}";
        if ($this->importEstensioni > 0) $body .= " · Estensioni: {$this->importEstensioni}";
        if ($this->importDipendenti > 0) $body .= " · Dipendenti: {$this->importDipendenti}";
        if ($saltatiCount > 0)           $body .= " · Saltati (tipo sconosciuto): {$saltatiCount}";
        if ($erroriCount > 0)            $body .= " · Errori: {$erroriCount}";

        $dettagliSaltati = '';
        if ($saltatiCount > 0 && ! empty($this->importTipiSconosciutiRun)) {
            $dettagliSaltati = "\nTipi non riconosciuti: " . implode(', ', array_map(fn ($t) => "\"{$t}\"", $this->importTipiSconosciutiRun));
        }

        if ($erroriCount > 0) {
            $dettagli = implode("\n", array_slice($this->importErroriRun, 0, 10));
            if ($erroriCount > 10) {
                $dettagli .= "\n… e altri " . ($erroriCount - 10) . " errori.";
            }
            Notification::make()
                ->title('Import soci completato con errori')
                ->body($body . $dettagliSaltati . "\n\n" . $dettagli)
                ->warning()
                ->persistent()
                ->sendToDatabase(auth()->user());
        } elseif ($saltatiCount > 0) {
            Notification::make()
                ->title('Import soci completato con righe saltate')
                ->body($body . $dettagliSaltati)
                ->warning()
                ->persistent()
                ->sendToDatabase(auth()->user());
        } else {
            Notification::make()
                ->title('Import soci completato')
                ->body($body)
                ->success()
                ->sendToDatabase(auth()->user());
        }

        // Allineamento (dimessi / pulizia estensioni): calcola l'anteprima; la conferma è
        // un secondo passo esplicito nella modale.
        if ($this->allineamentoAttivo()) {
            $this->preparaAllineamento();
        }
    }

    /** Calcola l'anteprima dell'allineamento dai CF raccolti durante l'import. */
    private function preparaAllineamento(): void
    {
        [$clId, $crId] = $this->allineaAmbitoIds();

        $ant = (new SociAllineamentoService())->anteprima(
            $this->allineaCfAppartenenti,
            $this->allineaCfEstensioni,
            $clId,
            $crId,
            $this->allineaSegnaDimessi,
            $this->allineaPuliziaEstensioni,
        );

        $this->allineaPreviewDimessi = $ant['dimessi']->map(fn ($s) => [
            'cognome'  => $s->cognome,
            'nome'     => $s->nome,
            'cf'       => $s->codice_fiscale,
            'comitato' => $s->comitatoLocale?->nome ?? '—',
            'attive'   => (bool) $s->getAttribute('ha_iscrizioni_attive'),
        ])->all();

        $this->allineaPreviewEstensioni = $ant['estensioniDaRimuovere']->map(fn ($s) => [
            'cognome'  => $s->cognome,
            'nome'     => $s->nome,
            'cf'       => $s->codice_fiscale,
            'comitato' => $s->comitatoLocaleEstensione?->nome ?? '—',
        ])->all();

        $this->allineaPreviewRientri = $ant['rientri']->map(fn ($s) => [
            'cognome'  => $s->cognome,
            'nome'     => $s->nome,
            'cf'       => $s->codice_fiscale,
            'comitato' => $s->comitatoLocale?->nome ?? '—',
        ])->all();

        $this->allineaAnteprimaPronta = true;
    }

    public function confermaAllineamento(): void
    {
        if (! $this->allineaAnteprimaPronta) {
            return;
        }

        [$clId, $crId] = $this->allineaAmbitoIds();
        $svc = new SociAllineamentoService();
        $ant = $svc->anteprima(
            $this->allineaCfAppartenenti,
            $this->allineaCfEstensioni,
            $clId,
            $crId,
            $this->allineaSegnaDimessi,
            $this->allineaPuliziaEstensioni,
        );

        $this->allineaRisultato       = $svc->applica($ant);
        $this->allineaAnteprimaPronta = false;
        $this->allineaCompletato      = true;

        $body = "Dimessi: {$this->allineaRisultato['dimessi']}";
        if ($this->allineaRisultato['rientri'] > 0)    $body .= " · Riattivati (trasferiti): {$this->allineaRisultato['rientri']}";
        if ($this->allineaRisultato['estensioni'] > 0) $body .= " · Estensioni rimosse: {$this->allineaRisultato['estensioni']}";

        Notification::make()
            ->title('Allineamento soci completato')
            ->body($body)
            ->success()
            ->sendToDatabase(auth()->user());
    }

    /** Chiude il riepilogo finale (import e/o allineamento) e azzera tutto lo stato. */
    public function chiudiRiepilogoImport(): void
    {
        $this->importCompletato  = false;
        $this->importOffset      = 0;
        $this->importTotale      = 0;
        $this->importCreati      = 0;
        $this->importAggiornati  = 0;
        $this->importEstensioni  = 0;
        $this->importDipendenti  = 0;
        $this->importSaltati     = 0;
        $this->importErroriRun   = [];
        $this->importTipiSconosciutiRun = [];

        $this->resetStatoAllineamento();
    }

    /** Salta l'allineamento senza applicarlo (l'import è già stato eseguito). */
    public function annullaAllineamento(): void
    {
        $this->allineaAnteprimaPronta = false;
        // Lascia visibile il riepilogo import finché l'utente non lo chiude.
        $this->importCompletato = true;
        $this->resetStatoAllineamento();
    }

    private function resetStatoAllineamento(): void
    {
        $this->allineaAnteprimaPronta = false;
        $this->allineaCompletato      = false;
        $this->allineaPreviewDimessi    = [];
        $this->allineaPreviewEstensioni = [];
        $this->allineaPreviewRientri    = [];
        $this->allineaRisultato = ['dimessi' => 0, 'estensioni' => 0, 'rientri' => 0];
        $this->allineaCfAppartenenti = [];
        $this->allineaCfEstensioni   = [];
        $this->allineaFileContieneEstensioni = false;
        $this->allineaSegnaDimessi      = false;
        $this->allineaPuliziaEstensioni = false;
        $this->allineaAmbitoTipo        = 'locale';
        $this->allineaComitatoLocaleId  = null;
        $this->allineaRegioneId         = null;
    }

    public function annullaImport(): void
    {
        if ($this->importFilePath) {
            Storage::disk('local')->delete($this->importFilePath);
        }
        $this->importFilePath = null;

        $this->analisiInCorso     = false;
        $this->analisiPronta      = false;
        $this->analisiErrore      = null;
        $this->analisiOffset      = 0;
        $this->analisiTotaleRighe = 0;
        $this->analisiRigheLette  = 0;
        $this->analisiCreati      = 0;
        $this->analisiAggiornati  = 0;
        $this->analisiEstensioni  = 0;
        $this->analisiDipendenti  = 0;
        $this->analisiSaltati     = 0;
        $this->analisiErroriRun   = [];
        $this->analisiTipiSconosciutiRun = [];

        $this->resetStatoAllineamento();
    }
}
