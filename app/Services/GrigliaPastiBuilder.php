<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\Presenza;

class GrigliaPastiBuilder
{
    /**
     * Costruisce righePresenze, pernotti e totali per un evento.
     * Usato da GestioneEvento (Livewire) e dai controller PDF.
     *
     * Ogni elemento di righePresenze ha:
     *   tipo, data, label, potenziale, effettivo, personeConEsigenze[]
     *     → nome, cf, allergeni[], diete[], note
     */
    public function build(Evento $evento): array
    {
        $evento->loadMissing([
            'corsi.iscrizioni.rispostaPreregistrazione',
            'staff.rispostaPreregistrazione',
        ]);

        // ── Slot da form_sections dei corsi ─────────────────────────────────────
        // $potenzialeIds: chiave => [partecipante_id => true] per deduplicare iscritti+staff
        $tipoLabelPrefix = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];
        $slotMap       = [];
        $potenzialeIds = [];
        foreach ($evento->corsi as $corso) {
            foreach ($corso->form_sections ?? [] as $sezione) {
                if (($sezione['attivo'] ?? true) === false) continue;
                $chiave = $sezione['tipo'] . '_' . $sezione['data'];
                if (! isset($slotMap[$chiave])) {
                    $dayLabel = \Carbon\Carbon::parse($sezione['data'])->translatedFormat('l j F');
                    $label    = ($tipoLabelPrefix[$sezione['tipo']] ?? ucfirst($sezione['tipo'])) . ' · ' . $dayLabel;
                    $slotMap[$chiave]       = ['tipo' => $sezione['tipo'], 'data' => $sezione['data'], 'label' => $label, 'potenziale' => 0];
                    $potenzialeIds[$chiave] = [];
                }
                foreach ($corso->iscrizioni as $iscr) {
                    $potenzialeIds[$chiave][$iscr->partecipante_id] = true;
                }
            }
        }

        // ── Slot staff ──────────────────────────────────────────────────────────
        $staffIds = $evento->staff->pluck('partecipante_id')->all();
        if (count($staffIds) > 0) {
            foreach (EventoSezioniGenerator::perGiorni($evento) as $s) {
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

        // ── Presenze ────────────────────────────────────────────────────────────
        $presenze = Presenza::where('evento_id', $evento->id)
            ->with(['partecipante:id,nome,cognome,codice_fiscale'])
            ->get();

        // Slot extra aggiunti manualmente dall'admin
        $tipoLabels = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];
        foreach ($presenze as $p) {
            $chiave = $p->tipo . '_' . $p->data->format('Y-m-d');
            if (! isset($slotMap[$chiave])) {
                $slotMap[$chiave] = [
                    'tipo'       => $p->tipo,
                    'data'       => $p->data->format('Y-m-d'),
                    'label'      => ($tipoLabels[$p->tipo] ?? ucfirst($p->tipo)) . ' · ' . $p->data->translatedFormat('l j F'),
                    'potenziale' => 0,
                ];
            }
        }

        if (empty($slotMap)) {
            return [
                'righePresenze' => [],
                'pernotti'      => [],
                'totali'        => ['pasti' => ['pot' => 0, 'eff' => 0], 'pernotti' => ['pot' => 0, 'eff' => 0], 'colazioni' => ['pot' => 0, 'eff' => 0]],
            ];
        }

        // ── Mappa risposte per persona (merge da iscrizioni + staff) ────────────
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

        foreach ($evento->corsi as $corso) {
            foreach ($corso->iscrizioni as $iscr) {
                $mergeRisposta($iscr->partecipante_id, $iscr->rispostaPreregistrazione);
            }
        }
        foreach ($evento->staff as $iscr) {
            $mergeRisposta($iscr->partecipante_id, $iscr->rispostaPreregistrazione);
        }

        // ── Effettivi e anagrafica ──────────────────────────────────────────────
        $effettiviPerChiave = $presenze
            ->groupBy(fn ($p) => $p->tipo . '_' . $p->data->format('Y-m-d'))
            ->map(fn ($g) => $g->pluck('partecipante_id')->unique()->count());

        $anagraficaMap = $presenze->groupBy('partecipante_id')
            ->map(fn ($g) => $g->first()->partecipante);

        // ── Costruzione righe ───────────────────────────────────────────────────
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

            $pidsPresenti = $presenze
                ->filter(fn ($p) => $p->tipo === $slot['tipo'] && $p->data->format('Y-m-d') === $slot['data'])
                ->pluck('partecipante_id')
                ->unique();

            $persone = self::persone($pidsPresenti, $anagraficaMap, $rispostePerPersona);

            $righePresenze[$chiave] = array_merge($slot, [
                'effettivo'          => $effettivo,
                'persone'            => $persone,
                'personeConEsigenze' => self::conEsigenze($persone),
            ]);
        }

