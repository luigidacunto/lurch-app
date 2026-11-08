<?php

namespace App\Filament\Resources\ComitatoRegionaleResource\Pages;

use App\Filament\Resources\ComitatoRegionaleResource;
use Filament\Resources\Pages\EditRecord;

class EditComitatoRegionale extends EditRecord
{
    protected static string $resource = ComitatoRegionaleResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . $this->record->nome;
    }
}
