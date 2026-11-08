<?php

namespace App\Filament\Resources\CorsoResource\Pages;

use App\Filament\Resources\CorsoResource;
use App\Traits\HasGaiaPaste;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCorso extends EditRecord
{
    use HasGaiaPaste;
    protected static string $resource = CorsoResource::class;

    public function getTitle(): string
    {
        $protocollo = $this->record->numero_protocollo;

        return $protocollo ? parent::getTitle() . ' — ' . $protocollo : parent::getTitle();
    }

    /**
     * Inserisce "Gestione corso" come livello intermedio del breadcrumb (Corsi → Gestione
     * corso → Modifica) — sostituisce il pulsante header "Modifica corso" ora spostato
     * nella sub-nav/ActionGroup di GestioneCorso, stesso pattern di EditEvento.
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = $this->getResourceBreadcrumbs();
        $breadcrumbs[CorsoResource::getUrl('gestione', ['record' => $this->record])] = $this->record->numero_protocollo ?: $this->record->nome;
        $breadcrumbs[] = $this->getBreadcrumb();

        return $breadcrumbs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('anteprima_form')
                ->label('Anteprima form')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn () => $this->record->iscrizioni()->first()
                    ? route('form.show', $this->record->iscrizioni()->first()->token) . '?preview=1'
                    : null
                )
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->iscrizioni()->exists()
                    && $this->record->form_sections
                    && count($this->record->form_sections) > 0
                ),
        ];
    }
}
