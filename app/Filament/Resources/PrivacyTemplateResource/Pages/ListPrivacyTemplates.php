<?php

namespace App\Filament\Resources\PrivacyTemplateResource\Pages;

use App\Filament\Resources\PrivacyTemplateResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListPrivacyTemplates extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = PrivacyTemplateResource::class;
}
