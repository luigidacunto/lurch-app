<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComitatoLocaleResource\Pages;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComitatoLocaleResource extends Resource
{
    protected static ?string $model = ComitatoLocale::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static \UnitEnum|string|null $navigationGroup = 'Archivi';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'comitati-locali';

    protected static ?string $modelLabel = 'Comitato Locale';

    protected static ?string $pluralModelLabel = 'Comitati Locali';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin() || $user?->isAdmin();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin() || $user?->isAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if ($user?->isSuperadmin()) return true;
        // Admin: solo i locali del proprio comitato regionale
        return $user?->isAdmin()
            && $record->comitato_regionale_id === $user->comitato_regionale_id;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    /** Scope query: include soft-deleted; admin vede solo i locali della propria regione. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->with('sottosedi');
        $user  = auth()->user();

        if ($user?->isAdmin()) {
            $query->where('comitato_regionale_id', $user->comitato_regionale_id);
        }

        return $query;
    }

    public static function form(Schema $form): Schema
    {
        $user = auth()->user();

        return $form->schema([
            Section::make()->schema([
                Forms\Components\Select::make('comitato_regionale_id')
                    ->label('Comitato Regionale')
                    ->options(
                        $user?->isAdmin()
                            // Admin: solo la propria regione
                            ? ComitatoRegionale::where('id', $user->comitato_regionale_id)
                                ->pluck('nome', 'id')
                            // Superadmin: tutte
                            : ComitatoRegionale::orderBy('nome')->pluck('nome', 'id')
                    )
                    ->default($user?->isAdmin() ? $user->comitato_regionale_id : null)
                    ->required()
                    ->searchable()
                    ->disabledOn('edit'),

                Forms\Components\TextInput::make('nome')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('rappresentante_legale')
                    ->label('Rappresentante legale')
                    ->maxLength(255)
                    ->placeholder('Es. Mario Rossi'),

                Forms\Components\TextInput::make('indirizzo')
                    ->label('Sede legale / Indirizzo')
                    ->maxLength(500)
                    ->placeholder('Es. Via Roma 1, 50100 Firenze')
                    ->helperText('Usato nei testi privacy come {indirizzo}.'),

                Forms\Components\TextInput::make('email')
                    ->label('E-mail istituzionale')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('Es. firenze@cri.it')
                    ->helperText('Usato nei testi privacy come {email}.'),

                Forms\Components\TextInput::make('codice')
                    ->label('Codice (es. LOM-MI)')
                    ->required()
                    ->maxLength(20)
                    ->unique(table: 'comitati_locali', column: 'codice', ignoreRecord: true)
                    ->alphaDash()
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),

                Forms\Components\TagsInput::make('sottosedi_nomi')
                    ->label('Sottosedi')
                    ->helperText('Premi Invio o virgola per aggiungere una sottosede.')
                    ->placeholder('es. Fiesole')
                    ->separator(',')
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state($record->sottosedi->pluck('nome')->all());
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('attivo')
                    ->label('Attivo')
                    ->default(true),
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
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('codice')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('comitatoRegionale.nome')
                    ->label('Regionale')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sottosedi_count')
                    ->label('Sottosedi')
                    ->getStateUsing(fn ($record) => $record->sottosedi->isNotEmpty()
                        ? $record->sottosedi->pluck('nome')->implode(', ')
                        : 'Nessuna sottosede'
                    )
                    ->wrap()
                    ->sortable(false)
                    ->searchable(false),

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

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica')
                    ->visible(fn (Model $record) => static::canEdit($record)),
                ActionGroup::make([
                    RestoreAction::make(),
                    DeleteAction::make()
                        ->visible(fn (Model $record) => static::canDelete($record)),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->defaultSort('nome');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListComitatoLocali::route('/'),
            'create' => Pages\CreateComitatoLocale::route('/create'),
            'edit'   => Pages\EditComitatoLocale::route('/{record}/edit'),
        ];
    }
}
