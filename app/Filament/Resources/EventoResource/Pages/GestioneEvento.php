<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use App\Mail\InfoEventoIscritto;
use App\Models\ConflittoIscrizione;
use App\Models\EmailTemplate;
use App\Models\Evento;
use App\Models\OspiteEvento;
use App\Models\Socio;
use App\Models\Presenza;
use App\Services\BadgeConfigResolver;
use App\Services\GrigliaPastiBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Mail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GestioneEvento extends Page
{
    protected static string $resource = EventoResource::class;

    protected string $view = 'filament.resources.evento-resource.pages.gestione-evento';

    protected static ?string $title = 'Gestione evento';

    public Evento $record;

    public function getTitle(): string
    {
        return 'Gestione evento — ' . $this->record->nome;
    }

    public static function canAccess(array $parameters = []): bool
    {
        // Filament's own mountCanAuthorizeAccess() hook calls canAccess() with
        // zero arguments on every mount (Filament\Pages\Concerns\CanAuthorizeAccess) —
        // that call must stay a no-op (true), the real per-record check only runs
        // explicitly via abort_unless() in mount() below, which always passes 'record'.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }

        $record = $parameters['record'];

        return $record instanceof Evento && EventoResource::canEdit($record);
    }

    public int   $conflittiCount     = 0;
    public array $conflittiDettaglio = [];

    /** Righe riepilogo pasti (pranzo/cena) con effettivo da presenze_evento */
    public array $righePresenze  = [];

    /** Righe pernotti per notte con effettivo da presenze_evento */
    public array $pernotti       = [];

    /** Lista nominativa pernottati (deduplicata per partecipante) */
    public array $listaPernottati = [];

    /** Totali aggregati: pasti, pernotti, colazioni */
    public array $totali = [];

    /** Colonne della griglia admin (slot tipo_data) */
    public array $grigliaSlots = [];

    /** Righe della griglia admin (una per partecipante) */
    public array $grigliaRighe = [];

    /** Statistiche aggregate iscritti + staff */
    public array $stats = [];

    /** Lista ospiti censiti per l'evento */
    public array $ospiti = [];

    /**
     * Necessari perché la tab-bar (EventoResource::getRecordSubNavigation()) si genera solo
     * se la pagina espone getRecord() — normalmente arriva dal trait InteractsWithRecord, non
     * usato qui perché questa pagina risolve $record via binding diretto Laravel nel proprio
     * mount(), non tramite il meccanismo record-resolution di Filament. Senza questi due
     * metodi Page::getSubNavigation() (il default, non quello del trait) ritorna [] sempre:
     * la tab-bar risulterebbe vuota senza errori, non un crash — così si è manifestato il bug.
     */
    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return $this->record;
    }

    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    public function mount(Evento $record): void
    {
        abort_unless(static::canAccess(['record' => $record]), 403);

        $this->record = $record->load([
            'corsi.iscrizioni.partecipante',
            'corsi.iscrizioni.rispostaPreregistrazione',
            'referenti',
            'staff.partecipante',
            'staff.rispostaPreregistrazione',
        ]);

        $this->caricaConflitti();
        $this->caricaStats();
        $this->caricaPresenze();
        $this->caricaGrigliaAdmin();
        $this->caricaOspiti();
    }

    /** Corsi non annullati: base per tutti i conteggi previsti/pianificazione (pasti, pernotti,
     *  statistiche). Un corso annullato non conta nei previsti — le presenze effettive non
     *  richiedono questo filtro perché un corso annullato non genera check-in. */
    private function corsiConsiderati(): \Illuminate\Support\Collection
    {
        return $this->record->corsi->whereNull('annullato_at');
    }

    private function caricaStats(): void
    {
        $iscrizioni = $this->corsiConsiderati()->flatMap(fn ($c) => $c->iscrizioni->where('stato', '!=', 'ritirato'));
        $staff      = $this->record->staff;

        $iscrittiPids = $iscrizioni->pluck('partecipante_id')->unique();
        $staffPids    = $staff->pluck('partecipante_id')->unique();

        $this->stats = [
            // Visione globale
            'partecipanti_unici'   => $iscrittiPids->merge($staffPids)->unique()->count(),

            // Iscrizioni ai corsi (possono contenere la stessa persona su più corsi)
            'iscrizioni_totale'    => $iscrizioni->count(),
            'iscritti_inviati'     => $iscrizioni->whereNotNull('form_inviato_at')->count(),
            'iscritti_compilati'   => $iscrizioni->filter(fn ($i) => $i->rispostaPreregistrazione !== null)->count(),
            'iscritti_senza_email' => $iscrizioni->filter(fn ($i) => ! $i->partecipante?->email)
                                        ->pluck('partecipante_id')->unique()->count(),

            // Staff (una riga per persona, nessun duplicato)
            'staff_totale'         => $staff->count(),
            'staff_inviati'        => $staff->whereNotNull('form_inviato_at')->count(),
            'staff_compilati'      => $staff->filter(fn ($i) => $i->rispostaPreregistrazione !== null)->count(),
            'staff_senza_email'    => $staff->filter(fn ($i) => ! $i->partecipante?->email)->count(),

            // Staff che sono anche iscritti ad almeno un corso
            'staff_anche_iscritti' => $staffPids->intersect($iscrittiPids)->count(),
        ];
    }

    private function caricaConflitti(): void
    {
        $iscrizioneIds = $this->record->corsi
            ->flatMap(fn ($c) => $c->iscrizioni->pluck('id'));

        if ($iscrizioneIds->isEmpty()) {
            return;
        }

        $conflitti = ConflittoIscrizione::where(function ($q) use ($iscrizioneIds) {
                $q->whereIn('iscrizione_a_id', $iscrizioneIds)
                  ->orWhereIn('iscrizione_b_id', $iscrizioneIds);
            })
            ->with([
                'partecipante:id,nome,cognome,codice_fiscale,email',
                'corsoA:id,nome,evento_id,giorni_presenza',
                'corsoA.evento:id,nome,giorni_evento',
                'corsoB:id,nome,evento_id,giorni_presenza',
                'corsoB.evento:id,nome,giorni_evento',
            ])
            ->get()
            ->sortBy(fn ($c) => $c->partecipante?->cognome . ' ' . $c->partecipante?->nome);

        $this->conflittiCount = $conflitti->unique('partecipante_id')->count();

        $this->conflittiDettaglio = $conflitti->map(fn ($c) => [
            'partecipante_id' => $c->partecipante?->id,
            'partecipante'    => trim(($c->partecipante?->cognome ?? '') . ' ' . ($c->partecipante?->nome ?? '')),
            'codice_fiscale'  => $c->partecipante?->codice_fiscale ?? '',
            'email'           => $c->partecipante?->email ?? '',
            'corso_a_nome'  => $c->corsoA?->nome ?? '—',
            // Giorni in presenza in comune (la sovrapposizione si valuta su quelli, non sui range)
            'corso_a_date'  => $c->corsoA && $c->corsoB ? $c->corsoA->giorniInPresenzaInComune($c->corsoB)->map(fn ($d) => $d->format('d/m/Y'))->implode(', ') : '',
            'corso_a_event' => $c->corsoA?->evento?->nome ?? '',
            'corso_b_nome'  => $c->corsoB?->nome ?? '—',
            'corso_b_date'  => $c->corsoA && $c->corsoB ? $c->corsoA->giorniInPresenzaInComune($c->corsoB)->map(fn ($d) => $d->format('d/m/Y'))->implode(', ') : '',
            'corso_b_event' => $c->corsoB?->evento?->nome ?? '',
        ])->values()->all();
    }

    private function caricaPresenze(): void
    {
        // ── Slot da form_sections dei corsi ─────────────────────────────────────
        // $potenzialeIds: chiave => [partecipante_id => true] per deduplicare iscritti+staff
        $slotMap       = [];
        $potenzialeIds = [];

        $tipoLabelPrefix = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];
        foreach ($this->corsiConsiderati() as $corso) {
            foreach ($corso->form_sections ?? [] as $sezione) {
                if (($sezione['attivo'] ?? true) === false) continue;
                $chiave = $sezione['tipo'] . '_' . $sezione['data'];
                if (! isset($slotMap[$chiave])) {
                    $dayLabel = \Carbon\Carbon::parse($sezione['data'])->translatedFormat('l j F');
                    $label    = ($tipoLabelPrefix[$sezione['tipo']] ?? ucfirst($sezione['tipo'])) . ' · ' . $dayLabel;
                    $slotMap[$chiave]       = ['tipo' => $sezione['tipo'], 'data' => $sezione['data'], 'label' => $label, 'potenziale' => 0];
                    $potenzialeIds[$chiave] = [];
                }
                foreach ($corso->iscrizioni->where('stato', '!=', 'ritirato') as $iscr) {
                    $potenzialeIds[$chiave][$iscr->partecipante_id] = true;
                }
            }
        }

        // ── Slot staff (pranzo/cena/pernotto/colazione per ogni giorno evento) ──
        $staffIds = $this->record->staff->pluck('partecipante_id')->all();
        if (count($staffIds) > 0) {
            foreach (\App\Services\EventoSezioniGenerator::perGiorni($this->record) as $s) {
                $chiave = $s['chiave'];
                if (! isset($slotMap[$chiave])) {
                    $slotMap[$chiave]       = ['tipo' => $s['tipo'], 'data' => $s['data'], 'label' => $s['label'], 'potenziale' => 0];
                    $potenzialeIds[$chiave] = [];
                }
                foreach ($staffIds as $pid) {
                    $potenzialeIds[$chiave][$pid] = true;
                }
            }
        }

        // Calcola potenziale come cardinalità dell'insieme unico partecipante_id
        foreach ($slotMap as $chiave => &$slot) {
            $slot['potenziale'] = count($potenzialeIds[$chiave] ?? []);
        }
        unset($slot);

        // ── Carica presenze evento (anticipato per scoprire slot extra admin) ───
        $presenze = Presenza::where('evento_id', $this->record->id)
            ->with(['partecipante:id,nome,cognome,codice_fiscale,email,prefisso_telefono,telefono',
                    'iscrizione.corso:id,nome'])
            ->get();

        // ── Aggiungi colonne per presenze admin fuori dal range/form_sections base ─
        $tipoLabels = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];
        foreach ($presenze as $p) {
            $chiave = $p->tipo . '_' . $p->data->format('Y-m-d');
            if (! isset($slotMap[$chiave])) {
                $dayLabel        = $p->data->translatedFormat('l j F');
                $slotMap[$chiave] = [
                    'tipo'       => $p->tipo,
                    'data'       => $p->data->format('Y-m-d'),
                    'label'      => ($tipoLabels[$p->tipo] ?? ucfirst($p->tipo)) . ' · ' . $dayLabel,
                    'potenziale' => 0,
                ];
            }
        }

        if (empty($slotMap)) {
            $this->totali = ['pasti' => ['pot' => 0, 'eff' => 0], 'pernotti' => ['pot' => 0, 'eff' => 0], 'colazioni' => ['pot' => 0, 'eff' => 0]];
            return;
        }

        // ── Mappa unificata: partecipante_id => allergie/diete/note mergiate ───────
        // Unisce tutte le risposte dello stesso socio (iscrizioni multiple + staff)
        $rispostePerPersona = [];
        $mergeRisposta = function (int $pid, $risposta) use (&$rispostePerPersona): void {
            if (! $risposta || ! $risposta->consenso_salute_accettata) return;
            if (! isset($rispostePerPersona[$pid])) {
                $rispostePerPersona[$pid] = ['allergeni' => [], 'diete' => [], 'note' => ''];
            }
            $r = &$rispostePerPersona[$pid];
            $r['allergeni'] = array_values(array_unique(array_merge($r['allergeni'], $risposta->risposte['allergeni'] ?? [])));
            $r['diete']     = array_values(array_unique(array_merge($r['diete'],     $risposta->risposte['diete']     ?? [])));
            $nota = trim($risposta->risposte['note_extra'] ?? '');
            if ($nota && ! str_contains($r['note'], $nota)) {
                $r['note'] = $r['note'] ? $r['note'] . ' / ' . $nota : $nota;
            }
        };
        foreach ($this->corsiConsiderati() as $corso) {
            foreach ($corso->iscrizioni->where('stato', '!=', 'ritirato') as $iscr) {
                $mergeRisposta($iscr->partecipante_id, $iscr->rispostaPreregistrazione);
            }
        }
        foreach ($this->record->staff as $iscr) {
            $mergeRisposta($iscr->partecipante_id, $iscr->rispostaPreregistrazione);
        }

        // Effettivi: persone UNICHE per slot (deduplicato per partecipante_id)
        $effettiviPerChiave = $presenze
            ->groupBy(fn ($p) => $p->tipo . '_' . $p->data->format('Y-m-d'))
            ->map(fn ($group) => $group->pluck('partecipante_id')->unique()->count());

        // Mappa per partecipante (prima presenza trovata porta i dati anagrafici)
        $presenzePerPartecipante = $presenze->groupBy('partecipante_id');
        // Cache anagrafica: prima presenza per partecipante
        $anagraficaMap = $presenzePerPartecipante->map(fn ($g) => $g->first()->partecipante);

        // ── Righe pasti e pernotti ───────────────────────────────────────────────
        $ordTipo       = ['pranzo' => 1, 'cena' => 2];
        $righePresenze = [];
        $pernotti      = [];

        foreach ($slotMap as $chiave => $slot) {
            $effettivo = $effettiviPerChiave->get($chiave, 0);

            if ($slot['tipo'] === 'pernotto') {
                $pernotti[$chiave] = array_merge($slot, ['effettivo' => $effettivo]);
                continue;
            }

            if (! in_array($slot['tipo'], ['pranzo', 'cena'], true)) continue;

            // Persone UNICHE presenti a questo pasto
            $pidsPresenti = $presenze
                ->filter(fn ($p) => $p->tipo === $slot['tipo'] && $p->data->format('Y-m-d') === $slot['data'])
                ->pluck('partecipante_id')
                ->unique();

            $persone = GrigliaPastiBuilder::persone($pidsPresenti, $anagraficaMap, $rispostePerPersona);

            $righePresenze[$chiave] = array_merge($slot, [
                'effettivo'          => $effettivo,
                'persone'            => $persone,
                'personeConEsigenze' => GrigliaPastiBuilder::conEsigenze($persone),
            ]);
        }

        // ── Colazione: mai un dato dichiarato a sé, si deduce dal pernotto effettivo
        // della notte precedente — una riga per ogni giorno reale con almeno una
        // persona che ha davvero pernottato la notte prima.
        $giorniEventoSet = $this->record->giorniEvento()->map(fn ($d) => $d->format('Y-m-d'))->flip();
        foreach ($pernotti as $pernottoSlot) {
            if (($pernottoSlot['effettivo'] ?? 0) === 0) continue;

            $dataColazione = \Carbon\Carbon::parse($pernottoSlot['data'])->addDay()->format('Y-m-d');
            if (! isset($giorniEventoSet[$dataColazione])) continue;

            $chiaveColazione = 'colazione_' . $dataColazione;
            if (isset($righePresenze[$chiaveColazione])) continue;

            $pidsPernotto = $presenze
                ->filter(fn ($p) => $p->tipo === 'pernotto' && $p->data->format('Y-m-d') === $pernottoSlot['data'])
                ->pluck('partecipante_id')
                ->unique();

            $persone = GrigliaPastiBuilder::persone($pidsPernotto, $anagraficaMap, $rispostePerPersona);

            $righePresenze[$chiaveColazione] = [
                'tipo'               => 'colazione',
                'data'               => $dataColazione,
                'label'              => 'Colazione · ' . \Carbon\Carbon::parse($dataColazione)->translatedFormat('l j F'),
                'potenziale'         => $pernottoSlot['potenziale'],
                'effettivo'          => $pernottoSlot['effettivo'],
                'persone'            => $persone,
                'personeConEsigenze' => GrigliaPastiBuilder::conEsigenze($persone),
            ];
        }

        $ordTipo['colazione'] = 0;

        uasort($righePresenze, function ($a, $b) use ($ordTipo) {
            $cmp = strcmp($a['data'], $b['data']);
            return $cmp !== 0 ? $cmp : (($ordTipo[$a['tipo']] ?? 5) <=> ($ordTipo[$b['tipo']] ?? 5));
        });
        uasort($pernotti, fn ($a, $b) => strcmp($a['data'], $b['data']));

        // ── Persone per notte (lista nominativa per ciascun pernotto) ────────────
        // Se qualcuno ha presenze sia come corsista che come staff, prevale lo staff
        foreach ($pernotti as &$notte) {
            $notte['persone'] = $presenze
                ->filter(fn ($p) => $p->tipo === 'pernotto' && $p->data->format('Y-m-d') === $notte['data'])
                ->groupBy('partecipante_id')
                ->map(function ($gruppo) {
                    $presenza = $gruppo->firstWhere('iscrizione_id', null) ?? $gruppo->first();
                    return [
                        'nome'              => trim(($presenza->partecipante?->cognome ?? '') . ' ' . ($presenza->partecipante?->nome ?? '')),
                        'email'             => $presenza->partecipante?->email ?? '',
                        'corso'             => $presenza->iscrizione_id === null ? 'Staff evento' : ($presenza->iscrizione?->corso?->nome ?? '—'),
                        'corso_protocollo'  => $presenza->iscrizione_id === null ? null : ($presenza->iscrizione?->corso?->numero_protocollo ?? null),
                        'is_staff'          => $presenza->iscrizione_id === null,
                    ];
                })
                ->sortBy('nome')
                ->values()
                ->all();
        }
        unset($notte);

        // ── Lista nominativa pernottati (aggregata, usata dall'export Excel) ─────
        // Se qualcuno ha presenze sia come corsista che come staff, prevale lo staff
        $listaPernottati = [];
        foreach ($presenzePerPartecipante as $partecipanteId => $pGrup) {
            $nottiGrup = $pGrup->where('tipo', 'pernotto');
            if ($nottiGrup->isEmpty()) continue;

            $primo   = $nottiGrup->firstWhere('iscrizione_id', null) ?? $pGrup->first();
            $p       = $primo->partecipante;
            $isStaff = $primo->iscrizione_id === null;

            $listaPernottati[$partecipanteId] = [
                'cognome'        => $p?->cognome ?? '',
                'nome'           => $p?->nome ?? '',
                'codice_fiscale' => $p?->codice_fiscale ?? '',
                'email'          => $p?->email ?? '',
                'telefono'       => trim(($p?->prefisso_telefono ?? '') . ' ' . ($p?->telefono ?? '')),
                'corso'          => $isStaff ? 'Staff evento' : ($primo->iscrizione?->corso?->nome ?? ''),
                'notti'          => $nottiGrup->pluck('data')->map(fn ($d) => $d->format('Y-m-d'))->unique()->count(),
                'is_staff'       => $isStaff,
            ];
        }

        uasort($listaPernottati, fn ($a, $b) => strcmp($a['cognome'] . ' ' . $a['nome'], $b['cognome'] . ' ' . $b['nome']));

        $this->righePresenze   = array_values($righePresenze);
        $this->pernotti        = array_values($pernotti);
        $this->listaPernottati = array_values($listaPernottati);

        $colazioneRows = array_filter($this->righePresenze, fn ($r) => $r['tipo'] === 'colazione');
        $pastoRows     = array_filter($this->righePresenze, fn ($r) => $r['tipo'] !== 'colazione');

        $this->totali = [
            'pasti'     => ['pot' => array_sum(array_column($pastoRows, 'potenziale')),     'eff' => array_sum(array_column($pastoRows, 'effettivo'))],
            'pernotti'  => ['pot' => array_sum(array_column($this->pernotti, 'potenziale')), 'eff' => array_sum(array_column($this->pernotti, 'effettivo'))],
            'colazioni' => ['pot' => array_sum(array_column($colazioneRows, 'potenziale')),  'eff' => array_sum(array_column($colazioneRows, 'effettivo'))],
        ];
    }

    private function caricaGrigliaAdmin(): void
    {
        $ordTipo  = ['pranzo' => 1, 'cena' => 2, 'pernotto' => 3, 'colazione' => 4];
        $tipoLabelsG = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];
        $slotsMap = [];

        // Colonne da form_sections dei corsi
        foreach ($this->corsiConsiderati() as $corso) {
            foreach (collect($corso->form_sections ?? [])->reject(fn ($s) => ($s['attivo'] ?? true) === false) as $s) {
                $chiave = $s['tipo'] . '_' . $s['data'];
                if (! isset($slotsMap[$chiave])) {
                    $label = $s['label'] ?? (($tipoLabelsG[$s['tipo']] ?? ucfirst($s['tipo'])) . ' · ' . \Carbon\Carbon::parse($s['data'])->translatedFormat('l j F'));
                    $slotsMap[$chiave] = ['chiave' => $chiave, 'tipo' => $s['tipo'], 'data' => $s['data'], 'label' => $label];
                }
            }
        }

        // Colonne da sezioni staff (pasti/pernotti per ogni giorno evento) — correzione
        // operatore, sempre sbloccata indipendentemente da Evento::residenziale.
        if ($this->record->staff->isNotEmpty()) {
            foreach (\App\Services\EventoSezioniGenerator::perGiorni($this->record, bypassResidenziale: true) as $s) {
                if (! isset($slotsMap[$s['chiave']])) {
                    $slotsMap[$s['chiave']] = $s;
                }
            }
        }

        // Scopri colonne extra da presenze admin fuori dal range/form_sections base
        $presenzeAll = Presenza::where('evento_id', $this->record->id)
            ->with('partecipante:id,nome,cognome')
            ->get();

        $tipoLabelsG = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];
        foreach ($presenzeAll as $p) {
            $chiave = $p->tipo . '_' . $p->data->format('Y-m-d');
            if (! isset($slotsMap[$chiave])) {
                $dayLabel      = $p->data->translatedFormat('l j F');
                $slotsMap[$chiave] = [
                    'chiave' => $chiave,
                    'tipo'   => $p->tipo,
                    'data'   => $p->data->format('Y-m-d'),
                    'label'  => ($tipoLabelsG[$p->tipo] ?? ucfirst($p->tipo)) . ' · ' . $dayLabel,
                ];
            }
        }

        uasort($slotsMap, function ($a, $b) use ($ordTipo) {
            $cmp = strcmp($a['data'], $b['data']);
            return $cmp !== 0 ? $cmp : (($ordTipo[$a['tipo']] ?? 5) <=> ($ordTipo[$b['tipo']] ?? 5));
        });

        $this->grigliaSlots = array_values($slotsMap);

        if (empty($this->grigliaSlots)) {
            $this->grigliaRighe = [];
            return;
        }

        $presenzeMap = $presenzeAll
            ->groupBy('partecipante_id')
            ->map(fn ($group) => $group->mapWithKeys(
                fn ($p) => [$p->tipo . '_' . $p->data->format('Y-m-d') => $p->fonte]
            ));

        // Iscritti ai corsi
        $partecipantiMap = [];
        foreach ($this->corsiConsiderati()->sortBy('data_inizio') as $corso) {
            foreach ($corso->iscrizioni->where('stato', '!=', 'ritirato')->sortBy('partecipante.cognome') as $iscr) {
                $pid = $iscr->partecipante_id;
                if (! isset($partecipantiMap[$pid])) {
                    $p = $iscr->partecipante;
                    $partecipantiMap[$pid] = [
                        'pid'      => $pid,
                        'nome'     => trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? '')),
                        'corso'    => $corso->nome,
                        'is_staff' => false,
                    ];
                }
            }
        }

        // Staff evento come righe aggiuntive
        foreach ($this->record->staff->sortBy('partecipante.cognome') as $iscrStaff) {
            $pid = $iscrStaff->partecipante_id;
            if (! isset($partecipantiMap[$pid])) {
                $p = $iscrStaff->partecipante;
                $partecipantiMap[$pid] = [
                    'pid'      => $pid,
                    'nome'     => trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? '')),
                    'corso'    => 'Staff evento',
                    'is_staff' => true,
                ];
            }
        }

        // Aggiungi eventuali partecipanti presenti solo nella tabella presenze
        // (admin potrebbe aver aggiunto presenze per persone non in iscrizioni/staff)
        foreach ($presenzeAll as $p) {
            $pid = $p->partecipante_id;
            if (isset($partecipantiMap[$pid])) continue;
            $s = $p->partecipante ?? \App\Models\Socio::find($pid);
            $partecipantiMap[$pid] = [
                'pid'      => $pid,
                'nome'     => trim(($s?->cognome ?? '') . ' ' . ($s?->nome ?? '')),
                'corso'    => '—',
                'is_staff' => false,
            ];
        }

        uasort($partecipantiMap, fn ($a, $b) => strcmp($a['nome'], $b['nome']));

        $righe = [];
        foreach ($partecipantiMap as $pid => $info) {
            $celle = [];
            foreach ($this->grigliaSlots as $slot) {
                $celle[$slot['chiave']] = $presenzeMap->get($pid)?->get($slot['chiave']) ?? '';
            }
            $righe[] = array_merge($info, ['celle' => $celle]);
        }

        $this->grigliaRighe = $righe;
    }

    public function togglePresenzaAdmin(int $partecipanteId, string $tipo, string $data): void
    {
        $esistente = Presenza::where('evento_id', $this->record->id)
            ->where('partecipante_id', $partecipanteId)
            ->where('tipo', $tipo)
            ->where('data', $data)
            ->first();

        if ($esistente) {
            if ($esistente->fonte === 'form') {
                Notification::make()
                    ->title('Questa presenza è stata indicata nel form dal partecipante — non può essere rimossa da qui.')
                    ->warning()
                    ->send();
                return;
            }
            $esistente->delete();
        } else {
            // Trova l'iscrizione del partecipante in questo evento (prende la prima)
            $iscrizione = $this->record->corsi
                ->flatMap(fn ($c) => $c->iscrizioni)
                ->firstWhere('partecipante_id', $partecipanteId);

            Presenza::create([
                'evento_id'       => $this->record->id,
                'partecipante_id' => $partecipanteId,
                'iscrizione_id'   => $iscrizione?->id,
                'tipo'            => $tipo,
                'data'            => $data,
                'fonte'           => 'admin',
                'updated_by'      => auth()->id(),
            ]);
        }

        $this->caricaPresenze();
        $this->caricaGrigliaAdmin();
    }

    public function inviaMailSovrapposizioneAction(): Action
    {
        return Action::make('inviaMailSovrapposizione')
            ->label('Invia segnalazione')
            ->modalHeading('Invia mail di segnalazione sovrapposizione')
            ->modalWidth('3xl')
            ->form([
                Forms\Components\TextInput::make('destinatario')
                    ->label('Destinatario (email)')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('oggetto')
                    ->label('Oggetto')
                    ->required(),
                Forms\Components\RichEditor::make('corpo')
                    ->label('Testo del messaggio')
                    ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link'])
                    ->required()
                    ->columnSpanFull(),
            ])
            ->fillForm(function (array $arguments): array {
                $partecipanteId = $arguments['partecipante_id'] ?? null;
                $partecipante   = Socio::find($partecipanteId);

                return [
                    'destinatario' => $partecipante?->email ?? '',
                    'oggetto'      => 'Segnalazione sovrapposizione iscrizioni — ' . $this->record->nome,
                    'corpo'        => $this->buildCorpoSovrapposizione($partecipanteId, $partecipante),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $template    = EmailTemplate::risolvi($this->record->comitato_regionale_id, 'iscritti');
                $fromAddress = $template?->from_address ?? config('mail.from.address');
                $fromName    = $template?->from_name    ?? config('mail.from.name');
                $replyTo     = $template?->reply_to;

                Mail::html(
                    $data['corpo'],
                    function ($m) use ($data, $fromAddress, $fromName, $replyTo) {
                        $m->to($data['destinatario'])
                          ->subject($data['oggetto'])
                          ->from($fromAddress, $fromName);
                        if ($replyTo) {
                            $m->replyTo($replyTo);
                        }
                    }
                );

                Notification::make()
                    ->title('Mail inviata a ' . $data['destinatario'])
                    ->success()
                    ->send();
            });
    }

    private function buildCorpoSovrapposizione(?int $partecipanteId, ?Socio $partecipante): string
    {
        $nome = $partecipante
            ? trim($partecipante->cognome . ' ' . $partecipante->nome)
            : 'Partecipante';

        $righe = collect($this->conflittiDettaglio)
            ->filter(fn ($c) => $c['partecipante_id'] === $partecipanteId)
            ->map(fn ($c) => sprintf(
                '<li><strong>%s</strong> (%s)&nbsp;&nbsp;⇄&nbsp;&nbsp;<strong>%s</strong> (%s)</li>',
                e($c['corso_a_nome']), e($c['corso_a_date']),
                e($c['corso_b_nome']), e($c['corso_b_date'])
            ))
            ->join('');

        return <<<HTML
<p>Gentile {$nome},</p>
<p>ti scriviamo per segnalarti che risulti iscritto/a a corsi con date sovrapposte:</p>
<ul>{$righe}</ul>
<p>Ti chiediamo di contattarci al più presto per valutare insieme come risolvere la situazione.</p>
<p>Cordiali saluti</p>
HTML;
    }

    /** Delega a Evento::hasComunicazioneValida() — unico punto di verità, condiviso con GestioneStaff. */
    public function hasComunicazione(): bool
    {
        return $this->record->hasComunicazioneValida();
    }

    /** Iscritti attivi di un corso che non hanno ancora ricevuto la comunicazione "info evento". */
    public function iscrizioniInfoPendentiPerCorso(\App\Models\Corso $corso): \Illuminate\Support\Collection
    {
        if ($corso->isAnnullato()) {
            return collect();
        }

        return $corso->iscrizioni->filter(fn ($i) => ! $i->isRitirato()
            && $i->rispostaPreregistrazione !== null
            && ! $i->info_evento_inviato_at);
    }

    public function inviaInfoCorsisti(int $corsoId): void
    {
        if (! $this->hasComunicazione()) {
            Notification::make()
                ->title('Nessun contenuto da inviare')
                ->body('Inserisci del testo o almeno un documento allegato nella sezione "Contenuti e documenti" dell\'evento.')
                ->warning()
                ->send();
            return;
        }

        $corso = $this->record->corsi->firstWhere('id', $corsoId);

        if (! $corso) return;

        $corso->load('iscrizioni.partecipante');

        $inviati = 0;

        foreach ($this->iscrizioniInfoPendentiPerCorso($corso) as $iscrizione) {
            $email = $iscrizione->partecipante?->email;
            if (! $email) continue;

            Mail::to($email)->send(new InfoEventoIscritto($iscrizione));
            $iscrizione->update(['info_evento_inviato_at' => now()]);
            $inviati++;
        }

        $corso->update(['invio_massivo_info_at' => now()]);

        // Ricarica per aggiornare i dati in blade
        $this->record->load('corsi.iscrizioni.partecipante');

        Notification::make()
            ->title('Comunicazione inviata')
            ->body("Inviata a {$inviati} iscritti del corso «{$corso->nome}».")
            ->success()
            ->send();
    }

    public function anteprimaInfoEventoAction(): Action
    {
        return Action::make('anteprimaInfoEvento')
            ->label('Anteprima comunicazione')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Anteprima comunicazione evento')
            ->modalWidth('4xl')
            ->modalContent(fn () => new \Illuminate\Support\HtmlString(
                $this->renderAnteprimaInfoEvento($this->record)
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi')
            ->visible(fn () => $this->hasComunicazione());
    }

    /**
     * @param \App\Models\Corso|null $corso se passato (anteprima dalla pagina di un corso) le date mostrate
     *        sono i giorni in presenza di quel corso, come nell'invio reale a un suo iscritto
     *        (InfoEventoIscritto); altrimenti i giorni dell'evento (staff / vista evento).
     */
    public static function renderAnteprimaInfoEvento(\App\Models\Evento $evento, ?\App\Models\Corso $corso = null): string
    {
        $corpo        = $evento->note_app ?? '';
        $nomeEvento   = $evento->nome;
        $comitatoNome = $evento->comitatoLocale?->nome ?? $evento->comitatoRegionale?->nome ?? 'Comitato organizzatore';
        $referenti    = $evento->referenti()->where('attivo', true)->get();
        $documenti    = $evento->getMedia('documenti');

        $intestazioneMedia = $evento->getFirstMedia('intestazione_app');
        $intestazioneSrc   = null;
        if ($intestazioneMedia && file_exists($intestazioneMedia->getPath())) {
            $intestazioneSrc = 'data:' . $intestazioneMedia->mime_type . ';base64,'
                . base64_encode(file_get_contents($intestazioneMedia->getPath()));
        }

        $dateTesto      = \App\Models\Evento::formattaGiorni($corso ? $corso->giorniInPresenza() : $evento->giorniEvento());
        $sede           = $evento->sede;
        $indirizzo      = $evento->indirizzo;
        $coordinateMaps = $evento->coordinate_maps;

        // Anteprima: qui non c'è una Mailable/$message, quindi il QR di check-in va passato
        // come data: URI (renderizzata nell'iframe dell'anteprima anche se i client email
        // la bloccano — nell'invio reale è un'immagine CID via $message->embedData()).
        $allegaBadge         = \App\Services\BadgeConfigResolver::qrCheckinInEmail($evento);
        $qrCheckinPng        = null;
        $qrCheckinPreviewSrc = $allegaBadge
            ? 'data:image/png;base64,' . base64_encode(
                \App\Services\BadgeConfigResolver::qrCheckinPng($evento, 'anteprima-token-non-valido')
            )
            : null;

        $emailHtml = view('emails.info-evento', compact(
            'corpo', 'nomeEvento', 'comitatoNome', 'referenti', 'intestazioneSrc',
            'dateTesto', 'sede', 'indirizzo', 'coordinateMaps',
            'allegaBadge', 'qrCheckinPng', 'qrCheckinPreviewSrc'
        ))->render();

        $out  = '<iframe srcdoc="' . e($emailHtml) . '" ';
        $out .= 'style="width:100%;height:540px;border:1px solid #e5e7eb;border-radius:8px;background:#f3f4f6;" ';
        $out .= 'sandbox="allow-same-origin"></iframe>';

        if ($allegaBadge) {
            $out .= '<div style="margin-top:12px;padding:10px 14px;border:1px solid #fde68a;border-radius:8px;background:#fffbeb;color:#92400e;font-size:12.5px;">'
                . '📲 Ogni destinatario riceve nel corpo della mail il proprio QR di check-in (immagine, non allegato). '
                . 'Il QR qui sopra è un esempio non valido.</div>';
        }

        if ($documenti->isNotEmpty()) {
            $out .= '<div style="margin-top:12px;padding:12px 16px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;font-size:13px;">';
            $out .= '<span style="font-weight:600;color:#374151;">Allegati inclusi nella mail (' . $documenti->count() . '):</span>';
            $out .= '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">';
            foreach ($documenti as $m) {
                $previewUrl = route('eventi.documento-preview', ['evento' => $evento->id, 'media' => $m->id]);
                $out .= '<a href="' . e($previewUrl) . '" target="_blank"'
                    . ' style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border:1px solid #bfdbfe;border-radius:6px;background:#eff6ff;color:#1d4ed8;text-decoration:none;font-size:12px;font-weight:500;">'
                    . '📎 ' . e($m->file_name)
                    . ' <span style="font-size:10px;opacity:.7;">↗</span></a>';
            }
            $out .= '</div></div>';
        }

        return $out;
    }

    private function caricaOspiti(): void
    {
        $this->ospiti = OspiteEvento::where('evento_id', $this->record->id)
            ->withCount('accessi')
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get()
            ->map(fn ($o) => [
                'id'           => $o->id,
                'nome'         => $o->nome,
                'cognome'      => $o->cognome,
                'cf'           => $o->cf,
                'ha_accessi'   => $o->accessi_count > 0,
                'checkin_url'  => route('checkin.ospite', ['evento' => $this->record->id, 'ospite' => $o->id]),
            ])
            ->values()->all();
    }

    public function aggiungiOspiteAction(): Action
    {
        return Action::make('aggiungiOspite')
            ->label('Aggiungi ospite')
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->modalHeading('Aggiungi ospite')
            ->modalWidth('md')
            ->form([
                Forms\Components\TextInput::make('cognome')
                    ->label('Cognome')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('nome')
                    ->label('Nome')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('cf')
                    ->label('Codice Fiscale')
                    ->required()
                    ->maxLength(16)
                    ->live()
                    ->afterStateUpdated(fn ($state, $set) => $set('cf', strtoupper((string) $state))),
            ])
            ->action(function (array $data): void {
                $cf = strtoupper($data['cf']);

                if (OspiteEvento::where('evento_id', $this->record->id)->where('cf', $cf)->exists()) {
                    Notification::make()
                        ->title('CF già registrato')
                        ->body('Un ospite con questo codice fiscale è già presente per questo evento.')
                        ->warning()
                        ->send();
                    return;
                }

                OspiteEvento::create([
                    'evento_id' => $this->record->id,
                    'nome'      => $data['nome'],
                    'cognome'   => $data['cognome'],
                    'cf'        => $cf,
                ]);

                $this->caricaOspiti();

                Notification::make()->title('Ospite aggiunto')->success()->send();
            });
    }

    public function eliminaOspite(int $id): void
    {
        $ospite = OspiteEvento::where('evento_id', $this->record->id)->findOrFail($id);

        if ($ospite->accessi()->exists()) {
            Notification::make()
                ->title('Impossibile eliminare')
                ->body('L\'ospite ha già degli accessi registrati. Usa il reset checkin se necessario.')
                ->warning()
                ->send();
            return;
        }

        $ospite->delete();
        $this->caricaOspiti();

        Notification::make()->title('Ospite eliminato')->success()->send();
    }

    /**
     * Navigazione verso Staff/Candidature ora gestita dalla sub-navigazione
     * (EventoResource::getRecordSubNavigation()) — "Modifica" resta qui perché non è una
     * pagina "sorella" dell'hub, è la modifica diretta del record (stesso standard usato
     * altrove nel progetto); le altre azioni generano un PDF.
     */
    /** Anteprima realistica di come apparirà UN badge stampato, con gli stessi criteri
     *  (posizioni/font/sfondo da badge_config) usati da BadgeCorsistiController/BadgeStaffController
     *  — dati di esempio (nome, protocollo), non un vero iscritto/staff, per non generare un PDF
     *  ogni volta solo per controllare l'impaginazione dopo una modifica ai parametri badge. */
    public function anteprimaBadgeAction(): Action
    {
        return Action::make('anteprimaBadge')
            ->label('Anteprima badge')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->modalHeading('Anteprima badge — ' . $this->record->nome)
            ->modalContent(function () {
                $config = BadgeConfigResolver::resolveConfig($this->record);
                $sfondo = BadgeConfigResolver::sfondoBase64($this->record);
                $qrSrc  = 'data:image/svg+xml;base64,' . base64_encode(
                    QrCode::format('svg')->size(300)->errorCorrection('M')->generate(url('/badge/anteprima'))
                );

                return view('filament.resources.evento-resource.partials.anteprima-badge', [
                    'config'     => $config,
                    'sfondo'     => $sfondo,
                    'qrSrc'      => $qrSrc,
                    'nomeEvento' => $this->record->nome,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi')
            ->slideOver();
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('modifica')
                    ->label('Modifica evento')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->url(fn () => EventoResource::getUrl('edit', ['record' => $this->record])),

                $this->anteprimaBadgeAction(),

                Action::make('badge_staff')
                    ->label('Badge Staff')
                    ->icon('heroicon-o-identification')
                    ->color('info')
                    ->url(fn () => route('eventi.badge-staff', ['evento' => $this->record]))
                    ->openUrlInNewTab(),

                Action::make('badge_vuoti')
                    ->label('Badge vuoti')
                    ->icon('heroicon-o-rectangle-stack')
                    ->color('info')
                    ->url(fn () => route('eventi.badge-vuoti', ['evento' => $this->record]))
                    ->openUrlInNewTab(),

                Action::make('griglia_pasti_pdf')
                    ->label('Griglia pasti PDF')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->url(fn () => route('eventi.pasti-griglia-pdf', ['evento' => $this->record]))
                    ->openUrlInNewTab(),

                Action::make('dettaglio_esigenze_pdf')
                    ->label('Dettaglio esigenze PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn () => route('eventi.pasti-dettaglio-pdf', ['evento' => $this->record]))
                    ->openUrlInNewTab(),

                Action::make('elenco_pasti_excel')
                    ->label('Elenco nominativo pasti Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->url(fn () => route('eventi.pasti-elenco-excel', ['evento' => $this->record])),
            ])
                ->label('Azioni')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('info')
                ->button(),
        ];
    }
}
