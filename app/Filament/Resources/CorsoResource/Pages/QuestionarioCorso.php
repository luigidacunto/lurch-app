<?php

namespace App\Filament\Resources\CorsoResource\Pages;

use App\Filament\Resources\CorsoResource;
use App\Models\Corso;
use App\Models\Iscrizione;
use App\Models\QuestionarioGradimento;
use App\Questionari\Domande;
use App\Questionari\InvioQuestionario;
use App\Questionari\PermessiQuestionario;
use App\Questionari\StatisticheQuestionario;
use App\Traits\UsaPostaRegionale;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;

/**
 * Questionario di gradimento del corso: configurazione (bloccata al primo invio), scelta dei destinatari, invio,
 * numero di compilazioni (mai chi ha compilato), risultati da StatisticheQuestionario::SOGLIA ed export.
 * Accesso: PermessiQuestionario::puoGestire (admin nel territorio, direttore del corso; mai gli operatori).
 */
class QuestionarioCorso extends Page
{
    use UsaPostaRegionale;

    protected static string $resource = CorsoResource::class;

    protected string $view = 'filament.resources.corso-resource.pages.questionario-corso';

    protected static ?string $title = 'Questionario di gradimento';

    #[Locked]
    public Corso $record;

    /** @var array<int> iscrizioni spuntate per il prossimo invio */
    public array $selezionati = [];

    public function getTitle(): string
    {
        $protocollo = $this->record->numero_protocollo;

        return $protocollo ? 'Questionario — ' . $protocollo : 'Questionario di gradimento';
    }

    public static function canAccess(array $parameters = []): bool
    {
        // Chiamata automatica di Filament senza argomenti: il controllo vero è in mount().
        if (! array_key_exists('record', $parameters)) {
            return true;
        }

        return $parameters['record'] instanceof Corso && PermessiQuestionario::puoGestire(auth()->user(), $parameters['record']);
    }

