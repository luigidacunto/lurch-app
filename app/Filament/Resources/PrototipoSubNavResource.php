<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrototipoSubNavResource\Pages;
use App\Models\Evento;
use Filament\Actions\Action;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Prototipo temporaneo — dimostra il meccanismo NATIVO di Filament per pagine
 * correlate allo stesso record (getRecordSubNavigation), come alternativa ai
 * pulsanti di navigazione infilati tra le azioni header (vedi discussione
 * pulsanti header troppo numerosi). Nascosto dal menu, dati reali (Eventi) in
 * sola lettura — da cancellare o tenere per confronti futuri, non tocca nulla
 * di esistente.
 */
class PrototipoSubNavResource extends Resource
{
    protected static ?string $model = Evento::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'prototipo-subnav';

    protected static ?string $modelLabel = 'Prototipo sub-nav';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Evento')->searchable(),
                Tables\Columns\TextColumn::make('data_inizio')->label('Data inizio')->date('d/m/Y'),
            ])
            ->actions([
                Action::make('apri')
                    ->label('Apri prototipo')
                    ->url(fn (Evento $record) => static::getUrl('panoramica', ['record' => $record])),
            ]);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return [
            NavigationItem::make('Panoramica')
                ->icon('heroicon-o-eye')
                ->url(static::getUrl('panoramica', ['record' => $page->getRecord()]))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.panoramica')),

            NavigationItem::make('Staff')
                ->icon('heroicon-o-user-group')
                ->url(static::getUrl('staff', ['record' => $page->getRecord()]))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.staff')),

            NavigationItem::make('Badge & stampe')
                ->icon('heroicon-o-identification')
                ->url(static::getUrl('badge', ['record' => $page->getRecord()]))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.badge')),

            NavigationItem::make('Vetrina componenti')
                ->icon('heroicon-o-swatch')
                ->url(static::getUrl('vetrina', ['record' => $page->getRecord()]))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.vetrina')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'      => Pages\ListEventiProto::route('/'),
            'panoramica' => Pages\Panoramica::route('/{record}/panoramica'),
            'staff'      => Pages\StaffTab::route('/{record}/staff'),
            'badge'      => Pages\BadgeTab::route('/{record}/badge'),
            'vetrina'    => Pages\VetrinaTab::route('/{record}/vetrina'),
        ];
    }
}
