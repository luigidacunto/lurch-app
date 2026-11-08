<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = UserResource::class;
}
