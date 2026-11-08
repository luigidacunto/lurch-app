<?php

namespace App\Filament\Resources\SocioResource\Pages;

use App\Filament\Resources\SocioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSocio extends CreateRecord
{
    protected static string $resource = SocioResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['diete'] = array_values(array_unique(array_merge(
            $data['diete'] ?? [],
            $data['diete_intolleranze'] ?? [],
            $data['diete_esclusioni'] ?? [],
        )));
        unset($data['diete_intolleranze'], $data['diete_esclusioni']);

        return $data;
    }
}