        // ── Colazione: mai un dato dichiarato a sé, si deduce dal pernotto effettivo
        // della notte precedente — una riga per ogni giorno reale con almeno una
        // persona che ha davvero pernottato la notte prima (non solo teoricamente disponibile).
        $giorniEventoSet = $evento->giorniEvento()->map(fn ($d) => $d->format('Y-m-d'))->flip();
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

            $persone = self::persone($pidsPernotto, $anagraficaMap, $rispostePerPersona);

            $righePresenze[$chiaveColazione] = [
                'tipo'               => 'colazione',
                'data'               => $dataColazione,
                'label'              => 'Colazione · ' . \Carbon\Carbon::parse($dataColazione)->translatedFormat('l j F'),
                'potenziale'         => $pernottoSlot['potenziale'],
                'effettivo'          => $pernottoSlot['effettivo'],
                'persone'            => $persone,
                'personeConEsigenze' => self::conEsigenze($persone),
            ];
        }

        $ordTipo['colazione'] = 0;

        uasort($righePresenze, function ($a, $b) use ($ordTipo) {
            $cmp = strcmp($a['data'], $b['data']);
            return $cmp !== 0 ? $cmp : (($ordTipo[$a['tipo']] ?? 5) <=> ($ordTipo[$b['tipo']] ?? 5));
        });
        uasort($pernotti, fn ($a, $b) => strcmp($a['data'], $b['data']));

        $righePresenze = array_values($righePresenze);
        $pernotti      = array_values($pernotti);

        $colazioneRows = array_filter($righePresenze, fn ($r) => $r['tipo'] === 'colazione');
        $pastoRows     = array_filter($righePresenze, fn ($r) => $r['tipo'] !== 'colazione');

        $totali = [
            'pasti'     => ['pot' => array_sum(array_column($pastoRows, 'potenziale')),     'eff' => array_sum(array_column($pastoRows, 'effettivo'))],
            'pernotti'  => ['pot' => array_sum(array_column($pernotti, 'potenziale')),      'eff' => array_sum(array_column($pernotti, 'effettivo'))],
            'colazioni' => ['pot' => array_sum(array_column($colazioneRows, 'potenziale')), 'eff' => array_sum(array_column($colazioneRows, 'effettivo'))],
        ];

        return compact('righePresenze', 'pernotti', 'totali');
    }

    /**
     * Elenco nominativo (ordinato) delle persone previste a un pasto, tutte, anche senza esigenze.
     * $pids: partecipante_id uniche con la presenza; $anagraficaMap: partecipante_id => Socio;
     * $rispostePerPersona: esigenze già mergiate per socio (solo con consenso salute accettato).
     * Ogni voce: nome (cognome nome), cf, allergeni[], diete[], note|null.
     * Usato anche da GestioneEvento::caricaPresenze(), che ha la sua copia del calcolo delle righe.
     */
    public static function persone($pids, $anagraficaMap, array $rispostePerPersona): array
    {
        $persone = [];
        foreach ($pids as $pid) {
            $p = $anagraficaMap->get($pid);
            $r = $rispostePerPersona[$pid] ?? ['allergeni' => [], 'diete' => [], 'note' => ''];
            $persone[] = [
                'nome'      => trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? '')),
                'cf'        => $p?->codice_fiscale ?? '',
                'allergeni' => $r['allergeni'],
                'diete'     => $r['diete'],
                'note'      => $r['note'] ?: null,
            ];
        }

        usort($persone, fn ($a, $b) => strcmp($a['nome'], $b['nome']));

        return $persone;
    }

    /** Sottoinsieme di persone() con almeno un allergene, una dieta o una nota. */
    public static function conEsigenze(array $persone): array
    {
        return array_values(array_filter($persone, fn ($p) => $p['allergeni'] || $p['diete'] || $p['note']));
    }
}
