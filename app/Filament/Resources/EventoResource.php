<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventoResource\Pages;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Evento;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EventoResource extends Resource
{
    protected static ?string $model = Evento::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestione operativa';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'eventi';

    protected static ?string $modelLabel = 'Evento';

    protected static ?string $pluralModelLabel = 'Eventi';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('eventi.view');
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('eventi.create');
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        if ($user?->isAdmin()) {
            return $record->comitato_regionale_id === $user->comitato_regionale_id;
        }
        if ($user?->isAdminLocale()) {
            return $record->comitato_locale_id === $user->comitato_locale_id;
        }
        // Operatore: deve essere assegnato all'evento E avere il permesso
        return $user?->can('eventi.edit')
            && $record->operatori->contains($user->id);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        if ($user?->isAdmin()) {
            return $record->comitato_regionale_id === $user->comitato_regionale_id;
        }
        if ($user?->isAdminLocale()) {
            return $record->comitato_locale_id === $user->comitato_locale_id;
        }
        return false;
    }

    /** Scope query per ruolo. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('operatori');
        $user  = auth()->user();

        if ($user?->isAdmin()) {
            // Admin regionale: tutti gli eventi della propria regione
            $query->where('comitato_regionale_id', $user->comitato_regionale_id);
        } elseif ($user?->isAdminLocale()) {
            // Admin locale: solo gli eventi del proprio comitato locale
            $query->where('comitato_locale_id', $user->comitato_locale_id);
        } elseif ($user?->isOperatore()) {
            // Operatore: solo gli eventi regionali (comitato_locale_id = null) a cui è assegnato
            $query->whereNull('comitato_locale_id')
                  ->whereHas('operatori', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query;
    }

    /** Oltre questo numero di giorni un singolo intervallo non si aggiunge più: si spezza in più aggiunte. */
    const MAX_GIORNI_INTERVALLO = 15;

    /** Da questo numero di giorni in su, un intervallo chiede conferma prima di aggiungersi. */
    const SOGLIA_CONFERMA_INTERVALLO = 5;

    /** true se "Dal"/"Al" sono compilati e "Al" non precede "Dal". Va controllato PRIMA di contare
     *  i giorni: un intervallo incoerente conta sempre 0 giorni, e quello non è un dato da mostrare
     *  come se fosse un conteggio normale — è un errore a sé, da segnalare per primo. */
    public static function intervalloCoerente(Get $get): bool
    {
        $inizio = $get('intervallo_da');
        $fine   = $get('intervallo_a');

        return filled($inizio) && filled($fine) && $fine >= $inizio;
    }

    /** Giorni dell'intervallo "Dal"/"Al" — 0 se incoerente o incompleto (mai usato per decidere
     *  se mostrare la modale senza aver prima passato da intervalloCoerente()). */
    public static function intervalloConteggio(Get $get): int
    {
        if (! static::intervalloCoerente($get)) return 0;

        return count(Evento::giorniRange($get('intervallo_da'), $get('intervallo_a')));
    }

    /**
     * Regola unica per ogni aggiunta di giorni (form evento, modale "Crea evento singolo"): nessun
     * duplicato (avviso se ne scarta), elenco sempre in ordine cronologico.
     */
    public static function riordinaGiorni(Get $get, Set $set, array $extra): void
    {
        $attuali = collect((array) $get('giorni_evento'))->filter();
        $uniche  = $attuali->merge($extra)->unique()->sort()->values();

        if ($uniche->count() < $attuali->count() + count($extra)) {
            Notification::make()->title('Alcune date erano già presenti: non vengono aggiunte due volte.')->warning()->send();
        }

        $set('giorni_evento', $uniche->all());
    }

    /**
     * Elenco "Giorni evento": pillole di sola lettura (rimozione con la X) + aggiunta di una data
     * singola o di un intervallo. Punto unico usato dal form evento e dalla modale "Crea evento
     * singolo" del corso. data_inizio/data_fine si ricavano da sole (Evento::saving).
     */
    public static function giorniEventoSchema(): array
    {
        return [
            // Stato reale: solo qui il valore viene salvato. Nessun input visibile — le uniche
            // interfacce di modifica sono le pillole e i due gruppi "Aggiungi" qui sotto.
            Forms\Components\Hidden::make('giorni_evento')
                ->default([])
                ->rule('min:1')
                ->validationMessages(['min' => 'Aggiungi almeno un giorno evento.']),

            Forms\Components\Placeholder::make('giorni_evento_vuoto')
                ->hiddenLabel()
                ->content('Nessun giorno aggiunto ancora.')
                ->visible(fn (Get $get) => blank($get('giorni_evento')))
                ->columnSpanFull(),

            // Ogni pillola è una coppia di pulsanti affiancati: la data è disabilitata (non
            // cancella nulla al click, serve solo da etichetta), solo la X rimuove il giorno —
            // altrimenti l'operatore non capisce che cliccare sulla data la elimina.
            SchemaActions::make(fn (Get $get) => collect($get('giorni_evento') ?? [])
                ->sort()
                ->flatMap(fn ($d) => [
                    // Coppia "stretta" tra loro (margine negativo copre il gap standard fra pulsanti)
                    // e con un margine extra dopo la X, così a colpo d'occhio si distingue quale
                    // coppia appartiene a quale X, anche con tante date centrali equidistanti.
                    Action::make('giorno_' . $d)
                        ->label(\Carbon\Carbon::parse($d)->translatedFormat('D d/m/Y'))
                        ->color('gray')
                        ->size('sm')
                        ->disabled(),
                    Action::make('rimuovi_giorno_' . $d)
                        ->icon('heroicon-o-x-mark')
                        ->iconButton()
                        ->tooltip('Rimuovi questo giorno')
                        ->color('gray')
                        ->size('sm')
                        ->extraAttributes(['style' => 'margin-left:-8px;margin-right:10px;'])
                        ->action(fn (Get $get, Set $set) => $set('giorni_evento',
                            collect($get('giorni_evento'))->reject(fn ($x) => $x === $d)->values()->all()
                        )),
                ])
                ->all())
                ->label('Giorni evento')
                ->visible(fn (Get $get) => filled($get('giorni_evento')))
                ->columnSpanFull(),

            // Entrambi i gruppi hanno la stessa struttura a 2 righe (picker sopra, pulsante centrato
            // sotto) così restano alti uguali indipendentemente dal numero di picker sulla prima riga.
            Group::make([
                Fieldset::make('Aggiungi singola data')
                    ->columns(1)
                    ->schema([
                        Forms\Components\DatePicker::make('giorno_singolo')->hiddenLabel()->native(false)->displayFormat('d/m/Y')->dehydrated(false),
                        SchemaActions::make([
                            Action::make('aggiungi_giorno')
                                ->label('Aggiungi singola data')
                                ->icon('heroicon-o-plus')
                                ->color('info')
                                ->action(function (Get $get, Set $set) {
                                    if (blank($get('giorno_singolo'))) return;

                                    static::riordinaGiorni($get, $set, [$get('giorno_singolo')]);
                                    $set('giorno_singolo', null);
                                }),
                        ])->alignment(Alignment::Center),
                    ]),

                Fieldset::make('Aggiungi intervallo')
                    ->columns(1)
                    ->schema([
                        Group::make([
                            // hiddenLabel + placeholder invece di ->label(): l'etichetta sopra il
                            // campo aggiungerebbe una riga in più, disallineando questo gruppo da
                            // "Aggiungi singola data" (che non ha etichetta sopra il suo picker).
                            Forms\Components\DatePicker::make('intervallo_da')->hiddenLabel()->placeholder('Dal')->native(false)->displayFormat('d/m/Y')->dehydrated(false),
                            Forms\Components\DatePicker::make('intervallo_a')->hiddenLabel()->placeholder('Al')->native(false)->displayFormat('d/m/Y')->dehydrated(false),
                        ])->columns(2),
                        SchemaActions::make([
                            Action::make('aggiungi_intervallo')
                                ->label('Aggiungi intervallo')
                                ->icon('heroicon-o-plus')
                                ->color('info')
                                // La modale si apre solo per un intervallo coerente e "grande" (>5gg):
                                // uno incoerente dà l'errore subito in action(), senza passare da qui —
                                // mostrare "stai per aggiungere 0 giorni, confermi?" per un errore
                                // sarebbe fuorviante (il conteggio è 0 solo perché l'intervallo è invertito).
                                ->requiresConfirmation(fn (Get $get) => static::intervalloConteggio($get) > static::SOGLIA_CONFERMA_INTERVALLO)
                                // Sopra il limite massimo la modale è un avviso bloccante, non una
                                // richiesta di conferma: niente pulsante "Sì, aggiungi", solo "Chiudi".
                                ->modalHeading(fn (Get $get) => static::intervalloConteggio($get) > static::MAX_GIORNI_INTERVALLO ? 'Intervallo troppo ampio' : 'Intervallo ampio')
                                ->modalDescription(function (Get $get) {
                                    $conteggio = static::intervalloConteggio($get);

                                    return $conteggio > static::MAX_GIORNI_INTERVALLO
                                        ? "Questo intervallo è di {$conteggio} giorni: il limite è di " . static::MAX_GIORNI_INTERVALLO . " giorni consecutivi per una singola aggiunta. Spezza l'intervallo in più aggiunte più corte."
                                        : "Stai per aggiungere {$conteggio} giorni in un colpo solo. Confermi?";
                                })
                                // La closure deve restituire l'azione di default o false: un `true` è un errore di tipo.
                                ->modalSubmitAction(fn (Get $get, $action) => static::intervalloConteggio($get) <= static::MAX_GIORNI_INTERVALLO ? $action : false)
                                ->modalSubmitActionLabel('Sì, aggiungi')
                                ->modalCancelActionLabel(fn (Get $get) => static::intervalloConteggio($get) > static::MAX_GIORNI_INTERVALLO ? 'Chiudi' : 'Annulla')
                                ->action(function (Get $get, Set $set) {
                                    $inizio = $get('intervallo_da');
                                    $fine   = $get('intervallo_a');

                                    if (blank($inizio) || blank($fine)) return;

                                    if (! static::intervalloCoerente($get)) {
                                        Notification::make()->title('Intervallo non coerente: la data "Al" precede la data "Dal".')->danger()->send();
                                        return;
                                    }

                                    $giorni = Evento::giorniRange($inizio, $fine);

                                    if (count($giorni) > static::MAX_GIORNI_INTERVALLO) {
                                        Notification::make()
                                            ->title('Intervallo troppo ampio: massimo ' . static::MAX_GIORNI_INTERVALLO . ' giorni per volta.')
                                            ->body('Spezza l\'aggiunta in più intervalli più corti.')
                                            ->danger()
                                            ->send();
                                        return;
                                    }

                                    static::riordinaGiorni($get, $set, $giorni);
                                    $set('intervallo_da', null);
                                    $set('intervallo_a', null);
                                }),
                        ])->alignment(Alignment::Center),
                    ]),
            ])->columns(2)->columnSpanFull(),
        ];
    }

    public static function form(Schema $form): Schema
    {
        $authUser = auth()->user();

        // Comitati locali per admin_locale (o tutti per superadmin/admin)
        $localiQuery = ComitatoLocale::orderBy('nome');
        if ($authUser?->isAdmin()) {
            $localiQuery->where('comitato_regionale_id', $authUser->comitato_regionale_id);
        } elseif ($authUser?->isAdminLocale()) {
            $localiQuery->where('id', $authUser->comitato_locale_id);
        }
        $localiOptions = $localiQuery->pluck('nome', 'id');

        // Filament forza ->columns(2) di default sul form intero (EditRecord::defaultForm())
        // a meno che la risorsa non lo imposti esplicitamente — senza questa riga le Section
        // di primo livello finiscono comunque affiancate a due colonne, vanificando la scelta
        // "Section sempre a colonna singola" (vedi regola layout form).
        return $form->columns(1)->schema([

            // ── 1. Informazioni generali (sempre aperta: qui vivono tutti i campi
            //      obbligatori del form, indipendentemente dalla loro natura — identità,
            //      territorio, date, luogo. Sotto-gruppi via Group per concettualizzare
            //      senza spezzarli in Section separate, vedi regola layout form) ─────
            Section::make('Informazioni generali')->schema([
                Forms\Components\TextInput::make('nome')
                    ->label('Nome evento')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Group::make([
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
                        ->label('Comitato Locale')
                        ->options($localiOptions)
                        ->default($authUser?->isAdminLocale() ? $authUser->comitato_locale_id : null)
                        ->nullable()
                        ->searchable()
                        ->disabled($authUser?->isAdminLocale())
                        ->dehydrated(true)
                        ->hidden($authUser?->isOperatore())
                        ->helperText('Lascia vuoto per un evento regionale. Valorizza per un evento locale visibile solo all\'admin locale.'),
                ])->columns(2),

                Forms\Components\Textarea::make('descrizione')
                    ->label('Descrizione')
                    ->rows(3)
                    ->columnSpanFull(),

                ...static::giorniEventoSchema(),

                Group::make([
                    Forms\Components\TextInput::make('sede')
                        ->label('Sede')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('indirizzo')
                        ->label('Indirizzo')
                        ->required()
                        ->maxLength(500),
                ])->columns(2),

                Forms\Components\TextInput::make('coordinate_maps')
                    ->label('Coordinate Google Maps')
                    ->placeholder('es. 45.464664, 9.188540')
                    ->helperText('Latitudine e longitudine separate da virgola. Puoi anche incollare un link di Google Maps: le coordinate vengono estratte automaticamente (non funziona con i link brevi maps.app.goo.gl).')
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if (! $state || ! str_contains($state, 'google.com/maps') && ! str_contains($state, 'goo.gl/maps')) {
                            return;
                        }

                        if (preg_match('/(-?\d{1,3}\.\d+),\s*(-?\d{1,3}\.\d+)/', $state, $matches)) {
                            $set('coordinate_maps', "{$matches[1]},{$matches[2]}");
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('attivo')
                    ->label('Evento attivo')
                    ->default(true)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('residenziale')
                    ->label('Evento residenziale (pernotto previsto per lo staff)')
                    ->helperText('Se disattivato, cena e pernotto non sono proposti allo staff in candidatura/pre-registrazione — solo l\'operatore potrà aggiungerli a mano per casi eccezionali. Non influisce sui corsisti, governati dal flag "residenziale" del singolo corso.')
                    ->default(true)
                    ->columnSpanFull(),

                // Selezioni multiple: altezza variabile in base al numero di elementi scelti,
                // non vanno mai appaiate in colonna con un'altra (vedi regola layout form).
                Forms\Components\Select::make('referenti')
                    ->label('Referenti regionali')
                    ->helperText('Mostrati nella gestione evento e inclusi nelle email tramite {referenti}.')
                    ->relationship(
                        name: 'referenti',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query) use ($authUser) {
                            $query->where('ruolo', 'admin')->where('attivo', true);
                            if ($authUser?->isAdmin()) {
                                $query->where('comitato_regionale_id', $authUser->comitato_regionale_id);
                            }
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' — ' . $record->email)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->placeholder('Digita per cercare un admin regionale…')
                    ->columnSpanFull(),

                Forms\Components\Select::make('operatori')
                    ->label('Operatori assegnati')
                    ->helperText('Potranno accedere agli strumenti di questo evento.')
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
                        . ($record->comitatoLocale?->nome
                            ? ' — ' . $record->comitatoLocale->nome
                            : ($record->comitatoRegionale?->nome ? ' — ' . $record->comitatoRegionale->nome : ''))
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->placeholder('Digita per cercare un operatore…')
                    ->columnSpanFull(),

                Forms\Components\Repeater::make('orario_campo')
                    ->label('Orario campo')
                    ->helperText('Schema giornaliero delle attività (salvato in formato JSON).')
                    ->schema([
                        Forms\Components\TextInput::make('giorno')
                            ->label('Giorno / Fascia')
                            ->placeholder('es. Lunedì, Tutti i giorni…')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('orario')
                            ->label('Orario')
                            ->placeholder('es. 09:00 – 22:30')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('note')
                            ->label('Note (opzionale)')
                            ->maxLength(255),
                    ])
                    ->columns(3)
                    ->itemLabel(fn (array $state) => trim(($state['giorno'] ?? '') . ' ' . ($state['orario'] ?? '')))
                    ->collapsible()
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]),

            // ── 4. Contenuti e documenti ────────────────────────────────────
            Section::make('Contenuti e documenti')->collapsed()->schema([

                // Intestazione app
                Forms\Components\Placeholder::make('intestazione_app_attuale')
                    ->label('Intestazione app attuale')
                    ->content(function ($record) {
                        $media = $record?->getFirstMedia('intestazione_app');
                        if (! $media) {
                            return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af;font-size:13px;">Nessuna immagine caricata.</span>');
                        }
                        return new \Illuminate\Support\HtmlString(
                            '<div style="display:flex;align-items:center;gap:12px;">'
                            . static::mediaImgTag($media)
                            . '<span style="font-size:13px;color:#6b7280;">' . e($media->file_name) . '</span>'
                            . '<button type="button"'
                            . ' wire:click="eliminaMedia(\'intestazione_app\')"'
                            . ' wire:confirm="Rimuovere l\'immagine di intestazione?"'
                            . ' style="color:#dc2626;font-size:12px;background:none;border:1px solid #fca5a5;border-radius:4px;cursor:pointer;padding:2px 10px;">'
                            . 'Rimuovi</button>'
                            . '</div>'
                        );
                    })
                    ->visible(fn ($record) => $record !== null)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('intestazione_app_nuova')
                    ->label('Immagine intestazione app')
                    ->helperText('Opzionale. Immagine mostrata come header nella pagina informazioni dell\'app. Formati consigliati: PNG o JPG, orientamento landscape.')
                    ->image()
                    ->disk('public')
                    ->directory('intestazione-app-tmp')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(10240)
                    ->dehydrated(false)
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('_nota_comunicazione')
                    ->hiddenLabel()
                    ->content(new \Illuminate\Support\HtmlString(
                        '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;font-size:13px;color:#1e40af;line-height:1.5;">'
                        . '<strong>ℹ️ Comunicazione evento</strong><br>'
                        . 'Il testo e i documenti inseriti qui vengono usati anche per inviare la <strong>comunicazione informativa</strong> via email a staff e iscritti (dalla pagina di gestione evento e dallo staff). '
                        . 'I pulsanti di invio sono attivi solo se è presente almeno del testo o un documento allegato.'
                        . '</div>'
                    ))
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('note_app')
                    ->label('Testo comunicazione / Note per l\'app')
                    ->helperText('Testo visualizzato nell\'app mobile e inviato via email come comunicazione informativa. Puoi includere indicazioni su come raggiungere la sede, regolamento, programma, ecc.')
                    ->toolbarButtons([
                        'h2',
                        'h3',
                        'paragraph',
                        'bold',
                        'italic',
                        'underline',
                        'strike',
                        'bulletList',
                        'orderedList',
                        'blockquote',
                        'link',
                        'horizontalRule',
                        'table',
                        'tableAddColumnBefore',
                        'tableAddColumnAfter',
                        'tableDeleteColumn',
                        'tableAddRowBefore',
                        'tableAddRowAfter',
                        'tableDeleteRow',
                        'tableDelete',
                        'undo',
                        'redo',
                    ])
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('documenti_esistenti')
                    ->label('Documenti allegati')
                    ->content(function ($record) {
                        if (! $record) {
                            return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af;font-size:13px;">Salva l\'evento per caricare i documenti.</span>');
                        }
                        $media = $record->getMedia('documenti');
                        if ($media->isEmpty()) {
                            return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af;font-size:13px;">Nessun documento caricato.</span>');
                        }
                        $html = '<div style="display:flex;flex-direction:column;gap:6px;">';
                        foreach ($media as $m) {
                            $previewUrl = route('eventi.documento-preview', ['evento' => $record->id, 'media' => $m->id]);
                            $html .= '<div style="display:flex;align-items:center;justify-content:space-between;padding:7px 12px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;">';
                            $html .= '<span style="font-size:13px;color:#374151;">📎 ' . e($m->file_name) . '</span>';
                            $html .= '<div style="display:flex;gap:6px;align-items:center;">';
                            $html .= '<a href="' . e($previewUrl) . '" target="_blank"'
                                . ' style="color:#2563eb;font-size:12px;background:none;border:1px solid #bfdbfe;border-radius:4px;cursor:pointer;padding:2px 10px;text-decoration:none;">'
                                . 'Apri</a>';
                            $html .= '<button type="button"'
                                . ' wire:click="eliminaDocumento(' . $m->id . ')"'
                                . ' wire:confirm="Eliminare \'' . e($m->file_name) . '\'? L\'operazione non è reversibile."'
                                . ' style="color:#dc2626;font-size:12px;background:none;border:1px solid #fca5a5;border-radius:4px;cursor:pointer;padding:2px 10px;">'
                                . 'Elimina</button>';
                            $html .= '</div>';
                            $html .= '</div>';
                        }
                        $html .= '</div>';
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('documenti_nuovi')
                    ->label('Aggiungi documenti')
                    ->helperText('PDF, immagini relativi all\'evento (programma, mappa, regolamento, ecc.).')
                    ->multiple()
                    ->disk('public')
                    ->directory('documenti-tmp')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'])
                    ->maxSize(20480)
                    ->dehydrated(false)
                    ->visible(fn ($record) => $record !== null)
                    ->columnSpanFull(),
            ]),

            // ── 5. Camere e pernotti ────────────────────────────────────────
            Section::make('Camere e pernotti')
                ->description('Definisci le camere/camerate disponibili per ogni notte dell\'evento. Ogni camera ha un\'etichetta e un numero di posti.')
                ->columnSpanFull()
                ->collapsed()
                ->visible(fn ($record) => $record?->data_inizio && $record?->data_fine)
                ->schema([
                    Forms\Components\Repeater::make('camere')
                        ->relationship('camere')
                        ->defaultItems(0)
                        ->schema([
                            Forms\Components\Select::make('data')
                                ->label('Notte')
                                ->options(fn ($livewire) => static::nottiEvento($livewire->record ?? null))
                                ->afterStateHydrated(fn ($state, $set) => $state ? $set('data', \Carbon\Carbon::parse($state)->format('Y-m-d')) : null)
                                ->required()
                                ->native(false)
                                ->searchable()
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('nome')
                                ->label('Nome / Etichetta')
                                ->placeholder('Es. Camera Rossa, Camerata B, Tende Nord…')
                                ->required()
                                ->maxLength(100)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('posti')
                                ->label('N° posti')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->suffix('posti')
                                ->columnSpan(1),
                        ])
                        ->columns(3)
                        ->addActionLabel('Aggiungi camera')
                        ->reorderable(false)
                        ->itemLabel(function (array $state): string {
                            $parts = [];

                            if (filled($state['data'])) {
                                $d = \Carbon\Carbon::parse($state['data']);
                                $parts[] = 'Notte ' . $d->format('d/m') . ' → ' . $d->copy()->addDay()->format('d/m');
                            }

                            if (filled($state['nome'])) {
                                $parts[] = $state['nome'];
                            }

                            if (filled($state['posti'])) {
                                $parts[] = $state['posti'] . ' posti';
                            }

                            return $parts ? implode(' · ', $parts) : 'Nuova camera';
                        })
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

            // ── 6. Override privacy (avanzato) ──────────────────────────────
            Section::make('Override privacy')
                ->description('Lascia vuoto per usare i template globali/regionali. Compila solo se questo evento richiede testi o PDF differenti.')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('privacy_override_informativa')
                        ->label('Testo informativa dati (override)')
                        ->rows(6)
                        ->columnSpanFull()
                        ->placeholder('Lascia vuoto per usare il template predefinito.'),

                    Forms\Components\FileUpload::make('privacy_override_informativa_pdf')
                        ->label('PDF informativa dati (override)')
                        ->acceptedFileTypes(['application/pdf'])
                        ->disk('public')
                        ->directory('privacy-override')
                        ->downloadable()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('privacy_override_foto_video')
                        ->label('Testo consenso foto/video (override)')
                        ->rows(4)
                        ->columnSpanFull()
                        ->placeholder('Lascia vuoto per usare il template predefinito.'),

                    Forms\Components\FileUpload::make('privacy_override_foto_video_pdf')
                        ->label('PDF consenso foto/video (override)')
                        ->acceptedFileTypes(['application/pdf'])
                        ->disk('public')
                        ->directory('privacy-override')
                        ->downloadable()
                        ->columnSpanFull(),
                ]),

            // ── Form staff (pre-registrazione) ──────────────────────────────
            Section::make('Form staff')
                ->description('Il form di pre-registrazione permette ai membri dello staff già confermati di completare i propri dati tramite un link personale. Verifica che i dati dell\'evento siano definitivi prima di attivarlo.')
                ->collapsed()
                ->visible(fn (?Model $record) => $record !== null)
                ->schema([
                    Forms\Components\Placeholder::make('form_staff_stato')
                        ->label('Stato')
                        ->content(fn (?Model $record) => $record?->form_staff_aperto_at
                            ? new \Illuminate\Support\HtmlString('<span style="color:#15803d;font-weight:600;">Aperto</span> dal ' . $record->form_staff_aperto_at->format('d/m/Y'))
                            : new \Illuminate\Support\HtmlString('<span style="color:#6b7280;font-weight:600;">Chiuso</span>')),

                    \Filament\Schemas\Components\Actions::make([
                        \Filament\Actions\Action::make('toggle_form_staff')
                            ->label(fn (?Model $record) => $record?->form_staff_aperto_at ? 'Chiudi form' : 'Attiva form')
                            ->icon(fn (?Model $record) => $record?->form_staff_aperto_at ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                            ->color(fn (?Model $record) => $record?->form_staff_aperto_at ? 'gray' : 'success')
                            ->requiresConfirmation()
                            ->modalHeading(fn (?Model $record) => $record?->form_staff_aperto_at ? 'Chiudi form staff?' : 'Attiva form staff?')
                            ->modalDescription(fn (?Model $record) => $record?->form_staff_aperto_at
                                ? 'Il form non sarà più accessibile ai membri dello staff. I dati già compilati restano salvati.'
                                : 'Il form diventerà accessibile ai membri dello staff tramite il loro link personale.')
                            ->modalSubmitActionLabel(fn (?Model $record) => $record?->form_staff_aperto_at ? 'Chiudi form' : 'Attiva form')
                            ->action(function (?Model $record, $livewire) {
                                if ($record->form_staff_aperto_at) {
                                    $record->update(['form_staff_aperto_at' => null]);
                                    Notification::make()->title('Form staff chiuso.')->warning()->send();
                                } else {
                                    $record->update(['form_staff_aperto_at' => now()]);
                                    Notification::make()->title('Form staff attivato.')->success()->send();
                                }
                                $livewire->refreshFormData(['form_staff_aperto_at']);
                            }),
                    ]),
                ])->columns(1),

            // ── Candidature staff (avanzato) ────────────────────────────────
            Section::make('Candidature staff')
                ->description('Se configurato, apre un form pubblico dove chi vuole candidarsi come staff può iscriversi da solo, entro la finestra di date qui sotto. Le candidature vanno poi confermate/rifiutate dalla pagina "Candidature staff" dell\'evento.')
                ->collapsed()
                ->schema([
                    Group::make([
                        Forms\Components\DateTimePicker::make('candidature_staff_apertura_at')
                            ->label('Apertura candidature')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i'),

                        Forms\Components\DateTimePicker::make('candidature_staff_chiusura_at')
                            ->label('Chiusura candidature')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->afterOrEqual('candidature_staff_apertura_at'),
                    ])->columns(2),

                    Forms\Components\Placeholder::make('candidature_staff_link')
                        ->label('Link pubblico candidatura')
                        ->content(fn ($record) => $record?->candidature_staff_uuid
                            ? new \Illuminate\Support\HtmlString('<span style="font-family:monospace;font-size:12px;">' . e(route('candidatura-staff.mostra', $record->candidature_staff_uuid)) . '</span>')
                            : '—')
                        ->visible(fn ($record) => $record !== null)
                        ->columnSpanFull(),
                ]),

            // ── 6. Badge (avanzato) ─────────────────────────────────────────
            Section::make('Badge')
                ->description('Immagine di sfondo e posizionamento degli elementi per la stampa dei badge. Le coordinate sono in mm dal bordo superiore-sinistro del badge (61×97mm).')
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('badge_sfondo_info')
                        ->label('Sfondo attuale')
                        ->content(function ($record) {
                            $media = $record?->getFirstMedia('badge_sfondo');
                            if (! $media) {
                                return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af;font-size:13px;">Nessun sfondo caricato.</span>');
                            }
                            return new \Illuminate\Support\HtmlString(
                                '<div style="display:flex;align-items:center;gap:12px;">'
                                . static::mediaImgTag($media)
                                . '<span style="font-size:13px;color:#6b7280;">' . e($media->file_name) . '</span>'
                                . '<button type="button"'
                                . ' wire:click="eliminaMedia(\'badge_sfondo\')"'
                                . ' wire:confirm="Rimuovere lo sfondo del badge?"'
                                . ' style="color:#dc2626;font-size:12px;background:none;border:1px solid #fca5a5;border-radius:4px;cursor:pointer;padding:2px 10px;">'
                                . 'Rimuovi</button>'
                                . '</div>'
                            );
                        })
                        ->visible(fn ($record) => $record !== null)
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('badge_sfondo_nuovo')
                        ->label('Carica sfondo badge (PNG/JPG)')
                        ->image()
                        ->disk('public')
                        ->directory('badge-tmp')
                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                        ->maxSize(5120)
                        ->helperText('Dimensione consigliata: 610×970px (rapporto 1:1.59, orientamento portrait).')
                        ->dehydrated(false)
                        ->visible(fn ($record) => $record !== null)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('badge_config.titolo_evento.attivo')
                        ->label('Stampa il titolo evento sul badge')
                        ->helperText('Da attivare solo se lo sfondo caricato NON ha già il titolo disegnato al suo interno (es. template regionale vuoto) — altrimenti il testo verrebbe stampato due volte.')
                        ->default(false)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('badge_config.qr_checkin_in_email')
                        ->label('Includi il QR di check-in nella comunicazione evento via email')
                        ->helperText('Se attivo, la comunicazione evento inviata via email a iscritti e staff mostra in coda al testo il QR code personale per il check-in (immagine nel corpo della mail, non un allegato) con un promemoria per conservare la mail e mostrarlo all\'arrivo, in entrata e in uscita. È lo stesso QR del badge stampato.')
                        ->default(false)
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('badge_config_spiegazione')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="font-size:12.5px; color:#6b7280; line-height:1.5;">'
                            . '<strong>Titolo evento / Nome-Cognome / Protocollo</strong> — il testo è centrato: <strong>X</strong> è il margine dal bordo sinistro <em>e</em> dal bordo destro (aumentandolo il testo diventa più stretto e va più facilmente a capo, occupando più <strong>altezza</strong> verso il basso); <strong>Y</strong> è la distanza dal bordo superiore a cui inizia il testo — se il testo va a capo, l\'altezza occupata cresce a partire da lì, verso il basso.<br>'
                            . '<strong>QR Code</strong> — qui X e Y sono l\'angolo in alto a sinistra del codice (non centrato): X = distanza dal bordo sinistro, Y = distanza dal bordo superiore; <strong>Dimensione</strong> è sia la larghezza sia l\'altezza (il QR è sempre quadrato).'
                            . '</div>'
                        ))
                        ->columnSpanFull(),

                    // 12 campi numerici brevi, stesso componente, stessa altezza — sicuri da
                    // affiancare su più colonne (vedi regola layout form).
                    Group::make([
                        Forms\Components\TextInput::make('badge_config.titolo_evento.x')
                            ->label('Titolo evento — X, margine ai lati (mm)')
                            ->numeric()->minValue(0)->maxValue(30)->step(0.5)->default(4),

                        Forms\Components\TextInput::make('badge_config.titolo_evento.y')
                            ->label('Titolo evento — Y, dall\'alto (mm)')
                            ->numeric()->minValue(0)->maxValue(97)->step(0.5)->default(25),

                        Forms\Components\TextInput::make('badge_config.titolo_evento.font_size')
                            ->label('Titolo evento — Font (pt)')
                            ->numeric()->minValue(6)->maxValue(36)->default(14),

                        Forms\Components\TextInput::make('badge_config.nome_cognome.x')
                            ->label('Nome/Cognome — X, margine ai lati (mm)')
                            ->numeric()->minValue(0)->maxValue(30)->step(0.5)->default(5),

                        Forms\Components\TextInput::make('badge_config.nome_cognome.y')
                            ->label('Nome/Cognome — Y, dall\'alto (mm)')
                            ->numeric()->minValue(0)->maxValue(97)->step(0.5)->default(58),

                        Forms\Components\TextInput::make('badge_config.nome_cognome.font_size')
                            ->label('Nome/Cognome — Font (pt)')
                            ->numeric()->minValue(6)->maxValue(36)->default(14),

                        Forms\Components\TextInput::make('badge_config.corso_ruolo.x')
                            ->label('Protocollo — X, margine ai lati (mm)')
                            ->numeric()->minValue(0)->maxValue(30)->step(0.5)->default(5),

                        Forms\Components\TextInput::make('badge_config.corso_ruolo.y')
                            ->label('Protocollo — Y, dall\'alto (mm)')
                            ->numeric()->minValue(0)->maxValue(97)->step(0.5)->default(52),

                        Forms\Components\TextInput::make('badge_config.corso_ruolo.font_size')
                            ->label('Protocollo — Font (pt)')
                            ->numeric()->minValue(6)->maxValue(36)->default(11),

                        Forms\Components\TextInput::make('badge_config.qr.x')
                            ->label('QR Code — X, dal bordo sinistro (mm)')
                            ->numeric()->minValue(0)->maxValue(51)->step(0.5)->default(16),

                        Forms\Components\TextInput::make('badge_config.qr.y')
                            ->label('QR Code — Y, dal bordo superiore (mm)')
                            ->numeric()->minValue(0)->maxValue(87)->step(0.5)->default(66),

                        Forms\Components\TextInput::make('badge_config.qr.size')
                            ->label('QR Code — Larghezza e altezza (mm)')
                            ->numeric()->minValue(10)->maxValue(60)->default(28),
                    ])->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                // canViewAny() include l'operatore con solo eventi.view; canCreate() richiede
                // in più eventi.create — senza ->visible() qui il pulsante comparirebbe comunque.
                CreateAction::make()
                    ->color('success')
                    ->visible(fn () => static::canCreate()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('_gestione')
                    ->label('')
                    ->getStateUsing(fn () => 'Gestione')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-chart-bar'),

                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tipo_evento')
                    ->label('Tipo')
                    ->getStateUsing(fn ($record) => $record->comitato_locale_id ? 'Locale' : 'Regionale')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Locale' ? 'info' : 'gray')
                    ->visible(fn () => ! auth()->user()?->isAdminLocale()),

                Tables\Columns\TextColumn::make('comitatoRegionale.nome')
                    ->label('Regione')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => ! auth()->user()?->isAdminLocale()),

                Tables\Columns\TextColumn::make('comitatoLocale.nome')
                    ->label('Comitato Locale')
                    ->searchable()
                    ->sortable()
                    ->default('—')
                    ->visible(fn () => auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()),

                Tables\Columns\TextColumn::make('data_inizio')
                    ->label('Dal')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('data_fine')
                    ->label('Al')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sede')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('corsi_count')
                    ->counts('corsi')
                    ->label('Corsi')
                    ->sortable(),

                Tables\Columns\IconColumn::make('attivo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('comitato_regionale_id')
                    ->label('Regione')
                    ->relationship('comitatoRegionale', 'nome')
                    ->visible(fn () => auth()->user()?->isSuperadmin()),

                Tables\Filters\TernaryFilter::make('attivo')
                    ->label('Stato'),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->visible(fn (Evento $record) => static::canEdit($record)),
                ActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (Evento $record) => static::canDelete($record)
                            && $record->corsi()->doesntExist()
                            && $record->iscrizioniStaff()->doesntExist())
                        ->modalDescription(fn (Evento $record) => 'Stai per eliminare: ' . $record->nome)
                        ->before(function (DeleteAction $action, Evento $record) {
                            if ($record->hasDatiCorrelati()) {
                                Notification::make()
                                    ->title('Eliminazione non consentita')
                                    ->body('Questo evento contiene corsi con dati compilati (form di preregistrazione, consensi o checkin). Per eliminarlo definitivamente contatta un amministratore di sistema.')
                                    ->danger()
                                    ->persistent()
                                    ->send();
                                $action->halt();
                            }
                        }),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->recordUrl(fn (Evento $record) => static::getUrl('gestione', ['record' => $record]))
            ->defaultSort('data_inizio', 'desc');
    }

    /**
     * Tab bar (Top) per le pagine legate allo stesso evento — sostituisce i pulsanti di
     * navigazione sparsi tra header actions (GestioneEvento) e link HTML manuali nella blade,
     * come da convenzione "Navigazione a pagine legate allo stesso record".
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        $routeBase = static::getRouteBaseName();
        $record    = $page->getRecord();

        $tab = fn (string $route, string $label, string $icon) => NavigationItem::make($label)
            ->icon($icon)
            ->url(static::getUrl($route, ['record' => $record]))
            ->isActiveWhen(fn () => request()->routeIs("{$routeBase}.{$route}"));

        return [
            // "Modifica" NON è una tab: non è una pagina "sorella" dell'hub come Staff/
            // Presenze/ecc., è la modifica diretta del record — resta un'azione (vedi
            // GestioneEvento::getHeaderActions()), stesso standard usato altrove nel progetto.
            $tab('gestione', 'Gestione', 'heroicon-o-chart-bar'),

            // Raggruppate per tema (stesso ->group() label = un solo tab a tendina): riduce
            // gli 8 tab originali a 5 voci di primo livello, tab-bar più stretta e leggibile.
            $tab('staff', 'Gestione staff', 'heroicon-o-user-group')->group('Staff'),
            $tab('candidature-staff', 'Candidature', 'heroicon-o-clipboard-document-check')->group('Staff'),

            $tab('partecipanti', 'Partecipanti', 'heroicon-o-users'),

            $tab('presenze-live', 'Presenze live', 'heroicon-o-signal')->group('Presenze'),
            $tab('checkin-risposte', 'Risposte check-in', 'heroicon-o-qr-code')->group('Presenze'),
            $tab('presenze-effettive', 'Presenze effettive', 'heroicon-o-clipboard-document-list')->group('Presenze'),

            $tab('report', 'Report', 'heroicon-o-document-chart-bar'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'    => Pages\ListEventi::route('/'),
            'create'   => Pages\CreateEvento::route('/create'),
            'edit'     => Pages\EditEvento::route('/{record}/edit'),
            'gestione'       => Pages\GestioneEvento::route('/{record}/gestione'),
            'staff'          => Pages\GestioneStaff::route('/{record}/staff'),
            'candidature-staff' => Pages\GestioneCandidatureStaff::route('/{record}/candidature-staff'),
            'partecipanti'   => Pages\PartecipantiEvento::route('/{record}/partecipanti'),
            'presenze-live'       => Pages\PresenzeLive::route('/{record}/presenze-live'),
            'checkin-risposte'    => Pages\CheckinRisposteEvento::route('/{record}/checkin-risposte'),
            'presenze-effettive'  => Pages\GestionePresenzeEffettive::route('/{record}/presenze-effettive'),
            'report'              => Pages\ReportEvento::route('/{record}/report'),
        ];
    }

    /**
     * Genera un tag <img> con src data URI leggendo il file da disco.
     * Evita dipendenza dal symlink storage:link (necessario su Windows dev).
     */
    public static function nottiEvento(?\App\Models\Evento $evento): array
    {
        if (! $evento?->exists) {
            return [];
        }

        // Stesse notti dei pernotti offerti ovunque (giorni reali + giorno prima di ogni blocco),
        // non un range data_inizio→data_fine.
        return collect(\App\Services\EventoSezioniGenerator::perGiorni($evento, bypassResidenziale: true))
            ->where('tipo', 'pernotto')
            ->mapWithKeys(function ($s) {
                $d = \Carbon\Carbon::parse($s['data']);

                return [$s['data'] => 'Notte ' . $d->format('d/m') . ' → ' . $d->copy()->addDay()->format('d/m')];
            })
            ->all();
    }

    public static function mediaImgTag(\Spatie\MediaLibrary\MediaCollections\Models\Media $media, string $style = 'height:80px;border-radius:6px;border:1px solid #e5e7eb;object-fit:contain;background:#f9fafb;'): string
    {
        $path = $media->getPath();

        if (! file_exists($path)) {
            return '<span style="font-size:13px;color:#9ca3af;">File non trovato sul disco.</span>';
        }

        $src = 'data:' . $media->mime_type . ';base64,' . base64_encode(file_get_contents($path));

        return '<img src="' . $src . '" style="' . $style . '">';
    }
}
