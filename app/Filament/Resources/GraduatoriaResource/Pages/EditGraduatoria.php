<?php

namespace App\Filament\Resources\GraduatoriaResource\Pages;

use App\Filament\Resources\GraduatoriaResource;
use App\Models\Graduatoria;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGraduatoria extends EditRecord
{
    protected static string $resource = GraduatoriaResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . $this->record->titolo;
    }

    protected function afterSave(): void
    {
        if (Graduatoria::titoloDuplicato($this->record->titolo, $this->record->id)) {
            Notification::make()
                ->title('Esiste già un\'altra graduatoria con questo titolo')
                ->body('Se la riproponi ogni anno, valuta di aggiungere l\'anno (e il periodo, se capita più volte nello stesso anno — es. "Autunno 2026") per distinguerle in elenco.')
                ->warning()
                ->send();
        }
    }
}
