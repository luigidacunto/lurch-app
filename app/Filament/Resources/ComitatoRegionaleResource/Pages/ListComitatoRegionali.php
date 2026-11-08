<?php

namespace App\Filament\Resources\ComitatoRegionaleResource\Pages;

use App\Filament\Resources\ComitatoRegionaleResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListComitatoRegionali extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = ComitatoRegionaleResource::class;
}
