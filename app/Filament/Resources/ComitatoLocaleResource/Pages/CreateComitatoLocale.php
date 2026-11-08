<?php

namespace App\Filament\Resources\ComitatoLocaleResource\Pages;

use App\Filament\Resources\ComitatoLocaleResource;
use App\Models\ComitatoSottosede;
use Filament\Resources\Pages\CreateRecord;

class CreateComitatoLocale extends CreateRecord
{
    protected static string $resource = ComitatoLocaleResource::class;

    protected function afterCreate(): void
    {
        $nomi = array_values(array_filter(array_map('trim', $this->data['sottosedi_nomi'] ?? [])));

        foreach ($nomi as $nome) {
            ComitatoSottosede::firstOrCreate([
                'comitato_locale_id' => $this->record->id,
                'nome'               => $nome,
            ]);
        }
    }
}
