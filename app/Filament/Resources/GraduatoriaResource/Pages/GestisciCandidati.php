<?php

namespace App\Filament\Resources\GraduatoriaResource\Pages;

use App\Filament\Resources\GraduatoriaResource;
use App\Filament\Resources\GraduatoriaResource\RelationManagers\CandidatiRelationManager;
use App\Models\EmailTemplate;
use App\Models\Graduatoria;
use App\Services\TemplatePlaceholders;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ManageRelatedRecords;
use Illuminate\Support\HtmlString;

class GestisciCandidati extends ManageRelatedRecords
{
    protected static string $resource = GraduatoriaResource::class;

    protected static string $relationship = 'candidati';

    protected static ?string $title = 'Gestione';

    public function getTitle(): string
    {
        return 'Gestione — ' . $this->getRecord()->titolo;
    }

    // ⚠️ ManageRelatedRecords implementa HasTable: il layout Filament di default
    // omette <x-filament-actions::modals /> per queste pagine (vedi component
    // page/index.blade.php), quindi i modal delle header action (es. anteprimaEmail)
    // non si aprono mai senza una view custom che lo includa esplicitamente.
    protected string $view = 'filament.resources.graduatoria-resource.pages.gestisci-candidati';

    public function getSubheading(): ?string
    {
        /** @var Graduatoria $record */
        $record = $this->getRecord();
        return $record->corso?->nome;
    }

    public function getBreadcrumb(): string
    {
        return 'Gestione';
    }

    public function getRelationManagers(): array
    {
        return [
            CandidatiRelationManager::class,
        ];
    }

    // Il pulsante userscript (installaUserscriptGaia) resta fuori da qui e viene renderizzato
    // manualmente nella view custom, in una riga dedicata sotto titolo/sottotitolo (vedi
    // gestisci-candidati.blade.php) — resta risolvibile come
    // {{ $this->nomeAzione }} grazie a ResolvesDynamicLivewireProperties (Filament\Schemas\
    // Concerns), che per un nome non ancora in cache cerca un metodo "{nome}Action" sulla
    // classe. ⚠️ Quella stessa scorciatoia funziona SOLO per metodi che restituiscono una
    // singola Action (reflection su Action::class) — un ActionGroup (azioniAction() sotto)
    // NON è una Action e va per forza qui, nel vero getHeaderActions(), altrimenti Livewire
    // lancia PropertyNotFoundException (bug reale, 2026-08-18).
    protected function getHeaderActions(): array
    {
        return [
            $this->azioniAction(),
        ];
    }

    public function modificaGraduatoriaAction(): Action
    {
        return Action::make('modificaGraduatoria')
            ->label('Modifica graduatoria')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->url(fn () => GraduatoriaResource::getUrl('edit', ['record' => $this->getRecord()]));
    }

    public function copiaLinkAction(): Action
    {
        return Action::make('copiaLink')
            ->label('Copia link iscrizione')
            ->icon('heroicon-o-link')
            ->color('gray')
            ->extraAttributes(function () {
                /** @var Graduatoria $graduatoria */
                $graduatoria = $this->getRecord();
                $url         = route('graduatoria.mostra', $graduatoria->codice_pubblico);

                return [
                    'x-on:click.prevent.stop' => 'window.navigator.clipboard.writeText(' . \Illuminate\Support\Js::from($url) . ");"
                        . " \$tooltip('Link copiato!', { theme: \$store.theme, timeout: 2000 })",
                ];
            });
    }

