<?php

namespace App\Filament\Resources\OpzioneAlimentareResource\Pages;

use App\Filament\Resources\OpzioneAlimentareResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListOpzioniAlimentari extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = OpzioneAlimentareResource::class;
}
