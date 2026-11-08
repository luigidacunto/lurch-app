<?php

namespace App\Filament\Resources\IstruzionePagamentoResource\Pages;

use App\Filament\Resources\IstruzionePagamentoResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditIstruzionePagamento extends EditRecord
{
    protected static string $resource = IstruzionePagamentoResource::class;

    public function getTitle(): string
    {
        $etichetta = $this->record->comitatoRegionale?->nome ?? Str::limit($this->record->testo, 50);

        return $etichetta ? parent::getTitle() . ' — ' . $etichetta : parent::getTitle();
    }
}
