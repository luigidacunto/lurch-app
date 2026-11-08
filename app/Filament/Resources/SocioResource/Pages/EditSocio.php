<?php

namespace App\Filament\Resources\SocioResource\Pages;

use App\Filament\Resources\SocioResource;
use Filament\Resources\Pages\EditRecord;

class EditSocio extends EditRecord
{
    protected static string $resource = SocioResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . $this->record->cognome . ' ' . $this->record->nome;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // La sezione "Salute"/alimentazione del form è disabilitata (SocioResource::form,
        // ->visible(false)): i suoi campi non arrivano nel payload. Senza questa guardia il
        // merge sotto scriverebbe `diete = []` ad ogni salvataggio, azzerando
        // intolleranze/diete/esclusioni già registrate. Riattivare la sezione rimuove il caso.
        if (! array_key_exists('diete', $data)
            && ! array_key_exists('diete_intolleranze', $data)
            && ! array_key_exists('diete_esclusioni', $data)) {
            return $data;
        }

        $data['diete'] = array_values(array_unique(array_merge(
            $data['diete'] ?? [],
            $data['diete_intolleranze'] ?? [],
            $data['diete_esclusioni'] ?? [],
        )));
        unset($data['diete_intolleranze'], $data['diete_esclusioni']);

        return $data;
    }
}
