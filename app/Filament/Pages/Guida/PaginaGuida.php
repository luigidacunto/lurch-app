<?php

namespace App\Filament\Pages\Guida;

use App\Support\Guida;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Una sezione della guida (docs/<sezione>) nel pannello: indice a sinistra, pagina a destra.
 * Visibile a tutti gli utenti del pannello. ?p= sceglie la pagina, vuoto = README della sezione.
 */
abstract class PaginaGuida extends Page
{
    protected string $view = 'filament.pages.guida';

    protected static \UnitEnum|string|null $navigationGroup = 'Guida';

    /** Cartella della sezione in docs/. */
    protected static string $sezione;

    #[Url]
    public ?string $p = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Scarica PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(route('guida.pdf', static::$sezione)),
        ];
    }

    public function getViewData(): array
    {
        $pagina = Guida::pagina(static::$sezione, $this->p ?: null);
        abort_unless($pagina, 404);

        return [
            'indice' => Guida::indice(static::$sezione),
            'pagina' => $pagina,
        ];
    }
}
