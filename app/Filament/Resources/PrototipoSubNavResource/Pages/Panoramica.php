<?php

namespace App\Filament\Resources\PrototipoSubNavResource\Pages;

use App\Filament\Resources\PrototipoSubNavResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class Panoramica extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PrototipoSubNavResource::class;

    protected string $view = 'filament.resources.prototipo-sub-nav-resource.pages.tab';

    protected static ?string $title = 'Panoramica';

    public string $descrizione = 'Questa è la pagina "principale" del record. La navigazione verso le altre pagine correlate (Staff, Badge) non è più un pulsante nell\'header: è la barra di tab qui sopra, generata da Filament stesso via Resource::getRecordSubNavigation(). L\'header resta libero per le sole azioni operative.';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }
}
