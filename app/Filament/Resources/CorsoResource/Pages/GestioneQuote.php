<?php

namespace App\Filament\Resources\CorsoResource\Pages;

use App\Filament\Resources\CorsoResource;
use App\Models\Corso;
use App\Services\SostituzioneDocumento;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;

class GestioneQuote extends Page
{
    protected static string $resource = CorsoResource::class;

    protected string $view = 'filament.resources.corso-resource.pages.gestione-quote';

    protected static ?string $title = 'Gestione quote';

    public Corso $record;

    public function getTitle(): string
    {
        $protocollo = $this->record->numero_protocollo;

        return $protocollo ? 'Gestione quote — ' . $protocollo : 'Gestione quote';
    }

    public function mount(Corso $record): void
    {
        abort_unless($record->pagamento_previsto, 404);

        $this->record = $record->load([
            'evento.comitatoRegionale',
            'evento.comitatoLocale',
            'iscrizioni.partecipante.comitatoLocale',
            'iscrizioni.rispostaPreregistrazione.verificatoDa',
        ]);
    }

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return $this->record;
    }

    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('anteprima_excel')
                    ->label('Anteprima Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->url(fn () => route('corsi.quote-anteprima', $this->record))
                    ->openUrlInNewTab(),

                Action::make('scarica_excel')
                    ->label('Scarica Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn () => route('corsi.quote-excel', $this->record)),

                Action::make('anteprima_pdf')
                    ->label('Anteprima PDF')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn () => route('corsi.quote-pdf', $this->record))
                    ->openUrlInNewTab(),

                Action::make('scarica_pdf')
                    ->label('Scarica PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn () => route('corsi.quote-pdf', ['corso' => $this->record, 'download' => true])),
            ])->label('Azioni')->icon('heroicon-o-ellipsis-vertical')->color('info')->button(),
        ];
    }


    public function verificaPagamentoAction(): Action
    {
        return Action::make('verificaPagamento')
            ->label('Verifica')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Confermi che la ricevuta di {$nome} è valida e il pagamento è stato verificato?";
            })
            ->modalSubmitActionLabel('Verifica')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $risposta = $iscrizione->rispostaPreregistrazione;

                if (! $risposta || ! $risposta->ricevuta_path) {
                    Notification::make()
                        ->title('Nessuna ricevuta allegata — impossibile verificare.')
                        ->warning()
                        ->send();
                    return;
                }

                if (! $risposta->data_pagamento) {
                    Notification::make()
                        ->title('Inserisci la data di pagamento prima di verificare.')
                        ->warning()
                        ->send();
                    return;
                }

                $risposta->update([
                    'pagamento_verificato'       => true,
                    'pagamento_verificato_at'    => now(),
                    'pagamento_verificato_da_id' => Auth::id(),
                ]);

                $this->record->load('iscrizioni.rispostaPreregistrazione.verificatoDa');

                Notification::make()
                    ->title('Pagamento verificato per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome)
                    ->success()
                    ->send();
            });
    }

    public function visualizzaRicevutaAction(): Action
    {
        return Action::make('visualizzaRicevuta')
            ->label('Visualizza')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => 'Ricevuta — ' . ($arguments['nome'] ?? ''))
            ->modalContent(fn (array $arguments) => view('filament.resources.corso-resource.partials.anteprima-documento', [
                'url' => route('iscrizioni.ricevuta-preview', $arguments['iscrizione_id']),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->slideOver();
    }

    /**
     * Sostituzione della ricevuta caricata per errore, consentita solo finché il pagamento
     * non è stato verificato (poi la riga è di sola lettura).
     */
    public function sostituisciRicevutaAction(): Action
    {
        return Action::make('sostituisciRicevuta')
            ->label('Sostituisci')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => 'Sostituisci ricevuta — ' . ($arguments['nome'] ?? ''))
            ->modalDescription('Il file precedente verrà eliminato. Possibile solo finché il pagamento non è verificato.')
            ->form([
                Forms\Components\FileUpload::make('documento')
                    ->label('Nuova ricevuta (JPG, PNG o PDF, max 10 MB)')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('ricevute/sostituzioni')
                    ->required(),
            ])
            ->modalSubmitActionLabel('Sostituisci')
            ->action(function (array $data, array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $risposta   = $iscrizione?->rispostaPreregistrazione;

                if (! $risposta || ! $risposta->ricevuta_path) {
                    Notification::make()->title('Nessuna ricevuta da sostituire.')->warning()->send();
                    return;
                }

                $ok = app(SostituzioneDocumento::class)->sostituisci($risposta, 'ricevuta', $data['documento']);

                $this->record->load('iscrizioni.rispostaPreregistrazione.verificatoDa');

                Notification::make()
                    ->title($ok
                        ? 'Ricevuta sostituita per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome
                        : 'Pagamento già verificato: la ricevuta non è più sostituibile.')
                    ->{$ok ? 'success' : 'warning'}()
                    ->send();
            });
    }

    public function annullaVerificaAction(): Action
    {
        return Action::make('annullaVerifica')
            ->label('Annulla verifica')
            ->icon('heroicon-o-x-mark')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(function (array $arguments) {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                $nome       = trim(($iscrizione?->partecipante?->cognome ?? '') . ' ' . ($iscrizione?->partecipante?->nome ?? ''));
                return "Annullare la verifica del pagamento di {$nome}?";
            })
            ->modalSubmitActionLabel('Annulla verifica')
            ->action(function (array $arguments): void {
                $iscrizione = $this->record->iscrizioni->firstWhere('id', $arguments['iscrizione_id'] ?? null);
                if (! $iscrizione) return;

                $risposta = $iscrizione->rispostaPreregistrazione;
                if (! $risposta) return;

                $risposta->update([
                    'pagamento_verificato'       => false,
                    'pagamento_verificato_at'    => null,
                    'pagamento_verificato_da_id' => null,
                ]);

                $this->record->load('iscrizioni.rispostaPreregistrazione.verificatoDa');

                Notification::make()
                    ->title('Verifica annullata per ' . $iscrizione->partecipante?->cognome . ' ' . $iscrizione->partecipante?->nome)
                    ->warning()
                    ->send();
            });
    }

    public function aggiornaDataPagamento(int $iscrizioneId, ?string $data): void
    {
        $iscrizione = $this->record->iscrizioni->firstWhere('id', $iscrizioneId);
        if (! $iscrizione) return;

        $risposta = $iscrizione->rispostaPreregistrazione;
        if (! $risposta || $risposta->pagamento_verificato) return;

        $risposta->update(['data_pagamento' => $data ?: null]);
        $this->record->load('iscrizioni.rispostaPreregistrazione.verificatoDa');
    }

}
