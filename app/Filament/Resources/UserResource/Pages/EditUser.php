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
            ->intersect(array_keys(UserResource::PERMESSI_OPERATORE))
            ->values()
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
            // I permessi non mostrati nella scheda (senza effetto) restano come erano.
            $nascosti = $this->record->getDirectPermissions()->pluck('name')
                ->diff(array_keys(UserResource::PERMESSI_OPERATORE));
            $this->record->syncPermissions(
                $nascosti->merge($this->data['permissions'] ?? [])->unique()->values()->all()
            );
        } else {
            // Ruolo non più operatore: rimuove tutti i permessi diretti
            $this->record->syncPermissions([]);
        }
    }
}
