<?php

namespace App\Filament\Resources\GraduatoriaResource\Pages;

use App\Filament\Resources\GraduatoriaResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListGraduatorie extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = GraduatoriaResource::class;
}
