<?php

namespace App\Filament\Resources\CorsoResource\Pages;

use App\Filament\Resources\CorsoResource;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;
use App\Filament\Resources\EventoResource\Pages\GestioneEvento;
use App\Imports\IscrittiImport;
use App\Mail\FormPreregistrazioneLink;
use App\Mail\InfoEventoIscritto;
use App\Mail\SollecitoPreregistrazioneLink;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\ConflittoIscrizione;
use App\Models\Corso;
use App\Models\EmailTemplate;
use App\Models\Iscrizione;
use App\Models\Presenza;
use App\Models\Socio;
use App\Models\SocioEvento;
use App\Services\SostituzioneDocumento;
use App\Services\TemplatePlaceholders;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Attributes\Locked;

class GestioneCorso extends Page
{
    protected static string $resource = CorsoResource::class;

    protected string $view = 'filament.resources.corso-resource.pages.gestione-corso';

    protected static ?string $title = 'Gestione corso';

    public function getTitle(): string
    {
        $protocollo = $this->record->numero_protocollo;

        return $protocollo ? 'Gestione corso — ' . $protocollo : 'Gestione corso';
    }

    #[Locked]
    public Corso $record;

    /**
     * Il protocollo (non il nome, spesso lungo o poco leggibile) identifica il corso
     * nel breadcrumb — stesso criterio usato in EditCorso::getBreadcrumbs().
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = $this->getResourceBreadcrumbs();
        $breadcrumbs[] = $this->record->numero_protocollo ?: $this->record->nome;

        return $breadcrumbs;
    }

    /** Map iscrizione_id => [ ['corso' => nome, 'date' => '...'], ... ] */
    public array $conflittiPerIscrizione = [];

    /** Risultato dry-run import: null = nessuna preview attiva */
    public ?array $importPreview = null;
    /** Path temp del file Excel da importare dopo conferma */
    public ?string $importFilePath = null;
    /** Flag sync da usare nella conferma */
    public bool $importSync = false;
    /** Iscritti che verrebbero rimossi se sync attivo: [{nome, cf}] */
    public array $importRimossi = [];

    /** Set di partecipante_id con almeno un accesso all'evento (check-in effettuato) */
    public array $checkinIds = [];

    /** True se oggi è il giorno di inizio evento o successivo */
    public bool $eventoIniziato = false;

    public function mount(Corso $record): void
    {
        abort_unless(CorsoResource::puoGestireCorso($record), 403);

        $this->record = $record->load([
            'evento.comitatoLocale',
            'evento.comitatoRegionale',
            'iscrizioni.partecipante.comitatoLocale',
            'iscrizioni.rispostaPreregistrazione.nullaostaVerificatoDa',
            'iscrizioni.rispostaPreregistrazione.documentoAggiuntivoVerificatoDa',
        ]);

        $this->eventoIniziato = today()->gte($record->evento->data_inizio);

        if ($this->eventoIniziato) {
            $this->checkinIds = SocioEvento::where('evento_id', $record->evento_id)
                ->whereHas('accessi')
                ->pluck('partecipante_id')
                ->flip()
                ->all();
        }

        $this->caricaConflitti();
    }

    /** Azioni consentite al direttore senza permessi sui corsi (solo consultazione + esame). */
    private const AZIONI_DIRETTORE_LETTURA = [
        'creaEsame', 'vaiEsame', 'anteprimaEmail', 'anteprimaSollecito', 'anteprimaInfoEvento',
        'visualizzaRicevuta', 'visualizzaNullaosta', 'visualizzaDocumentoAggiuntivo',
    ];

    /**
     * True per chi accede solo perché direttore del corso (nessun ruolo admin né permesso corsi.view):
     * vede i dati ma non modifica il corso né le iscrizioni. Guardia lato server, valida anche se i
     * pulsanti della blade (non condizionati per ruolo) sono visibili. Audit permessi: più avanti.
     */
    private function soloConsultazione(): bool
    {
        $u = auth()->user();

        return ! ($u?->isSuperadmin() || $u?->isAdmin() || $u?->isAdminLocale() || $u?->can('corsi.view'));
    }

    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        if ($this->soloConsultazione() && ! in_array($name, self::AZIONI_DIRETTORE_LETTURA, true)) {
            Notification::make()->title('Operazione non consentita al direttore del corso.')->warning()->send();

            return null;
        }

