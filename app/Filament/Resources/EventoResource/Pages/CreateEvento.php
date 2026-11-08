<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvento extends CreateRecord
{
    protected static string $resource = EventoResource::class;

    /**
     * Dopo la creazione dell'evento, il FileUpload delle cartine è visibile
     * solo in edit (->visible quando record != null), quindi non serve
     * gestire cartine in afterCreate. L'upload avviene solo in afterSave (Edit).
     */
}
