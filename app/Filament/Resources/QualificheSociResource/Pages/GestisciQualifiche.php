<?php

namespace App\Filament\Resources\QualificheSociResource\Pages;

use App\Filament\Resources\QualificheSociResource;
use App\Filament\Resources\QualificheSociResource\RelationManagers\QualificheRelationManager;
use App\Models\Socio;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Resources\RelationManagers\RelationGroup;

class GestisciQualifiche extends ManageRelatedRecords
{
    protected static string $resource = QualificheSociResource::class;

    protected static string $relationship = 'qualificheSoci';

    protected static ?string $title = 'Gestione Qualifiche';

    public function getTitle(): string
    {
        /** @var Socio $record */
        $record = $this->getRecord();

        return 'Gestione Qualifiche — ' . $record->cognome . ' ' . $record->nome;
    }

    public function getSubheading(): ?string
    {
        /** @var Socio $record */
        $record = $this->getRecord();
        return $record->cognome . ' ' . $record->nome
            . ' · ' . $record->codice_fiscale
            . ' · ' . ($record->comitatoLocale?->nome ?? '—');
    }

    public function getBreadcrumb(): string
    {
        return 'Gestisci qualifiche';
    }

    public function getRelationManagers(): array
    {
        return [
            QualificheRelationManager::class,
        ];
    }
}
