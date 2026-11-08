<?php

namespace App\Filament\Resources\ComitatoLocaleResource\Pages;

use App\Filament\Resources\ComitatoLocaleResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListComitatoLocali extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = ComitatoLocaleResource::class;
}
