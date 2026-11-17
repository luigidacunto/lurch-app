<?php

namespace App\Filament\Pages\Guida;

class GuidaModuliPubblici extends PaginaGuida
{
    protected static string $sezione = '03-moduli-pubblici';

    protected static ?string $title = 'Moduli pubblici';

    protected static ?string $slug = 'guida/moduli-pubblici';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 4;
}
