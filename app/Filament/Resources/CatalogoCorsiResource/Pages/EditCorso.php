<?php

namespace App\Filament\Resources\CatalogoCorsiResource\Pages;

use App\Filament\Resources\CatalogoCorsiResource;
use Filament\Resources\Pages\EditRecord;

class EditCorso extends EditRecord
{
    protected static string $resource = CatalogoCorsiResource::class;

    public function getTitle(): string
    {
        $codice = $this->record->codice;

        return $codice ? parent::getTitle() . ' — ' . $codice : parent::getTitle();
    }
}
