<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = EmailTemplateResource::class;
}
