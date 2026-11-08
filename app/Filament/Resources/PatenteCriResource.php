<?php

namespace App\Filament\Resources;

use App\Models\PatenteCri;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PatenteCriResource extends Resource
{
    protected static ?string $model = PatenteCri::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static \UnitEnum|string|null $navigationGroup = 'Archivi';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Patente CRI';

    protected static ?string $pluralModelLabel = 'Patenti CRI';

    // "cri" è un acronimo invariabile: senza slug esplicito Filament pluralizza in "patente-cris".
    protected static ?string $slug = 'patenti-cri';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('tipologia')
                ->label('Tipologia (es. 1, 4m, 5b)')
                ->required()
                ->maxLength(10),

            Forms\Components\TextInput::make('ordine')
                ->label('Ordine')
                ->numeric()
                ->default(0),

            Forms\Components\Textarea::make('descrizione')
                ->label('Descrizione')
                ->rows(2)
                ->columnSpanFull(),

            Forms\Components\Toggle::make('attivo')
                ->label('Attiva')
                ->default(true)
                ->inline(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->color('success'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('ordine')->sortable()->label('#'),
                Tables\Columns\TextColumn::make('tipologia')->sortable()->weight('bold')->label('Tipologia'),
                Tables\Columns\TextColumn::make('descrizione')->limit(70)->placeholder('—'),
                Tables\Columns\IconColumn::make('attivo')->boolean()->label('Attiva'),
            ])
            ->reorderable('ordine')
            ->defaultSort('ordine')
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica'),
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\DeleteAction::make(),
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
            'index'  => \App\Filament\Resources\PatenteCriResource\Pages\ListPatentiCri::route('/'),
            'create' => \App\Filament\Resources\PatenteCriResource\Pages\CreatePatenteCri::route('/create'),
            'edit'   => \App\Filament\Resources\PatenteCriResource\Pages\EditPatenteCri::route('/{record}/edit'),
        ];
    }
}
