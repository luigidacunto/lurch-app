<?php

namespace App\Filament\Resources\OpzioneAlimentareResource\Pages;

use App\Filament\Resources\OpzioneAlimentareResource;
use Filament\Resources\Pages\EditRecord;

class EditOpzioneAlimentare extends EditRecord
{
    protected static string $resource = OpzioneAlimentareResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . ($this->record->etichetta ?: $this->record->nome);
    }
}
