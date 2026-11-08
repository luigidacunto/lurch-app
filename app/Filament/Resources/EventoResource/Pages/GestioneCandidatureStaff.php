<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Models\CandidaturaStaff;
use App\Models\EmailVerifica;
use App\Models\Evento;
use App\Models\IscrizioneStaff;
use App\Models\Socio;
use App\Models\SocioEvento;
use App\Services\CandidaturaSocioSync;
use App\Services\EventoSezioniGenerator;
use App\Services\StaffRimozione;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class GestioneCandidatureStaff extends ManageRelatedRecords
{
    protected static string $resource = EventoResource::class;

    protected static string $relationship = 'candidatureStaff';

    protected static ?string $title = 'Candidature staff';

    public function getTitle(): string
    {
        return 'Candidature staff — ' . $this->getRecord()->nome;
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof Evento && EventoResource::canEdit($record);
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventoResource::getUrl('index')                                 => 'Eventi',
            EventoResource::getUrl('gestione', ['record' => $this->getRecord()]) => $this->getRecord()->nome,
            ''                                                                => 'Candidature staff',
        ];
    }

    /**
     * Logica di conferma condivisa tra l'azione di riga e l'azione bulk: sincronizza il socio,
     * crea/aggiorna l'IscrizioneStaff (firstOrNew: non tocca token/stato se già staff esistente)
     * e il SocioEvento, poi segna la candidatura confermata.
     */
    protected static function confermaCandidatura(CandidaturaStaff $record): void
    {
        $socio = CandidaturaSocioSync::sincronizza([
            'socio_id'              => $record->socio_id,
            'codice_fiscale'        => $record->codice_fiscale,
            'nome'                  => $record->nome,
            'cognome'               => $record->cognome,
            'email'                 => $record->email,
            'telefono'              => $record->telefono,
            'comitato_regionale_id' => $record->comitato_regionale_id,
            'comitato_locale_id'    => $record->comitato_locale_id,
            'email_verificata_at'   => $record->email_verificata_at,
        ]);

        $iscrizione = IscrizioneStaff::firstOrNew([
            'evento_id'       => $record->evento_id,
            'partecipante_id' => $socio->id,
        ]);
        if (! $iscrizione->exists) {
            $iscrizione->token = Str::uuid();
            $iscrizione->stato = 'pending';
        }
        $iscrizione->candidatura_staff_id = $record->id;
        $iscrizione->ruolo                = $record->haDisponibilitaDocente() ? 'docenza' : 'logistica';
        $iscrizione->save();

        SocioEvento::firstOrCreate([
            'evento_id'       => $record->evento_id,
            'partecipante_id' => $socio->id,
            'ruolo'           => 'staff',
        ]);

        $record->update([
            'socio_id'         => $socio->id,
            'stato'            => 'confermato',
            'confermato_at'    => now(),
            'confermato_da_id' => auth()->id(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copiaLink')
                ->label('Copia link candidatura')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->extraAttributes(function () {
                    /** @var Evento $evento */
                    $evento = $this->getRecord();
                    $url    = route('candidatura-staff.mostra', $evento->candidature_staff_uuid);

                    return [
                        'x-on:click.prevent.stop' => 'window.navigator.clipboard.writeText(' . \Illuminate\Support\Js::from($url) . ");"
                            . " \$tooltip('Link copiato!', { theme: \$store.theme, timeout: 2000 })",
                    ];
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cognome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('codice_fiscale')
                    ->label('CF')
                    ->searchable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('comitatoLocale.nome')
                    ->label('Comitato')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('anagrafica')
                    ->label('Anagrafica')
                    ->getStateUsing(fn (CandidaturaStaff $record) => match (true) {
                        (bool) $record->socio_id => 'trovato',
                        (bool) $record->confermato_esterno_at => 'esterno',
                        default => 'da_verificare',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'trovato' => 'heroicon-o-check-circle',
                        'esterno' => 'heroicon-o-minus-circle',
                        default   => 'heroicon-o-x-circle',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'trovato' => 'success',
                        'esterno' => 'gray',
                        default   => 'danger',
                    })
                    ->tooltip(fn (CandidaturaStaff $record) => match (true) {
                        (bool) $record->socio_id => 'Socio trovato in archivio',
                        (bool) $record->confermato_esterno_at => 'Confermato come esterno all\'associazione'
                            . ($record->confermatoEsternoDa ? " da {$record->confermatoEsternoDa->name}" : '')
                            . ' il ' . $record->confermato_esterno_at->format('d/m/Y'),
                        default => 'Socio non trovato — verificare CF/dati',
                    }),

                Tables\Columns\IconColumn::make('email_verificata')
                    ->label('Email')
                    ->getStateUsing(fn (CandidaturaStaff $record) => EmailVerifica::isVerificata($record))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (CandidaturaStaff $record) => EmailVerifica::isVerificata($record)
                        ? 'Email verificata'
                        : 'Email non ancora verificata'),

                Tables\Columns\TextColumn::make('disponibilita')
                    ->label('Disponibilità')
                    ->html()
                    ->getStateUsing(function (CandidaturaStaff $record) {
                        $giorni = collect($record->disponibilita)->sortBy([['data', 'asc'], ['porzione', 'asc']])->groupBy('data');
                        if ($giorni->isEmpty()) return '—';

                        $righe = $giorni->map(function ($slots, $data) {
                            $dataLabel = \Carbon\Carbon::parse($data)->day . '-' . mb_strtolower(\Carbon\Carbon::parse($data)->translatedFormat('M'));

                            $sigle = $slots->map(function ($s) {
                                $sigla = ($s['porzione'] === 'mattina' ? 'M' : 'P') . ($s['tipo'] === 'docente' ? 'D' : 'L');
                                $tooltip = ucfirst($s['porzione']) . ' · ' . ($s['tipo'] === 'docente' ? 'Docenza' : 'Logistica');
                                [$colore, $sfondo, $bordo] = $s['tipo'] === 'docente' ? ['#7c3aed', '#f5f3ff', '#ddd6fe'] : ['#0f766e', '#f0fdfa', '#99f6e4'];

                                return '<span title="' . e($tooltip) . '" style="display:inline-flex;align-items:center;justify-content:center;height:18px;padding:0 5px;border-radius:9999px;font-size:10px;font-weight:700;color:' . $colore . ';background:' . $sfondo . ';border:1px solid ' . $bordo . ';cursor:default;">' . $sigla . '</span>';
                            })->implode(' ');

                            return '<tr>'
                                . '<td style="color:#9ca3af;padding:0 6px 2px 0;text-align:right;white-space:nowrap;">' . e($dataLabel) . ':</td>'
                                . '<td style="padding:0 0 2px 0;white-space:nowrap;">' . $sigle . '</td>'
                                . '</tr>';
                        })->implode('');

                        return '<table style="border-collapse:collapse;font-size:12px;">' . $righe . '</table>';
                    }),

                Tables\Columns\TextColumn::make('corsi')
                    ->label('Corsi (prot.)')
                    ->html()
                    ->getStateUsing(function (CandidaturaStaff $record) {
                        if (! $record->haDisponibilitaDocente()) return '—';

                        $protocolli = $record->corsi->pluck('numero_protocollo')->filter();
                        if ($protocolli->isEmpty()) return $record->corsi->count() . ' corsi';

                        return $protocolli
                            ->map(fn ($p) => '<div style="margin-bottom:2px;"><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;">' . e($p) . '</span></div>')
                            ->implode('');
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'confermato' => 'Confermata',
                        'rifiutato'  => 'Rifiutata',
                        default      => 'In attesa',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'confermato' => 'success',
                        'rifiutato'  => 'danger',
                        default      => 'warning',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Candidato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('stato')
                    ->options([
                        'in_attesa'  => 'In attesa',
                        'confermato' => 'Confermata',
                        'rifiutato'  => 'Rifiutata',
                    ]),

                // whereJsonContains(colonna, ['tipo' => 'x']) confronta un array Postgres con un
                // oggetto nudo ({"tipo":"x"}) — sempre falso, il containment jsonb tra array
                // richiede array contro array. Va incapsulato in un altro array ([['tipo' => 'x']])
                // così diventa [{"tipo":"x"}], confrontabile con un elemento dell'array in colonna.
                Tables\Filters\Filter::make('disponibilita_docente')
                    ->label('Disponibilità docenti')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereJsonContains('disponibilita', [['tipo' => 'docente']])),

                Tables\Filters\Filter::make('disponibilita_logistica')
                    ->label('Disponibilità logistica')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereJsonContains('disponibilita', [['tipo' => 'logistica']])),

                Tables\Filters\SelectFilter::make('anagrafica')
                    ->options([
                        'trovato'      => 'Socio trovato',
                        'esterno'      => 'Confermato esterno',
                        'da_verificare' => 'Da verificare',
                    ])
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query, array $data) => $query
                        ->when($data['value'] ?? null, fn ($q, $stato) => match ($stato) {
                            'trovato'       => $q->whereNotNull('socio_id'),
                            'esterno'       => $q->whereNull('socio_id')->whereNotNull('confermato_esterno_at'),
                            'da_verificare' => $q->whereNull('socio_id')->whereNull('confermato_esterno_at'),
                        })),

                Tables\Filters\TernaryFilter::make('email_verificata')
                    ->label('Email verificata')
                    // EmailVerifica::isVerificata() guarda al socio agganciato (se presente),
                    // non al campo grezzo della candidatura — stesso identikit della colonna
                    // "Email" in tabella. Con pochi record per evento, filtrare in PHP sugli
                    // id è più semplice e sicuro di replicare quella logica in SQL/join.
                    ->queries(
                        true: fn ($query) => $query->whereIn('id', $query->clone()->with('socio')->get()
                            ->filter(fn (CandidaturaStaff $c) => EmailVerifica::isVerificata($c))->pluck('id')),
                        false: fn ($query) => $query->whereIn('id', $query->clone()->with('socio')->get()
                            ->reject(fn (CandidaturaStaff $c) => EmailVerifica::isVerificata($c))->pluck('id')),
                    ),
            ])
            ->actions([
                Action::make('dettagli')
                    ->label('Dettagli')
                    ->tooltip('Vedi tutti i dati della candidatura')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->iconButton()
                    ->modalHeading(fn (CandidaturaStaff $record) => 'Candidatura — ' . $record->cognome . ' ' . $record->nome)
                    ->modalSubmitActionLabel('Salva')
                    ->modalSubmitAction(fn ($action) => $action->color('success'))
                    ->modalCancelActionLabel('Chiudi')
                    ->schema(function (CandidaturaStaff $record) {
                        $schema = [
                            SchemaView::make('filament.candidatura-staff.dettagli')
                                ->viewData(['record' => $record])
                                ->columnSpanFull(),

                            Forms\Components\Hidden::make('modifica_email')
                                ->default(false)
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->live()
                                // Testo di sola lettura finché non si clicca "Modifica" — non deve
                                // essere possibile cambiarla per sbaglio scrivendoci sopra come se
                                // fosse un campo qualunque, va sempre un gesto esplicito.
                                ->readOnly(fn ($get) => ! $get('modifica_email'))
                                ->suffixAction(
                                    Action::make('modificaEmail')
                                        ->icon('heroicon-o-pencil')
                                        ->tooltip('Modifica email')
                                        ->visible(fn ($get) => ! $get('modifica_email'))
                                        ->action(fn (Forms\Set $set) => $set('modifica_email', true)),
                                ),
                        ];

                        if (! EmailVerifica::isVerificata($record)) {
                            // Stato tenuto in un campo nascosto così Placeholder/Action possono
                            // rileggerlo con $get e restare aggiornati dopo l'azione annidata
                            // sotto, senza dover richiudere e riaprire la modale.
                            $schema[] = Forms\Components\Hidden::make('ultima_verifica_invio')
                                ->dehydrated(false)
                                ->default(optional(EmailVerifica::ultimaRichiesta($record))->toIso8601String());

                            $schema[] = Forms\Components\Placeholder::make('email_stato')
                                ->label('Verifica email')
                                ->content(fn ($get) => new HtmlString(
                                    '<span style="color:#d97706;font-weight:600;">Da verificare</span> — '
                                    . EmailVerifica::descrizioneUltimaRichiesta($get('ultima_verifica_invio') ? \Carbon\Carbon::parse($get('ultima_verifica_invio')) : null)
                                ));

                            $schema[] = SchemaActions::make([
                                Action::make('inviaVerificaEmailInline')
                                    ->label(fn ($get) => $get('ultima_verifica_invio') ? 'Reinvia email di verifica' : 'Invia email di verifica')
                                    ->icon('heroicon-o-envelope')
                                    ->color('brand_pink')
                                    ->requiresConfirmation()
                                    ->modalDescription(fn (CandidaturaStaff $record) => "Verrà inviato un link/codice di verifica a {$record->email}.")
                                    ->action(function (CandidaturaStaff $record, Forms\Set $set) {
                                        EmailVerifica::reinvia($record);
                                        $set('ultima_verifica_invio', now()->toIso8601String());
                                        Notification::make()->title("Email di verifica inviata a {$record->email}")->success()->send();
                                    }),
                            ]);
                        }

                        return $schema;
                    })
                    ->fillForm(fn (CandidaturaStaff $record) => $record->only('email'))
                    ->action(function (CandidaturaStaff $record, array $data) {
                        if ($data['email'] !== $record->email) {
                            EmailVerifica::aggiornaIndirizzo($record, $data['email']);
                            Notification::make()->title('Email aggiornata — verifica azzerata, nuova verifica inviata')->success()->send();
                        }
                    }),

                Action::make('modificaDisponibilita')
                    ->label('Modifica disponibilità')
                    ->tooltip('Correggi slot e tipologia (Docenza/Logistica) — i dati anagrafici non si toccano da qui')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->iconButton()
                    ->modalHeading(fn (CandidaturaStaff $record) => 'Disponibilità — ' . $record->cognome . ' ' . $record->nome)
                    ->modalSubmitActionLabel('Salva')
                    ->modalSubmitAction(fn ($action) => $action->color('success'))
                    ->modalCancelActionLabel('Annulla')
                    ->closeModalByClickingAway(false)
                    ->closeModalByEscaping(false)
                    ->fillForm(fn (CandidaturaStaff $record) => [
                        'disp'  => collect($record->disponibilita)->mapWithKeys(fn ($d) => [$d['data'] . '_' . $d['porzione'] => $d['tipo']])->all(),
                        'corsi' => $record->corsi->pluck('id')->all(),
                    ])
                    ->schema(function (CandidaturaStaff $record) {
                        $evento      = $record->evento;
                        $giorniReali = $evento->giorniEvento()->map(fn ($d) => $d->format('Y-m-d'))->all();

                        // Slot generati dall'evento + eventuali slot già salvati che non esistono più
                        // (date evento cambiate dopo la candidatura): così non si perdono in silenzio.
                        $slots = collect(EventoSezioniGenerator::slotDisponibilitaStaff($evento))->keyBy('chiave');
                        foreach ($record->disponibilita ?? [] as $d) {
                            $chiave = $d['data'] . '_' . $d['porzione'];
                            $slots->has($chiave) || $slots->put($chiave, [
                                'data' => $d['data'], 'porzione' => $d['porzione'], 'chiave' => $chiave,
                                'label' => \Carbon\Carbon::parse($d['data'])->translatedFormat('l j F') . ' — ' . ucfirst($d['porzione']),
                            ]);
                        }

                        $campi = $slots->map(fn (array $s) => Forms\Components\Select::make('disp.' . $s['chiave'])
                            ->label($s['label'])
                            ->placeholder('Non disponibile')
                            ->options(in_array($s['data'], $giorniReali)
                                ? ['docente' => 'Docenza', 'logistica' => 'Logistica']
                                : ['logistica' => 'Logistica']) // montaggio/smontaggio: solo logistica
                            ->live()
                        )->values()->all();

                        $avviso = IscrizioneStaff::where('candidatura_staff_id', $record->id)->whereHas('rispostaPreregistrazione')->exists();

                        return [
                            Forms\Components\Placeholder::make('avviso_form')
                                ->hiddenLabel()
                                ->visible($avviso)
                                ->content(new HtmlString('<span style="color:#d97706;font-weight:600;">Il form di pre-registrazione è già stato compilato:</span> le risposte già inviate non cambiano con questa modifica. Per fargli rifare il form (senza nullaosta se non serve più) usa "Reset form" in Gestione staff — verrà precompilato con la disponibilità corretta qui.')),

                            \Filament\Schemas\Components\Grid::make(2)->schema($campi),

                            Forms\Components\CheckboxList::make('corsi')
                                ->label('Corsi in cui farà docenza')
                                ->options($evento->corsi()->orderBy('nome')->get()->mapWithKeys(fn ($c) => [$c->id => trim(($c->numero_protocollo ? $c->numero_protocollo . ' — ' : '') . $c->nome)])->all())
                                ->visible(fn ($get) => collect($get('disp') ?? [])->contains('docente'))
                                ->required(fn ($get) => collect($get('disp') ?? [])->contains('docente')),
                        ];
                    })
                    ->action(function (CandidaturaStaff $record, array $data, Action $action) {
                        $disponibilita = collect($data['disp'] ?? [])->filter()
                            ->map(fn ($tipo, $chiave) => ['data' => substr($chiave, 0, 10), 'porzione' => substr($chiave, 11), 'tipo' => $tipo])
                            ->sortBy([['data', 'asc'], ['porzione', 'asc']])
                            ->values();

                        if ($disponibilita->isEmpty()) {
                            Notification::make()->title('Seleziona almeno uno slot di disponibilità')->danger()->send();
                            $action->halt();
                        }

                        $record->update(['disponibilita' => $disponibilita->all()]);
                        $record->refresh();
                        $record->corsi()->sync($record->haDisponibilitaDocente() ? ($data['corsi'] ?? []) : []);

                        // Se la candidatura è già confermata, il ruolo dello staff deriva da qui.
                        IscrizioneStaff::where('candidatura_staff_id', $record->id)
                            ->update(['ruolo' => $record->haDisponibilitaDocente() ? 'docenza' : 'logistica']);

                        Notification::make()->title('Disponibilità aggiornata')->success()->send();
                    }),

                Action::make('correggiCf')
                    ->label('Correggi CF')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Correggi un codice fiscale inserito male e riprova l\'aggancio al socio')
                    ->visible(fn (CandidaturaStaff $record) => $record->stato === 'in_attesa')
                    ->schema([
                        Forms\Components\TextInput::make('codice_fiscale')
                            ->label('Codice fiscale corretto')
                            ->required()
                            ->maxLength(16)
                            ->regex('/^[A-Za-z0-9]{16}$/')
                            ->validationMessages(['regex' => 'Il codice fiscale deve essere di 16 caratteri alfanumerici.'])
                            // Una volta agganciato un socio il CF non si tocca più — l'aggancio
                            // resta definitivo (a differenza di "Esterno", modificabile in
                            // qualsiasi momento). La modale resta comunque apribile per
                            // consultazione, solo il campo si blocca.
                            ->disabled(fn (CandidaturaStaff $record) => (bool) $record->socio_id)
                            ->dehydrated(fn (CandidaturaStaff $record) => ! $record->socio_id)
                            ->helperText(fn (CandidaturaStaff $record) => $record->socio_id
                                ? 'Candidatura già agganciata a un socio: il CF non è più modificabile.'
                                : null),
                    ])
                    ->fillForm(fn (CandidaturaStaff $record) => $record->only('codice_fiscale'))
                    ->modalDescription('Il sistema ricercherà un socio con il CF corretto e, se lo trova, collega la candidatura (la conferma resta comunque manuale, come per ogni candidatura staff). Se non trova nessun socio, il CF viene comunque aggiornato e la candidatura resta in attesa.')
                    ->action(function (CandidaturaStaff $record, array $data) {
                        if ($record->socio_id) {
                            return;
                        }

                        $nuovoCf = strtoupper(trim($data['codice_fiscale']));

                        $duplicato = CandidaturaStaff::where('evento_id', $record->evento_id)
                            ->where('codice_fiscale', $nuovoCf)
                            ->where('id', '!=', $record->id)
                            ->first();

                        if ($duplicato) {
                            Notification::make()
                                ->title('CF già presente')
                                ->body('Un\'altra candidatura di questo evento ha già questo codice fiscale — verifica manualmente entrambe le candidature prima di procedere.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $socio = Socio::where('codice_fiscale', $nuovoCf)->first();

                        if (! $socio) {
                            $record->update(['codice_fiscale' => $nuovoCf]);
                            Notification::make()
                                ->title('Codice fiscale aggiornato')
                                ->body('Nessun socio trovato con questo CF — la candidatura resta in attesa.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $record->update([
                            'codice_fiscale' => $nuovoCf,
                            'socio_id'       => $socio->id,
                            'nome'           => $socio->nome,
                            'cognome'        => $socio->cognome,
                        ]);

                        Notification::make()
                            ->title('CF corretto — socio agganciato')
                            ->body("Candidatura collegata a {$socio->nome_completo}. Ricorda di confermare la candidatura per aggiungerlo allo staff.")
                            ->success()
                            ->send();
                    }),

                Action::make('confermaEsterno')
                    ->label('Esterno')
                    ->tooltip('Conferma che questa persona non è socio CRI (es. docente esterno) — silenzia l\'alert senza dover collegare un\'anagrafica interna')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->iconButton()
                    ->visible(fn (CandidaturaStaff $record) => ! $record->socio_id && ! $record->confermato_esterno_at && $record->stato === 'in_attesa')
                    ->requiresConfirmation()
                    ->modalDescription('La candidatura resta senza socio agganciato. Se in seguito risultasse comunque essere socio, puoi ancora usare "Correggi CF".')
                    ->action(function (CandidaturaStaff $record) {
                        $record->update([
                            'confermato_esterno_at'    => now(),
                            'confermato_esterno_da_id' => auth()->id(),
                        ]);
                        Notification::make()->title('Candidatura confermata come esterna all\'associazione')->success()->send();
                    }),

                Action::make('conferma')
                    ->label('Conferma')
                    ->tooltip('Conferma candidatura (crea/aggiorna socio e membro staff dell\'evento)')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalDescription('Viene creato/aggiornato il socio con questi dati e aggiunto allo staff dell\'evento.')
                    ->visible(fn (CandidaturaStaff $record) => $record->stato !== 'confermato')
                    ->action(function (CandidaturaStaff $record) {
                        static::confermaCandidatura($record);
                        Notification::make()->title('Candidatura confermata')->body('Aggiunto allo staff dell\'evento.')->success()->send();
                    }),

                ActionGroup::make([
                    Action::make('ritiraConferma')
                        ->label('Ritira conferma')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('La persona viene rimossa dallo staff dell\'evento; la candidatura torna in attesa e può essere riconfermata in seguito.')
                        ->visible(fn (CandidaturaStaff $record) => $record->stato === 'confermato')
                        ->action(function (CandidaturaStaff $record) {
                            $iscrizione = IscrizioneStaff::where('candidatura_staff_id', $record->id)->first();
                            if ($iscrizione) {
                                StaffRimozione::rimuovi($iscrizione);
                            } else {
                                $record->update(['stato' => 'in_attesa', 'confermato_at' => null, 'confermato_da_id' => null]);
                            }

                            Notification::make()->title('Conferma ritirata')->body('Candidatura tornata in attesa.')->success()->send();
                        }),

                    Action::make('rifiuta')
                        ->label('Rifiuta')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(fn (CandidaturaStaff $record) => $record->stato === 'confermato'
                            ? 'La persona viene rimossa dallo staff dell\'evento e la candidatura segnata come rifiutata.'
                            : 'La candidatura viene segnata come rifiutata.')
                        ->visible(fn (CandidaturaStaff $record) => in_array($record->stato, ['in_attesa', 'confermato']))
                        ->action(function (CandidaturaStaff $record) {
                            if ($record->stato === 'confermato') {
                                $iscrizione = IscrizioneStaff::where('candidatura_staff_id', $record->id)->first();
                                if ($iscrizione) {
                                    StaffRimozione::rimuovi($iscrizione);
                                }
                            }

                            $record->update(['stato' => 'rifiutato']);
                        }),

                    Action::make('riportaInAttesa')
                        ->label('Riporta in attesa')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('La candidatura torna in attesa e può essere valutata di nuovo.')
                        ->visible(fn (CandidaturaStaff $record) => $record->stato === 'rifiutato')
                        ->action(function (CandidaturaStaff $record) {
                            $record->update(['stato' => 'in_attesa']);
                            Notification::make()->title('Candidatura riportata in attesa')->success()->send();
                        }),

                    // Elimina definitivamente — SOLO se non agganciata a un socio: tipico
                    // il caso di "docente esterno" spuntato per errore da chi in realtà è
                    // socio (la dichiarazione salta la verifica del consenso GAIA), oppure
                    // un CF inserito male. Nessun dato reale da preservare in questi casi —
                    // la persona può ricompilare la candidatura da capo con il flusso corretto.
                    Action::make('elimina')
                        ->label('Elimina')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (CandidaturaStaff $record) => ! $record->socio_id)
                        ->requiresConfirmation()
                        ->modalHeading('Elimina candidatura')
                        ->modalDescription('Candidatura mai collegata a un socio (es. "docente esterno" spuntato per errore, o CF inserito male). L\'eliminazione è definitiva: la persona dovrà ricompilare la candidatura.')
                        ->modalSubmitActionLabel('Sì, elimina')
                        ->action(function (CandidaturaStaff $record) {
                            $record->delete();
                            Notification::make()->title('Candidatura eliminata')->success()->send();
                        }),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkAction::make('confermaMultiple')
                    ->label('Conferma selezionate')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Per ogni candidatura selezionata viene creato/aggiornato il socio e aggiunto allo staff dell\'evento. Le candidature già confermate vengono ignorate.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $daConfermare = $records->reject(fn (CandidaturaStaff $record) => $record->stato === 'confermato');

                        foreach ($daConfermare as $record) {
                            static::confermaCandidatura($record);
                        }

                        $saltate = $records->count() - $daConfermare->count();
                        Notification::make()
                            ->title('Confermate ' . $daConfermare->count() . ' candidature')
                            ->body($saltate > 0 ? $saltate . ' già confermate, ignorate.' : null)
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
