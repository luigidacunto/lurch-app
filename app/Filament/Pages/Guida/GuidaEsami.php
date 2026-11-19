<?php

namespace App\Filament\Pages\Guida;

class GuidaEsami extends PaginaGuida
{
    protected static string $sezione = '04-esami';

    protected static ?string $title = 'Esami';

    protected static ?string $slug = 'guida/esami';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 5;
}
