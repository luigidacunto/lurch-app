<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    public function getTitle(): string
    {
        $etichetta = $this->record->soggetto ?: $this->record->tipo;

        return $etichetta ? parent::getTitle() . ' — ' . $etichetta : parent::getTitle();
    }
}
