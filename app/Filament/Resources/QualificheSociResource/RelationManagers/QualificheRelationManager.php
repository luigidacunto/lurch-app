<?php

namespace App\Filament\Resources\QualificheSociResource\RelationManagers;

use App\Models\CatalogoCorso;
use App\Models\QualificaSocio;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class QualificheRelationManager extends RelationManager
{
    protected static string $relationship = 'qualificheSoci';

    protected static ?string $title = 'Qualifiche';

    protected static bool $isLazy = true;

    public function placeholder(): View
    {
        return view('filament.placeholders.qualifiche-loading');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    /**
     * Filament 5 rende di default "sola lettura" qualunque RelationManager agganciato a una
     * pagina ViewRecord (qui ViewSocio) — un controllo che vince prima di canCreate/canEdit/
     * canDelete già scritti sotto, disabilitando Aggiungi/Modifica/Elimina anche per un
     * superadmin. Questa RelationManager ha già la sua logica di permessi granulare (vedi
     * canCreate/canEdit/canDelete), quindi il default va disattivato qui.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('qualifiche.manage');
    }

    protected function canEdit(Model $record): bool
    {
        return $this->canCreate();
    }

    /**
     * Decisione 2026-08-12: a differenza di create/edit, l'eliminazione ha regole diverse
     * per ruolo — un admin locale può eliminare solo qualifiche inserite manualmente (mai
     * quelle importate da GAIA/Excel, dato ufficiale), l'operatore con permesso puntuale
     * qualifiche.manage non può eliminare affatto (per ora).
     */
    protected function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin() || $user?->isAdmin()) return true;
        if ($user?->isAdminLocale()) return $record->fonte === 'inserimento_manuale';
        return false;
    }

    /** Bulk-delete riservato a superadmin/admin regionale — mai admin locale, mai operatore. */
    protected function canDeleteAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin() || $user?->isAdmin();
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('catalogo_corso_id')
                ->label('Qualifica')
                // Label "[codice] nome": la ricerca di ->searchable() filtra sul testo
                // dell'opzione mostrata, quindi da sola basta a cercare anche per codice
                // (l'operatore lo conosce a memoria molto più spesso del nome per esteso).
                ->options(fn () => CatalogoCorso::attivo()->orderBy('nome')->get()
                    ->mapWithKeys(fn (CatalogoCorso $c) => [$c->id => "[{$c->codice}] {$c->nome}"]))
                ->searchable()
                ->required()
                ->disabledOn('edit'),

            Forms\Components\Select::make('fonte')
                ->label('Fonte')
                ->options([
                    'inserimento_manuale'     => 'Inserimento manuale',
                    'importazione_excel'      => 'Importazione Excel',
                    'importazione_gaia'       => 'Importazione GAIA',
                    'creata_da_aggiornamento' => 'Creata da cascata aggiornamento',
                    'calcolato'               => 'Calcolato (aggregatore)',
                ])
                ->default('inserimento_manuale')
                ->searchable()
                ->required(),

            Forms\Components\DatePicker::make('data_acquisizione')
                ->label('Data acquisizione')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->required()
                ->helperText('Prima volta che il socio ha ottenuto questa qualifica.'),

            Forms\Components\DatePicker::make('data_ultimo_rinnovo')
                ->label('Data ultimo rinnovo')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->nullable()
                ->helperText('Compilare solo se la qualifica è stata rinnovata dopo l\'acquisizione iniziale.'),

            Forms\Components\Textarea::make('note')
                ->label('Note')
                ->rows(2)
                ->nullable()
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('catalogoCorso.codice')
                    ->label('Codice')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('catalogoCorso.nome')
                    ->label('Qualifica')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('data_acquisizione')
                    ->label('Prima acquisizione')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('data_ultimo_rinnovo')
                    ->label('Ultimo rinnovo')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('scadenza_calcolata')
                    ->label('Scade il')
                    ->date('d/m/Y')
                    ->placeholder('Nessuna scadenza')
                    ->color(fn (QualificaSocio $record) => $record->colore_scadenza)
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('stato_scadenza')
                    ->label('Stato')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'scaduta'        => 'Scaduta',
                        'in_scadenza'    => 'In scadenza',
                        'valida'         => 'Valida',
                        'nessuna_scadenza' => 'Nessuna scadenza',
                        default          => '—',
                    })
                    ->color(fn ($state) => match ($state) {
                        'scaduta'        => 'danger',
                        'in_scadenza'    => 'warning',
                        'valida'         => 'success',
                        default          => 'gray',
                    }),

                Tables\Columns\TextColumn::make('fonte')
                    ->label('Fonte')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'inserimento_manuale'     => 'Manuale',
                        'importazione_excel'      => 'Import Excel',
                        'importazione_gaia'       => 'Import GAIA',
                        'creata_da_aggiornamento' => 'Cascata aggiornamento',
                        'calcolato'               => 'Calcolato',
                        default                   => $state,
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'importazione_excel'      => 'info',
                        'importazione_gaia'       => 'info',
                        'creata_da_aggiornamento' => 'warning',
                        'calcolato'               => 'purple',
                        default                   => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fonte')
                    ->label('Fonte')
                    ->options([
                        'inserimento_manuale'     => 'Manuale',
                        'importazione_excel'      => 'Import Excel',
                        'importazione_gaia'       => 'Import GAIA',
                        'creata_da_aggiornamento' => 'Cascata aggiornamento',
                        'calcolato'               => 'Calcolato',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Aggiungi qualifica')
                    ->color('success')
                    ->after(fn (QualificaSocio $record) => $record->aggiornaScadenzaCalcolata()),

                // Rilancia aggiornaScadenzaCalcolata() su tutte le qualifiche già presenti
                // per questo socio (stessi dati già in memoria, nessun nuovo import) — utile
                // quando le regole di un corso (validità regionale, eredita_scadenza_da_corsi)
                // sono cambiate dopo l'inserimento e la scadenza calcolata è rimasta indietro.
                // Sincrono (non un job): pochi record per singolo socio, nessuna necessità di coda.
                Action::make('ricalcolaScadenze')
                    ->label('Ricalcola scadenze')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn () => $this->canCreate())
                    ->tooltip('Ricalcola la scadenza di tutte le qualifiche di questo socio con le regole attuali dei corsi')
                    ->action(function () {
                        $this->getOwnerRecord()->qualificheSoci->each->aggiornaScadenzaCalcolata();

                        \Filament\Notifications\Notification::make()
                            ->title('Scadenze ricalcolate')
                            ->success()
                            ->send();
                    }),

                // "Importa CV GAIA"/"Cerca ID GAIA" NON stanno più qui (spostate in
                // ViewSocio::getHeaderActions()): questo pannello è lazy ($isLazy=true), il
                // link "Installa" generato da Filament comparirebbe tardi nel DOM e resterebbe
                // cliccabile verso il .user.js grezzo per tutta la durata del controllo versione
                // asincrono dello userscript — bug reale, riscontrato più volte (v1.10.9). Ogni
                // bottone che deve interagire con lo userscript va sempre a livello di pagina,
                // mai dentro un pannello/tabella che carica in ritardo.
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->after(fn (QualificaSocio $record) => $record->aggiornaScadenzaCalcolata()),

                ActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn () => auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin() || auth()->user()?->isAdminLocale())
                        ->disabled(fn (QualificaSocio $record) => auth()->user()?->isAdminLocale() && $record->fonte !== 'inserimento_manuale')
                        ->tooltip(fn (QualificaSocio $record) => (auth()->user()?->isAdminLocale() && $record->fonte !== 'inserimento_manuale')
                            ? 'Importata da GAIA/Excel: un admin locale può eliminare solo qualifiche inserite manualmente'
                            : null),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()),
                ]),
            ])
            ->defaultSort('catalogoCorso.nome');
    }
}
