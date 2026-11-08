<?php

namespace App\Filament\Resources\EsameResource\Pages;

use App\Filament\Resources\EsameResource;
use App\Filament\Resources\EsameResource\Concerns\HaSubNavigationEsame;
use App\Models\Esame;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditEsame extends EditRecord
{
    use HaSubNavigationEsame;

    protected static string $resource = EsameResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('titolo')->required()->maxLength(255),
            Forms\Components\TextInput::make('lap')
                ->label('LAP (soglia di idoneità, %)')
                ->helperText('Modificabile finché l\'esame non è chiuso.')
                ->numeric()->minValue(0)->maxValue(100)->required(),
            Forms\Components\TextInput::make('tipo_codice')->label('Tipo')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('modello_versione')->label('Versione del modello')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('stato')
                ->disabled()->dehydrated(false)
                ->formatStateUsing(fn ($state) => EsameResource::etichettaStato($state)),
            Forms\Components\TextInput::make('direttore')
                ->label('Direttore')
                ->disabled()->dehydrated(false)
                ->formatStateUsing(fn () => $this->record->corso->direttore?->nome_completo ?? $this->record->corso->direttore_corso),
        ])->columns(2);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort_unless($this->record->puoModificareConfigurazione(auth()->user()), 403);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless($this->record->fresh()->puoModificareConfigurazione(auth()->user()), 403);
        $data['updated_by'] = auth()->id();

        return $data;
    }

    /** La Modifica è solo per i parametri base: niente sub-nav delle altre sezioni (stanno in Gestione). */
    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getRedirectUrl(): ?string
    {
        return EsameResource::getUrl('gestione', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('riportaInBozza')
                ->label('Riporta in bozza')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->record->stato === Esame::STATO_IN_CORSO)
                ->disabled(fn () => $this->record->motivoNonRiportabileInBozza() !== null)
                ->tooltip(fn () => $this->record->motivoNonRiportabileInBozza())
                ->requiresConfirmation()
                ->modalHeading('Riporta in bozza')
                ->modalDescription('Possibile solo perché non esistono ancora schede, test o esiti. Il modello d\'esame verrà ricopiato alla prossima attivazione.')
                ->modalSubmitActionLabel('Riporta in bozza')
                ->action(function () {
                    $this->record->riportaInBozza();
                    $this->record->refresh();
                    $this->fillForm();
                    Notification::make()->title('Esame riportato in bozza.')->success()->send();
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return static::getResource()::breadcrumbs($this->getRecord(), null, 'Modifica');
    }
}
