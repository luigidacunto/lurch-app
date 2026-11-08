<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Imports\StaffImport;
use App\Mail\FormPreregistrazioneStaffLink;
use App\Mail\InfoEventoStaff;
use App\Mail\SollecitoPreregistrazioneStaffLink;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\EmailTemplate;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\Presenza;
use App\Models\Socio;
use App\Models\SocioEvento;
use App\Services\SostituzioneDocumento;
use App\Services\TemplatePlaceholders;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Attributes\Locked;

class GestioneStaff extends Page
{
    protected static string $resource = EventoResource::class;

    protected string $view = 'filament.resources.evento-resource.pages.gestione-staff';

    #[Locked]
    public Evento $record;

    public static function canAccess(array $parameters = []): bool
    {
        // Filament's own mountCanAuthorizeAccess() hook calls canAccess() with
        // zero arguments on every mount (Filament\Pages\Concerns\CanAuthorizeAccess) —
        // that call must stay a no-op (true), the real per-record check only runs
        // explicitly via abort_unless() in mount() below, which always passes 'record'.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }

        $record = $parameters['record'];

        return $record instanceof Evento && EventoResource::canEdit($record);
    }

    public function getTitle(): string
    {
        return 'Gestione staff — ' . $this->record->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventoResource::getUrl('index')                                     => 'Eventi',
            EventoResource::getUrl('gestione', ['record' => $this->record])     => $this->record->nome,
            ''                                                                  => 'Gestione staff',
        ];
    }

    public function mount(Evento $record): void
    {
        abort_unless(static::canAccess(['record' => $record]), 403);

        $this->record = $record->load([
            'staff.partecipante.comitatoLocale',
            'staff.rispostaPreregistrazione.nullaostaVerificatoDa',
            'staff.candidaturaStaff.corsi',
            'corsi:id,evento_id,numero_protocollo,nome',
        ]);
    }

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return $this->record;
    }

    /** Ricarica lo staff con le relazioni usate dal blade — stesso set ovunque per evitare
     *  N+1 dopo ogni azione che tocca rispostaPreregistrazione. */
    private function reloadStaff(): void
    {
        $this->record->refresh()->load([
            'staff.partecipante.comitatoLocale',
            'staff.rispostaPreregistrazione.nullaostaVerificatoDa',
            'staff.candidaturaStaff.corsi',
            'corsi:id,evento_id,numero_protocollo,nome',
        ]);
    }

    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->importaStaffAction(),
                $this->aggiungiStaffAction(),
            ])
                ->label('Azioni')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('info')
                ->button(),
        ];
    }

    public function importaStaffAction(): Action
    {
        return Action::make('importaStaff')
            ->label('Importa Staff')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('brand_pink')
            ->form([
                Forms\Components\Placeholder::make('istruzioni')
                    ->label('')
                    ->content('Carica un file Excel (formato GAIA). Colonne usate: cognome, nome, codice_fiscale, data_di_nascita, email, numeri_di_telefono, comitato, sede_regionale. Colonna opzionale: ruolo (logistica / docenza).'),
                Forms\Components\FileUpload::make('file_xlsx')
                    ->label('File Excel (.xlsx / .xls)')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->disk('local')
                    ->directory('imports-tmp')
                    ->required(),
            ])
            ->modalHeading('Importa Staff da Excel')
            ->modalSubmitActionLabel('Avvia import')
            ->action(function (array $data): void {
                $path     = $data['file_xlsx'];
                $fullPath = Storage::disk('local')->path($path);
                $import   = new StaffImport($this->record->id);
                try {
                    Excel::import($import, $fullPath);
                } finally {
                    Storage::disk('local')->delete($path);
                }

                $this->record->refresh()->load([
                    'staff.partecipante.comitatoLocale',
                    'staff.rispostaPreregistrazione',
                ]);

                $erroriCount = count($import->errori);
                $body = "Staff iscritti: {$import->iscritti} · Soci creati: {$import->creati} · Soci aggiornati: {$import->aggiornati}"
                    . ($erroriCount > 0 ? " · Errori: {$erroriCount}" : '');

                if ($erroriCount > 0) {
                    Notification::make()
                        ->title('Import completato con errori')
                        ->body($body . "\n" . implode("\n", array_slice($import->errori, 0, 5)))
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Import staff completato')
                        ->body($body)
                        ->success()
                        ->send();
                }
            });
    }

    public function aggiungiStaffAction(): Action
    {
        return Action::make('aggiungiStaff')
            ->label('Aggiungi staff')
            ->icon('heroicon-o-user-plus')
            ->color('success')
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

                Group::make([
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
                    ->required()
                    ->maxLength(255),

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
            ->modalHeading('Aggiungi membro staff')
            ->modalSubmitActionLabel('Aggiungi')
            ->action(function (array $data): void {
                $cf    = strtoupper(trim($data['codice_fiscale']));
                $socio = Socio::where('codice_fiscale', $cf)->first();

                if ($socio) {
                    $updates = [];
                    if (! $socio->email && $data['email']) $updates['email'] = $data['email'];
                    if (! $socio->telefono && ($data['telefono'] ?? null)) {
                        $updates['telefono']         = $data['telefono'];
                        $updates['prefisso_telefono'] = $data['prefisso_telefono'] ?? '+39';
                    }
                    if ($updates) $socio->update($updates);
                } else {
                    $socio = Socio::create([
                        'codice_fiscale'        => $cf,
                        'cognome'               => mb_convert_case(trim($data['cognome']), MB_CASE_TITLE, 'UTF-8'),
                        'nome'                  => mb_convert_case(trim($data['nome']), MB_CASE_TITLE, 'UTF-8'),
                        'email'                 => strtolower(trim($data['email'])),
                        'prefisso_telefono'     => $data['prefisso_telefono'] ?? '+39',
                        'telefono'              => $data['telefono'] ?? null,
                        'comitato_regionale_id' => $data['comitato_regionale_id'],
                        'comitato_locale_id'    => $data['comitato_locale_id'] ?? null,
                    ]);
                }

                if ($this->record->staff()->where('partecipante_id', $socio->id)->exists()) {
                    Notification::make()
                        ->title($socio->cognome . ' ' . $socio->nome . ' è già nello staff.')
                        ->warning()
                        ->send();
                    return;
                }

                IscrizioneStaff::create([
                    'evento_id'       => $this->record->id,
                    'partecipante_id' => $socio->id,
                    'token'           => Str::uuid(),
                    'stato'           => 'pending',
                ]);

                SocioEvento::firstOrCreate([
                    'evento_id'       => $this->record->id,
                    'partecipante_id' => $socio->id,
                    'ruolo'           => 'staff',
                ]);

                $this->record->refresh()->load([
                    'staff.partecipante.comitatoLocale',
                    'staff.rispostaPreregistrazione',
                ]);

                Notification::make()
                    ->title($socio->cognome . ' ' . $socio->nome . ' aggiunto allo staff.')
                    ->success()
                    ->send();
            });
    }

    /** Staff che non ha ancora ricevuto il form di preregistrazione. */
    private function staffFormPendenti(): \Illuminate\Support\Collection
    {
        return $this->record->staff->filter(fn ($i) => ! $i->form_inviato_at);
    }

    /** Staff che non ha ancora risposto al form (sollecitabile, anche più volte). */
    private function staffSollecitoPendenti(): \Illuminate\Support\Collection
    {
        return $this->record->staff->filter(
            fn ($i) => $i->rispostaPreregistrazione === null
        );
    }

    /** Staff che ha completato la preregistrazione e non ha ancora ricevuto la comunicazione
     *  "info evento". */
    private function staffInfoPendenti(): \Illuminate\Support\Collection
    {
        return $this->record->staff->filter(fn ($i) => $i->rispostaPreregistrazione !== null && ! $i->info_evento_inviato_at);
    }

    public function inviaTuttiAction(): Action
    {
        return Action::make('inviaTutti')
            ->label(function () {
                $data = $this->record->invio_massivo_staff_at;
                return $data ? 'Invia form a tutti (ultimo invio: ' . $data->format('d/m/Y H:i') . ')' : 'Invia form a tutti';
            })
            ->icon(fn () => $this->staffFormPendenti()->isEmpty() ? 'heroicon-o-check-circle' : 'heroicon-o-paper-airplane')
            ->color(fn () => $this->staffFormPendenti()->isEmpty() ? 'gray' : 'brand_pink')
            ->disabled(fn () => today()->gt($this->record->data_fine) || $this->staffFormPendenti()->isEmpty())
            ->tooltip(function () {
                if (today()->gt($this->record->data_fine)) return 'Evento concluso: invio non più disponibile';
                if ($this->staffFormPendenti()->isEmpty()) return 'Tutto lo staff ha già ricevuto il form';
                return null;
            })
            ->requiresConfirmation()
            ->modalHeading('Invia form di preregistrazione staff')
            ->modalDescription(function () {
                if (! $this->record->form_staff_aperto_at) {
                    return 'Il form staff non è ancora attivo. Attivalo prima di inviare le email (pulsante "Attiva form staff" nella pagina Modifica evento).';
                }
                $destinatari = $this->staffFormPendenti();
                $conEmail    = $destinatari->filter(fn ($i) => $i->partecipante?->email)->count();
                $senzaEmail  = $destinatari->count() - $conEmail;
                $msg = "Verranno inviate {$conEmail} email a chi non le ha ancora ricevute.";
                if ($senzaEmail > 0) $msg .= " {$senzaEmail} membri senza email verranno saltati.";
                return $msg;
            })
            ->modalSubmitActionLabel('Invia')
            ->action(function () {
                if (! $this->record->form_staff_aperto_at) {
                    Notification::make()->title('Attiva prima il form staff dall\'evento.')->warning()->send();
                    return;
                }

                $inviati = 0;
                $saltati = 0;
                $this->record->staff->loadMissing('evento');

                foreach ($this->staffFormPendenti() as $iscrizione) {
                    $email = $iscrizione->partecipante?->email;
                    if (! $email) { $saltati++; continue; }

                    Mail::to($email)->send(new FormPreregistrazioneStaffLink($iscrizione));
                    $iscrizione->update(['form_inviato_at' => now()]);
                    $inviati++;
                }

                $this->record->refresh()->load([
                    'staff.partecipante.comitatoLocale',
                    'staff.rispostaPreregistrazione',
                ]);

                $this->record->update(['invio_massivo_staff_at' => now()]);

                $msg = "Form inviato a {$inviati} membri staff.";
                if ($saltati > 0) $msg .= " {$saltati} saltati (senza email).";

                Notification::make()->title($msg)->success()->send();
            });
    }

    public function inviaSollecitoTuttiAction(): Action
    {
        return Action::make('inviaSollecitoTutti')
            ->label(function () {
                $data = $this->record->invio_massivo_sollecito_staff_at;
                return $data ? 'Invia sollecito a tutti (ultimo invio: ' . $data->format('d/m/Y H:i') . ')' : 'Invia sollecito a tutti';
            })
            ->icon(fn () => $this->staffSollecitoPendenti()->isEmpty() ? 'heroicon-o-check-circle' : 'heroicon-o-bell-alert')
            ->color(fn () => $this->staffSollecitoPendenti()->isEmpty() ? 'gray' : 'brand_pink')
            ->disabled(fn () => today()->gt($this->record->data_fine) || $this->staffSollecitoPendenti()->isEmpty())
            ->tooltip(function () {
                if (today()->gt($this->record->data_fine)) return 'Evento concluso: invio non più disponibile';
                if ($this->staffSollecitoPendenti()->isEmpty()) return 'Nessun membro staff da sollecitare al momento';
                return null;
            })
            ->requiresConfirmation()
            ->modalHeading('Invia sollecito preregistrazione staff')
            ->modalDescription(function () {
                $count = $this->staffSollecitoPendenti()
                    ->filter(fn ($i) => $i->partecipante?->email)->count();
                return "Verranno inviati {$count} solleciti ai membri staff che non hanno ancora compilato il form (anche a chi ha già ricevuto un sollecito in precedenza).";
            })
            ->modalSubmitActionLabel('Invia solleciti')
            ->action(function () {
                $inviati = 0;
                $saltati = 0;
                $this->record->staff->loadMissing('evento');

                foreach ($this->staffSollecitoPendenti() as $iscrizione) {
                    $email = $iscrizione->partecipante?->email;
                    if (! $email) { $saltati++; continue; }

                    Mail::to($email)->send(new SollecitoPreregistrazioneStaffLink($iscrizione));
                    $iscrizione->update(['sollecito_inviato_at' => now()]);
                    $inviati++;
                }

                $this->record->update(['invio_massivo_sollecito_staff_at' => now()]);

                $this->record->refresh()->load([
                    'staff.partecipante.comitatoLocale',
                    'staff.rispostaPreregistrazione',
                ]);

                $msg = "Sollecito inviato a {$inviati} membri staff.";
                if ($saltati > 0) $msg .= " {$saltati} saltati (senza email).";

                Notification::make()->title($msg)->success()->send();
            });
    }

    public function anteprimaEmailAction(): Action
    {
        return Action::make('anteprimaEmail')
            ->label('Form di preregistrazione')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->modalHeading('Anteprima email — ' . $this->record->nome)
            ->modalContent(function () {
                $evento   = $this->record;
                $template = EmailTemplate::risolvi($evento->comitato_regionale_id, 'staff');

                if (! $template) {
                    return new HtmlString(
                        '<div style="padding:20px; color:#6b7280; text-align:center;">
                            Nessun template email configurato per questa regione.<br>
                            <a href="' . \App\Filament\Resources\EmailTemplateResource::getUrl('create') . '" style="color:#2563eb;">Crea un template</a>
                        </div>'
                    );
                }

                $primoStaff   = $evento->staff->first();
                $soggetto     = TemplatePlaceholders::anteprimaPerEvento($template->soggetto, $evento, giorniScadenza: 7);
                $corpo        = TemplatePlaceholders::anteprimaPerEvento($template->corpo, $evento, giorniScadenza: 7);
                $comitatoNome = $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore';
                $haStaff      = $primoStaff !== null;
                $nomeStaff    = $primoStaff
                    ? trim(($primoStaff->partecipante?->cognome ?? '') . ' ' . ($primoStaff->partecipante?->nome ?? ''))
                    : '';

                return view('filament.resources.evento-resource.partials.anteprima-email-staff', compact(
                    'soggetto', 'corpo', 'comitatoNome', 'haStaff', 'nomeStaff'
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
                $evento   = $this->record;
                $template = EmailTemplate::risolvi($evento->comitato_regionale_id, 'sollecito_staff');

                if (! $template) {
                    return new HtmlString(
                        '<div style="padding:20px; color:#6b7280; text-align:center;">
                            Nessun template sollecito configurato per questa regione.<br>
                            <a href="' . \App\Filament\Resources\EmailTemplateResource::getUrl('create') . '" style="color:#2563eb;">Crea un template</a>
                        </div>'
                    );
                }

                $primoStaff   = $evento->staff->first();
                $soggetto     = TemplatePlaceholders::anteprimaPerEvento($template->soggetto, $evento, giorniScadenza: 1);
                $corpo        = TemplatePlaceholders::anteprimaPerEvento($template->corpo, $evento, giorniScadenza: 1);
                $comitatoNome = $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore';
                $haStaff      = $primoStaff !== null;
                $nomeStaff    = $primoStaff
                    ? trim(($primoStaff->partecipante?->cognome ?? '') . ' ' . ($primoStaff->partecipante?->nome ?? ''))
                    : '';

                return view('filament.resources.evento-resource.partials.anteprima-email-staff', compact(
                    'soggetto', 'corpo', 'comitatoNome', 'haStaff', 'nomeStaff'
                ));
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi')
            ->slideOver();
    }

    public function inviaFormSingolo(int $iscrizioneId): void
    {
        $iscrizione = $this->record->staff->firstWhere('id', $iscrizioneId);

        if (! $iscrizione) return;

        if (! $this->record->form_staff_aperto_at) {
            Notification::make()->title('Attiva prima il form staff dall\'evento.')->warning()->send();
            return;
        }

        if ($iscrizione->form_inviato_at) {
            Notification::make()->title('Form già inviato — usa il sollecito per un nuovo promemoria.')->warning()->send();
            return;
        }

        $email = $iscrizione->partecipante?->email;

        if (! $email) {
            Notification::make()
                ->title('Membro senza email — impossibile inviare.')
                ->warning()
                ->send();
            return;
        }

        $iscrizione->loadMissing('evento');

        Mail::to($email)->send(new FormPreregistrazioneStaffLink($iscrizione));
        $iscrizione->update(['form_inviato_at' => now()]);

        $this->record->refresh()->load([
            'staff.partecipante.comitatoLocale',
            'staff.rispostaPreregistrazione',
        ]);

        Notification::make()->title('Form inviato a ' . $email)->success()->send();
    }

    public function inviaSollecitoSingolo(int $iscrizioneId): void
    {
        $iscrizione = $this->record->staff->firstWhere('id', $iscrizioneId);
        if (! $iscrizione) return;

        $email = $iscrizione->partecipante?->email;

        if (! $email) {
            Notification::make()->title('Membro senza email — impossibile inviare.')->warning()->send();
            return;
        }

        $iscrizione->loadMissing('evento');

        Mail::to($email)->send(new SollecitoPreregistrazioneStaffLink($iscrizione));
        $iscrizione->update(['sollecito_inviato_at' => now()]);

        $this->record->refresh()->load([
            'staff.partecipante.comitatoLocale',
            'staff.rispostaPreregistrazione',
        ]);

        Notification::make()->title('Sollecito inviato a ' . $email)->success()->send();
    }

    public function inviaInfoStaffTuttiAction(): Action
    {
        return Action::make('inviaInfoStaffTutti')
            ->label(function () {
                $data = $this->record->invio_massivo_info_staff_at;
                return $data ? 'Invia info evento a tutti (ultimo invio: ' . $data->format('d/m/Y H:i') . ')' : 'Invia info evento a tutti';
            })
            ->icon(fn () => $this->staffInfoPendenti()->isEmpty() ? 'heroicon-o-check-circle' : 'heroicon-o-information-circle')
            ->color(fn () => $this->staffInfoPendenti()->isEmpty() ? 'gray' : 'brand_pink')
            // Contenuto mancante/troppo corto controllato PRIMA di "evento concluso" — stesso
            // ordine e stesso motivo già applicato su GestioneEvento (Evento::hasComunicazioneValida()).
            ->disabled(fn () => ! $this->record->hasComunicazioneValida() || today()->gt($this->record->data_fine) || $this->staffInfoPendenti()->isEmpty())
            ->tooltip(function () {
                if (! $this->record->hasComunicazioneValida()) return 'Nessuna informazione definita da inviare: entra nella modifica evento per inserire testo o allegati';
                if (today()->gt($this->record->data_fine)) return 'Evento concluso: invio non più disponibile';
                if ($this->staffInfoPendenti()->isEmpty()) return 'Tutto lo staff ha già ricevuto la comunicazione';
                return null;
            })
            ->requiresConfirmation()
            ->modalHeading('Invia comunicazione evento allo staff')
            ->modalDescription(function () {
                $count = $this->staffInfoPendenti()->filter(fn ($i) => $i->partecipante?->email)->count();
                return "Verrà inviata una email con le informazioni sull'evento (testo + allegati) a {$count} membri dello staff che non l'hanno ancora ricevuta. Procedere?";
            })
            ->action(function () {
                if (! $this->record->hasComunicazioneValida()) {
                    Notification::make()
                        ->title('Nessun contenuto da inviare')
                        ->body('Inserisci del testo o almeno un documento allegato nella sezione "Contenuti e documenti" dell\'evento.')
                        ->warning()
                        ->send();
                    return;
                }

                $inviati = 0;

                foreach ($this->staffInfoPendenti() as $iscrizione) {
                    $email = $iscrizione->partecipante?->email;
                    if (! $email) continue;

                    Mail::to($email)->send(new InfoEventoStaff($iscrizione));
                    $iscrizione->update(['info_evento_inviato_at' => now()]);
                    $inviati++;
                }

                $this->record->update(['invio_massivo_info_staff_at' => now()]);

                $this->record->refresh()->load([
                    'staff.partecipante.comitatoLocale',
                    'staff.rispostaPreregistrazione',
                ]);

                Notification::make()
                    ->title('Comunicazione inviata')
                    ->body("Inviata a {$inviati} membri dello staff.")
                    ->success()
                    ->send();
            });
    }

    public function anteprimaInfoEventoAction(): Action
    {
        return Action::make('anteprimaInfoEvento')
            ->label('Anteprima comunicazione')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->modalHeading('Anteprima comunicazione evento')
            ->modalWidth('4xl')
            ->modalContent(fn () => new HtmlString(GestioneEvento::renderAnteprimaInfoEvento($this->record)))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi');
    }

    public function inviaInfoStaffSingolo(int $iscrizioneId): void
    {
        if (! $this->record->hasComunicazioneValida()) {
            Notification::make()
                ->title('Nessun contenuto da inviare')
                ->body('Inserisci del testo o un documento allegato nell\'evento prima di inviare.')
                ->warning()
                ->send();
            return;
        }

        $iscrizione = $this->record->staff->firstWhere('id', $iscrizioneId);
        if (! $iscrizione) return;

        $email = $iscrizione->partecipante?->email;

        if (! $email) {
            Notification::make()->title('Membro senza email — impossibile inviare.')->warning()->send();
            return;
        }

        Mail::to($email)->send(new InfoEventoStaff($iscrizione));
        $iscrizione->update(['info_evento_inviato_at' => now()]);

        $this->record->refresh()->load([
            'staff.partecipante.comitatoLocale',
            'staff.rispostaPreregistrazione',
        ]);

        Notification::make()->title('Comunicazione inviata a ' . $email)->success()->send();
    }

    // -------------------------------------------------------------------------
    // Metodi helper: generazione slot e sezioni con estensione admin
    // -------------------------------------------------------------------------

    private function generaSlotEstesi(bool $isAdmin): array
    {
        $slots  = [];
        $evento = $this->record;
        $giorni = $evento->giorniEvento();

        if ($isAdmin) {
            $dayBefore = $giorni->isNotEmpty() ? $giorni->first()->copy()->subDay() : $evento->data_inizio->copy()->subDay();
            foreach (['mattina', 'pomeriggio'] as $porzione) {
                $data    = $dayBefore->format('Y-m-d');
                $slots[] = [
                    'chiave'   => $data . '_' . $porzione,
                    'data'     => $data,
                    'porzione' => $porzione,
                    'label'    => $dayBefore->translatedFormat('l j F') . ' — ' . ($porzione === 'mattina' ? 'Mattina' : 'Pomeriggio'),
                ];
            }
        }

        foreach ($giorni as $giorno) {
            foreach (['mattina', 'pomeriggio'] as $porzione) {
                $data    = $giorno->format('Y-m-d');
                $slots[] = [
                    'chiave'   => $data . '_' . $porzione,
                    'data'     => $data,
                    'porzione' => $porzione,
                    'label'    => $giorno->translatedFormat('l j F') . ' — ' . ($porzione === 'mattina' ? 'Mattina' : 'Pomeriggio'),
                ];
            }
        }

        if ($isAdmin) {
            $dayAfter = $giorni->isNotEmpty() ? $giorni->last()->copy()->addDay() : $evento->data_fine->copy()->addDay();
            foreach (['mattina', 'pomeriggio'] as $porzione) {
                $data    = $dayAfter->format('Y-m-d');
                $slots[] = [
                    'chiave'   => $data . '_' . $porzione,
                    'data'     => $data,
                    'porzione' => $porzione,
                    'label'    => $dayAfter->translatedFormat('l j F') . ' — ' . ($porzione === 'mattina' ? 'Mattina' : 'Pomeriggio'),
                ];
            }
        }

        return $slots;
    }

    private function generaSezioniEstese(bool $isAdmin): array
    {
        return \App\Services\EventoSezioniGenerator::perGiorni($this->record, bypassResidenziale: $isAdmin);
    }

    /** Dati griglia disponibilità per il blade (raggruppati per data → porzione).
     *  Il range di date è dinamico: include le date dell'evento + eventuali slot
     *  extra (giorno prima/dopo) inseriti dall'admin tramite modificaDatiStaff. */
    public function getGrigliaDisponibilita(): array
    {
        $staffConRisposta = $this->record->staff->filter(fn ($i) => $i->rispostaPreregistrazione !== null);

        // Raccoglie tutte le date presenti nelle disponibilità (anche extra admin)
        $tutteDate = collect();
        foreach ($staffConRisposta as $iscrizione) {
            foreach ($iscrizione->rispostaPreregistrazione->risposte['disponibilita'] ?? [] as $d) {
                if ($d['data'] ?? null) $tutteDate->push($d['data']);
            }
        }

        // Solo i giorni reali dell'evento + le date extra effettivamente presenti nelle risposte
        // (slot admin giorno prima/dopo): mai un range continuo, i giorni non contigui restano fuori.
        $period = $this->record->giorniEvento()
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->merge($tutteDate)
            ->unique()
            ->sort()
            ->map(fn ($d) => Carbon::parse($d));

        $griglia = [];
        foreach ($period as $giorno) {
            $data            = $giorno->format('Y-m-d');
            $griglia[$data]  = [
                'label'      => $giorno->translatedFormat('l j F'),
                'mattina'    => ['formazione' => [], 'logistica' => []],
                'pomeriggio' => ['formazione' => [], 'logistica' => []],
            ];
        }

        foreach ($staffConRisposta as $iscrizione) {
            $disponibilita = $iscrizione->rispostaPreregistrazione->risposte['disponibilita'] ?? [];
            $nome          = trim(($iscrizione->partecipante?->cognome ?? '') . ' ' . ($iscrizione->partecipante?->nome ?? ''));

            foreach ($disponibilita as $d) {
                $data     = $d['data']     ?? null;
                $porzione = $d['porzione'] ?? null;
                $tipo     = $d['tipo']     ?? null;

                if ($data && $porzione && $tipo && isset($griglia[$data][$porzione][$tipo])) {
                    $griglia[$data][$porzione][$tipo][] = $nome;
                }
            }
        }

        return $griglia;
    }

    // -------------------------------------------------------------------------
    // Action: modifica dati form staff (solo se form già compilato)
    // -------------------------------------------------------------------------

    public function modificaDatiStaffAction(): Action
    {
        return Action::make('modificaDatiStaff')
            ->label('Modifica dati')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->modalWidth('2xl')
            ->modalHeading(function (array $arguments): string {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $p          = $iscrizione?->partecipante;

                return 'Modifica dati — ' . trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? ''));
            })
            ->form(function (array $arguments): array {
                $isAdmin     = auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin();
                $slots       = $this->generaSlotEstesi($isAdmin);
                $sezioni     = $this->generaSezioniEstese($isAdmin);
                $slotOptions = collect($slots)->mapWithKeys(fn ($s) => [$s['chiave'] => $s['label']])->all();
                $pastiOpts   = collect($sezioni)->mapWithKeys(fn ($s) => [$s['tipo'] . '_' . $s['data'] => $s['label']])->all();

                return [
                    FormSection::make('Disponibilità operativa')
                        ->description('Seleziona gli slot in cui il membro staff sarà presente e il proprio ruolo.')
                        ->columns(2)
                        ->schema([
                            Forms\Components\CheckboxList::make('disponibilita_formazione')
                                ->label('Come Docenza')
                                ->options($slotOptions)
                                ->columns(1),
                            Forms\Components\CheckboxList::make('disponibilita_logistica')
                                ->label('Come Logistica')
                                ->options($slotOptions)
                                ->columns(1),
                        ]),

                    FormSection::make('Pasti e pernotti')
                        ->schema([
                            Forms\Components\CheckboxList::make('presenze_pasti')
                                ->label('')
                                ->options($pastiOpts)
                                ->columns(2),
                        ]),
                ];
            })
            ->fillForm(function (array $arguments): array {
                $isAdmin    = auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin();
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);

                if (! $iscrizione?->rispostaPreregistrazione) {
                    return [
                        'disponibilita_formazione' => [],
                        'disponibilita_logistica'  => [],
                        'presenze_pasti'           => [],
                    ];
                }

                $risposte      = $iscrizione->rispostaPreregistrazione->risposte ?? [];
                $disponibilita = $risposte['disponibilita'] ?? [];
                $dispForm      = [];
                $dispLog       = [];

                foreach ($disponibilita as $d) {
                    $chiave = $d['data'] . '_' . $d['porzione'];
                    if (($d['tipo'] ?? '') === 'formazione') $dispForm[] = $chiave;
                    if (($d['tipo'] ?? '') === 'logistica')  $dispLog[]  = $chiave;
                }

                // Presenze dalla tabella (fonte canonica); colazioni escluse (auto-derivate)
                $sezioniChiavi = collect($this->generaSezioniEstese($isAdmin))
                    ->map(fn ($s) => $s['tipo'] . '_' . $s['data'])
                    ->flip()
                    ->all();

                $presenze = Presenza::where('evento_id', $this->record->id)
                    ->where('partecipante_id', $iscrizione->partecipante_id)
                    ->whereNull('iscrizione_id')
                    ->get()
                    ->map(fn ($p) => $p->tipo . '_' . $p->data->format('Y-m-d'))
                    ->filter(fn ($k) => isset($sezioniChiavi[$k]))
                    ->values()
                    ->toArray();

                return [
                    'disponibilita_formazione' => $dispForm,
                    'disponibilita_logistica'  => $dispLog,
                    'presenze_pasti'           => $presenze,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione?->rispostaPreregistrazione) return;

                $isAdmin = auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin();
                $slots   = $this->generaSlotEstesi($isAdmin);
                $sezioni = $this->generaSezioniEstese($isAdmin);

                // Ricostruisce l'array disponibilità
                $dispForm = collect($data['disponibilita_formazione'] ?? []);
                $dispLog  = collect($data['disponibilita_logistica'] ?? []);

                $disponibilita = [];
                foreach ($slots as $slot) {
                    if ($dispForm->contains($slot['chiave'])) {
                        $disponibilita[] = ['data' => $slot['data'], 'porzione' => $slot['porzione'], 'tipo' => 'formazione'];
                    }
                    if ($dispLog->contains($slot['chiave'])) {
                        $disponibilita[] = ['data' => $slot['data'], 'porzione' => $slot['porzione'], 'tipo' => 'logistica'];
                    }
                }

                // Aggiorna risposte JSON
                $risposte                  = $iscrizione->rispostaPreregistrazione->risposte ?? [];
                $risposte['disponibilita'] = $disponibilita;

                $selezionatiPasti = collect($data['presenze_pasti'] ?? []);
                foreach ($sezioni as $s) {
                    $chiave          = $s['tipo'] . '_' . $s['data'];
                    $risposte[$chiave] = $selezionatiPasti->contains($chiave);
                }

                $iscrizione->rispostaPreregistrazione->update(['risposte' => $risposte]);

                // Aggiorna giorni_disponibili sull'iscrizione
                $giorni = collect($disponibilita)->pluck('data')->unique()->values()->toArray();

                // Sincronizza ruolo con la disponibilità dichiarata: la docenza prevale se presente
                // anche solo su uno slot (prima d'ora questo campo restava scollegato da qui, mai
                // aggiornato da questa modale).
                $ruolo = match (true) {
                    $dispForm->isNotEmpty() => 'docenza',
                    $dispLog->isNotEmpty()  => 'logistica',
                    default                 => $iscrizione->ruolo,
                };

                $iscrizione->update(['giorni_disponibili' => $giorni, 'ruolo' => $ruolo]);

                // Risincronizza presenze pasti (cancella e ricrea)
                Presenza::where('evento_id', $this->record->id)
                    ->where('partecipante_id', $iscrizione->partecipante_id)
                    ->whereNull('iscrizione_id')
                    ->delete();

                foreach ($sezioni as $s) {
                    $chiave = $s['tipo'] . '_' . $s['data'];
                    if ($selezionatiPasti->contains($chiave)) {
                        Presenza::create([
                            'evento_id'       => $this->record->id,
                            'partecipante_id' => $iscrizione->partecipante_id,
                            'iscrizione_id'   => null,
                            'tipo'            => $s['tipo'],
                            'data'            => $s['data'],
                            'fonte'           => 'admin',
                            'updated_by'      => auth()->id(),
                        ]);
                    }
                }

                // Colazione auto-derivata: esiste se e solo se il pernotto della notte precedente è selezionato
                $pernotti = collect($sezioni)->where('tipo', 'pernotto');
                foreach ($pernotti as $s) {
                    $dataColazione = \Carbon\Carbon::parse($s['data'])->addDay()->format('Y-m-d');
                    $pernottoChi   = 'pernotto_' . $s['data'];

                    if ($selezionatiPasti->contains($pernottoChi)) {
                        Presenza::firstOrCreate([
                            'evento_id'       => $this->record->id,
                            'partecipante_id' => $iscrizione->partecipante_id,
                            'iscrizione_id'   => null,
                            'tipo'            => 'colazione',
                            'data'            => $dataColazione,
                        ], ['fonte' => 'admin', 'updated_by' => auth()->id()]);
                    } else {
                        Presenza::where('evento_id', $this->record->id)
                            ->where('partecipante_id', $iscrizione->partecipante_id)
                            ->whereNull('iscrizione_id')
                            ->where('tipo', 'colazione')
                            ->where('data', $dataColazione)
                            ->delete();
                    }
                }

                $this->record->refresh()->load([
                    'staff.partecipante.comitatoLocale',
                    'staff.rispostaPreregistrazione',
                ]);

                Notification::make()->title('Dati aggiornati.')->success()->send();
            });
    }

    public function visualizzaNullaostaAction(): Action
    {
        return Action::make('visualizzaNullaosta')
            ->label('Visualizza')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => 'Nullaosta — ' . ($arguments['nome'] ?? ''))
            ->modalContent(fn (array $arguments) => view('filament.resources.corso-resource.partials.anteprima-documento', [
                'url' => route('iscrizioni-staff.nullaosta-preview', $arguments['iscrizione_id']),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->slideOver();
    }

    public function verificaNullaostaAction(): Action
    {
        return Action::make('verificaNullaosta')
            ->label('Verifica')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Confermi che il nullaosta di {$nome} è valido?";
            })
            ->modalSubmitActionLabel('Verifica')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $risposta   = $iscrizione?->rispostaPreregistrazione;

                if (! $risposta || ! $risposta->nullaosta_path) {
                    Notification::make()->title('Nessun nullaosta allegato — impossibile verificare.')->warning()->send();
                    return;
                }

                $risposta->update([
                    'nullaosta_verificato'       => true,
                    'nullaosta_verificato_at'    => now(),
                    'nullaosta_verificato_da_id' => Auth::id(),
                ]);

                $this->reloadStaff();

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
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Annullare la verifica del nullaosta di {$nome}?";
            })
            ->modalSubmitActionLabel('Annulla verifica')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $risposta   = $iscrizione?->rispostaPreregistrazione;
                if (! $risposta) return;

                $risposta->update([
                    'nullaosta_verificato'       => false,
                    'nullaosta_verificato_at'    => null,
                    'nullaosta_verificato_da_id' => null,
                ]);

                $this->reloadStaff();

                Notification::make()
                    ->title('Verifica annullata per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome)
                    ->warning()
                    ->send();
            });
    }

    /**
     * Sostituzione del nullaosta caricato per errore, consentita solo finché non è stato
     * verificato (poi la pillola è di sola lettura).
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
                    ->label('Nuovo nullaosta (PDF o JPG, max 10 MB)')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg'])
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('nullaosta-staff/sostituzioni')
                    ->required(),
            ])
            ->modalSubmitActionLabel('Sostituisci')
            ->action(function (array $data, array $arguments): void {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $risposta   = $iscrizione?->rispostaPreregistrazione;

                if (! $risposta || ! $risposta->nullaosta_path) {
                    Notification::make()->title('Nessun nullaosta da sostituire.')->warning()->send();
                    return;
                }

                $ok = app(SostituzioneDocumento::class)->sostituisci($risposta, 'nullaosta', $data['documento']);

                $this->reloadStaff();

                Notification::make()
                    ->title($ok
                        ? 'Nullaosta sostituito per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome
                        : 'Nullaosta già verificato: non è più sostituibile.')
                    ->{$ok ? 'success' : 'warning'}()
                    ->send();
            });
    }

    public function resetFormStaffAction(): Action
    {
        return Action::make('resetFormStaff')
            ->label('Reset form')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Resettare il form di {$nome}? Verranno eliminati tutti i dati compilati (incluse eventuali presenze modificate dalla segreteria). Il membro staff potrà ricompilare dal link originale.";
            })
            ->modalSubmitActionLabel('Sì, resetta')
            ->action(function (array $arguments): void {
                $user = auth()->user();

                if (! $user?->isSuperadmin() && ! $user?->isAdmin()) {
                    Notification::make()->title('Operazione non consentita.')->danger()->send();
                    return;
                }

                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $risposta = $iscrizione->rispostaPreregistrazione;
                if (! $risposta) {
                    Notification::make()->title('Nessun form compilato da resettare.')->warning()->send();
                    return;
                }

                // Cancella tutte le presenze staff (form + admin) — reset completo
                \App\Models\Presenza::where('evento_id', $this->record->id)
                    ->where('partecipante_id', $iscrizione->partecipante_id)
                    ->whereNull('iscrizione_id')
                    ->delete();

                $risposta->delete();
                $iscrizione->update(['stato' => 'pending']);

                $this->reloadStaff();

                $nome = trim(($iscrizione->partecipante?->cognome ?? '') . ' ' . ($iscrizione->partecipante?->nome ?? ''));
                Notification::make()
                    ->title("Form di {$nome} resettato.")
                    ->body('Il membro staff può ora ricompilare il form dal link originale.')
                    ->success()
                    ->send();
            });
    }

    public function rimuoviStaffAction(): Action
    {
        return Action::make('rimuoviStaff')
            ->label('Rimuovi dallo staff')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Rimuovere {$nome} dallo staff?";
            })
            ->modalSubmitActionLabel('Rimuovi')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->staff->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $nome = trim(($iscrizione->partecipante?->cognome ?? '') . ' ' . ($iscrizione->partecipante?->nome ?? ''));

                \App\Services\StaffRimozione::rimuovi($iscrizione);

                $this->reloadStaff();

                Notification::make()
                    ->title("{$nome} rimosso dallo staff.")
                    ->success()
                    ->send();
            });
    }
}
