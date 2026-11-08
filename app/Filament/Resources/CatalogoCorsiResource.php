<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogoCorsiResource\Pages;
use App\Filament\Resources\CatalogoCorsiResource\RelationManagers\ValiditaRegionaliRelationManager;
use App\Models\CatalogoCorso;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CatalogoCorsiResource extends Resource
{
    protected static ?string $model = CatalogoCorso::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static \UnitEnum|string|null $navigationGroup = 'Archivi';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'catalogo-corsi';

    protected static ?string $modelLabel = 'Corso';

    protected static ?string $pluralModelLabel = 'Catalogo Corsi';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminLocale();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->with(['equivalenteA', 'corsiEquivalenti']);
    }

    public static function form(Schema $form): Schema
    {
        $tipiOptions = [
            'Catalogo corsi formazione'              => 'Formazione',
            'Catalogo corsi formazione online'       => 'Formazione Online',
            'Catalogo corsi formazione equipollenza' => 'Formazione Equipollenza',
            'Catalogo corsi formazione asincroni - Training CRI' => 'Training CRI (Asincroni)',
            'Catalogo corsi aggregatore'              => 'Aggregatore',
        ];

        $obiettiviOptions = [
            'Salute'                      => 'Salute',
            'Salute e sicurezza'          => 'Salute e Sicurezza',
            'Inclusione Sociale'          => 'Inclusione Sociale',
            'Emergenza'                   => 'Emergenza',
            'Principi e Valori'           => 'Principi e Valori',
            'Giovani'                     => 'Giovani',
            'Sviluppo Organizzativo'      => 'Sviluppo Organizzativo',
            'Migrazioni'                  => 'Migrazioni',
            'Cooperazione Internazionale' => 'Cooperazione Internazionale',
            'Motorizzazione'              => 'Motorizzazione',
        ];

        $livelliOptions = [
            'I Livello'   => 'I Livello',
            'II Livello'  => 'II Livello',
            'III Livello' => 'III Livello',
            'IV Livello'  => 'IV Livello',
            'Aggregatore' => 'Aggregatore',
        ];

        return $form->schema([
            Section::make('Identificazione')->schema([
                Forms\Components\TextInput::make('codice')
                    ->label('Codice')
                    ->required()
                    ->maxLength(50)
                    ->unique(table: 'catalogo_corsi', column: 'codice', ignoreRecord: true)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),

                Forms\Components\TextInput::make('nome')
                    ->label('Nome GAIA')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2)
                    ->helperText('Testo esatto così come compare in GAIA — usato per il match automatico durante l\'import Excel. Non ripulire qui: eventuali refusi vanno corretti solo nel "Nome visualizzato".'),

                Forms\Components\TagsInput::make('nomi_alternativi')
                    ->label('Nomi alternativi (alias)')
                    ->placeholder('Es. Operatore Sociale Generico')
                    ->helperText('Vecchie diciture GAIA per lo stesso corso (es. dopo un rinominio) — usate anch\'esse per il match automatico durante l\'import, invece di creare un corso separato.')
                    ->columnSpan(2),

                Forms\Components\TextInput::make('nome_visibile')
                    ->label('Nome visualizzato')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2)
                    ->helperText('Testo mostrato agli utenti su LURCH (liste, PDF, export): ripulito da spazi doppi, apici e maiuscolo eccessivo. Se il corso è inserito a mano, va compilato coerente col "Nome GAIA".'),

                Forms\Components\Toggle::make('attivo')
                    ->label('Attivo')
                    ->default(true)
                    ->inline(false),

                Forms\Components\Toggle::make('aggregatore')
                    ->label('Corso aggregatore')
                    ->default(false)
                    ->inline(false)
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $set('gestito_manualmente', true);
                        }
                    })
                    ->helperText('Nessuno lo consegue direttamente: la sua scadenza è calcolata come la più recente tra tutti i corsi che lo indicano come "Equivalente a" (es. Salute e Sicurezza ← SIC2/SICC/SICCOL/A-SICCOL/PRE/DIR). Convenzione codice: prefisso "AGGR-" (es. AGGR-SIC).')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('gestito_manualmente')
                    ->label('Gestito manualmente')
                    ->default(false)
                    ->inline(false)
                    ->helperText('Attivare per i corsi senza codice ufficiale nel catalogo GAIA esportabile (inseriti a mano). Il sync automatico del catalogo (CatalogoCorsiSeeder) non disattiva mai i corsi con questo flag attivo, anche se il loro codice non compare nel file di import.')
                    ->columnSpanFull(),

                Forms\Components\Select::make('equivalente_a_id')
                    ->label('Equivalente a')
                    ->options(fn () => CatalogoCorso::attivo()->orderBy('nome')
                        ->get()
                        ->mapWithKeys(fn ($c) => [$c->id => "[{$c->codice}] {$c->nome}"]))
                    ->searchable()
                    ->nullable()
                    ->placeholder('Nessuna equivalenza')
                    ->helperText('Se questo corso è equiparabile a un altro (es. SA ≡ TSSA), selezionarlo qui. I soci con questo corso appariranno nelle liste del corso master. Se il corso selezionato è un "aggregatore", la sua scadenza verrà aggiornata automaticamente.')
                    ->columnSpanFull(),

                Forms\Components\TagsInput::make('eredita_scadenza_da_corsi')
                    ->label('Eredita scadenza da (codici corso)')
                    ->placeholder('Es. SA, TSSA')
                    ->helperText('Se il socio possiede uno dei corsi indicati, la scadenza di questo corso viene ereditata automaticamente dal corso più recente tra quelli elencati. Inserire i codici corso (es. SA, TSSA). Lasciare vuoto per scadenza indipendente.')
                    ->rules([fn () => CatalogoCorso::regolaCodiciValidi()])
                    ->columnSpanFull(),
            ])->columns(4),

            Section::make('Classificazione')->schema([
                Forms\Components\Select::make('tipo')
                    ->label('Tipo')
                    ->options($tipiOptions)
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('obiettivo')
                    ->label('Obiettivo')
                    ->options($obiettiviOptions)
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('livello')
                    ->label('Livello')
                    ->options($livelliOptions)
                    ->searchable()
                    ->nullable()
                    ->placeholder('Non specificato'),
            ])->columns(3),

            Section::make('Dettagli')->schema([
                Forms\Components\Textarea::make('descrizione')
                    ->label('Descrizione')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('scheda_pdf')
                    ->label('Link scheda GAIA')
                    ->url()
                    ->maxLength(1000)
                    ->placeholder('https://datafiles-gaia.cri.it/...')
                    ->helperText('Aggiornato automaticamente dal seeder del catalogo. Modificabile manualmente se necessario.')
                    ->suffixAction(
                        \Filament\Actions\Action::make('apri_scheda_gaia')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->label('Apri')
                            ->url(fn ($get) => $get('scheda_pdf'))
                            ->openUrlInNewTab()
                            ->visible(fn ($get) => (bool) $get('scheda_pdf'))
                    )
                    ->columnSpanFull(),

                Grid::make(2)->schema([
                    Forms\Components\Select::make('scadenza_tipo')
                        ->label('Scadenza nazionale')
                        ->options([
                            'mesi_esatti' => 'A mesi esatti dalla data di acquisizione',
                            'anno_solare' => 'Anno solare (rinnovo entro fine anno)',
                        ])
                        ->placeholder('Nessuna scadenza')
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->helperText('Default nazionale. Le regioni possono avere override specifici (tab "Override regionali").'),

                    Forms\Components\TextInput::make('scadenza_durata')
                        ->label(fn (Get $get) => match ($get('scadenza_tipo')) {
                            'anno_solare' => 'Anni di validità',
                            default       => 'Mesi di validità',
                        })
                        ->numeric()
                        ->minValue(1)
                        ->required(fn (Get $get) => in_array($get('scadenza_tipo'), ['mesi_esatti', 'anno_solare']))
                        ->visible(fn (Get $get) => in_array($get('scadenza_tipo'), ['mesi_esatti', 'anno_solare']))
                        ->helperText(fn (Get $get) => match ($get('scadenza_tipo')) {
                            'mesi_esatti' => 'Es. 12 = scade esattamente 12 mesi dopo la data di acquisizione.',
                            'anno_solare' => 'Es. 2 = scade entro il 31/12 del secondo anno solare successivo.',
                            default       => null,
                        }),

                    Forms\Components\TagsInput::make('corsi_aggiornamento')
                        ->label('Codici corsi validi per aggiornamento')
                        ->helperText('Inserisci i codici dei corsi (es. BLS, TSSA) che rinnovano questa qualifica. Premi Invio per aggiungere.')
                        ->separator(',')
                        ->placeholder('es. BLS')
                        ->live()
                        ->rules([fn () => CatalogoCorso::regolaCodiciValidi()]),

                    Forms\Components\Toggle::make('aggiornamento_crea_base')
                        ->label('Crea qualifica base se assente durante import')
                        ->helperText('Se abilitato, durante l\'import Excel i soci che hanno un corso di aggiornamento ma non questa qualifica base la riceveranno automaticamente con la data più vecchia trovata nel file.')
                        ->inline(false)
                        ->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                // CreateAction non nasconde da sola il pulsante in base a canCreate() — solo la
                // rotta /create la controlla (403 al click). ->visible() esplicito qui evita che
                // admin_locale (canViewAny=true, canCreate=false) veda "Nuovo" e prenda un 403.
                CreateAction::make()
                    ->color('success')
                    ->visible(fn () => static::canCreate()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('codice')
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('nome_visibile')
                    ->label('Nome')
                    ->searchable(['nome_visibile', 'nome'])
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('scadenza_nazionaleLegibile')
                    ->label('Scadenza nazionale')
                    ->getStateUsing(fn ($record) => $record->scadenza_nazionale_legibile)
                    ->badge()
                    ->color(fn ($record) => match ($record->scadenza_tipo) {
                        'mesi_esatti' => 'warning',
                        'anno_solare' => 'info',
                        default       => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('validita_regionali_count')
                    ->label('Override regionali')
                    ->counts('validitaRegionali')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'primary' : 'gray')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('equivalenteA.codice')
                    ->label('Equivalente a')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('corsiEquivalenti.codice')
                    ->label('Corsi equivalenti')
                    ->headerTooltip('Corsi che indicano questo come "Equivalente a": chi li possiede compare anche nelle liste di questo corso')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scadenze_correlate')
                    ->label('Scadenze')
                    ->headerTooltip('Verde = codici corso che aggiornano questa qualifica (import GAIA). Verde con "≡" = corsi del gruppo per gli aggregatori. Blu = codici corso da cui questa scadenza viene ereditata automaticamente')
                    ->getStateUsing(fn ($record) => collect($record->corsi_aggiornamento ?? [])
                        ->filter(fn ($c) => filled($c))
                        ->map(fn ($c) => "AGG::{$c}")
                        ->merge(
                            $record->aggregatore
                                ? $record->corsiEquivalenti->pluck('codice')->filter(fn ($c) => filled($c))->map(fn ($c) => "AGGGRP::{$c}")
                                : []
                        )
                        ->merge(collect($record->eredita_scadenza_da_corsi ?? [])->filter(fn ($c) => filled($c))->map(fn ($c) => "ERED::{$c}"))
                        ->all())
                    ->formatStateUsing(fn ($state) => str_replace(['AGG::', 'AGGGRP::', 'ERED::'], ['', '≡ ', ''], $state))
                    ->color(fn ($state) => str_starts_with($state, 'ERED::') ? 'info' : 'success')
                    ->badge()
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(function (Builder $q) use ($search) {
                        $q->whereRaw('corsi_aggiornamento::text ILIKE ?', ["%{$search}%"])
                            ->orWhereRaw('eredita_scadenza_da_corsi::text ILIKE ?', ["%{$search}%"]);
                    }))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('attivo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('gestito_manualmente')
                    ->label('Manuale')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminato il')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'Catalogo corsi formazione'              => 'Formazione',
                        'Catalogo corsi formazione online'       => 'Online',
                        'Catalogo corsi formazione equipollenza' => 'Equipollenza',
                        'Catalogo corsi formazione asincroni - Training CRI' => 'Training CRI',
                        'Catalogo corsi aggregatore'              => 'Aggregatore',
                    ]),

                Tables\Filters\SelectFilter::make('obiettivo')
                    ->label('Obiettivo')
                    ->options([
                        'Salute'                      => 'Salute',
                        'Salute e sicurezza'          => 'Salute e Sicurezza',
                        'Inclusione Sociale'          => 'Inclusione Sociale',
                        'Emergenza'                   => 'Emergenza',
                        'Principi e Valori'           => 'Principi e Valori',
                        'Giovani'                     => 'Giovani',
                        'Sviluppo Organizzativo'      => 'Sviluppo Organizzativo',
                        'Migrazioni'                  => 'Migrazioni',
                        'Cooperazione Internazionale' => 'Cooperazione Internazionale',
                        'Motorizzazione'              => 'Motorizzazione',
                    ]),

                Tables\Filters\SelectFilter::make('livello')
                    ->options([
                        'I Livello'   => 'I Livello',
                        'II Livello'  => 'II Livello',
                        'III Livello' => 'III Livello',
                        'IV Livello'  => 'IV Livello',
                        'Aggregatore' => 'Aggregatore',
                    ]),

                Tables\Filters\TernaryFilter::make('attivo')
                    ->label('Stato'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->visible(fn (Model $record) => static::canEdit($record)),
                \Filament\Actions\Action::make('scheda_gaia')
                    ->label('GAIA')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn (Model $record) => $record->scheda_pdf)
                    ->openUrlInNewTab()
                    ->visible(fn (Model $record) => (bool) $record->scheda_pdf),
                ActionGroup::make([
                    RestoreAction::make(),
                    DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Elimina corso dal catalogo')
                        ->modalDescription('Il corso verrà eliminato e non sarà più visibile nel catalogo. Potrà essere ripristinato da un amministratore. Continuare?')
                        ->modalSubmitActionLabel('Sì, elimina')
                        ->visible(fn (Model $record) => static::canDelete($record)),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('codice');
    }

    public static function getRelations(): array
    {
        return [
            ValiditaRegionaliRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCatalogoCorsi::route('/'),
            'create' => Pages\CreateCorso::route('/create'),
            'edit'   => Pages\EditCorso::route('/{record}/edit'),
        ];
    }
}
