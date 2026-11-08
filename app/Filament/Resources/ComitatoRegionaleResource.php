<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComitatoRegionaleResource\Pages;
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
use Illuminate\Database\Eloquent\Model;

class ComitatoRegionaleResource extends Resource
{
    protected static ?string $model = ComitatoRegionale::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static \UnitEnum|string|null $navigationGroup = 'Archivi';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'comitati-regionali';

    protected static ?string $modelLabel = 'Comitato Regionale';

    protected static ?string $pluralModelLabel = 'Comitati Regionali';

    // Solo il superadmin accede a questa risorsa.

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
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

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make()->schema([
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
                    ->placeholder('Es. toscana@cri.it')
                    ->helperText('Usato nei testi privacy come {email}.'),

                Forms\Components\TextInput::make('codice')
                    ->label('Codice (es. LOM)')
                    ->required()
                    ->maxLength(10)
                    ->unique(table: 'comitati_regionali', column: 'codice', ignoreRecord: true)
                    ->alphaDash()
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),

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
                CreateAction::make()
                    ->color('success'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('codice')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('comitatiLocali_count')
                    ->counts('comitatiLocali')
                    ->label('Locali')
                    ->sortable(),

                Tables\Columns\IconColumn::make('attivo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->label('Creato il')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListComitatoRegionali::route('/'),
            'create' => Pages\CreateComitatoRegionale::route('/create'),
            'edit'   => Pages\EditComitatoRegionale::route('/{record}/edit'),
        ];
    }
}
