<?php

namespace App\Filament\Resources\GraduatoriaResource\Pages;

use App\Filament\Resources\GraduatoriaResource;
use App\Models\Graduatoria;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateGraduatoria extends CreateRecord
{
    protected static string $resource = GraduatoriaResource::class;

    protected function afterCreate(): void
    {
        if (Graduatoria::titoloDuplicato($this->record->titolo, $this->record->id)) {
            Notification::make()
                ->title('Esiste già una graduatoria con questo titolo')
                ->body('Se la riproponi ogni anno, valuta di aggiungere l\'anno (e il periodo, se capita più volte nello stesso anno — es. "Autunno 2026") per distinguerle in elenco.')
                ->warning()
                ->send();
        }
    }

    public function riempiDaGaia(array $data): void
    {
        if (empty($data['corso'])) return;

        if (isset($data['corso'])) $this->data['titolo'] = $data['corso'];

        $notifica = Notification::make()
            ->title('Dati GAIA incollati: ' . $data['corso'])
            ->success();

        $this->dispatch('notificationSent', notification: $notifica->toArray());
    }
}
