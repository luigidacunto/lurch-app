<?php

namespace App\Console\Commands;

use App\Models\Corso;
use App\Models\Iscrizione;
use App\Models\RispostaPreregistrazione;
use Illuminate\Console\Command;

/**
 * Una-tantum. Ripulisce `corsi.form_sections` dalle voci pasto "fantasma": quelle su una
 * data FUORI da `corsi.giorni_presenza` che nessun iscritto ha mai spuntato.
 *
 * Nasce per i corsi già compilati (congelati) prima dell'introduzione di `giorni_presenza`
 * (2026-09-01), che si portano dietro una form_sections gonfia (es. 49 pranzi di cui 44
 * disattivati a mano). I fix a report/check-in già le ignorano, ma restano visibili nel DB.
 *
 * Sicuro per definizione: una voce viene rimossa SOLO se non compare come `true` in nessuna
 * risposta di preregistrazione del corso E si trova in almeno una di queste condizioni:
 *   - la sua data non è tra i giorni in presenza (`giorni_presenza`);
 *   - è `attivo:false` (disattivata a mano dall'operatore — es. "cena ultimo giorno",
 *     che il generatore attuale non produrrebbe comunque).
 * Le voci attive su un giorno valido restano sempre; qualunque voce che qualcuno ha
 * spuntato resta sempre.
 *
 * Dry-run di default. `--apply` per scrivere.
 */
class PulisciFormSectionsCorsi extends Command
{
    protected $signature = 'corsi:pulisci-form-sections {--apply : Applica le modifiche (senza, mostra solo cosa cambierebbe)}';

    protected $description = 'Rimuove dalle form_sections dei corsi le voci pasto su date fuori da giorni_presenza mai selezionate da nessuno (una-tantum, corsi congelati)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $righe = [];
        $totRimosse = 0;

        Corso::with('evento')->orderBy('id')->chunkById(100, function ($corsi) use (&$righe, &$totRimosse) {
            foreach ($corsi as $corso) {
                $sezioni = $corso->form_sections ?? [];
                if (empty($sezioni)) {
                    continue;
                }

                $giorniValidi = collect($corso->giorni_presenza ?? [])->flip();
                $chiaviConRisposta = $this->chiaviConRisposta($corso);

                $daTenere = function ($s) use ($giorniValidi, $chiaviConRisposta) {
                    $chiave = $s['tipo'] . '_' . $s['data'];
                    if (isset($chiaviConRisposta[$chiave])) {
                        return true; // qualcuno l'ha spuntata → sempre
                    }
                    return ($s['attivo'] ?? true) !== false && $giorniValidi->has($s['data']);
                };

                $tenute = array_values(array_filter($sezioni, $daTenere));

                $slotRimossi = collect($sezioni)->reject($daTenere)->map(function ($s) use ($giorniValidi) {
                    $motivo = ($s['attivo'] ?? true) === false ? 'disattivato' : 'giorno non previsto';
                    $d = \Carbon\Carbon::parse($s['data'])->format('d/m');
                    return "{$s['tipo']} {$d} ({$motivo})";
                })->values();

                if ($slotRimossi->isEmpty()) {
                    continue;
                }

                $descrizione = $slotRimossi->count() > 3
                    ? $slotRimossi->take(3)->implode('  ·  ') . "  …  (+" . ($slotRimossi->count() - 3) . ")"
                    : $slotRimossi->implode('  ·  ');

                $righe[] = [
                    $corso->id,
                    \Illuminate\Support\Str::limit($corso->nome, 34),
                    count($sezioni) . ' → ' . count($tenute),
                    $descrizione,
                ];
                $totRimosse += $slotRimossi->count();

                if ($this->option('apply')) {
                    $corso->form_sections = $tenute;
                    $corso->saveQuietly();
                }
            }
        });

        if (empty($righe)) {
            $this->info('Nessun corso da ripulire — tutte le form_sections sono già coerenti con i giorni in presenza.');
            return self::SUCCESS;
        }

        $this->line('Ogni voce = un pasto in un giorno (es. "cena 20/09"). Le date del corso non cambiano,');
        $this->line('sparisce solo il singolo pasto disattivato o su un giorno non previsto che nessuno ha spuntato.');
        $this->newLine();
        $this->table(['ID', 'Corso', 'Voci (prima → dopo)', 'Pasti rimossi'], $righe);
        $this->newLine();

        if ($apply) {
            $this->info(count($righe) . " corsi ripuliti, {$totRimosse} pasti fantasma rimossi.");
        } else {
            $this->warn(count($righe) . " corsi con pasti fantasma ({$totRimosse} in totale). Rilancia con --apply per rimuoverli.");
        }

        return self::SUCCESS;
    }

    /** Chiavi `tipo_data` spuntate a `true` in almeno una risposta di preregistrazione del corso. */
    private function chiaviConRisposta(Corso $corso): array
    {
        $iscrizioneIds = Iscrizione::where('corso_id', $corso->id)->pluck('id');
        if ($iscrizioneIds->isEmpty()) {
            return [];
        }

        $chiavi = [];
        RispostaPreregistrazione::where('iscrizioneable_type', Iscrizione::class)
            ->whereIn('iscrizioneable_id', $iscrizioneIds)
            ->pluck('risposte')
            ->each(function ($risposte) use (&$chiavi) {
                foreach ($risposte ?? [] as $k => $v) {
                    if ($v === true && preg_match('/^(pranzo|cena|colazione|pernotto)_\d{4}-\d{2}-\d{2}$/', $k)) {
                        $chiavi[$k] = true;
                    }
                }
            });

        return $chiavi;
    }
}
