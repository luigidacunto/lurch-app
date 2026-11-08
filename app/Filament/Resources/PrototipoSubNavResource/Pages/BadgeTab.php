<?php

namespace App\Filament\Resources\PrototipoSubNavResource\Pages;

use App\Filament\Resources\PrototipoSubNavResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class BadgeTab extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PrototipoSubNavResource::class;

    protected string $view = 'filament.resources.prototipo-sub-nav-resource.pages.tab';

    protected static ?string $title = 'Badge & stampe';

    public string $descrizione = 'Qui in header restano SOLO azioni operative reali (stampa, export, invio) — la navigazione è già fuori, nei tab qui sopra. Simulate 5 azioni operative (oggi ce ne sono 2, ma cresceranno) per dimostrare che regge anche sotto crescita: 1 primaria sciolta ("Badge Staff", la più usata), le altre 4 in "Altre azioni" — stesso ActionGroup già usato nelle tabelle, applicato qui solo ad azioni operative, non più mescolato con la navigazione.';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('badge_staff')->label('Badge Staff')->icon('heroicon-o-identification')->color('warning'),

            ActionGroup::make([
                Action::make('badge_vuoti')->label('Badge vuoti')->icon('heroicon-o-rectangle-stack')->color('gray'),
                Action::make('esporta_excel')->label('Esporta Excel')->icon('heroicon-o-table-cells')->color('gray'),
                Action::make('anteprima_pdf')->label('Anteprima PDF')->icon('heroicon-o-document')->color('gray'),
                Action::make('invia_riepilogo')->label('Invia riepilogo email')->icon('heroicon-o-envelope')->color('gray'),
            ])
                ->label('Altre azioni')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }
}
