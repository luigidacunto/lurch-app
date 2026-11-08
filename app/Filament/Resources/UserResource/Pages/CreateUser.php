<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // Pre-riempi il form con i valori dell'utente creato in precedenza (solo dopo "Salva & nuovo").
    public function mount(): void
    {
        parent::mount();

        $defaults = session('create_user_defaults');
        if ($defaults) {
            $this->form->fill($defaults);
        }
    }

    protected function afterCreate(): void
    {
        $permissions = $this->data['permissions'] ?? [];

        if ($this->record->isOperatore()) {
            $this->record->syncPermissions($permissions);
        }

        // Flash: persiste solo per la richiesta immediatamente successiva.
        // Se l'utente fa "Salva & nuovo" → create page legge i valori.
        // Se fa "Salva" → redirect alla lista consuma il flash, create page non li trova.
        session()->flash('create_user_defaults', [
            'ruolo'                 => $this->record->ruolo,
            'comitato_regionale_id' => $this->record->comitato_regionale_id,
            'comitato_locale_id'    => $this->record->comitato_locale_id,
            'attivo'                => $this->record->attivo,
            'permissions'           => $permissions,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['permissions']);
        return $data;
    }
}
