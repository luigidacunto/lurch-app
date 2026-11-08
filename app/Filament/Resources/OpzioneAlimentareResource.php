<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OpzioneAlimentareResource\Pages;
use App\Models\OpzioneAlimentare;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OpzioneAlimentareResource extends Resource
{
    protected static ?string $model = OpzioneAlimentare::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static \UnitEnum|string|null $navigationGroup = 'Impostazioni';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'opzioni-alimentari';

    protected static ?string $modelLabel = 'Opzione alimentare';

    protected static ?string $pluralModelLabel = 'Opzioni alimentari';

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('tipo')
                ->label('Tipo')
                ->options([
                    'allergene' => 'Allergene',
                    'dieta'     => 'Dieta / preferenza alimentare',
                ])
                ->searchable()
                ->required(),

            Forms\Components\TextInput::make('nome')
                ->label('Nome (chiave interna)')
                ->helperText('Valore salvato nelle risposte. Non modificare se ci sono form già compilati.')
                ->required()
                ->maxLength(100),

            Forms\Components\TextInput::make('etichetta')
                ->label('Etichetta (mostrata nel form)')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('ordine')
                ->label('Ordine')
                ->numeric()
                ->default(0)
                ->required(),

            Forms\Components\Toggle::make('attivo')
                ->label('Attivo')
                ->default(true),
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
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($state) => $state === 'allergene' ? 'warning' : 'info')
                    ->formatStateUsing(fn ($state) => $state === 'allergene' ? 'Allergene' : 'Dieta'),

                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('etichetta')
                    ->label('Etichetta')
                    ->searchable(),

                Tables\Columns\TextColumn::make('ordine')
                    ->label('Ordine')
                    ->sortable(),

                Tables\Columns\IconColumn::make('attivo')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'allergene' => 'Allergeni',
                        'dieta'     => 'Diete / preferenze',
                    ]),
            ])
            ->defaultSort('tipo')
            ->reorderable('ordine')
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica'),
                ActionGroup::make([
                    DeleteAction::make(),
                ])
                    ->label('Altre azioni')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('danger')
                    ->iconButton(),
            ], position: Tables\Enums\RecordActionsPosition::BeforeColumns);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOpzioniAlimentari::route('/'),
            'create' => Pages\CreateOpzioneAlimentare::route('/create'),
            'edit'   => Pages\EditOpzioneAlimentare::route('/{record}/edit'),
        ];
    }
}
