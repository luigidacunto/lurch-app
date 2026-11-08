<?php

namespace App\Filament\Resources\GraduatoriaResource\RelationManagers;

use App\Models\GraduatoriaCandidato;
use App\Models\GraduatoriaPunteggioManuale;
use App\Models\SocioTitoloStudioVerificato;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Griglia dei candidati non attivi nella graduatoria — o perché ritirati (manuale o
 * duplicato risolto da "Correggi CF"; l'azione "Ripristina" li riattiva sempre,
 * l'operatore giudica dal motivo mostrato se è opportuno farlo), o perché esclusi
 * dall'ultimo calcolo classifica (manca un requisito obbligatorio — sicurezza o
 * propedeutica; questi ultimi di sola consultazione, si risolvono correggendo
 * l'anagrafica/qualifiche e rilanciando "Calcola punteggi").
 * Volutamente separata da CandidatiRelationManager e inclusa manualmente via @livewire()
 * nella view della pagina (non tramite getRelationManagers(), che raggrupperebbe le due
 * griglie a tab invece di mostrarle entrambe impilate — vedi CandidatiRelationManager::table()
 * per la query che esclude questi stessi record dalla griglia principale).
 */
class CandidatiEsclusiRelationManager extends RelationManager
{
    protected static string $relationship = 'candidati';

    protected static ?string $title = 'Candidati esclusi';

    /**
     * La griglia principale (componente Livewire separato) dispatcha questo evento dopo
     * ogni azione che può far entrare/uscire candidati da qui (ritira, correggiCf,
     * calcolaClassifica) — altrimenti questa griglia resterebbe visivamente ferma finché
     * non si ricarica manualmente la pagina.
     */
    #[On('graduatoria-esclusi-refresh')]
    public function refresh(): void {}

    public function table(Table $table): Table
    {
        $richiedeAttestazione = $this->getOwnerRecord()->richiede_attestazione_presidente;
        $richiedeDiploma = $this->getOwnerRecord()->richiede_diploma;
        $richiedeAutocertificazione = $this->getOwnerRecord()->richiede_autocertificazione;

        return $table
            ->recordTitleAttribute('cognome')
            ->description('Punteggio calcolato ma non ammissibili all\'ultimo calcolo: manca un requisito obbligatorio (propedeutica o Sicurezza) configurato su questa graduatoria.')
            ->modifyQueryUsing(fn ($query) => $query
                ->where(fn ($q) => $q->where('ammissibile', false)->orWhereNotNull('ritirato_at'))
                ->orderBy('cognome')
                ->with([
                    'socio.titoliStudioVerificati',
                    'socio.comitatoLocale', 'socio.comitatoRegionale',
                    'socio.comitatoLocaleDipendente', 'socio.comitatoRegionaleDipendente',
                    'comitatoLocale',
                ]))
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

                Tables\Columns\TextColumn::make('tipo_esclusione')
                    ->label('Tipo')
                    ->badge()
                    ->getStateUsing(fn (GraduatoriaCandidato $record) => $record->ritirato_at ? 'Ritirato' : 'Requisiti mancanti')
                    ->color(fn (GraduatoriaCandidato $record) => $record->ritirato_at ? 'gray' : 'danger'),

                Tables\Columns\TextColumn::make('punteggio_finale')
                    ->label('Punteggio')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('motivo_esclusione')
                    ->label('Motivo')
                    ->getStateUsing(function (GraduatoriaCandidato $record) {
                        if ($record->ritirato_at) {
                            return 'Ritirato il ' . $record->ritirato_at->format('d/m/Y')
                                . ($record->motivo_ritiro ? ' — ' . $record->motivo_ritiro : '');
                        }

                        return $this->getOwnerRecord()->riepilogoProblemiRequisiti($record->socio, $record) ?? 'Socio non trovato in archivio';
                    })
                    ->wrap(),

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
            ->actions([
                Action::make('ripristina')
                    ->label('Ripristina')
                    ->tooltip(fn (GraduatoriaCandidato $record) => $record->ritirato_at
                        ? 'Annulla il ritiro: la candidatura torna attiva'
                        : 'Escluso per requisiti mancanti, non ritirato: risolvere l\'anagrafica/qualifiche e rilanciare "Calcola punteggi"')
                    ->icon('heroicon-o-arrow-uturn-right')
                    ->color('success')
                    ->iconButton()
                    ->disabled(fn (GraduatoriaCandidato $record) => ! $record->ritirato_at)
                    ->schema([
                        Forms\Components\Textarea::make('motivo_ripristino')
                            ->label('Motivo del ripristino')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (GraduatoriaCandidato $record, array $data) {
                        $record->update([
                            'ritirato_at'    => null,
                            'ritirato_da_id' => null,
                            'motivo_ritiro'  => null,
                        ]);
                        $record->storicoRitiri()->create([
                            'tipo'           => 'ripristino',
                            'motivo'         => $data['motivo_ripristino'],
                            'eseguito_da_id' => auth()->id(),
                        ]);
                        $this->dispatch('graduatoria-esclusi-refresh');

                        Notification::make()->title('Candidatura ripristinata')->success()->send();
                    }),

                Action::make('dettagliCandidato')
                    ->label('Dettagli')
                    ->tooltip('Dettagli candidatura')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->iconButton()
                    ->modalHeading(fn (GraduatoriaCandidato $record) => "Dettagli candidatura — {$record->cognome} {$record->nome}")
                    ->modalSubmitActionLabel('Salva')
                    ->modalSubmitAction(fn ($action) => $action->color('success'))
                    ->modalCancelActionLabel('Chiudi')
                    ->schema(function (GraduatoriaCandidato $record) use ($richiedeAttestazione, $richiedeAutocertificazione) {
                        $graduatoria = $this->getOwnerRecord();
                        $socio       = $record->socio;

                        $schema = [
                            Forms\Components\Placeholder::make('contatti')
                                ->label('Contatti')
                                ->content(new HtmlString(
                                    '<span style="color:#6b7280;">Email:</span> ' . e($record->email) . '<br>'
                                    . '<span style="color:#6b7280;">Telefono:</span> ' . e($record->telefono ?: '—')
                                )),

                            Forms\Components\Placeholder::make('scheda_socio')
                                ->label('Anagrafica')
                                ->visible((bool) $record->socio_id)
                                ->content(fn () => new HtmlString(
                                    '<a href="' . e(\App\Filament\Resources\SocioResource::getUrl('view', ['record' => $record->socio_id])) . '" target="_blank" style="color:#2563eb;text-decoration:underline;">↗ Apri scheda socio</a>'
                                )),

                            Forms\Components\Placeholder::make('motivo')
                                ->label($record->ritirato_at ? 'Motivo del ritiro' : 'Motivo esclusione')
                                ->content($record->ritirato_at
                                    ? 'Ritirato il ' . $record->ritirato_at->format('d/m/Y') . ($record->motivo_ritiro ? ' — ' . $record->motivo_ritiro : '')
                                    : ($graduatoria->riepilogoProblemiRequisiti($socio, $record) ?? 'Socio non trovato in archivio')),

                            Forms\Components\Placeholder::make('esito_calcolo')
                                ->label('Esito calcolo classifica')
                                ->content(new HtmlString(CandidatiRelationManager::renderEsitoCalcolo($record, $graduatoria->ultimoCalcoloClassifica()))),
                        ];

                        $haPrerequisiti = $graduatoria->richiede_corso_sicurezza || filled($graduatoria->qualifiche_propedeutiche) || $graduatoria->richiede_diploma || $graduatoria->richiede_autocertificazione;
                        if ($haPrerequisiti) {
                            $prerequisiti = $graduatoria->elencoPrerequisiti($socio, $record);
                            $schema[] = Forms\Components\Placeholder::make('prerequisiti')
                                ->label('Prerequisiti')
                                ->content(new HtmlString($prerequisiti
                                    ? CandidatiRelationManager::renderRigheQualifiche($prerequisiti)
                                    : '<span style="color:#6b7280;">Nessun socio agganciato: impossibile verificare.</span>'));
                        }

                        $haCondizioniPunteggio = $graduatoria->qualifichePreferenziali->isNotEmpty()
                            || $graduatoria->preferenza_diversifica_comitato
                            || ($graduatoria->richiede_attestazione_presidente && $graduatoria->punteggio_attestazione_presidente !== null);
                        if ($haCondizioniPunteggio) {
                            $condizioni = $graduatoria->elencoCondizioniPunteggio($socio, $record);
                            $schema[] = Forms\Components\Placeholder::make('condizioni_punteggio')
                                ->label('Condizioni extra per punteggio')
                                ->content(new HtmlString(CandidatiRelationManager::renderRigheCondizioni($condizioni)));
                        }

                        // Punteggi manuali (+/-): stessa logica di CandidatiRelationManager —
                        // registrazione manuale di fattori non verificabili da Lurch, utile qui
                        // perché un escluso può comunque acquisire i requisiti mancanti prima
                        // della chiusura e rientrare tra gli ammissibili.
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

                            // Niente fillForm su questo campo: vedi la stessa nota in
                            // CandidatiRelationManager — Filament non idrata l'anteprima di un
                            // file esistente su disco privato, il campo parte sempre vuoto.
                            // Sostituzione solo finché non verificato (poi sola lettura).
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

                        $schema = [...$schema, ...CandidatiRelationManager::storicoRitiriSection($record)];

                        return $schema;
                    })
                    ->fillForm(fn (GraduatoriaCandidato $record) => [
                        'attestazione_presidente_verificata' => $record->attestazione_presidente_verificata,
                        'autocertificazione_verificata' => $record->autocertificazione_verificata,
                        'punteggi_manuali' => $record->punteggiManuali->map(fn (GraduatoriaPunteggioManuale $p) => [
                            'id' => $p->id, 'valore' => (string) $p->valore, 'motivo' => $p->motivo,
                        ])->all(),
                    ])
                    ->action(function (GraduatoriaCandidato $record, array $data) {
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

                // Elimina definitivamente — SOLO se la candidatura è ritirata e non è mai
                // stata agganciata a un socio: tipicamente una registrazione con CF sbagliato,
                // ritirata in automatico da "Correggi CF" perché il CF corretto risultava già
                // confermato su un'altra candidatura (vedi CandidatiRelationManager, ramo
                // "Duplicato"). In questi casi non c'è alcun dato reale da preservare — audit
                // trail compreso — quindi a differenza di "Ripristina" questa azione elimina
                // anche lo storico ritiri collegato
                // (storico_ritiri è polimorfico, nessuna FK/cascade a livello DB).
                // Se invece socio_id è valorizzato, la riga rappresenta una candidatura reale
                // (anche se ritirata) e va SEMPRE gestita con "Ripristina", mai eliminata qui.
                ActionGroup::make([
                    Action::make('elimina')
                        ->label('Elimina')
                        ->tooltip(function (GraduatoriaCandidato $record) {
                            if (! $record->ritirato_at) return 'Disponibile solo per candidature ritirate';
                            if ($record->socio_id) return 'Candidatura collegata a un socio: usa "Ripristina", non eliminare';
                            return 'Elimina definitivamente: nessun socio agganciato, nessun dato reale da preservare';
                        })
                        ->icon('heroicon-o-trash')
                        ->disabled(fn (GraduatoriaCandidato $record) => ! $record->ritirato_at || $record->socio_id)
                        ->requiresConfirmation()
                        ->modalHeading('Elimina candidatura')
                        ->modalDescription('Candidatura ritirata, mai collegata a un socio (tipicamente un CF inserito male). L\'eliminazione è definitiva e cancella anche lo storico dei ritiri di questa riga.')
                        ->modalSubmitActionLabel('Sì, elimina')
                        ->action(function (GraduatoriaCandidato $record) {
                            $record->storicoRitiri()->delete();
                            $record->delete();
                            $this->dispatch('graduatoria-esclusi-refresh');

                            Notification::make()->title('Candidatura eliminata')->success()->send();
                        }),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns);
    }
}