        return parent::mountAction($name, $arguments, $context);
    }

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return $this->record;
    }

    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    /**
     * Stessa condizione di FormPreregistrazioneController::show() (mostra "form.non-disponibile"
     * invece del form) — se il corso non ha ancora sezioni di form configurate, il link che
     * "Invia form"/"Invia sollecito" mandano via email non porta a un form funzionante.
     */
    private function formNonGenerato(): bool
    {
        return blank($this->record->form_sections) || count($this->record->form_sections) === 0;
    }

    private function caricaConflitti(): void
    {
        $iscrizioneIds = $this->record->iscrizioni->pluck('id');

        $conflitti = ConflittoIscrizione::where(function ($q) use ($iscrizioneIds) {
                $q->whereIn('iscrizione_a_id', $iscrizioneIds)
                  ->orWhereIn('iscrizione_b_id', $iscrizioneIds);
            })
            ->with([
                'corsoA:id,nome,evento_id,giorni_presenza',
                'corsoA.evento:id,giorni_evento',
                'corsoB:id,nome,evento_id,giorni_presenza',
                'corsoB.evento:id,giorni_evento',
            ])
            ->get();

        $mappa = [];

        foreach ($conflitti as $c) {
            // Giorni in presenza in comune tra i due corsi (stessa regola del job di calcolo)
            $date = $c->corsoA->giorniInPresenzaInComune($c->corsoB)->map(fn ($d) => $d->format('d/m/Y'))->implode(', ');

            if ($iscrizioneIds->contains($c->iscrizione_a_id)) {
                $mappa[$c->iscrizione_a_id][] = ['corso' => $c->corsoB->nome, 'date' => $date];
            }
            if ($iscrizioneIds->contains($c->iscrizione_b_id)) {
                $mappa[$c->iscrizione_b_id][] = ['corso' => $c->corsoA->nome, 'date' => $date];
            }
        }

        $this->conflittiPerIscrizione = $mappa;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->importaIscrittiAction(),
                $this->aggiungiIscrittoAction(),

                Action::make('modifica')
                    ->label('Modifica corso')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->visible(fn () => CorsoResource::canEdit($this->record))
                    ->url(fn () => CorsoResource::getUrl('edit', ['record' => $this->record])),

                $this->creaEsameAction(),
                $this->vaiEsameAction(),

                Action::make('badge_corsisti')
                    ->label('Badge corsisti')
                    ->icon('heroicon-o-identification')
                    ->color('info')
                    ->url(fn () => route('corsi.badge-corsisti', ['corso' => $this->record]))
                    ->openUrlInNewTab(),

                Action::make('esporta_discenti')
                    ->label('Esporta discenti (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn () => route('corsi.discenti-excel', ['corso' => $this->record]))
                    ->openUrlInNewTab(),
            ])->label('Azioni')->icon('heroicon-o-ellipsis-vertical')->color('info')->button(),
        ];
    }

    /** Crea l'esame del corso (tipo dal catalogo collegato, Laboratorio 1 incluso). Vedi Esame::creaPerCorso(). */
    public function creaEsameAction(): Action
    {
        return Action::make('creaEsame')
            ->label('Crea esame')
            ->icon('heroicon-o-academic-cap')
            ->color('brand_pink')
            ->visible(fn () => ! $this->record->esame()->exists()
                && EsameResource::puoCreareDaCorso($this->record, auth()->user()))
            ->disabled(fn () => Esame::motivoNonCreabile($this->record) !== null)
            ->tooltip(fn () => Esame::motivoNonCreabile($this->record))
            ->modalHeading('Crea esame')
            ->modalDescription('Il tipo di esame arriva dal tipo di corso (catalogo) collegato al corso.')
            ->modalSubmitActionLabel('Crea')
            ->schema([
                Forms\Components\TextInput::make('titolo')->required()->maxLength(255),
                Forms\Components\TextInput::make('lap')
                    ->label('LAP (soglia di idoneità, %)')
                    ->helperText('Facoltativo: se lasci vuoto vale 60. Si può modificare anche dopo, fino alla chiusura dell\'esame.')
                    ->numeric()->minValue(0)->maxValue(100)->default(60),
            ])
            ->fillForm(fn () => ['titolo' => 'Esame ' . ($this->record->numero_protocollo ?: $this->record->nome), 'lap' => 60])
            ->action(function (array $data) {
                if (! EsameResource::puoCreareDaCorso($this->record, auth()->user())) {
                    abort(403);
                }
                $esame = Esame::creaPerCorso($this->record, $data['titolo'], $data['lap'] ?? 60);

                return redirect(EsameResource::getUrl('gestione', ['record' => $esame]));
            });
    }

    public function vaiEsameAction(): Action
    {
        return Action::make('vaiEsame')
            ->label("Vai all'esame")
            ->icon('heroicon-o-academic-cap')
            ->color('info')
            ->visible(fn () => $this->record->esame !== null
                && EsameResource::canEdit($this->record->esame))
            ->url(fn () => EsameResource::getUrl('gestione', ['record' => $this->record->esame]));
    }

    public function importaIscrittiAction(): Action
    {
        return Action::make('importaIscritti')
            ->label('Importa Iscritti')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->visible(fn () => auth()->user()?->isSuperadmin()
                || auth()->user()?->isAdmin()
                || auth()->user()?->can('iscritti.import'))
            ->form([
                Forms\Components\Placeholder::make('istruzioni')
                    ->label('')
                    ->content('Carica un file XLSX dal sistema GAIA. Colonne usate: Cognome · Nome · Codice Fiscale · Data di Nascita · Email · Numeri di telefono · Comitato · Sede regionale.'),
                Forms\Components\FileUpload::make('file_xlsx')
                    ->label('File XLSX')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->disk('local')
                    ->directory('imports-tmp')
                    ->required(),
                Forms\Components\Toggle::make('sync')
                    ->label('Sincronizza lista (rimuovi chi non è nel file)')
                    ->helperText('Se attivo, gli iscritti non presenti nel file verranno rimossi dal corso.')
                    ->inline(false)
                    ->default(false),
            ])
            ->modalHeading('Importa iscritti da Excel')
            ->modalSubmitActionLabel('Analizza file')
            ->action(function (array $data): void {
                $path   = is_array($data['file_xlsx']) ? array_values($data['file_xlsx'])[0] : $data['file_xlsx'];
                $import = new IscrittiImport($this->record->id, dryRun: true);

                try {
                    Excel::import($import, Storage::disk('local')->path($path));
                } catch (\Throwable $e) {
                    Notification::make()->title('Errore analisi file')->body($e->getMessage())->danger()->send();
                    Storage::disk('local')->delete($path);
                    return;
                }

                $this->importPreview  = $import->righeAnalizzate;
                $this->importFilePath = $path;
                $this->importSync     = (bool) ($data['sync'] ?? false);
                $this->importRimossi  = [];

                if ($this->importSync) {
                    $cfInFile = collect($import->righeAnalizzate)
                        ->where('stato', '!=', 'skip')
                        ->pluck('cf')
                        ->all();
                    $this->importRimossi = Iscrizione::where('corso_id', $this->record->id)
                        ->whereHas('partecipante', fn ($q) => $q->whereNotIn('codice_fiscale', $cfInFile))
                        ->with('partecipante:id,nome,cognome,codice_fiscale')
                        ->get()
                        ->map(fn ($i) => [
                            'nome' => trim($i->partecipante->cognome . ' ' . $i->partecipante->nome),
                            'cf'   => $i->partecipante->codice_fiscale,
                        ])
                        ->toArray();
                }
            });
    }

    public function confermaImport(): void
    {
        abort_if($this->soloConsultazione(), 403);

        if (! $this->importFilePath) {
            return;
        }

        $fullPath = Storage::disk('local')->path($this->importFilePath);
        $import   = new IscrittiImport($this->record->id, sync: $this->importSync);

        try {
            Excel::import($import, $fullPath);
        } finally {
            Storage::disk('local')->delete($this->importFilePath);
        }

        $this->importPreview  = null;
        $this->importFilePath = null;
        $this->importRimossi  = [];

        $this->record->refresh()->load([
            'iscrizioni.partecipante.comitatoLocale',
            'iscrizioni.rispostaPreregistrazione',
        ]);
        $this->caricaConflitti();

        $righeSaltate = array_filter($import->righeAnalizzate, fn ($r) => $r['stato'] === 'skip');
        $skippati     = count($righeSaltate);
        $body = "Iscritti: {$import->iscritti} · Creati: {$import->creati} · Aggiornati: {$import->aggiornati}"
            . ($import->rimossi > 0 ? " · Rimossi: {$import->rimossi}" : '')
            . ($skippati > 0 ? " · Saltati: {$skippati}" : '');

        if ($skippati > 0) {
            $dettagli = implode("\n", array_map(
                fn ($r) => "{$r['nome']} ({$r['cf']}): {$r['errore']}",
                array_slice($righeSaltate, 0, 10)
            ));
            if ($skippati > 10) {
                $dettagli .= "\n… e altre " . ($skippati - 10) . " righe saltate.";
            }

            Notification::make()
                ->title('Import completato con righe saltate')
                ->body($body . "\n\n" . $dettagli)
                ->color('warning')
                ->persistent()
                ->sendToDatabase(auth()->user());
        } else {
            Notification::make()
                ->title('Import completato')
                ->body($body)
                ->color('success')
                ->send();
        }
    }

    public function annullaImport(): void
    {
        if ($this->importFilePath) {
            Storage::disk('local')->delete($this->importFilePath);
        }
        $this->importPreview  = null;
        $this->importFilePath = null;
        $this->importRimossi  = [];
    }

    public function aggiungiIscrittoAction(): Action
    {
        return Action::make('aggiungiIscritto')
            ->label('Aggiungi iscritto')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->visible(fn () => auth()->user()?->isSuperadmin()
                || auth()->user()?->isAdmin()
                || auth()->user()?->isAdminLocale())
            ->form([
                Forms\Components\TextInput::make('codice_fiscale')
                    ->label('Codice Fiscale')
                    ->required()
                    ->maxLength(16)
                    ->extraAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper(trim($state)))
                    ->suffixAction(
                        Action::make('cerca_cf')
                            ->label('Cerca')
                            ->icon('heroicon-o-magnifying-glass')
                            ->action(function ($get, $set) {
                                $cf = strtoupper(trim($get('codice_fiscale') ?? ''));
                                if (! $cf) return;
                                $socio = Socio::where('codice_fiscale', $cf)->first();
                                if (! $socio) {
                                    Notification::make()->title('Nessun socio trovato con questo CF — compila i dati manualmente.')->warning()->send();
                                    return;
                                }
                                $set('nome', $socio->nome);
                                $set('cognome', $socio->cognome);
                                $set('email', $socio->email);
                                $set('prefisso_telefono', $socio->prefisso_telefono ?? '+39');
                                $set('telefono', $socio->telefono);
                                $set('comitato_regionale_id', $socio->comitato_regionale_id);
                                $set('comitato_locale_id', $socio->comitato_locale_id);
                                Notification::make()->title('Socio trovato: ' . $socio->cognome . ' ' . $socio->nome)->success()->send();
                            })
                    ),

                \Filament\Schemas\Components\Group::make([
                    Forms\Components\TextInput::make('cognome')
                        ->label('Cognome')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('nome')
                        ->label('Nome')
                        ->required()
                        ->maxLength(100),
                ])->columns(2),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                \Filament\Schemas\Components\Group::make([
                    Forms\Components\Select::make('prefisso_telefono')
                        ->label('Prefisso')
                        ->options([
                            '+39' => '🇮🇹 +39 — Italia',
                            '+41' => '🇨🇭 +41 — Svizzera',
                            '+33' => '🇫🇷 +33 — Francia',
                            '+34' => '🇪🇸 +34 — Spagna',
                            '+49' => '🇩🇪 +49 — Germania',
                            '+43' => '🇦🇹 +43 — Austria',
                            '+44' => '🇬🇧 +44 — Regno Unito',
                            '+1'  => '🇺🇸 +1 — USA / Canada',
                        ])
                        ->default('+39')
                        ->searchable()
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('telefono')
                        ->label('Telefono')
                        ->tel()
                        ->maxLength(20)
                        ->columnSpan(2),
                ])->columns(3),

                Forms\Components\Select::make('comitato_regionale_id')
                    ->label('Comitato Regionale')
                    ->required()
                    ->options(ComitatoRegionale::orderBy('nome')->pluck('nome', 'id'))
                    ->searchable()
                    ->live(),

                Forms\Components\Select::make('comitato_locale_id')
                    ->label('Comitato Locale')
                    ->placeholder('— Nessuno (solo regionale) —')
                    ->options(fn ($get) => $get('comitato_regionale_id')
                        ? ComitatoLocale::where('comitato_regionale_id', $get('comitato_regionale_id'))
                            ->orderBy('nome')->pluck('nome', 'id')
                        : [])
                    ->searchable()
                    ->nullable(),
            ])
            ->modalHeading('Aggiungi iscritto manualmente')
            ->modalSubmitActionLabel('Aggiungi')
            ->action(function (array $data): void {
                $cf    = strtoupper(trim($data['codice_fiscale']));
                $socio = Socio::where('codice_fiscale', $cf)->first();

                if ($socio) {
                    $updates = [];
                    if (! $socio->email && ($data['email'] ?? null)) $updates['email'] = $data['email'];
                    if (! $socio->telefono && ($data['telefono'] ?? null)) {
                        $updates['telefono']          = $data['telefono'];
                        $updates['prefisso_telefono'] = $data['prefisso_telefono'] ?? '+39';
                    }
                    if ($updates) $socio->update($updates);
                } else {
                    $socio = Socio::create([
                        'codice_fiscale'        => $cf,
                        'cognome'               => mb_convert_case(trim($data['cognome']), MB_CASE_TITLE, 'UTF-8'),
                        'nome'                  => mb_convert_case(trim($data['nome']), MB_CASE_TITLE, 'UTF-8'),
                        'email'                 => $data['email'] ? strtolower(trim($data['email'])) : null,
                        'prefisso_telefono'     => $data['prefisso_telefono'] ?? '+39',
                        'telefono'              => $data['telefono'] ?? null,
                        'comitato_regionale_id' => $data['comitato_regionale_id'],
                        'comitato_locale_id'    => $data['comitato_locale_id'] ?? null,
                    ]);
                }

                if ($this->record->iscrizioni->where('partecipante_id', $socio->id)->isNotEmpty()) {
                    Notification::make()
                        ->title($socio->cognome . ' ' . $socio->nome . ' è già iscritto a questo corso.')
                        ->warning()
                        ->send();
                    return;
                }

                Iscrizione::create([
                    'corso_id'        => $this->record->id,
                    'partecipante_id' => $socio->id,
                    'token'           => Str::uuid(),
                    'stato'           => 'pending',
                ]);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);
                $this->caricaConflitti();

                Notification::make()
                    ->title($socio->cognome . ' ' . $socio->nome . ' aggiunto al corso.')
                    ->success()
                    ->send();
            });
    }

    /** Iscritti attivi che non hanno ancora ricevuto il form di preregistrazione. */
    private function iscrizioniFormPendenti(): \Illuminate\Support\Collection
    {
        return $this->record->iscrizioni->filter(fn ($i) => ! $i->isRitirato() && ! $i->form_inviato_at);
    }

    /** Iscritti attivi che non hanno ancora risposto al form (sollecitabili, anche più volte). */
    private function iscrizioniSollecitoPendenti(): \Illuminate\Support\Collection
    {
        return $this->record->iscrizioni->filter(
            fn ($i) => ! $i->isRitirato() && $i->rispostaPreregistrazione === null
        );
    }

    /** Iscritti attivi che hanno completato la preregistrazione e non hanno ancora ricevuto
     *  la comunicazione "info evento". */
    private function iscrizioniInfoPendenti(): \Illuminate\Support\Collection
    {
        return $this->record->iscrizioni->filter(fn ($i) => ! $i->isRitirato()
            && $i->rispostaPreregistrazione !== null
            && ! $i->info_evento_inviato_at);
    }

    public function inviaTuttiAction(): Action
    {
        return Action::make('inviaTutti')
            ->label(function () {
                $data = $this->record->invio_massivo_iscritti_at;
                return $data ? 'Invia form a tutti (ultimo invio: ' . $data->format('d/m/Y H:i') . ')' : 'Invia form a tutti';
            })
            ->icon(fn () => $this->iscrizioniFormPendenti()->isEmpty() ? 'heroicon-o-check-circle' : 'heroicon-o-paper-airplane')
            ->color(fn () => $this->iscrizioniFormPendenti()->isEmpty() ? 'gray' : 'brand_pink')
            ->disabled(fn () => $this->formNonGenerato() || today()->gt($this->record->data_fine) || $this->record->isAnnullato() || $this->iscrizioniFormPendenti()->isEmpty())
            ->tooltip(function () {
                if ($this->formNonGenerato()) return 'Nessun form generato per questo corso: modifica il corso per configurarlo prima di inviarlo';
                if ($this->record->isAnnullato()) return 'Corso annullato: invio non disponibile';
                if (today()->gt($this->record->data_fine)) return 'Corso concluso: invio non più disponibile';
                if ($this->iscrizioniFormPendenti()->isEmpty()) return 'Tutti gli iscritti hanno già ricevuto il form';
                return null;
            })
            ->requiresConfirmation()
            ->modalHeading('Invia form di preregistrazione')
            ->modalDescription(function () {
                $destinatari = $this->iscrizioniFormPendenti();
                $conEmail    = $destinatari->filter(fn ($i) => $i->partecipante?->email)->count();
                $senzaEmail  = $destinatari->count() - $conEmail;
                $msg = "Verranno inviate {$conEmail} email a chi non le ha ancora ricevute.";
                if ($senzaEmail > 0) $msg .= " {$senzaEmail} iscritti senza email verranno saltati.";
                return $msg;
            })
            ->modalSubmitActionLabel('Invia')
            ->action(function () {
                $inviati = 0;
                $saltati = 0;
                $this->record->iscrizioni->loadMissing('corso.evento');

                foreach ($this->iscrizioniFormPendenti() as $iscrizione) {
                    $email = $iscrizione->partecipante?->email;
                    if (! $email) { $saltati++; continue; }

                    Mail::to($email)->send(new FormPreregistrazioneLink($iscrizione));
                    $iscrizione->update(['form_inviato_at' => now()]);
                    $inviati++;
                }

                $this->record->update(['invio_massivo_iscritti_at' => now()]);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);

                $msg = "Form inviato a {$inviati} iscritti.";
                if ($saltati > 0) $msg .= " {$saltati} saltati (senza email).";

                Notification::make()->title($msg)->success()->send();
            });
    }

    public function inviaSollecitoTuttiAction(): Action
    {
        return Action::make('inviaSollecitoTutti')
            ->label(function () {
                $data = $this->record->invio_massivo_sollecito_iscritti_at;
                return $data ? 'Invia sollecito a tutti (ultimo invio: ' . $data->format('d/m/Y H:i') . ')' : 'Invia sollecito a tutti';
            })
            ->icon(fn () => $this->iscrizioniSollecitoPendenti()->isEmpty() ? 'heroicon-o-check-circle' : 'heroicon-o-bell-alert')
            ->color(fn () => $this->iscrizioniSollecitoPendenti()->isEmpty() ? 'gray' : 'brand_pink')
            ->disabled(fn () => $this->formNonGenerato() || today()->gt($this->record->data_fine) || $this->record->isAnnullato() || $this->iscrizioniSollecitoPendenti()->isEmpty())
            ->tooltip(function () {
                if ($this->formNonGenerato()) return 'Nessun form generato per questo corso: modifica il corso per configurarlo prima di inviarlo';
                if ($this->record->isAnnullato()) return 'Corso annullato: invio non disponibile';
                if (today()->gt($this->record->data_fine)) return 'Corso concluso: invio non più disponibile';
                if ($this->iscrizioniSollecitoPendenti()->isEmpty()) return 'Nessun iscritto da sollecitare al momento';
                return null;
            })
            ->requiresConfirmation()
            ->modalHeading('Invia sollecito preregistrazione')
            ->modalDescription(function () {
                $count = $this->iscrizioniSollecitoPendenti()
                    ->filter(fn ($i) => $i->partecipante?->email)->count();
                return "Verranno inviati {$count} solleciti agli iscritti che non hanno ancora compilato il form (anche a chi ha già ricevuto un sollecito in precedenza).";
            })
            ->modalSubmitActionLabel('Invia solleciti')
            ->action(function () {
                $inviati = 0;
                $saltati = 0;
                $this->record->iscrizioni->loadMissing('corso.evento');

                foreach ($this->iscrizioniSollecitoPendenti() as $iscrizione) {
                    $email = $iscrizione->partecipante?->email;
                    if (! $email) { $saltati++; continue; }

                    Mail::to($email)->send(new SollecitoPreregistrazioneLink($iscrizione));
                    $iscrizione->update(['sollecito_inviato_at' => now()]);
                    $inviati++;
                }

                $this->record->update(['invio_massivo_sollecito_iscritti_at' => now()]);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);

                $msg = "Sollecito inviato a {$inviati} iscritti.";
                if ($saltati > 0) $msg .= " {$saltati} saltati (senza email).";

                Notification::make()->title($msg)->success()->send();
            });
    }

    public function inviaInfoTuttiAction(): Action
    {
        return Action::make('inviaInfoTutti')
            ->label(function () {
                $data = $this->record->invio_massivo_info_at;
                return $data ? 'Invia a tutti (ultimo invio: ' . $data->format('d/m/Y H:i') . ')' : 'Invia a tutti';
            })
            ->icon(fn () => $this->iscrizioniInfoPendenti()->isEmpty() ? 'heroicon-o-check-circle' : 'heroicon-o-information-circle')
            ->color(fn () => $this->iscrizioniInfoPendenti()->isEmpty() ? 'gray' : 'brand_pink')
            ->disabled(fn () => ! $this->record->evento->hasComunicazioneValida() || today()->gt($this->record->data_fine) || $this->record->isAnnullato() || $this->iscrizioniInfoPendenti()->isEmpty())
            ->tooltip(function () {
                if (! $this->record->evento->hasComunicazioneValida()) return 'Nessuna informazione definita da inviare: entra nella modifica evento per inserire testo o allegati';
                if ($this->record->isAnnullato()) return 'Corso annullato: invio non disponibile';
                if (today()->gt($this->record->data_fine)) return 'Corso concluso: invio non più disponibile';
                if ($this->iscrizioniInfoPendenti()->isEmpty()) return 'Tutti gli iscritti hanno già ricevuto la comunicazione';
                return null;
            })
            ->requiresConfirmation()
            ->modalHeading('Invia comunicazione evento')
            ->modalDescription(function () {
                $destinatari = $this->iscrizioniInfoPendenti();
                $conEmail    = $destinatari->filter(fn ($i) => $i->partecipante?->email)->count();
                $senzaEmail  = $destinatari->count() - $conEmail;
                $msg = "Verranno inviate {$conEmail} email a chi non ha ancora ricevuto le informazioni sull'evento.";
                if ($senzaEmail > 0) $msg .= " {$senzaEmail} iscritti senza email verranno saltati.";
                return $msg;
            })
            ->modalSubmitActionLabel('Invia')
            ->action(function () {
                if (! $this->record->evento->hasComunicazioneValida()) {
                    Notification::make()
                        ->title('Nessun contenuto da inviare')
                        ->body('Inserisci del testo o almeno un documento allegato nella sezione "Contenuti e documenti" dell\'evento.')
                        ->warning()
                        ->send();
                    return;
                }

                $inviati = 0;
                $saltati = 0;
                $this->record->iscrizioni->loadMissing('corso.evento');

                foreach ($this->iscrizioniInfoPendenti() as $iscrizione) {
                    $email = $iscrizione->partecipante?->email;
                    if (! $email) { $saltati++; continue; }

                    Mail::to($email)->send(new InfoEventoIscritto($iscrizione));
                    $iscrizione->update(['info_evento_inviato_at' => now()]);
                    $inviati++;
                }

                $this->record->update(['invio_massivo_info_at' => now()]);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);

                $msg = "Comunicazione inviata a {$inviati} iscritti.";
                if ($saltati > 0) $msg .= " {$saltati} saltati (senza email).";

                Notification::make()->title($msg)->success()->send();
            });
    }

    public function anteprimaInfoEventoAction(): Action
    {
        return Action::make('anteprimaInfoEvento')
            ->label('Anteprima')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Anteprima comunicazione evento')
            ->modalWidth('4xl')
            ->modalContent(fn () => new HtmlString(
                GestioneEvento::renderAnteprimaInfoEvento($this->record->evento, $this->record)
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi');
    }

    public function anteprimaEmailAction(): Action
    {
        return Action::make('anteprimaEmail')
            ->label('Form di preregistrazione')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->modalHeading('Anteprima email — ' . $this->record->nome)
            ->modalContent(function () {
                $corso    = $this->record;
                $evento   = $corso->evento;
                $template = EmailTemplate::risolvi($evento->comitato_regionale_id, 'iscritti');

                if (! $template) {
                    return new HtmlString(
                        '<div style="padding:20px; color:#6b7280; text-align:center;">
                            Nessun template email configurato per questa regione.<br>
                            <a href="' . \App\Filament\Resources\EmailTemplateResource::getUrl('create') . '" style="color:#2563eb;">Crea un template</a>
                        </div>'
                    );
                }

                $primaIscrizione = $corso->iscrizioni->first();
                $soggetto        = TemplatePlaceholders::anteprimaPerCorso($template->soggetto, $corso, giorniScadenza: 7);
                $corpo           = TemplatePlaceholders::anteprimaPerCorso($template->corpo, $corso, giorniScadenza: 7);
                $comitatoNome    = $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore';
                $haIscritti      = $primaIscrizione !== null;
                $nomeIscritto    = $primaIscrizione
                    ? trim(($primaIscrizione->partecipante?->cognome ?? '') . ' ' . ($primaIscrizione->partecipante?->nome ?? ''))
                    : '';

                return view('filament.resources.corso-resource.partials.anteprima-email', compact(
                    'soggetto', 'corpo', 'comitatoNome', 'haIscritti', 'nomeIscritto'
                ));
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi')
            ->slideOver();
    }

    public function anteprimaSollecitoAction(): Action
    {
        return Action::make('anteprimaSollecito')
            ->label('Sollecito preregistrazione')
            ->icon('heroicon-o-bell-alert')
            ->color('info')
            ->modalHeading('Anteprima sollecito — ' . $this->record->nome)
            ->modalContent(function () {
                $corso    = $this->record;
                $evento   = $corso->evento;
                $template = EmailTemplate::risolvi($evento->comitato_regionale_id, 'sollecito_iscritti');

                if (! $template) {
                    return new HtmlString(
                        '<div style="padding:20px; color:#6b7280; text-align:center;">
                            Nessun template sollecito configurato per questa regione.<br>
                            <a href="' . \App\Filament\Resources\EmailTemplateResource::getUrl('create') . '" style="color:#2563eb;">Crea un template</a>
                        </div>'
                    );
                }

                $primaIscrizione = $corso->iscrizioni->first();
                $soggetto        = TemplatePlaceholders::anteprimaPerCorso($template->soggetto, $corso, giorniScadenza: 1);
                $corpo           = TemplatePlaceholders::anteprimaPerCorso($template->corpo, $corso, giorniScadenza: 1);
                $comitatoNome    = $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore';
                $haIscritti      = $primaIscrizione !== null;
                $nomeIscritto    = $primaIscrizione
                    ? trim(($primaIscrizione->partecipante?->cognome ?? '') . ' ' . ($primaIscrizione->partecipante?->nome ?? ''))
                    : '';

                return view('filament.resources.corso-resource.partials.anteprima-email', compact(
                    'soggetto', 'corpo', 'comitatoNome', 'haIscritti', 'nomeIscritto'
                ));
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi')
            ->slideOver();
    }

    /**
     * Sezioni pasti/pernotti per la gestione presenze di un iscritto: stessa logica del pannello staff
     * (EventoSezioniGenerator), sui soli giorni in presenza del corso (`giorni_presenza`, fallback ai
     * giorni evento se non impostati) — mai il range data_inizio→data_fine. Admin: cena/pernotto sempre
     * disponibili + "giorno prima" a inizio blocco; operatore: solo se il corso è residenziale.
     * Colazioni escluse (auto-derivate dai pernotti). Restituisce ['tipo','data','chiave','label'].
     */
    private function generaSezioniCorso(bool $isAdmin): array
    {
        return \App\Services\EventoSezioniGenerator::perElencoGiorni(
            $this->record->giorniInPresenza(),
            $isAdmin || $this->record->residenziale,
        );
    }

    public function gestisciPresenzeAction(): Action
    {
        return Action::make('gestisciPresenze')
            ->label('Presenze')
            ->modalHeading(function (array $arguments): string {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $p          = $iscrizione?->partecipante;
                return 'Presenze — ' . trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? ''));
            })
            ->modalDescription('Le voci con "(form)" sono state dichiarate dal partecipante nel preregistro.')
            ->modalWidth('lg')
            ->form(function (array $arguments): array {
                $isAdmin      = auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin();
                $iscrizioneId = $arguments['iscrizione_id'] ?? null;
                $iscrizione   = $iscrizioneId
                    ? ($this->record->iscrizioni->firstWhere('id', $iscrizioneId) ?? Iscrizione::find($iscrizioneId))
                    : null;

                $presenzeForm = $iscrizione
                    ? Presenza::where('evento_id', $this->record->evento_id)
                        ->where('partecipante_id', $iscrizione->partecipante_id)
                        ->where('fonte', 'form')
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->tipo . '_' . $p->data->format('Y-m-d') => true])
                    : collect();

                $opzioni = collect($this->generaSezioniCorso($isAdmin))
                    ->mapWithKeys(function ($s) use ($presenzeForm) {
                        $chiave = $s['tipo'] . '_' . $s['data'];
                        return [$chiave => $s['label'] . ($presenzeForm->has($chiave) ? ' (form)' : '')];
                    })
                    ->all();

                return [
                    Forms\Components\CheckboxList::make('presenze')
                        ->label('Pasti e pernotti')
                        ->options($opzioni)
                        ->columns(2),
                ];
            })
            ->fillForm(function (array $arguments): array {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return ['presenze' => []];

                $presenze = Presenza::where('evento_id', $this->record->evento_id)
                    ->where('partecipante_id', $iscrizione->partecipante_id)
                    ->where('tipo', '!=', 'colazione')
                    ->get()
                    ->map(fn ($p) => $p->tipo . '_' . $p->data->format('Y-m-d'))
                    ->toArray();

                return ['presenze' => $presenze];
            })
            ->action(function (array $data, array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $eventoId       = $this->record->evento_id;
                $partecipanteId = $iscrizione->partecipante_id;
                $isAdmin        = auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin();
                $selezionati    = collect($data['presenze'] ?? []);

                $tutteSezioni = collect($this->generaSezioniCorso($isAdmin));

                foreach ($tutteSezioni as $s) {
                    $chiave = $s['tipo'] . '_' . $s['data'];

                    if ($selezionati->contains($chiave)) {
                        $presenza = Presenza::firstOrNew([
                            'evento_id'       => $eventoId,
                            'partecipante_id' => $partecipanteId,
                            'tipo'            => $s['tipo'],
                            'data'            => $s['data'],
                            ]);
                            if (! $presenza->exists) {
                                $presenza->fonte = 'admin';
                            }
                            $presenza->iscrizione_id = $iscrizione->id;
                            $presenza->updated_by    = auth()->id();
                            $presenza->save();
                        } else {
                            Presenza::where('evento_id', $eventoId)
                                ->where('partecipante_id', $partecipanteId)
                                ->where('tipo', $s['tipo'])
                                ->where('data', $s['data'])
                                ->delete();
                        }
                    }

                // Auto-deriva colazioni dai pernotti
                $tutteSezioni->where('tipo', 'pernotto')->each(
                    function ($s) use ($selezionati, $eventoId, $partecipanteId, $iscrizione): void {
                        $colazioneData = Carbon::parse($s['data'])->addDay()->format('Y-m-d');
                        if ($selezionati->contains('pernotto_' . $s['data'])) {
                            $col = Presenza::firstOrNew([
                                'evento_id'       => $eventoId,
                                'partecipante_id' => $partecipanteId,
                                'tipo'            => 'colazione',
                                'data'            => $colazioneData,
                            ]);
                            if (! $col->exists) {
                                $col->fonte = 'admin';
                            }
                            $col->iscrizione_id = $iscrizione->id;
                            $col->updated_by    = auth()->id();
                            $col->save();
                        } else {
                            Presenza::where('evento_id', $eventoId)
                                ->where('partecipante_id', $partecipanteId)
                                ->where('tipo', 'colazione')
                                ->where('data', $colazioneData)
                                ->delete();
                        }
                    }
                );

                Notification::make()
                    ->title('Presenze aggiornate.')
                    ->success()
                    ->send();
            });
    }

    public function inviaFormSingoloAction(): Action
    {
        return Action::make('inviaFormSingolo')
            ->label('Invia form')
            ->icon('heroicon-o-paper-airplane')
            ->color('brand_pink')
            ->disabled(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                return $this->formNonGenerato() || today()->gt($this->record->data_fine) || $this->record->isAnnullato() || $iscrizione?->form_inviato_at;
            })
            ->tooltip(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if ($this->formNonGenerato()) return 'Nessun form generato per questo corso: modifica il corso per configurarlo prima di inviarlo';
                if ($this->record->isAnnullato()) return 'Corso annullato: invio non disponibile';
                if (today()->gt($this->record->data_fine)) return 'Corso concluso: invio non più disponibile';
                if ($iscrizione?->form_inviato_at) return 'Form già inviato — usa il sollecito per un nuovo promemoria';
                return null;
            })
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $email      = $iscrizione?->partecipante?->email;
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Inviare il form di preregistrazione a {$nome} ({$email})?";
            })
            ->modalSubmitActionLabel('Invia')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                if ($iscrizione->form_inviato_at) {
                    Notification::make()->title('Form già inviato — usa il sollecito per un nuovo promemoria.')->warning()->send();
                    return;
                }

                $email = $iscrizione->partecipante?->email;
                if (! $email) {
                    Notification::make()->title('Iscritto senza email — impossibile inviare.')->warning()->send();
                    return;
                }

                $iscrizione->loadMissing('corso.evento');

                Mail::to($email)->send(new FormPreregistrazioneLink($iscrizione));
                $iscrizione->update(['form_inviato_at' => now()]);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);

                Notification::make()
                    ->title('Form inviato a ' . $email)
                    ->success()
                    ->send();
            });
    }

    public function inviaSollecitoSingoloAction(): Action
    {
        return Action::make('inviaSollecitoSingolo')
            ->label('Invia sollecito')
            ->icon('heroicon-o-bell-alert')
            ->color('brand_pink')
            ->disabled(fn () => $this->formNonGenerato() || today()->gt($this->record->data_fine) || $this->record->isAnnullato())
            ->tooltip(fn () => $this->formNonGenerato()
                ? 'Nessun form generato per questo corso: modifica il corso per configurarlo prima di inviarlo'
                : ($this->record->isAnnullato()
                    ? 'Corso annullato: invio non disponibile'
                    : (today()->gt($this->record->data_fine) ? 'Corso concluso: invio non più disponibile' : null)))
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $email      = $iscrizione?->partecipante?->email;
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Inviare un sollecito a {$nome} ({$email})?";
            })
            ->modalSubmitActionLabel('Invia')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $email = $iscrizione->partecipante?->email;
                if (! $email) {
                    Notification::make()->title('Iscritto senza email — impossibile inviare.')->warning()->send();
                    return;
                }

                $iscrizione->loadMissing('corso.evento');

                Mail::to($email)->send(new SollecitoPreregistrazioneLink($iscrizione));
                $iscrizione->update(['sollecito_inviato_at' => now()]);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);

                Notification::make()->title('Sollecito inviato a ' . $email)->success()->send();
            });
    }

    public function inviaInfoSingoloAction(): Action
    {
        return Action::make('inviaInfoSingolo')
            ->label('Invia info')
            ->icon('heroicon-o-information-circle')
            ->color('brand_pink')
            ->disabled(fn () => today()->gt($this->record->data_fine) || $this->record->isAnnullato())
            ->tooltip(fn () => $this->record->isAnnullato()
                ? 'Corso annullato: invio non disponibile'
                : (today()->gt($this->record->data_fine) ? 'Corso concluso: invio non più disponibile' : null))
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $email      = $iscrizione?->partecipante?->email;
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Inviare le informazioni sull'evento a {$nome} ({$email})?";
            })
            ->modalSubmitActionLabel('Invia')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $evento = $this->record->evento;

                if (! filled($evento->note_app) && $evento->getMedia('documenti')->isEmpty()) {
                    Notification::make()
                        ->title('Nessun contenuto da inviare')
                        ->body('Inserisci del testo o un documento allegato nell\'evento prima di inviare.')
                        ->warning()
                        ->send();
                    return;
                }

                $email = $iscrizione->partecipante?->email;
                if (! $email) {
                    Notification::make()->title('Iscritto senza email — impossibile inviare.')->warning()->send();
                    return;
                }

                $iscrizione->loadMissing('corso.evento');

                Mail::to($email)->send(new InfoEventoIscritto($iscrizione));
                $iscrizione->update(['info_evento_inviato_at' => now()]);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);

                Notification::make()->title('Comunicazione inviata a ' . $email)->success()->send();
            });
    }

    public function visualizzaRicevutaAction(): Action
    {
        return Action::make('visualizzaRicevuta')
            ->label('Visualizza')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => 'Pagamento — ' . ($arguments['nome'] ?? ''))
            ->modalContent(fn (array $arguments) => view('filament.resources.corso-resource.partials.anteprima-documento', [
                'url' => route('iscrizioni.ricevuta-preview', $arguments['iscrizione_id']),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->slideOver();
    }

    public function visualizzaNullaostaAction(): Action
    {
        return Action::make('visualizzaNullaosta')
            ->label('Visualizza')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => 'Nullaosta — ' . ($arguments['nome'] ?? ''))
            ->modalContent(fn (array $arguments) => view('filament.resources.corso-resource.partials.anteprima-documento', [
                'url' => route('iscrizioni.nullaosta-preview', $arguments['iscrizione_id']),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->slideOver();
    }

    public function visualizzaDocumentoAggiuntivoAction(): Action
    {
        return Action::make('visualizzaDocumentoAggiuntivo')
            ->label('Visualizza')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => 'Documento aggiuntivo — ' . ($arguments['nome'] ?? ''))
            ->modalDescription(fn () => $this->record->dicitura_documento_aggiuntivo)
            ->modalContent(fn (array $arguments) => view('filament.resources.corso-resource.partials.anteprima-documento', [
                'url' => route('iscrizioni.documento-aggiuntivo-preview', $arguments['iscrizione_id']),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->slideOver();
    }

    private function iscrizionePerArg(array $arguments): ?Iscrizione
    {
        return $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
    }

    /**
     * Verifica manuale del nullaosta regionale del corsista — stesso meccanismo delle ricevute
     * (GestioneQuote) e del nullaosta staff (GestioneStaff). Colonne generiche
     * `nullaosta_verificato*` su risposte_preregistrazione.
     */
    public function verificaNullaostaAction(): Action
    {
        return Action::make('verificaNullaosta')
            ->label('Verifica')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $nome = trim(($this->iscrizionePerArg($arguments)?->partecipante?->cognome ?? '') . ' ' . ($this->iscrizionePerArg($arguments)?->partecipante?->nome ?? ''));
                return "Confermi che il nullaosta di {$nome} è valido?";
            })
            ->modalSubmitActionLabel('Verifica')
            ->action(function (array $arguments): void {
                $iscrizione = $this->iscrizionePerArg($arguments);
                $risposta   = $iscrizione?->rispostaPreregistrazione;

                if (! $risposta || ! $risposta->nullaosta_path) {
                    Notification::make()->title('Nessun nullaosta allegato — impossibile verificare.')->warning()->send();
                    return;
                }

                $risposta->update([
                    'nullaosta_verificato'       => true,
                    'nullaosta_verificato_at'    => now(),
                    'nullaosta_verificato_da_id' => auth()->id(),
                ]);

                $this->record->load('iscrizioni.rispostaPreregistrazione.nullaostaVerificatoDa');

                Notification::make()
                    ->title('Nullaosta verificato per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome)
                    ->success()
                    ->send();
            });
    }

    public function annullaVerificaNullaostaAction(): Action
    {
        return Action::make('annullaVerificaNullaosta')
            ->label('Annulla verifica')
            ->icon('heroicon-o-x-mark')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $nome = trim(($this->iscrizionePerArg($arguments)?->partecipante?->cognome ?? '') . ' ' . ($this->iscrizionePerArg($arguments)?->partecipante?->nome ?? ''));
                return "Annullare la verifica del nullaosta di {$nome}?";
            })
            ->modalSubmitActionLabel('Annulla verifica')
            ->action(function (array $arguments): void {
                $iscrizione = $this->iscrizionePerArg($arguments);
                $risposta   = $iscrizione?->rispostaPreregistrazione;
                if (! $risposta) return;

                $risposta->update([
                    'nullaosta_verificato'       => false,
                    'nullaosta_verificato_at'    => null,
                    'nullaosta_verificato_da_id' => null,
                ]);

                $this->record->load('iscrizioni.rispostaPreregistrazione.nullaostaVerificatoDa');

                Notification::make()
                    ->title('Verifica annullata per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome)
                    ->warning()
                    ->send();
            });
    }

    /**
     * Sostituzione del nullaosta caricato per errore, consentita solo finché non è verificato.
     */
    public function sostituisciNullaostaAction(): Action
    {
        return Action::make('sostituisciNullaosta')
            ->label('Sostituisci')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => 'Sostituisci nullaosta — ' . ($arguments['nome'] ?? ''))
            ->modalDescription('Il file precedente verrà eliminato. Possibile solo finché il nullaosta non è verificato.')
            ->form([
                Forms\Components\FileUpload::make('documento')
                    ->label('Nuovo nullaosta (solo PDF, max 10 MB)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('nullaosta/sostituzioni')
                    ->required(),
            ])
            ->modalSubmitActionLabel('Sostituisci')
            ->action(function (array $data, array $arguments): void {
                $iscrizione = $this->iscrizionePerArg($arguments);
                $risposta   = $iscrizione?->rispostaPreregistrazione;

                if (! $risposta || ! $risposta->nullaosta_path) {
                    Notification::make()->title('Nessun nullaosta da sostituire.')->warning()->send();
                    return;
                }

                $ok = app(SostituzioneDocumento::class)->sostituisci($risposta, 'nullaosta', $data['documento']);

                $this->record->load('iscrizioni.rispostaPreregistrazione.nullaostaVerificatoDa');

                Notification::make()
                    ->title($ok
                        ? 'Nullaosta sostituito per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome
                        : 'Nullaosta già verificato: non è più sostituibile.')
                    ->{$ok ? 'success' : 'warning'}()
                    ->send();
            });
    }

    /**
     * Verifica manuale del documento aggiuntivo richiesto dal corso (dicitura libera dell'admin):
     * stesso meccanismo del nullaosta, colonne `documento_aggiuntivo_verificato*`.
     */
    public function verificaDocumentoAggiuntivoAction(): Action
    {
        return Action::make('verificaDocumentoAggiuntivo')
            ->label('Verifica')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $nome = trim(($this->iscrizionePerArg($arguments)?->partecipante?->cognome ?? '') . ' ' . ($this->iscrizionePerArg($arguments)?->partecipante?->nome ?? ''));
                return "Confermi che il documento di {$nome} è coerente con quanto richiesto?";
            })
            ->modalSubmitActionLabel('Verifica')
            ->action(function (array $arguments): void {
                $iscrizione = $this->iscrizionePerArg($arguments);
                $risposta   = $iscrizione?->rispostaPreregistrazione;

                if (! $risposta || ! $risposta->documento_aggiuntivo_path) {
                    Notification::make()->title('Nessun documento allegato — impossibile verificare.')->warning()->send();
                    return;
                }

                $risposta->update([
                    'documento_aggiuntivo_verificato'       => true,
                    'documento_aggiuntivo_verificato_at'    => now(),
                    'documento_aggiuntivo_verificato_da_id' => auth()->id(),
                ]);

                $this->record->load('iscrizioni.rispostaPreregistrazione.documentoAggiuntivoVerificatoDa');

                Notification::make()
                    ->title('Documento verificato per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome)
                    ->success()
                    ->send();
            });
    }

    public function annullaVerificaDocumentoAggiuntivoAction(): Action
    {
        return Action::make('annullaVerificaDocumentoAggiuntivo')
            ->label('Annulla verifica')
            ->icon('heroicon-o-x-mark')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $nome = trim(($this->iscrizionePerArg($arguments)?->partecipante?->cognome ?? '') . ' ' . ($this->iscrizionePerArg($arguments)?->partecipante?->nome ?? ''));
                return "Annullare la verifica del documento di {$nome}?";
            })
            ->modalSubmitActionLabel('Annulla verifica')
            ->action(function (array $arguments): void {
                $iscrizione = $this->iscrizionePerArg($arguments);
                $risposta   = $iscrizione?->rispostaPreregistrazione;
                if (! $risposta) return;

                $risposta->update([
                    'documento_aggiuntivo_verificato'       => false,
                    'documento_aggiuntivo_verificato_at'    => null,
                    'documento_aggiuntivo_verificato_da_id' => null,
                ]);

                $this->record->load('iscrizioni.rispostaPreregistrazione.documentoAggiuntivoVerificatoDa');

                Notification::make()
                    ->title('Verifica annullata per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome)
                    ->warning()
                    ->send();
            });
    }

    /**
     * Caricamento (documento mancante: risposta inviata prima che lo switch venisse attivato) o
     * sostituzione (file già presente, eliminato dal disco) del documento aggiuntivo da parte
     * dell'admin, solo finché non è verificato. Stesso `sostituisci()` del lato pubblico/PWA.
     */
    public function sostituisciDocumentoAggiuntivoAction(): Action
    {
        return Action::make('sostituisciDocumentoAggiuntivo')
            ->label('Carica / sostituisci')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => ($arguments['presente'] ?? false ? 'Sostituisci' : 'Carica') . ' documento aggiuntivo — ' . ($arguments['nome'] ?? ''))
            ->modalDescription(fn (array $arguments) => ($arguments['presente'] ?? false)
                ? 'Il file precedente verrà eliminato dal disco. Possibile solo finché il documento non è verificato.'
                : 'Stai caricando il documento al posto dell\'iscritto, che ha già inviato il form.')
            ->form([
                Forms\Components\FileUpload::make('documento')
                    ->label('Nuovo documento (solo PDF, max 10 MB)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('documenti-aggiuntivi/sostituzioni')
                    ->required(),
            ])
            ->modalSubmitActionLabel('Sostituisci')
            ->action(function (array $data, array $arguments): void {
                $iscrizione = $this->iscrizionePerArg($arguments);
                $risposta   = $iscrizione?->rispostaPreregistrazione;

                if (! $risposta || ! $this->record->richiede_documento_aggiuntivo) {
                    Storage::disk('local')->delete($data['documento']);
                    Notification::make()->title('Documento non previsto per questa iscrizione.')->warning()->send();
                    return;
                }

                $ok = app(SostituzioneDocumento::class)->sostituisci($risposta, 'documento_aggiuntivo', $data['documento']);

                $this->record->load('iscrizioni.rispostaPreregistrazione.documentoAggiuntivoVerificatoDa');

                Notification::make()
                    ->title($ok
                        ? 'Documento caricato per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome
                        : 'Documento già verificato: non è più sostituibile.')
                    ->{$ok ? 'success' : 'warning'}()
                    ->send();
            });
    }

    /** True se l'iscrizione ha già compilato il form (→ "ritira" invece di "rimuovi"). */
    private function iscrizioneCompilata(array $arguments): bool
    {
        return $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null)?->rispostaPreregistrazione !== null;
    }

    public function rimuoviIscrittoAction(): Action
    {
        return Action::make('rimuoviIscritto')
            ->label('Rimuovi iscritto')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => $this->iscrizioneCompilata($arguments) ? 'Ritira iscritto' : 'Rimuovi iscritto')
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return $iscrizione?->rispostaPreregistrazione !== null
                    ? "Ritirare {$nome} dal corso? Poiché ha già compilato il form verrà segnato come Ritirato e i suoi dati (form e pagamento) verranno conservati."
                    : "Rimuovere {$nome} dal corso? Non ha compilato il form: l'iscrizione verrà eliminata definitivamente.";
            })
            ->modalSubmitActionLabel(fn (array $arguments) => $this->iscrizioneCompilata($arguments) ? 'Ritira' : 'Rimuovi')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $nome = $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome;

                if ($iscrizione->rispostaPreregistrazione !== null) {
                    // Ha compilato il form: segna come ritirato, conserva dati pagamento
                    Presenza::where('iscrizione_id', $iscrizione->id)->where('fonte', 'form')->delete();
                    $iscrizione->update(['stato' => 'ritirato']);

                    Notification::make()
                        ->title("{$nome} segnato come ritirato.")
                        ->body('I dati di pagamento sono stati conservati.')
                        ->success()
                        ->send();
                } else {
                    // Non ha compilato il form: rimozione definitiva
                    $ricevutaPath  = $iscrizione->rispostaPreregistrazione?->ricevuta_path;
                    $nullaostaPath = $iscrizione->rispostaPreregistrazione?->nullaosta_path;
                    if ($ricevutaPath)  Storage::disk('local')->delete($ricevutaPath);
                    if ($nullaostaPath) Storage::disk('local')->delete($nullaostaPath);
                    if ($docAggPath = $iscrizione->rispostaPreregistrazione?->documento_aggiuntivo_path) {
                        Storage::disk('local')->delete($docAggPath);
                    }

                    Presenza::where('iscrizione_id', $iscrizione->id)->where('fonte', 'form')->delete();
                    $iscrizione->rispostaPreregistrazione?->delete();
                    $iscrizione->delete();

                    Notification::make()
                        ->title("{$nome} rimosso dal corso.")
                        ->success()
                        ->send();
                }

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);
                $this->caricaConflitti();
            });
    }

    public function resetFormIscrittoAction(): Action
    {
        return Action::make('resetFormIscritto')
            ->label('Reset form')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Resettare il form di {$nome}? Verranno eliminati tutti i dati compilati (incluse eventuali presenze modificate dalla segreteria). Il partecipante potrà ricompilare dal link originale.";
            })
            ->modalSubmitActionLabel('Sì, resetta')
            ->action(function (array $arguments): void {
                $user = auth()->user();

                $isSuperadmin = $user?->isSuperadmin();
                $isReferente  = $user?->isAdmin() && $this->record->evento->referenti()
                    ->where('user_id', $user->id)
                    ->exists();

                if (! $isSuperadmin && ! $isReferente) {
                    Notification::make()->title('Operazione non consentita.')->danger()->send();
                    return;
                }

                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $risposta = $iscrizione->rispostaPreregistrazione;
                if (! $risposta) {
                    Notification::make()->title('Nessun form compilato da resettare.')->warning()->send();
                    return;
                }

                if ($risposta->ricevuta_path) {
                    Notification::make()
                        ->title('Reset non consentito')
                        ->body('È presente una ricevuta di pagamento allegata. Rimuovila prima di poter resettare il form.')
                        ->danger()
                        ->send();
                    return;
                }

                if ($risposta->nullaosta_path) {
                    Storage::disk('local')->delete($risposta->nullaosta_path);
                }
                if ($risposta->documento_aggiuntivo_path) {
                    Storage::disk('local')->delete($risposta->documento_aggiuntivo_path);
                }

                // Cancella tutte le presenze (form + admin) — reset completo
                Presenza::where('iscrizione_id', $iscrizione->id)->delete();

                $risposta->delete();
                $iscrizione->update(['stato' => 'pending']);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);

                $nome = trim($iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome);
                Notification::make()
                    ->title("Form di {$nome} resettato.")
                    ->body('Il partecipante può ora ricompilare il form dal link originale.')
                    ->success()
                    ->send();
            });
    }

    public function ripristinaIscrittoAction(): Action
    {
        return Action::make('ripristinaIscritto')
            ->label('Ripristina')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Ripristinare {$nome} come iscritto attivo?";
            })
            ->modalSubmitActionLabel('Ripristina')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione || ! $iscrizione->isRitirato()) return;

                $stato = $iscrizione->rispostaPreregistrazione ? 'form_completato' : 'pending';
                $iscrizione->update(['stato' => $stato]);

                $this->record->refresh()->load([
                    'iscrizioni.partecipante.comitatoLocale',
                    'iscrizioni.rispostaPreregistrazione',
                ]);
                $this->caricaConflitti();

                $nome = $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome;
                Notification::make()
                    ->title("{$nome} ripristinato come iscritto attivo.")
                    ->success()
                    ->send();
            });
    }

    public function copiaLink(int $iscrizioneId): void
    {
        // Il link viene copiato lato JS — questo metodo non fa nulla sul server
    }
}
