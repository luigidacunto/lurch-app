<?php

namespace App\Filament\Resources\ComitatoLocaleResource\Pages;

use App\Filament\Resources\ComitatoLocaleResource;
use App\Models\ComitatoSottosede;
use Filament\Resources\Pages\EditRecord;

class EditComitatoLocale extends EditRecord
{
    protected static string $resource = ComitatoLocaleResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . $this->record->nome;
    }

    protected function afterSave(): void
    {
        $this->sincronizzaSottosedi();
    }

    private function sincronizzaSottosedi(): void
    {
        $nomi = array_values(array_filter(array_map('trim', $this->data['sottosedi_nomi'] ?? [])));

        // Inserisce nuove sottosedi
        foreach ($nomi as $nome) {
            ComitatoSottosede::firstOrCreate([
                'comitato_locale_id' => $this->record->id,
                'nome'               => $nome,
            ]);
        }

        // Rimuove quelle non più presenti
        $this->record->sottosedi()
            ->whereNotIn('nome', $nomi)
            ->delete();
    }
}
