<?php

namespace App\Filament\Resources\PrivacyTemplateResource\Pages;

use App\Filament\Resources\PrivacyTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditPrivacyTemplate extends EditRecord
{
    protected static string $resource = PrivacyTemplateResource::class;

    public function getTitle(): string
    {
        $etichetta = $this->record->tipo . ' · ' . ($this->record->comitatoRegionale?->nome ?? 'globale');

        return parent::getTitle() . ' — ' . $etichetta;
    }
}
