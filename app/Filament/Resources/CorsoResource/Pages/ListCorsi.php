<?php

namespace App\Filament\Resources\CorsoResource\Pages;

use App\Filament\Resources\CorsoResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListCorsi extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = CorsoResource::class;
}
