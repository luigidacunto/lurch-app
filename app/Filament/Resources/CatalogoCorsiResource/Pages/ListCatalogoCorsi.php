<?php

namespace App\Filament\Resources\CatalogoCorsiResource\Pages;

use App\Filament\Resources\CatalogoCorsiResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListCatalogoCorsi extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = CatalogoCorsiResource::class;
}
