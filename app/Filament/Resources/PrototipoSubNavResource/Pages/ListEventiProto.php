<?php

namespace App\Filament\Resources\PrototipoSubNavResource\Pages;

use App\Filament\Resources\PrototipoSubNavResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListEventiProto extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = PrototipoSubNavResource::class;

    protected static ?string $title = 'Prototipo sub-navigation — scegli un evento';
}
