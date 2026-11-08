<?php

namespace App\Filament\Resources\PrototipoSubNavResource\Pages;

use App\Filament\Resources\PrototipoSubNavResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class StaffTab extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PrototipoSubNavResource::class;

    protected string $view = 'filament.resources.prototipo-sub-nav-resource.pages.tab';

    protected static ?string $title = 'Staff';

    public string $descrizione = 'Qui oggi in "Gestione Staff" reale ci sarebbe la tabella staff. In questo prototipo conta il fatto che il tab "Staff" resti evidenziato come attivo: la sub-nav sa sempre dove ti trovi, senza dover colorare a mano nessun pulsante.';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }
}
