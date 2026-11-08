<?php

namespace App\Filament\Resources\PatenteCriResource\Pages;

use App\Filament\Resources\PatenteCriResource;
use Filament\Resources\Pages\EditRecord;

class EditPatenteCri extends EditRecord
{
    protected static string $resource = PatenteCriResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . $this->record->tipologia;
    }
}
