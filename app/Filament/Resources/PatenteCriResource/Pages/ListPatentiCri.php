<?php

namespace App\Filament\Resources\PatenteCriResource\Pages;

use App\Filament\Resources\PatenteCriResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListPatentiCri extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = PatenteCriResource::class;
}
