<?php

namespace App\Filament\Pages\Guida;

class GuidaPannello extends PaginaGuida
{
    protected static string $sezione = '01-pannello';

    protected static ?string $title = 'Pannello di gestione';

    protected static ?string $slug = 'guida/pannello';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?int $navigationSort = 2;
}
