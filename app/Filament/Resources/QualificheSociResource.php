<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualificheSociResource\Pages;
use App\Models\Socio;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class QualificheSociResource extends Resource
{
    protected static ?string $model = Socio::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestione operativa';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'qualifiche-soci';

    protected static ?string $modelLabel = 'Qualifica';

    protected static ?string $pluralModelLabel = 'Qualifiche';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('qualifiche.view')
            || $user?->can('qualifiche.manage');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index'    => Pages\ListQualificheSoci::route('/'),
            'gestisci' => Pages\GestisciQualifiche::route('/{record}/gestisci'),
        ];
    }
}
