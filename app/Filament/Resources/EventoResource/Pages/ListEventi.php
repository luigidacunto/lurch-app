<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListEventi extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = EventoResource::class;
}
