<?php

namespace App\Filament\Resources\GraduatoriaResource\RelationManagers;

use App\Mail\GraduatoriaCandidaturaConfermata;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\EmailVerifica;
use App\Models\GraduatoriaCandidato;
use App\Models\GraduatoriaPunteggioManuale;
use App\Models\Socio;
use App\Models\SocioTitoloStudioVerificato;
use App\Models\StoricoRitiro;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class CandidatiRelationManager extends RelationManager
{
    protected static string $relationship = 'candidati';

    protected static ?string $title = 'Candidati';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    protected function canCreate(): bool
    {
        return $this->getOwnerRecord()->puoGestire(auth()->user());
    }

    protected function canEdit(Model $record): bool
    {
        return $this->canCreate();
    }

    protected function canDelete(Model $record): bool
    {
        return $this->canCreate();
    }

    public function table(Table $table): Table
    {
        $graduatoriaAperta = $this->getOwnerRecord()->isAperta();
        $richiedeAttestazione = $this->getOwnerRecord()->richiede_attestazione_presidente;
        $richiedeDiploma = $this->getOwnerRecord()->richiede_diploma;
        $richiedeAutocertificazione = $this->getOwnerRecord()->richiede_autocertificazione;

        return $table
            ->recordTitleAttribute('cognome')
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
                    ->label('CF')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('CF copiato')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('comitato_socio')
                    ->label('Comitato')
                    ->getStateUsing(fn (GraduatoriaCandidato $record) => $record->comitatoSocio() ?? ($record->comitatoLocale?->nome ?? '—')),

                Tables\Columns\TextColumn::make('ruolo_socio')
                    ->label('Ruolo')
                    ->badge()
                    ->getStateUsing(fn (GraduatoriaCandidato $record) => $record->tipoSocio() ?? 'Anagrafica non collegata a Socio')
                    ->color(fn (string $state) => match ($state) {
                        'Volontario' => 'gray',
                        'Dipendente' => 'info',
                        default      => 'danger',
                    }),

                Tables\Columns\IconColumn::make('socio_id')
                    ->label('Anagrafica')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->tooltip(fn (GraduatoriaCandidato $record) => $record->socio_id
                        ? 'Socio trovato in archivio'
                        : 'Socio non trovato — verificare CF/dati'),

                Tables\Columns\IconColumn::make('prerequisiti')
                    ->label('Prerequisiti')
                    ->getStateUsing(fn (GraduatoriaCandidato $record) => $this->getOwnerRecord()->coloreRequisiti($record->socio, $record))
                    ->icon(fn (?string $state) => match ($state) {
                        'danger'  => 'heroicon-o-x-circle',
                        'warning' => 'heroicon-o-exclamation-triangle',
                        'success' => 'heroicon-o-check-circle',
                        default   => 'heroicon-o-minus-circle',
                    })
                    ->color(fn (?string $state) => $state ?? 'gray')
                    ->tooltip(fn (GraduatoriaCandidato $record) => $this->getOwnerRecord()->riepilogoProblemiRequisiti($record->socio, $record) ?? 'Nessun requisito da segnalare'),

                Tables\Columns\TextColumn::make('stato')
                    ->badge()
                    // Finché il bando è aperto, "Confermato" non riflette ancora un esito
                    // reale (la conferma effettiva in graduatoria dipenderà dal calcolo
                    // punteggi/selezione, non ancora implementato) — dicitura generica.
                    ->formatStateUsing(function (string $state) use ($graduatoriaAperta) {
                        if ($state === 'rifiutato') return 'Escluso';
                        if ($state === 'in_attesa') return 'In attesa';
                        return $graduatoriaAperta ? 'Candidatura ricevuta' : 'Confermata';
                    })
                    ->color(function (string $state) use ($graduatoriaAperta) {
                        if ($state === 'rifiutato') return 'danger';
                        if ($state === 'in_attesa') return 'warning';
                        return $graduatoriaAperta ? 'gray' : 'success';
                    }),

                Tables\Columns\TextColumn::make('posizione_classifica')
                    ->label('Pos.')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('punteggio_finale')
                    ->label('Punteggio')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('esito_calcolo')
                    ->label('Esito')
                    ->badge()
                    ->getStateUsing(function (GraduatoriaCandidato $record) {
                        if (! $record->calcolato_at) return 'non_calcolato';
                        return $record->ammesso ? 'ammesso' : 'lista_attesa';
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'ammesso' => 'Ammesso',
                        'lista_attesa' => 'Lista d\'attesa',
                        default => 'Non calcolato',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'ammesso' => 'success',
                        'lista_attesa' => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (GraduatoriaCandidato $record) => filled($record->dettaglio_punteggio)
                        ? implode(' · ', $record->dettaglio_punteggio)
                        : null)
                    ->sortable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $direction) {
                        $query->orderByRaw("
                            CASE
                                WHEN calcolato_at IS NULL THEN 2
                                WHEN ammesso = true THEN 0
                                ELSE 1
                            END {$direction}
                        ");
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Iscritto il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->badge()
                    ->color(fn (GraduatoriaCandidato $record) => $record->fuori_termine ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('email_verificata')
                    ->label('Email')
                    ->getStateUsing(fn (GraduatoriaCandidato $record) => EmailVerifica::isVerificata($record) ? 'Verificata' : 'Da verificare')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Verificata'    => 'success',
                        'Da verificare' => 'warning',
                        default         => 'gray',
                    }),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('attestazione_presidente')
                    ->label('Attestazione')
                    ->visible($richiedeAttestazione)
                    ->getStateUsing(function (GraduatoriaCandidato $record) {
                        if (! $record->attestazione_presidente_path) return 'assente';
                        return $record->attestazione_presidente_verificata ? 'verificata' : 'da_verificare';
                    })
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'verificata'    => 'Verificata',
                        'da_verificare' => 'Da verificare',
                        default         => '—',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'verificata'    => 'success',
                        'da_verificare' => 'warning',
                        default         => 'gray',
                    }),

                Tables\Columns\TextColumn::make('diploma')
                    ->label('Titolo di studio')
                    ->visible($richiedeDiploma)
                    ->getStateUsing(fn (GraduatoriaCandidato $record) => $record->diplomaVerificatoAt() ? 'verificato' : 'da_verificare')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'verificato'    => 'Verificato',
                        'da_verificare' => 'Da verificare',
                        default         => '—',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'verificato'    => 'success',
                        'da_verificare' => 'warning',
                        default         => 'gray',
                    })
                    ->tooltip(fn (GraduatoriaCandidato $record) => $record->diplomaVerificatoAt()
                        ? 'Diploma di scuola secondaria superiore verificato il ' . $record->diplomaVerificatoAt()->format('d/m/Y') . ' da ' . ($record->diplomaVerificatoDa()?->name ?? '—')
                        : 'Diploma di scuola secondaria superiore non ancora verificato'),

                Tables\Columns\TextColumn::make('autocertificazione')
                    ->label('Autocertificazione')
                    ->visible($richiedeAutocertificazione)
                    ->getStateUsing(function (GraduatoriaCandidato $record) {
                        if (! $record->autocertificazione_path) return 'assente';
                        return $record->autocertificazione_verificata ? 'verificata' : 'da_verificare';
                    })
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'verificata'    => 'Verificata',
                        'da_verificare' => 'Da verificare',
                        default         => '—',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'verificata'    => 'success',
                        'da_verificare' => 'warning',
                        default         => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stato')
                    ->options([
                        'confermato' => 'Confermato',
                        'in_attesa'  => 'In attesa',
                        'rifiutato'  => 'Escluso',
                    ]),
            ])
            ->modifyQueryUsing(fn ($query) => $query
                ->where(fn ($q) => $q->whereNull('ammissibile')->orWhere('ammissibile', true))
                ->whereNull('ritirato_at')
                ->with([
                    'socio.titoliStudioVerificati',
                    'socio.comitatoLocale', 'socio.comitatoRegionale',
                    'socio.comitatoLocaleDipendente', 'socio.comitatoRegionaleDipendente',
                    'comitatoLocale',
                ]))
            // ->defaultSort() invece di un orderBy fisso in modifyQueryUsing(): quest'ultimo
            // viene applicato PRIMA del sort scelto cliccando una colonna, quindi in SQL
            // dominava sempre (posizione_classifica non ha quasi mai pareggi) e il click
            // sull'intestazione diventava un tie-breaker che non si vedeva mai. defaultSort()
            // è invece applicato DOPO l'eventuale sort attivo, come tie-breaker vero.
            ->defaultSort(fn (\Illuminate\Database\Eloquent\Builder $query) => $query
                ->orderByRaw('posizione_classifica IS NULL')
                ->orderBy('posizione_classifica')
                ->orderBy('cognome'))
            ->headerActions([
                Action::make('calcolaClassifica')
                    ->label('Calcola punteggi')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->visible(fn () => $this->getOwnerRecord()->puoGestire(auth()->user()))
                    ->requiresConfirmation()
                    ->modalDescription('Ricalcola punteggio, ammissibilità e classifica per tutti i candidati confermati (non ritirati). I risultati precedenti vengono sovrascritti.')
                    ->action(function () {
                        $esito = $this->getOwnerRecord()->calcolaClassifica();
                        $this->dispatch('graduatoria-esclusi-refresh');

                        Notification::make()
                            ->title('Classifica calcolata')
                            ->body("Ammissibili: {$esito['ammissibili']} (di cui ammessi: {$esito['ammessi']}) · Esclusi per requisiti: {$esito['esclusi']}")
                            ->success()
                            ->send();
                    }),

                CreateAction::make()
                    ->label('Aggiungi candidato')
                    ->color('success')
                    ->modalHeading('Aggiungi candidato fuori termine')
                    ->schema(fn () => static::formCandidato())
                    ->mutateDataUsing(function (array $data): array {
                        $data['stato']            = 'confermato';
                        $data['fuori_termine']     = true;
                        $data['privacy_accettata'] = true;
                        $data['confermato_at']     = now();
                        $data['confermato_da_id']  = auth()->id();
                        $data['aggiunto_da_id']    = auth()->id();
                        return $data;
                    })
                    ->after(function (GraduatoriaCandidato $record) {
                        static::sincronizzaSocio($record);
                        Mail::to($record->email)->send(new GraduatoriaCandidaturaConfermata($record));
                    }),
            ])
            ->actions([
                Action::make('dettagliCandidato')
                    ->label('Dettagli')
                    ->tooltip('Dettagli candidatura')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->iconButton()
                    ->modalHeading(fn (GraduatoriaCandidato $record) => "Dettagli candidatura — {$record->cognome} {$record->nome}")
                    ->modalSubmitActionLabel('Salva')
                    ->modalSubmitAction(fn ($action) => $action->color('success'))
                    ->schema(function (GraduatoriaCandidato $record) use ($richiedeAttestazione, $richiedeAutocertificazione) {
                        $graduatoria = $this->getOwnerRecord();
                        $socio       = $record->socio;

                        $schema = [
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

                            Forms\Components\Placeholder::make('telefono')
                                ->label('Telefono')
                                ->content($record->telefono ?: '—'),

                            // "Conferma"/"Correggi CF" spostati qui (erano azioni riga dirette):
                            // riguardano solo il caso raro di una candidatura ancora in_attesa,
                            // lo stato è già visibile in tabella — sempre presenti ma disabled+
                            // tooltip quando non serve, come le altre azioni annidate di questa
                            // modale (vedi "Invia verifica email" sotto).
                            SchemaActions::make([
                                Action::make('conferma')
                                    ->label('Conferma')
                                    ->tooltip(fn (GraduatoriaCandidato $record) => $record->stato === 'in_attesa'
                                        ? 'Conferma candidatura (crea il socio se non esiste ancora)'
                                        : 'Candidatura già gestita, non c\'è nulla da confermare')
                                    ->icon('heroicon-o-check')
                                    ->color('success')
                                    ->disabled(fn (GraduatoriaCandidato $record) => $record->stato !== 'in_attesa')
                                    ->schema(fn (GraduatoriaCandidato $record) => static::formCandidato($record))
                                    ->fillForm(fn (GraduatoriaCandidato $record) => $record->only([
                                        'nome', 'cognome', 'email', 'telefono', 'comitato_regionale_id', 'comitato_locale_id',
                                    ]))
                                    ->action(function (GraduatoriaCandidato $record, array $data) {
                                        $record->update(array_merge($data, [
                                            'stato'            => 'confermato',
                                            'confermato_at'    => now(),
                                            'confermato_da_id' => auth()->id(),
                                        ]));
                                        static::sincronizzaSocio($record);
                                        Mail::to($record->email)->send(new GraduatoriaCandidaturaConfermata($record));

                                        Notification::make()->title('Candidatura confermata')->success()->send();
                                    }),

                                Action::make('correggiCf')
                                    ->label('Correggi CF')
                                    ->icon('heroicon-o-identification')
                                    ->color('warning')
                                    ->tooltip(fn (GraduatoriaCandidato $record) => $record->stato === 'in_attesa'
                                        ? 'Correggi un codice fiscale inserito male e riprova l\'aggancio al socio'
                                        : 'Candidatura già gestita: il CF non è più correggibile da qui')
                                    ->disabled(fn (GraduatoriaCandidato $record) => $record->stato !== 'in_attesa')
                                    ->schema([
                                        Forms\Components\TextInput::make('codice_fiscale')
                                            ->label('Codice fiscale corretto')
                                            ->required()
                                            ->maxLength(16)
                                            ->regex('/^[A-Za-z0-9]{16}$/')
                                            ->validationMessages(['regex' => 'Il codice fiscale deve essere di 16 caratteri alfanumerici.'])
                                            // Una volta agganciato un socio il CF non si tocca più — l'aggancio
                                            // resta definitivo, non si aggancia/sgancia continuamente. In pratica
                                            // il pulsante è già disabled sopra appena confermato, ma il campo
                                            // resta comunque bloccato per coerenza con la candidatura staff e
                                            // come difesa in profondità.
                                            ->disabled(fn (GraduatoriaCandidato $record) => (bool) $record->socio_id)
                                            ->dehydrated(fn (GraduatoriaCandidato $record) => ! $record->socio_id)
                                            ->helperText(fn (GraduatoriaCandidato $record) => $record->socio_id
                                                ? 'Candidatura già agganciata a un socio: il CF non è più modificabile.'
                                                : null),
                                    ])
                                    ->fillForm(fn (GraduatoriaCandidato $record) => $record->only('codice_fiscale'))
                                    ->modalDescription('Il sistema ricercherà un socio con il CF corretto e, se lo trova, aggancia e conferma la candidatura esattamente come farebbe il form pubblico. Se non trova nessun socio, il CF viene comunque aggiornato e la candidatura resta in attesa.')
                                    ->action(function (GraduatoriaCandidato $record, array $data) {
                                        if ($record->socio_id) {
                                            return;
                                        }

                                        $graduatoria = $this->getOwnerRecord();
                                        $nuovoCf     = strtoupper(trim($data['codice_fiscale']));

                                        $duplicato = GraduatoriaCandidato::where('graduatoria_id', $graduatoria->id)
                                            ->where('codice_fiscale', $nuovoCf)
                                            ->where('id', '!=', $record->id)
                                            ->first();

                                        // Il CF corretto appartiene già a un'altra candidatura di questa
                                        // graduatoria, già regolarmente confermata e agganciata a un socio
                                        // (tipico: la persona si è ri-candidata da sola col CF giusto prima
                                        // che l'operatore la correggesse da qui). La riga corrente è ormai un
                                        // doppione senza informazioni aggiuntive: si ritira con nota, non si
                                        // blocca a vuoto e non si elimina nulla (audit trail). ⚠️ NON si
                                        // scrive $nuovoCf su questa riga: violerebbe il vincolo unique
                                        // (graduatoria_id, codice_fiscale), già occupato dall'altra riga —
                                        // il CF originale (sbagliato) resta come riferimento storico.
                                        if ($duplicato && $duplicato->stato === 'confermato' && $duplicato->socio_id) {
                                            $motivoDuplicato = "Duplicato: il CF corretto ({$nuovoCf}) risulta già confermato sulla candidatura #{$duplicato->id}.";
                                            $record->update([
                                                'ritirato_at'    => now(),
                                                'ritirato_da_id' => auth()->id(),
                                                'motivo_ritiro'  => $motivoDuplicato,
                                            ]);
                                            $record->storicoRitiri()->create([
                                                'tipo'           => 'ritiro',
                                                'motivo'         => $motivoDuplicato,
                                                'eseguito_da_id' => auth()->id(),
                                            ]);
                                            $this->dispatch('graduatoria-esclusi-refresh');

                                            Notification::make()
                                                ->title('Candidatura duplicata')
                                                ->body("Questo CF risulta già confermato su un'altra candidatura di questa graduatoria (#{$duplicato->id}). Questa riga è stata segnata come ritirata/duplicata.")
                                                ->warning()
                                                ->send();
                                            return;
                                        }

                                        if ($duplicato) {
                                            Notification::make()
                                                ->title('CF già presente')
                                                ->body('Un altro candidato di questa graduatoria ha già questo codice fiscale, ma non risulta ancora confermato — verifica manualmente entrambe le candidature.')
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
                                            'codice_fiscale'   => $nuovoCf,
                                            'socio_id'         => $socio->id,
                                            'nome'             => $socio->nome,
                                            'cognome'          => $socio->cognome,
                                            'stato'            => 'confermato',
                                            'confermato_at'    => now(),
                                            'confermato_da_id' => auth()->id(),
                                        ]);

                                        static::sincronizzaSocio($record);
                                        Mail::to($record->email)->send(new GraduatoriaCandidaturaConfermata($record));

                                        $avvisi = $graduatoria->avvisiRequisiti($socio);

                                        $notifica = Notification::make()
                                            ->title('CF corretto — socio agganciato e candidatura confermata')
                                            ->body($avvisi ? implode(' · ', $avvisi) : "Candidatura collegata a {$socio->nome_completo}.");
                                        $avvisi ? $notifica->warning() : $notifica->success();
                                        $notifica->send();
                                    }),
                            ]),

                            Forms\Components\Placeholder::make('scheda_socio')
                                ->label('Anagrafica')
                                ->visible((bool) $record->socio_id)
                                ->content(fn () => new HtmlString(
                                    '<a href="' . e(\App\Filament\Resources\SocioResource::getUrl('view', ['record' => $record->socio_id])) . '" target="_blank" style="color:#2563eb;text-decoration:underline;">↗ Apri scheda socio</a>'
                                )),

                            Forms\Components\Placeholder::make('esito_calcolo')
                                ->label('Esito calcolo classifica')
                                ->content(new HtmlString(static::renderEsitoCalcolo($record, $graduatoria->ultimoCalcoloClassifica()))),
                        ];

                        $haPrerequisiti = $graduatoria->richiede_corso_sicurezza || filled($graduatoria->qualifiche_propedeutiche) || $graduatoria->richiede_diploma;
                        if ($haPrerequisiti) {
                            $prerequisiti = $graduatoria->elencoPrerequisiti($socio, $record);
                            $schema[] = Forms\Components\Placeholder::make('prerequisiti')
                                ->label('Prerequisiti')
                                ->content(new HtmlString($prerequisiti
                                    ? static::renderRigheQualifiche($prerequisiti)
                                    : '<span style="color:#6b7280;">Nessun socio agganciato: impossibile verificare.</span>'));
                        }

                        $haCondizioniPunteggio = $graduatoria->qualifichePreferenziali->isNotEmpty()
                            || $graduatoria->preferenza_diversifica_comitato
                            || ($graduatoria->richiede_attestazione_presidente && $graduatoria->punteggio_attestazione_presidente !== null);
                        if ($haCondizioniPunteggio) {
                            $condizioni = $graduatoria->elencoCondizioniPunteggio($socio, $record);
                            $schema[] = Forms\Components\Placeholder::make('condizioni_punteggio')
                                ->label('Condizioni extra per punteggio')
                                ->content(new HtmlString(static::renderRigheCondizioni($condizioni)));
                        }

                        if (! EmailVerifica::isVerificata($record)) {
                            // Stato tenuto in un campo nascosto (non l'esito diretto di
                            // ultimaRichiesta()) così Placeholder/Action possono rileggerlo con
                            // $get e restare aggiornati dopo l'azione annidata sotto, senza dover
                            // richiudere e riaprire la modale — un Action::content()/label()
                            // valutato una volta sola allo schema-build non si ridisegna da solo.
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
                                    ->color('warning')
                                    ->requiresConfirmation()
                                    ->modalDescription(fn (GraduatoriaCandidato $record) => "Verrà inviato un link/codice di verifica a {$record->email} (indirizzo indicato nella candidatura).")
                                    ->action(function (GraduatoriaCandidato $record, Forms\Set $set) {
                                        EmailVerifica::reinvia($record);
                                        $set('ultima_verifica_invio', now()->toIso8601String());

                                        Notification::make()->title("Email di verifica inviata a {$record->email}")->success()->send();
                                    }),
                            ]);
                        }

                        // Punteggi manuali (+/-): per fattori non verificabili
                        // automaticamente da Lurch. Repeater a stato semplice (non
                        // ->relationship()) perché questa è un'Action generica, non una
                        // EditRecord/CreateRecord — il salvataggio è gestito a mano
                        // nell'->action() sotto (diff create/update/delete). Permanenti:
                        // si riapplicano ad ogni calcolaClassifica() finché non rimossi.
                        $schema[] = Forms\Components\Repeater::make('punteggi_manuali')
                            ->label('Punteggi manuali (+/-)')
                            ->helperText('Per fattori non verificabili automaticamente da Lurch. Ogni voce richiede un motivo.')
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('valore')
                                    ->label('Punteggio (+/-)')
                                    ->numeric()
                                    ->step(0.01)
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\Textarea::make('motivo')
                                    ->label('Motivo')
                                    ->required()
                                    ->rows(1)
                                    ->columnSpan(3),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Aggiungi punteggio manuale')
                            ->columnSpanFull();

                        if ($richiedeAttestazione) {
                            $schema[] = Forms\Components\Placeholder::make('attestazione_link')
                                ->label('Documento allegato')
                                ->content($record->attestazione_presidente_path
                                    ? new HtmlString('<a href="' . e(route('graduatoria.candidato.attestazione', $record)) . '" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline;">↗ Apri anteprima PDF</a>')
                                    : new HtmlString('<span style="color:#6b7280;">Nessun documento caricato.</span>'));

                            // Niente fillForm su questo campo: Filament non riesce a idratare
                            // l'anteprima di un file esistente su disco privato 'local' (resta
                            // vuoto), e un submit senza tocchi lo sovrascriverebbe con null —
                            // provato e corretto il 31/07/2026. Il campo parte sempre vuoto,
                            // esattamente come il vecchio pulsante dedicato: si valorizza SOLO
                            // se l'operatore carica davvero un nuovo file in questo submit.
                            // Sostituzione consentita solo finché non verificato — dopo è di
                            // sola lettura.
                            if (! $record->attestazione_presidente_verificata) {
                                $schema[] = Forms\Components\FileUpload::make('attestazione_presidente_path')
                                    ->label($record->attestazione_presidente_path ? 'Sostituisci documento' : 'Carica documento')
                                    ->helperText($record->attestazione_presidente_path
                                        ? 'Caricare un nuovo file sostituisce ed elimina quello esistente.'
                                        : null)
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk('local')
                                    ->directory('graduatorie-attestazioni')
                                    ->dehydrated(fn ($state) => filled($state));
                            }

                            if ($record->attestazione_presidente_path) {
                                $schema[] = Forms\Components\Toggle::make('attestazione_presidente_verificata')
                                    ->label('Documento verificato')
                                    ->helperText($record->attestazione_presidente_verificata
                                        ? 'Documento verificato: per sostituirlo, annulla prima la verifica.'
                                        : null);
                            }
                        }

                        if ($richiedeAutocertificazione) {
                            $schema[] = Forms\Components\Placeholder::make('autocertificazione_link')
                                ->label('Documento allegato')
                                ->content($record->autocertificazione_path
                                    ? new HtmlString('<a href="' . e(route('graduatoria.candidato.autocertificazione', $record)) . '" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline;">↗ Apri anteprima PDF</a>')
                                    : new HtmlString('<span style="color:#6b7280;">Nessun documento caricato.</span>'));

                            // Stesso vincolo dell'attestazione presidente: niente fillForm su
                            // questo campo (idratazione impossibile su disco privato 'local'),
                            // sostituzione consentita solo finché non verificato.
                            if (! $record->autocertificazione_verificata) {
                                $schema[] = Forms\Components\FileUpload::make('autocertificazione_path')
                                    ->label($record->autocertificazione_path ? 'Sostituisci documento' : 'Carica documento')
                                    ->helperText($record->autocertificazione_path
                                        ? 'Caricare un nuovo file sostituisce ed elimina quello esistente.'
                                        : null)
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk('local')
                                    ->directory('graduatorie-autocertificazioni')
                                    ->dehydrated(fn ($state) => filled($state));
                            }

                            if ($record->autocertificazione_path) {
                                $schema[] = Forms\Components\Toggle::make('autocertificazione_verificata')
                                    ->label('Documento verificato')
                                    ->helperText($record->autocertificazione_verificata
                                        ? 'Documento verificato: per sostituirlo, annulla prima la verifica.'
                                        : null);
                            }
                        }

                        $schema = [...$schema, ...static::storicoRitiriSection($record)];

                        return $schema;
                    })
                    ->fillForm(fn (GraduatoriaCandidato $record) => [
                        ...$record->only(['email', 'attestazione_presidente_verificata', 'autocertificazione_verificata']),
                        'punteggi_manuali' => $record->punteggiManuali->map(fn (GraduatoriaPunteggioManuale $p) => [
                            'id' => $p->id, 'valore' => (string) $p->valore, 'motivo' => $p->motivo,
                        ])->all(),
                    ])
                    ->action(function (GraduatoriaCandidato $record, array $data) {
                        if ($data['email'] !== $record->email) {
                            EmailVerifica::aggiornaIndirizzo($record, $data['email']);
                            Notification::make()->title('Email aggiornata — verifica azzerata, nuova verifica inviata')->success()->send();
                        }

                        if (array_key_exists('punteggi_manuali', $data)) {
                            $mantenuti = [];
                            foreach ($data['punteggi_manuali'] as $riga) {
                                $entry = filled($riga['id'] ?? null)
                                    ? $record->punteggiManuali()->find($riga['id'])
                                    : $record->punteggiManuali()->make(['creato_da_id' => auth()->id()]);
                                if (! $entry) continue;
                                $entry->valore = $riga['valore'];
                                $entry->motivo = $riga['motivo'];
                                $entry->save();
                                $mantenuti[] = $entry->id;
                            }
                            $record->punteggiManuali()->whereNotIn('id', $mantenuti)->delete();

                            Notification::make()->title('Punteggi manuali aggiornati')->success()->send();
                        }

                        // Upload possibile solo se non verificato (il campo è nascosto altrimenti):
                        // qui non serve azzerare la verifica, ma va eliminato il file precedente.
                        if (! empty($data['attestazione_presidente_path']) && ! $record->attestazione_presidente_verificata) {
                            $vecchio = $record->attestazione_presidente_path;
                            $record->update(['attestazione_presidente_path' => $data['attestazione_presidente_path']]);
                            if ($vecchio && $vecchio !== $data['attestazione_presidente_path']) {
                                \Illuminate\Support\Facades\Storage::disk('local')->delete($vecchio);
                            }
                            Notification::make()->title($vecchio ? 'Documento sostituito' : 'Documento caricato')->success()->send();
                        } elseif (array_key_exists('attestazione_presidente_verificata', $data)) {
                            $nuovo = (bool) $data['attestazione_presidente_verificata'];
                            if ($nuovo !== (bool) $record->attestazione_presidente_verificata) {
                                $record->update([
                                    'attestazione_presidente_verificata'       => $nuovo,
                                    'attestazione_presidente_verificata_at'    => $nuovo ? now() : null,
                                    'attestazione_presidente_verificata_da_id' => $nuovo ? auth()->id() : null,
                                ]);
                                Notification::make()->title($nuovo ? 'Documento verificato' : 'Verifica annullata')->success()->send();
                            }
                        }

                        if (! empty($data['autocertificazione_path']) && ! $record->autocertificazione_verificata) {
                            $vecchio = $record->autocertificazione_path;
                            $record->update(['autocertificazione_path' => $data['autocertificazione_path']]);
                            if ($vecchio && $vecchio !== $data['autocertificazione_path']) {
                                \Illuminate\Support\Facades\Storage::disk('local')->delete($vecchio);
                            }
                            Notification::make()->title($vecchio ? 'Documento sostituito' : 'Documento caricato')->success()->send();
                        } elseif (array_key_exists('autocertificazione_verificata', $data)) {
                            $nuovo = (bool) $data['autocertificazione_verificata'];
                            if ($nuovo !== (bool) $record->autocertificazione_verificata) {
                                $record->update([
                                    'autocertificazione_verificata'       => $nuovo,
                                    'autocertificazione_verificata_at'    => $nuovo ? now() : null,
                                    'autocertificazione_verificata_da_id' => $nuovo ? auth()->id() : null,
                                ]);
                                Notification::make()->title($nuovo ? 'Documento verificato' : 'Verifica annullata')->success()->send();
                            }
                        }
                    }),

                Action::make('apriCurriculumGaia')
                    ->label('Apri su GAIA')
                    ->tooltip(fn (GraduatoriaCandidato $record) => filled($record->socio?->gaia_persona_id)
                        ? null
                        : 'Nessun ID GAIA collegato a questo socio')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->disabled(fn (GraduatoriaCandidato $record) => blank($record->socio?->gaia_persona_id))
                    ->url(fn (GraduatoriaCandidato $record) => filled($record->socio?->gaia_persona_id)
                        ? "https://gaia.cri.it/profilo/{$record->socio->gaia_persona_id}/curriculum/"
                        : null)
                    ->openUrlInNewTab(),

                Action::make('confermaVerificaDiploma')
                    ->label('Conferma verifica titolo di studio')
                    ->tooltip('Diploma di scuola secondaria superiore non ancora verificato — clicca per confermare la verifica su GAIA')
                    ->icon('heroicon-o-academic-cap')
                    ->color('danger')
                    ->iconButton()
                    ->visible(fn (GraduatoriaCandidato $record) => $richiedeDiploma && ! $record->diplomaVerificatoAt())
                    ->requiresConfirmation()
                    ->modalDescription('Confermi di aver verificato su GAIA che questo candidato ha caricato il diploma di scuola secondaria superiore richiesto? La verifica resta salvata sul socio: non andrà ripetuta nelle prossime graduatorie.')
                    ->action(function (GraduatoriaCandidato $record) {
                        $verificatoAt = now();
                        $record->update([
                            'diploma_verificato_at' => $verificatoAt,
                            'diploma_verificato_da_id' => auth()->id(),
                        ]);

                        if ($record->socio_id) {
                            SocioTitoloStudioVerificato::updateOrCreate(
                                ['socio_id' => $record->socio_id, 'tipo' => GraduatoriaCandidato::TIPO_DIPLOMA_SUPERIORE],
                                ['verificato_at' => $verificatoAt, 'verificato_da_id' => auth()->id()],
                            );
                        }

                        Notification::make()->title('Titolo di studio verificato')->success()->send();
                    }),

                Action::make('annullaVerificaDiploma')
                    ->label('Annulla verifica titolo di studio')
                    ->tooltip(fn (GraduatoriaCandidato $record) => 'Verificato il '
                        . $record->diplomaVerificatoAt()?->format('d/m/Y')
                        . ' da ' . ($record->diplomaVerificatoDa()?->name ?? '—') . ' — clicca per annullare')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->iconButton()
                    ->visible(fn (GraduatoriaCandidato $record) => $richiedeDiploma && (bool) $record->diplomaVerificatoAt())
                    ->requiresConfirmation()
                    ->modalDescription('Annulla la verifica del titolo di studio, sia per questa candidatura sia sul socio. Potrai riconfermarla in seguito.')
                    ->action(function (GraduatoriaCandidato $record) {
                        $record->update([
                            'diploma_verificato_at' => null,
                            'diploma_verificato_da_id' => null,
                        ]);

                        if ($record->socio_id) {
                            SocioTitoloStudioVerificato::where('socio_id', $record->socio_id)
                                ->where('tipo', GraduatoriaCandidato::TIPO_DIPLOMA_SUPERIORE)
                                ->delete();
                        }

                        Notification::make()->title('Verifica annullata')->success()->send();
                    }),

                Action::make('modificaContatti')
                    ->label('Modifica')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->tooltip('Modifica contatti candidatura')
                    ->iconButton()
                    ->modalSubmitAction(fn ($action) => $action->color('success'))
                    ->schema([
                        Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
                        Forms\Components\TextInput::make('telefono')->tel()->maxLength(50),
                        Forms\Components\Textarea::make('note')
                            ->label('Note operatore')
                            ->helperText('Es. motivo e modalità con cui è stato ricontattato il candidato.')
                            ->rows(3),
                    ])
                    ->fillForm(fn (GraduatoriaCandidato $record) => $record->only(['email', 'telefono', 'note']))
                    ->requiresConfirmation()
                    ->modalDescription('Se modifichi l\'email, verrà inviata una nuova verifica al nuovo indirizzo prima che sostituisca quella attuale sul socio.')
                    ->action(function (GraduatoriaCandidato $record, array $data) {
                        $emailCambiata = $data['email'] !== $record->email;

                        // aggiornaIndirizzo() già scrive 'email' sul record (e invalida/reinnesca
                        // la verifica) — qui restano solo gli altri campi del form.
                        $record->update(['telefono' => $data['telefono'], 'note' => $data['note']]);
                        if ($emailCambiata) {
                            EmailVerifica::aggiornaIndirizzo($record, $data['email']);
                        }

                        Notification::make()
                            ->title($emailCambiata ? 'Contatti aggiornati — email di verifica inviata' : 'Contatti aggiornati')
                            ->success()
                            ->send();
                    }),

                // Azioni distruttive/di esclusione raggruppate in fondo (a destra all'interno
                // del gruppo BeforeColumns): Filament ha una sola colonna di azioni per riga,
                // posizionabile solo tutta a sinistra o tutta a destra — per avere quelle
                // "sicure" sempre a portata di click a sinistra e quelle pericolose isolate
                // in un menu a tendina in fondo, si usa un ActionGroup come ultimo elemento.
                ActionGroup::make([
                    Action::make('ritira')
                        ->label('Ritira')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->visible(fn (GraduatoriaCandidato $record) => ! $record->ritirato_at)
                        ->schema([
                            Forms\Components\Textarea::make('motivo_ritiro')
                                ->label('Motivo del ritiro')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (GraduatoriaCandidato $record, array $data) {
                            $record->update([
                                'ritirato_at'    => now(),
                                'ritirato_da_id' => auth()->id(),
                                'motivo_ritiro'  => $data['motivo_ritiro'],
                            ]);
                            $record->storicoRitiri()->create([
                                'tipo'           => 'ritiro',
                                'motivo'         => $data['motivo_ritiro'],
                                'eseguito_da_id' => auth()->id(),
                            ]);
                            $this->dispatch('graduatoria-esclusi-refresh');

                            Notification::make()->title('Candidatura ritirata')->success()->send();
                        }),

                    Action::make('rifiuta')
                        ->label('Rifiuta')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (GraduatoriaCandidato $record) => $record->stato === 'in_attesa')
                        ->action(fn (GraduatoriaCandidato $record) => $record->update(['stato' => 'rifiutato'])),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),

            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns);
    }

    /**
     * Accordion "Storico ritiri/ripristini" per la modale "Dettagli candidatura" —
     * assente (array vuoto, niente Section) se il candidato non ha mai avuto eventi.
     * Condivisa con CandidatiEsclusiRelationManager (stessa convenzione di
     * renderEsitoCalcolo/renderRigheQualifiche/renderRigheCondizioni).
     */
    public static function storicoRitiriSection(GraduatoriaCandidato $record): array
    {
        $eventi = $record->storicoRitiri()->with('eseguitoDa')->orderByDesc('created_at')->get();

        if ($eventi->isEmpty()) {
            return [];
        }

        return [
            Section::make('Storico ritiri/ripristini')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('storico_ritiri')
                        ->hiddenLabel()
                        ->content(new HtmlString(static::renderStoricoRitiri($eventi))),
                ]),
        ];
    }

    /** Righe HTML dello storico ritiri/ripristini (data/ora, operatore, motivo), più recenti in cima. */
    public static function renderStoricoRitiri(\Illuminate\Support\Collection $eventi): string
    {
        return $eventi->map(function (StoricoRitiro $e) {
            $etichetta = $e->tipo === 'ritiro' ? 'Ritirato' : 'Ripristinato';
            $colore    = $e->tipo === 'ritiro' ? '#dc2626' : '#16a34a';

            return '<div style="padding:6px 0;border-bottom:1px solid #e5e7eb;">'
                . '<div><span style="color:' . $colore . ';font-weight:600;">' . $etichetta . '</span>'
                . ' <span style="color:#6b7280;font-size:12px;">' . $e->created_at->format('d/m/Y H:i')
                . ($e->eseguitoDa ? ' · ' . e($e->eseguitoDa->name) : '') . '</span></div>'
                . '<div style="margin-top:2px;">' . e($e->motivo) . '</div>'
                . '</div>';
        })->implode('');
    }

    /** Righe prerequisiti (nome + stato colorato + scadenza) per la modale "Dettagli candidatura". */
    public static function renderRigheQualifiche(array $righe): string
    {
        $etichette = [
            'ok'                       => ['label' => 'Valida', 'color' => '#16a34a'],
            'in_scadenza_entro_limite' => ['label' => 'In scadenza', 'color' => '#d97706'],
            'scaduta'                  => ['label' => 'Scaduta', 'color' => '#dc2626'],
            'assente'                  => ['label' => 'Assente', 'color' => '#dc2626'],
            'non_verificato'           => ['label' => 'Non verificato', 'color' => '#dc2626'],
        ];

        return collect($righe)->map(function (array $r) use ($etichette) {
            $e        = $etichette[$r['stato']];
            $scadenza = $r['scadenza'] ? $r['scadenza']->format('d/m/Y') : (in_array($r['stato'], ['assente', 'non_verificato'], true) ? '—' : 'Nessuna scadenza');

            return '<div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:1px solid #e5e7eb;">'
                . '<span>' . e($r['nome']) . '</span>'
                . '<span><span style="color:' . $e['color'] . ';font-weight:600;">' . $e['label'] . '</span> · ' . $scadenza . '</span>'
                . '</div>';
        })->implode('');
    }

    /** Righe condizioni punteggio (label + punteggio/priorità + ottenuta/non verificabile + scadenza) per la stessa modale. */
    public static function renderRigheCondizioni(array $righe): string
    {
        if (! $righe) {
            return '<span style="color:#6b7280;">Nessuna condizione configurata.</span>';
        }

        return collect($righe)->map(function (array $r) {
            if (! $r['verificabile']) {
                $stato  = 'Socio non agganciato';
                $colore = '#9ca3af';
            } else {
                $stato  = $r['ottenuta'] ? 'Ottenuta' : 'Non ottenuta';
                $colore = $r['ottenuta'] ? '#16a34a' : '#9ca3af';
            }
            $scadenza = $r['scadenza'] ? ' · ' . $r['scadenza']->format('d/m/Y') : '';

            return '<div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:1px solid #e5e7eb;">'
                . '<span>' . e($r['label']) . ' <span style="color:#6b7280;">(' . e($r['punteggio']) . ')</span></span>'
                . '<span><span style="color:' . $colore . ';font-weight:600;">' . $stato . '</span>' . $scadenza . '</span>'
                . '</div>';
        })->implode('');
    }

    /** Riepilogo esito calcolo classifica per il singolo candidato, con avviso se non allineato all'ultimo calcolo generale. */
    public static function renderEsitoCalcolo(GraduatoriaCandidato $record, ?\Carbon\Carbon $ultimoCalcoloGlobale): string
    {
        if (! $record->calcolato_at) {
            $nota = $ultimoCalcoloGlobale
                ? 'Non incluso nell\'ultimo calcolo (' . $ultimoCalcoloGlobale->format('d/m/Y H:i') . ') — probabilmente candidatura arrivata dopo. Rilancia "Calcola punteggi" per includerlo.'
                : 'Nessun calcolo eseguito ancora su questa graduatoria.';

            return '<span style="color:#d97706;font-weight:600;">Non ancora calcolato.</span><br>'
                . '<span style="color:#6b7280;font-size:12px;">' . e($nota) . '</span>';
        }

        $righe = [
            'Calcolato il: ' . $record->calcolato_at->format('d/m/Y H:i'),
            'Punteggio finale: ' . number_format((float) $record->punteggio_finale, 2, ',', ''),
            'Ammissibile: ' . ($record->ammissibile ? 'Sì' : 'No'),
            'Ammesso: ' . ($record->ammesso === null ? '—' : ($record->ammesso ? 'Sì' : 'No')),
            'Posizione: ' . ($record->posizione_classifica ?? '—'),
        ];

        $html = implode('<br>', array_map('e', $righe));

        if (filled($record->dettaglio_punteggio)) {
            $html .= '<div style="margin-top:6px;color:#6b7280;font-size:12px;">' . e(implode(' · ', $record->dettaglio_punteggio)) . '</div>';
        }

        if ($ultimoCalcoloGlobale && $record->calcolato_at->lt($ultimoCalcoloGlobale)) {
            $html .= '<div style="margin-top:6px;color:#d97706;">⚠ Esiste un calcolo più recente (' . e($ultimoCalcoloGlobale->format('d/m/Y H:i'))
                . ') per altri candidati — questo potrebbe non essere aggiornato. Rilancia "Calcola punteggi".</div>';
        }

        return $html;
    }

    /**
     * Schema condiviso per la creazione manuale (bypass admin) e per la modale di conferma.
     */
    protected static function formCandidato(?GraduatoriaCandidato $record = null): array
    {
        return [
            Forms\Components\Select::make('socio_id')
                ->label('Cerca socio esistente (CF, nome o cognome)')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => Socio::query()
                    ->where('codice_fiscale', 'ilike', "%{$search}%")
                    ->orWhere('nome', 'ilike', "%{$search}%")
                    ->orWhere('cognome', 'ilike', "%{$search}%")
                    ->limit(20)
                    ->get()
                    ->mapWithKeys(fn (Socio $s) => [$s->id => "{$s->cognome} {$s->nome} — {$s->codice_fiscale}"]))
                ->getOptionLabelUsing(fn ($value) => optional(Socio::find($value))->nome_completo)
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    if (! $state) return;
                    $socio = Socio::find($state);
                    if (! $socio) return;
                    $set('codice_fiscale', $socio->codice_fiscale);
                    $set('nome', $socio->nome);
                    $set('cognome', $socio->cognome);
                    $set('email', $socio->email);
                    $set('telefono', $socio->telefono);
                    $set('comitato_regionale_id', $socio->comitato_regionale_id);
                    $set('comitato_locale_id', $socio->comitato_locale_id);
                })
                ->visible(fn () => $record === null)
                ->dehydrated(false)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('codice_fiscale')
                ->label('Codice fiscale')
                ->required()
                ->maxLength(16)
                ->visible(fn () => $record === null)
                ->dehydrated(fn () => $record === null),

            Forms\Components\TextInput::make('nome')->required()->maxLength(255),
            Forms\Components\TextInput::make('cognome')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
            Forms\Components\TextInput::make('telefono')->tel()->maxLength(50),

            Forms\Components\Select::make('comitato_regionale_id')
                ->label('Comitato Regionale')
                ->options(ComitatoRegionale::orderBy('nome')->pluck('nome', 'id'))
                ->searchable(),

            Forms\Components\Select::make('comitato_locale_id')
                ->label('Comitato Locale')
                ->options(ComitatoLocale::orderBy('nome')->pluck('nome', 'id'))
                ->searchable(),
        ];
    }

    /**
     * Crea o aggiorna il Socio con i dati confermati dalla segreteria.
     */
    protected static function sincronizzaSocio(GraduatoriaCandidato $record): void
    {
        $socio = \App\Services\CandidaturaSocioSync::sincronizza([
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

        if ($record->socio_id !== $socio->id) {
            $record->updateQuietly(['socio_id' => $socio->id]);
        }
    }
}