    public function mount(Corso $record): void
    {
        abort_unless(static::canAccess(['record' => $record]), 403);

        $this->record = $record->load(['evento', 'direttore', 'esame', 'questionarioGradimento']);
        $this->selezionati = $this->iscrizioniInvitabili()
            ->reject(fn (Iscrizione $i) => $i->isRitirato())
            ->pluck('id')->all();
    }

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return $this->record;
    }

    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    public function questionario(): ?QuestionarioGradimento
    {
        return $this->record->questionarioGradimento;
    }

    /** Tutti gli iscritti, con lo stato utile alla scelta (ordinati per cognome). */
    public function iscrizioni(): Collection
    {
        return $this->record->iscrizioni()->with('partecipante', 'corso.esame')->get()
            ->sortBy(fn (Iscrizione $i) => mb_strtolower(($i->partecipante?->cognome ?? '') . ' ' . ($i->partecipante?->nome ?? '')))
            ->values();
    }

    /** Data dell'email già inviata per iscrizione. */
    public function invitati(): array
    {
        return $this->questionario()?->inviti()->pluck('inviato_il', 'iscrizione_id')->all() ?? [];
    }

    /** Iscritti che possono ancora ricevere l'email: con email e mai invitati. */
    public function iscrizioniInvitabili(): Collection
    {
        $invitati = $this->invitati();

        return $this->iscrizioni()->filter(fn (Iscrizione $i) => $i->partecipante?->email && ! isset($invitati[$i->id]))->values();
    }

    private function selezionate(): Collection
    {
        $ids = array_map('intval', $this->selezionati);

        return $this->iscrizioniInvitabili()->filter(fn (Iscrizione $i) => in_array($i->id, $ids, true))->values();
    }

    /** Avviso prima dell'invio: selezionati ammessi senza test né esito pubblicati (non avrebbero la frase sull'esame). */
    public function avvisoEsame(): int
    {
        return InvioQuestionario::senzaRisultatiPubblicati($this->record, $this->selezionate());
    }

    public function motivoBlocco(): ?string
    {
        return InvioQuestionario::motivoBlocco($this->record, $this->questionario());
    }

    protected function getHeaderActions(): array
    {
        $nonDisponibile = fn () => ! ($this->questionario()?->eInviato() && StatisticheQuestionario::visibili($this->questionario()));
        $motivo = 'Disponibile da ' . StatisticheQuestionario::SOGLIA . ' compilazioni';

        return [
            ActionGroup::make([
                Action::make('esporta_excel')
                    ->label('Excel delle risposte')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->disabled($nonDisponibile)
                    ->tooltip(fn () => $nonDisponibile() ? $motivo : null)
                    ->url(fn () => route('corsi.questionario-excel', $this->record)),
                Action::make('pdf_corso')
                    ->label('PDF di riepilogo del corso')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->disabled($nonDisponibile)
                    ->tooltip(fn () => $nonDisponibile() ? $motivo : null)
                    ->url(fn () => route('corsi.questionario-pdf', $this->record))
                    ->openUrlInNewTab(),
            ])->label('Azioni')->icon('heroicon-o-ellipsis-vertical')->color('info')->button(),
        ];
    }

    public function configuraAction(): Action
    {
        return Action::make('configura')
            ->label(fn () => $this->questionario() ? 'Modifica impostazioni' : 'Imposta il questionario')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('success')
            ->disabled(fn () => (bool) $this->questionario()?->eInviato())
            ->tooltip(fn () => $this->questionario()?->eInviato() ? 'Impostazioni bloccate dal primo invio' : null)
            ->modalHeading('Impostazioni del questionario')
            ->modalDescription('Le sezioni facoltative compaiono solo se spuntate qui. Dopo il primo invio le impostazioni non si modificano più.')
            ->fillForm(fn () => [
                'giorni_apertura' => $this->questionario()?->giorni_apertura ?? 7,
                'sezioni'         => $this->questionario()?->sezioni ?? [],
            ])
            ->schema([
                Forms\Components\TextInput::make('giorni_apertura')
                    ->label('Giorni di apertura')
                    ->helperText('Il questionario si chiude alle 23:59 del giorno «invio + giorni».')
                    ->integer()->minValue(1)->maxValue(90)->required(),
                Forms\Components\CheckboxList::make('sezioni')
                    ->label('Sezioni facoltative')
                    ->options(Domande::FLAG),
            ])
            ->modalSubmitActionLabel('Salva')
            ->action(function (array $data): void {
                if ($this->questionario()?->eInviato()) {
                    return;
                }
                QuestionarioGradimento::updateOrCreate(['corso_id' => $this->record->id], [
                    'versione'        => Domande::VERSIONE_CORRENTE,
                    'giorni_apertura' => (int) $data['giorni_apertura'],
                    'sezioni'         => array_values(array_intersect(array_keys(Domande::FLAG), $data['sezioni'] ?? [])),
                ]);
                $this->record->load('questionarioGradimento');
                Notification::make()->title('Impostazioni salvate')->success()->send();
            });
    }

    public function inviaAction(): Action
    {
        return Action::make('invia')
            ->label(fn () => $this->questionario()?->eInviato() ? 'Invia ai nuovi selezionati' : 'Invia il questionario')
            ->icon('heroicon-o-paper-airplane')
            ->color('brand_pink')
            ->disabled(fn () => $this->motivoBlocco() !== null || $this->selezionate()->isEmpty())
            ->tooltip(fn () => $this->motivoBlocco() ?? ($this->selezionate()->isEmpty() ? 'Nessun destinatario selezionato' : null))
            ->requiresConfirmation()
            ->modalHeading('Invia il questionario')
            ->modalDescription(function () {
                $n = $this->selezionate()->count();
                $msg = "Verrà inviata un'email a {$n} " . ($n === 1 ? 'iscritto' : 'iscritti') . '. Chi la riceve non potrà riceverla di nuovo.';
                if (! $this->questionario()?->eInviato()) {
                    $msg .= ' Con il primo invio le impostazioni si bloccano e il questionario resta aperto fino al '
                        . now()->addDays($this->questionario()?->giorni_apertura ?? 0)->format('d/m/Y') . '.';
                }
                if ($senza = $this->avvisoEsame()) {
                    $msg .= " ATTENZIONE: {$senza} " . ($senza === 1 ? 'discente non ha' : 'discenti non hanno')
                        . " ancora né il test scritto né l'esito dell'esame pubblicati: la loro email non citerà i risultati dell'esame. Se è una svista, pubblica prima i risultati.";
                }

                return $msg;
            })
            ->modalSubmitActionLabel('Invia')
            ->action(function (): void {
                if (! $posta = $this->postaRegionale($this->record->evento->comitato_regionale_id)) {
                    return;
                }
                try {
                    $esito = InvioQuestionario::invia($this->record, $this->questionario(), auth()->user(), $this->selezionate(), $posta);
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }
                $this->record->load('questionarioGradimento');
                $this->selezionati = [];
                Notification::make()->title("Questionario inviato a {$esito['inviati']} iscritti.")->success()->send();
            });
    }
}
