<?php

namespace App\Filament\Pages\Guida;

class GuidaAppSoci extends PaginaGuida
{
    protected static string $sezione = '02-app-soci';

    protected static ?string $title = 'App dei soci';

    protected static ?string $slug = 'guida/app-soci';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 3;
}
