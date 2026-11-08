<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . ($this->record->name ?: $this->record->email);
    }

    /**
     * Prima di riempire il form, carica i permessi diretti
     * assegnati all'utente come array di nomi.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permissions'] = $this->record
            ->getDirectPermissions()
            ->pluck('name')
            ->toArray();

        return $data;
    }

    /**
     * Rimuove 'permissions' prima del salvataggio nel DB;
     * viene sincronizzato separatamente in afterSave().
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['permissions']);
        return $data;
    }

    /**
     * Dopo il salvataggio, sincronizza i permessi granulari.
     * Se il ruolo è cambiato da operatore, revoca tutto.
     */
    protected function afterSave(): void
    {
        if ($this->record->isOperatore()) {
            $this->record->syncPermissions(
                $this->data['permissions'] ?? []
            );
        } else {
            // Ruolo non più operatore: rimuove tutti i permessi diretti
            $this->record->syncPermissions([]);
        }
    }
}
