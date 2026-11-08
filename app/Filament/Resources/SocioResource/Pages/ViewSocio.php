<?php

namespace App\Filament\Resources\SocioResource\Pages;

use App\Filament\Resources\QualificheSociResource\RelationManagers\QualificheRelationManager;
use App\Filament\Resources\SocioResource;
use App\Filament\Resources\SocioResource\RelationManagers\RitiriGraduatorieRelationManager;
use App\Models\OpzioneAlimentare;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSocio extends ViewRecord
{
    protected static string $resource = SocioResource::class;

    public function getTitle(): string
    {
        return parent::getTitle() . ' — ' . $this->record->cognome . ' ' . $this->record->nome;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->azioniAction(),
            $this->installaUserscriptGaiaAction(),
        ];
    }

    /**
     * "Modifica" + "Apri curriculum GAIA" raggruppate in un unico ActionGroup, come da
     * convenzione header-actions ("2+ azioni → un solo ActionGroup,
     * trigger sempre color('info')") — corretto 2026-08-18, erano rimaste sciolte per svista.
     * installaUserscriptGaia resta fuori dal gruppo apposta: deve comparire nel DOM in modo
     * sincrono e cliccabile per lo userscript "LURCH - GAIA Bridge" (stessa eccezione già
     * applicata su GestisciCandidati::azioniAction()).
     */
    public function azioniAction(): ActionGroup
    {
        return ActionGroup::make([
            EditAction::make()
                ->label('Modifica')
                ->visible(fn () => SocioResource::canEdit($this->record)),

            $this->apriCurriculumGaiaAction(),
        ])->label('Azioni')->icon('heroicon-o-ellipsis-vertical')->color('info')->button();
    }

    /** Stessa soglia di ruolo di QualificheRelationManager::canCreate() — chi può gestire le qualifiche del socio. */
    protected function puoGestireQualifiche(): bool
    {
        $user = auth()->user();
        return $user?->isSuperadmin()
            || $user?->isAdmin()
            || $user?->isAdminLocale()
            || $user?->can('qualifiche.manage');
    }

    /**
     * Link passivo verso il profilo curriculum GAIA del socio — nessuna azione dello userscript,
     * solo un `openUrlInNewTab()` (stesso pattern di `apriCurriculumGaia` in
     * `CandidatiRelationManager`/`CandidatiEsclusiRelationManager`). Sempre visibile finché la
     * soglia GAIA è raggiunta, ma `->disabled()` + tooltip (non `->visible()`) quando il socio
     * non ha ancora un `gaia_persona_id` — "non applicabile ora", non "non autorizzato", quindi
     * niente sparizione del bottone (regola disabled+tooltip).
     */
    public function apriCurriculumGaiaAction(): Action
    {
        return Action::make('apriCurriculumGaia')
            ->label('Apri curriculum GAIA')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('info') // apre una risorsa esterna, nessun side effect
            ->visible(fn () => $this->puoGestireQualifiche() && auth()->user()?->puoUsareGaiaBridge())
            ->disabled(fn () => blank($this->record->gaia_persona_id))
            ->tooltip(fn () => filled($this->record->gaia_persona_id) ? null : 'Nessun ID GAIA collegato a questo socio')
            ->url(fn () => filled($this->record->gaia_persona_id)
                ? "https://gaia.cri.it/profilo/{$this->record->gaia_persona_id}/curriculum/"
                : null)
            ->openUrlInNewTab();
    }

    /**
     * Deliberatamente qui in header, non dentro QualificheRelationManager (pannello Filament
     * lazy, $isLazy=true): il link "Installa userscript" generato da Filament resta cliccabile
     * col suo href originale finché lo userscript non lo sostituisce (dopo un fetch asincrono di
     * controllo versione), e su un pannello lazy compare tardi — un click nella finestra prima
     * della sostituzione naviga davvero al file .user.js, che Tampermonkey intercetta mostrando
     * la sua schermata di installazione/reinstallazione invece di eseguire il tool (bug reale
     * riscontrato più volte, v1.10.9 dello userscript). L'header della pagina è invece renderizzato
     * in modo sincrono col resto — stesso motivo/pattern già usato da
     * GestisciCandidati::installaUserscriptGaiaAction().
     *
     * ⚠️ Un solo bottone, non due (fix 2026-08-18, stessa richiesta esplicita già applicata su
     * GestisciCandidati): prima "Importa CV GAIA" e "Cerca ID GAIA" erano due Action separate,
     * mutuamente esclusive per `->visible()` in base a `gaia_persona_id` — comodo finché
     * bastava una delle due funzioni alla volta, ma servono entrambe sempre disponibili
     * (es. correggere un ID sbagliato senza dover prima svuotare il campo). Ora un solo
     * data-userscript-install="lurch-gaia-socio-hub": resta un solo "Installa" (o "Aggiorna
     * vX→vY") finché lo userscript non è attivo/allineato, poi diventa i due bottoni
     * funzionanti insieme — vedi creaBottoniPaginaSocio() nello userscript.
     */
    public function installaUserscriptGaiaAction(): Action
    {
        return Action::make('installaUserscriptGaia')
            ->label('Installa userscript GAIA')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('info') // scarica/apre una risorsa esterna
            ->visible(fn () => $this->puoGestireQualifiche() && auth()->user()?->puoUsareGaiaBridge())
            ->tooltip('Scarica e installa (Tampermonkey) lo userscript che automatizza la ricerca ID GAIA e l\'import delle qualifiche dal curriculum GAIA di questo socio')
            ->extraAttributes([
                'data-userscript-install' => 'lurch-gaia-socio-hub',
                'data-gaia-persona-id'    => $this->record->gaia_persona_id,
                'data-socio-id'           => $this->record->id,
                'data-cf'                 => $this->record->codice_fiscale,
            ])
            ->url(asset('tampermonkey/lurch-gaia-bridge.user.js'))
            ->openUrlInNewTab();
    }

    public function getRelationManagers(): array
    {
        return [
            QualificheRelationManager::class,
            RitiriGraduatorieRelationManager::class,
        ];
    }

    public function infolist(Schema $infolist): Schema
    {
        $allergeniOptions    = OpzioneAlimentare::allergeni()->pluck('etichetta', 'nome');
        $intolleranzeOptions = OpzioneAlimentare::intolleranze()->pluck('etichetta', 'nome');
        $dieteOptions        = OpzioneAlimentare::diete()->pluck('etichetta', 'nome');
        $esclusioniOptions   = OpzioneAlimentare::esclusioni()->pluck('etichetta', 'nome');

        $hasEstensione = fn () => $this->record->comitato_locale_estensione_id !== null
            || $this->record->comitato_regionale_estensione_id !== null;
        $hasDipendente = fn () => $this->record->comitato_locale_dipendente_id !== null
            || $this->record->comitato_regionale_dipendente_id !== null;

        return $infolist->columns(2)->schema([

            // ── Riga 1: Anagrafica | Contatti ────────────────────────────────
            Section::make('Anagrafica')->schema([
                TextEntry::make('cognome')->label('Cognome'),
                TextEntry::make('nome')->label('Nome'),
                TextEntry::make('codice_fiscale')
                    ->label('Codice Fiscale')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('CF copiato'),
                TextEntry::make('data_nascita')
                    ->label('Data di nascita')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextEntry::make('volontario_da')
                    ->label('Volontario dal')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextEntry::make('ruolo_associativo')
                    ->label('Ruolo associativo')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'volontario'            => 'Volontario',
                        'dipendente'            => 'Dipendente',
                        'infermiere_volontarie' => 'Infermiere Volontarie CRI',
                        'corpo_militare'        => 'Corpo Militare Volontario CRI',
                        default                 => '—',
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'volontario'            => 'success',
                        'dipendente'            => 'info',
                        'infermiere_volontarie' => 'warning',
                        'corpo_militare'        => 'danger',
                        default                 => 'gray',
                    }),
            ])->columns(3)->columnSpan(1),

            Section::make('Contatti')->schema([
                TextEntry::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->copyable(),
                TextEntry::make('telefono')
                    ->label('Cellulare principale')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $state) return '—';
                        return ($record->prefisso_telefono ?? '+39') . ' ' . $state;
                    })
                    ->placeholder('—'),
                TextEntry::make('telefoni_secondari')
                    ->label('Cellulari aggiuntivi')
                    ->formatStateUsing(fn ($state) => collect($state ?? [])->implode(', '))
                    ->placeholder('Nessuno')
                    ->columnSpanFull(),
            ])->columns(2)->columnSpan(1),

            // ── Riga 2: Appartenenze (full width) ────────────────────────────
            Section::make('Appartenenze')->schema([
                TextEntry::make('_appartenenze')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->html()
                    ->state(function () {
                        $r = $this->record;
                        $r->loadMissing([
                            'comitatoLocale', 'comitatoRegionale', 'sottosedeAppartenenza',
                            'comitatoLocaleEstensione', 'comitatoRegionaleEstensione', 'sottosedeEstensione',
                            'comitatoLocaleDipendente', 'comitatoRegionaleDipendente', 'sottosedeDipendente',
                        ]);

                        $regione = fn ($rel) => $rel
                            ? preg_replace('/^Comitato Regionale\s*/i', '', $rel->nome)
                            : null;

                        $riga = function ($comitato, $reg, $sottosede = null) use ($regione) {
                            $parts = array_filter([
                                $comitato?->nome,
                                $regione($reg),
                            ]);
                            $html = '<div style="margin-bottom:2px;">' . e(implode(' · ', $parts)) . '</div>';
                            if ($sottosede) {
                                $html .= '<div style="font-size:12px;color:#6b7280;margin-bottom:2px;">' . e($sottosede->nome) . '</div>';
                            }
                            return $html;
                        };

                        $hasEstensione = $r->comitato_locale_estensione_id || $r->comitato_regionale_estensione_id;
                        $hasDipendente = $r->comitato_locale_dipendente_id || $r->comitato_regionale_dipendente_id;

                        $html  = '<div style="line-height:1.6;">';

                        $html .= '<div style="font-weight:700;font-size:13px;margin-bottom:4px;">Volontario</div>';
                        $html .= $riga($r->comitatoLocale, $r->comitatoRegionale, $r->sottosedeAppartenenza);

                        if ($hasEstensione) {
                            $html .= '<div style="font-weight:600;font-size:11px;margin-top:8px;margin-bottom:2px;color:#374151;text-transform:uppercase;letter-spacing:.04em;">Estensione</div>';
                            $html .= $riga($r->comitatoLocaleEstensione, $r->comitatoRegionaleEstensione, $r->sottosedeEstensione);
                        }

                        if ($hasDipendente) {
                            $html .= '<hr style="margin:10px 0;border:none;border-top:1px solid #e5e7eb;">';
                            $html .= '<div style="font-weight:700;font-size:13px;margin-bottom:4px;">Dipendente</div>';
                            $html .= $riga($r->comitatoLocaleDipendente, $r->comitatoRegionaleDipendente, $r->sottosedeDipendente);
                        }

                        $html .= '</div>';

                        return new \Illuminate\Support\HtmlString($html);
                    }),
            ])->columnSpan(2),

            // ── Riga 3: Patenti CRI (full width) ─────────────────────────────
            Section::make('Patenti CRI')->schema([
                TextEntry::make('_patenti')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->html()
                    ->state(function () {
                        $this->record->loadMissing('patenti');
                        $patenti = $this->record->patenti->sortBy('ordine');

                        if ($patenti->isEmpty()) {
                            return 'Nessuna patente CRI registrata';
                        }

                        $data = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
                        $fonte = function ($f) {
                            $label = match ($f) {
                                'importazione_gaia' => 'GAIA',
                                'admin'             => 'Admin',
                                'importazione'      => 'Import',
                                default             => $f ?: '—',
                            };
                            $colore = $f === 'importazione_gaia'
                                ? 'background:#dcfce7;color:#166534;'
                                : 'background:#f1f5f9;color:#475569;';

                            return '<span style="display:inline-block;padding:1px 7px;border-radius:9px;font-size:11px;' . $colore . '">' . e($label) . '</span>';
                        };
                        $td = 'padding:4px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top;';

                        $righe = $patenti->map(fn ($p) => '<tr>'
                            . '<td style="' . $td . 'white-space:nowrap;font-weight:600;">Tipo ' . e($p->tipologia) . '</td>'
                            . '<td style="' . $td . 'color:#6b7280;">' . e($p->descrizione) . '</td>'
                            . '<td style="' . $td . 'white-space:nowrap;">' . $data($p->pivot->data_rilascio) . '</td>'
                            . '<td style="' . $td . 'white-space:nowrap;">' . $data($p->pivot->data_scadenza) . '</td>'
                            . '<td style="' . $td . 'white-space:nowrap;font-family:monospace;">' . e($p->pivot->protocollo ?: '—') . '</td>'
                            . '<td style="' . $td . 'white-space:nowrap;">' . $fonte($p->pivot->fonte) . '</td>'
                            . '</tr>')->implode('');

                        $th = 'padding:4px 10px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;border-bottom:1px solid #e5e7eb;';

                        return new \Illuminate\Support\HtmlString(
                            '<div style="overflow-x:auto;"><table style="border-collapse:collapse;width:100%;font-size:13px;">'
                            . '<thead><tr>'
                            . '<th style="' . $th . '">Tipo</th><th style="' . $th . '">Descrizione</th>'
                            . '<th style="' . $th . '">Rilascio</th><th style="' . $th . '">Scadenza</th>'
                            . '<th style="' . $th . '">Protocollo</th><th style="' . $th . '">Fonte</th>'
                            . '</tr></thead><tbody>' . $righe . '</tbody></table></div>'
                        );
                    }),
            ])->columnSpan(2),

            // ── Riga 4: Gestione pasti in evento — solo per chi ha il permesso ─
            // Ex "Salute": rinominato perché diete/esclusioni sono scelte personali, non salute;
            // il dato serve alla cucina/al fornitore pasti. Ordine per
            // importanza pratica come nel form pubblico ("Allergie e regime alimentare"):
            // allergeni → intolleranze → regime → esclusioni → note libere in fondo.
            // Il campo `allergie` (allergie mediche non alimentari) non è più raccolto da nessun
            // form: rimosso dalla vista (colonna DB intatta).
            Section::make('Gestione pasti in evento')
                ->description('Comunicati alla cucina o al fornitore pasti durante gli eventi')
                ->schema([
                    TextEntry::make('_pasti')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->html()
                        ->state(function () use ($allergeniOptions, $intolleranzeOptions, $dieteOptions, $esclusioniOptions) {
                            $r = $this->record;
                            $diete = collect($r->diete ?? []);

                            $categorie = [
                                ['Allergeni',         collect($r->allergeni ?? [])->map(fn ($k) => $allergeniOptions->get($k, $k))],
                                ['Intolleranze',      $diete->filter(fn ($k) => $intolleranzeOptions->has($k))->map(fn ($k) => $intolleranzeOptions->get($k))],
                                ['Regime alimentare', $diete->filter(fn ($k) => $dieteOptions->has($k))->map(fn ($k) => $dieteOptions->get($k))],
                                ['Esclusioni',        $diete->filter(fn ($k) => $esclusioniOptions->has($k))->map(fn ($k) => $esclusioniOptions->get($k))],
                            ];

                            $lbl  = 'flex:0 0 130px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;padding-top:3px;';
                            $pill = 'display:inline-block;padding:2px 8px;border-radius:9px;font-size:12px;background:#eef2ff;color:#3730a3;';
                            $riga = fn ($label, $inner) => '<div style="display:flex;gap:12px;padding:5px 0;border-bottom:1px solid #f1f5f9;">'
                                . '<div style="' . $lbl . '">' . e($label) . '</div><div style="min-width:0;">' . $inner . '</div></div>';

                            $html = collect($categorie)
                                ->filter(fn ($c) => $c[1]->isNotEmpty())
                                ->map(fn ($c) => $riga(
                                    $c[0],
                                    '<div style="display:flex;flex-wrap:wrap;gap:4px;">'
                                    . $c[1]->map(fn ($v) => '<span style="' . $pill . '">' . e($v) . '</span>')->implode('')
                                    . '</div>'
                                ))
                                ->implode('');

                            if (filled($r->note_alimentari)) {
                                $html .= $riga('Note libere', '<span style="font-size:13px;color:#374151;white-space:pre-wrap;">' . e($r->note_alimentari) . '</span>');
                            }

                            return $html === ''
                                ? 'Nessuna esigenza alimentare registrata'
                                : new \Illuminate\Support\HtmlString('<div style="font-size:13px;">' . $html . '</div>');
                        }),
                ])->columnSpan(2)->visible(fn () => SocioResource::canViewSalute()),

        ]);
    }
}