    public function anteprimaEmailAction(): Action
    {
        return Action::make('anteprimaEmail')
            ->label('Anteprima email di conferma')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->modalHeading('Anteprima email — ' . $this->getRecord()->titolo)
            ->modalContent(function () {
                /** @var Graduatoria $graduatoria */
                $graduatoria = $this->getRecord();
                $template    = EmailTemplate::risolvi($graduatoria->comitato_regionale_id, 'graduatoria');

                if (! $template) {
                    return new HtmlString(
                        '<div style="padding:20px; color:#6b7280; text-align:center;">
                            Nessun template email configurato per questa regione.<br>
                            <a href="' . \App\Filament\Resources\EmailTemplateResource::getUrl('create') . '" style="color:#2563eb;">Crea un template</a>
                        </div>'
                    );
                }

                $primoCandidato = $graduatoria->candidati()->first();
                $soggetto       = TemplatePlaceholders::anteprimaPerGraduatoria($template->soggetto, $graduatoria);
                $corpo          = TemplatePlaceholders::anteprimaPerGraduatoria($template->corpo, $graduatoria);
                $comitatoNome   = $graduatoria->comitatoLocale?->nome ?? $graduatoria->comitatoRegionale?->nome ?? 'Comitato organizzatore';
                $haIscritti     = $primoCandidato !== null;
                $nomeIscritto   = $primoCandidato ? trim($primoCandidato->cognome . ' ' . $primoCandidato->nome) : '';

                return view('filament.resources.corso-resource.partials.anteprima-email', compact(
                    'soggetto', 'corpo', 'comitatoNome', 'haIscritti', 'nomeIscritto'
                ));
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi')
            ->slideOver();
    }

    /**
     * Unico bottone "Installa userscript" per il bridge GAIA su questa pagina (ricerca ID +
     * import batch qualifiche da graduatoria). Deliberatamente
     * qui e non in CandidatiRelationManager::headerActions(): quella tabella è caricata in
     * async da Livewire, quindi il bottone comparirebbe nel DOM dopo che lo userscript ha già
     * cercato (e non trovato) [data-userscript-install] al document-idle — a questo livello di
     * pagina il render è invece sincrono col resto dell'header.
     *
     * ⚠️ Un solo bottone, non due (fix 2026-08-18): prima
     * c'erano due Action separate ("Cerca ID" + "Importa batch"), ciascuna col proprio link
     * "Installa" — se lo userscript non era ancora installato/attivo comparivano due inviti a
     * scaricare *lo stesso file*, ridondante e confuso. Ora c'è un solo
     * data-userscript-install="lurch-gaia-graduatoria-hub": se non installato resta un solo
     * "Installa"; se la versione non combacia diventa un solo "Aggiorna vX→vY"; se tutto ok lo
     * userscript lo sostituisce con i DUE bottoni funzionanti (vedi
     * aggiungiPulsantiPaginaCandidati() nello userscript, che ora chiama gestisciBottoneInstalla
     * con una creaBottoneFn che restituisce un wrapper con entrambi i bottoni dentro, non più
     * gestisciGruppoBottoniInstalla — quella pensata per più link "Installa" indipendenti,
     * qui non serve più avendone uno solo).
     */
    public function installaUserscriptGaiaAction(): Action
    {
        return Action::make('installaUserscriptGaia')
            ->label('Installa userscript GAIA')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('info') // scarica/apre una risorsa esterna
            // Soglia di ruolo puoUsareGaiaBridge() + scoping territoriale puoGestire() sulla
            // graduatoria.
            ->visible(fn () => $this->getRecord()->puoGestire(auth()->user()) && auth()->user()?->puoUsareGaiaBridge())
            ->tooltip('Scarica e installa (Tampermonkey) lo userscript che automatizza la ricerca ID GAIA e l\'import in blocco delle qualifiche dal curriculum GAIA dei candidati')
            ->extraAttributes(['data-userscript-install' => 'lurch-gaia-graduatoria-hub'])
            ->url(asset('tampermonkey/lurch-gaia-bridge.user.js'))
            ->openUrlInNewTab();
    }

    public function anteprimaExcelAction(): Action
    {
        return Action::make('anteprimaExcel')
            ->label('Anteprima Excel')
            ->icon('heroicon-o-table-cells')
            ->color('info')
            ->url(fn () => route('graduatorie.candidati-anteprima', $this->getRecord()))
            ->openUrlInNewTab();
    }

    public function scaricaExcelAction(): Action
    {
        return Action::make('scaricaExcel')
            ->label('Scarica Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('info')
            ->disabled(fn () => $this->getRecord()->haCandidatiNonCollegati())
            ->tooltip(fn () => $this->getRecord()->haCandidatiNonCollegati()
                ? 'Ci sono candidati non collegati a un socio esistente: completare i collegamenti prima di esportare'
                : null)
            ->url(fn () => route('graduatorie.candidati-excel', $this->getRecord()));
    }

    /**
     * Azioni "normali" (non legate allo userscript GAIA) raggruppate in un unico
     * ActionGroup, come da convenzione header-actions ("2+ azioni
     * → un solo ActionGroup, trigger sempre color('info')"). installaUserscriptGaia resta
     * fuori e loose: deve comparire nel DOM in modo sincrono e cliccabile per lo userscript
     * "LURCH - GAIA Bridge", che lo cerca via [data-userscript-install] — dentro un
     * ActionGroup a tendina non sarebbe affidabilmente trovabile/cliccabile (vedi
     * feedback_bottoni_userscript_mai_lazy).
     */
    public function azioniAction(): ActionGroup
    {
        return ActionGroup::make([
            $this->modificaGraduatoriaAction(),
            $this->copiaLinkAction(),
            $this->anteprimaEmailAction(),
            $this->anteprimaExcelAction(),
            $this->scaricaExcelAction(),
        ])->label('Azioni')->icon('heroicon-o-ellipsis-vertical')->color('info')->button();
    }
}
