<?php

namespace App\Console\Commands;

use App\Models\Iscrizione;
use App\Models\Presenza;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPresenzeEvento extends Command
{
    protected $signature = 'presenze:backfill {--dry-run : Mostra cosa verrebbe inserito senza salvare}';

    protected $description = 'Popola presenze_evento dai dati JSONB esistenti in risposte_preregistrazione';

    private const TIPI_VALIDI = ['pranzo', 'cena', 'pernotto', 'colazione'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $iscrizioni = Iscrizione::with(['corso.evento', 'rispostaPreregistrazione'])
            ->whereHas('rispostaPreregistrazione')
            ->get();

        $this->info("Iscrizioni con risposta: {$iscrizioni->count()}");

        $inserite   = 0;
        $saltate    = 0;
        $duplicate  = 0;

        DB::beginTransaction();

        try {
            foreach ($iscrizioni as $iscrizione) {
                $risposta = $iscrizione->rispostaPreregistrazione;
                $risposte = $risposta->risposte ?? [];
                $corso    = $iscrizione->corso;
                $evento   = $corso?->evento;

                if (! $evento) {
                    $this->warn("  iscrizione #{$iscrizione->id}: nessun evento trovato — saltata");
                    $saltate++;
                    continue;
                }

                foreach ($risposte as $chiave => $valore) {
                    if (! $valore) continue;

                    $parti = explode('_', $chiave, 2);
                    if (count($parti) !== 2) continue;

                    [$tipo, $data] = $parti;

                    if (! in_array($tipo, self::TIPI_VALIDI, true)) continue;

                    // Valida formato data
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) continue;

                    if ($dryRun) {
                        $this->line("  [dry-run] evento={$evento->id} partecipante={$iscrizione->partecipante_id} tipo={$tipo} data={$data}");
                        $inserite++;
                        continue;
                    }

                    $result = Presenza::updateOrCreate(
                        [
                            'evento_id'       => $evento->id,
                            'partecipante_id' => $iscrizione->partecipante_id,
                            'tipo'            => $tipo,
                            'data'            => $data,
                        ],
                        [
                            'iscrizione_id' => $iscrizione->id,
                            'fonte'         => 'form',
                        ]
                    );

                    $result->wasRecentlyCreated ? $inserite++ : $duplicate++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info("Dry-run completato — {$inserite} presenze verrebbero inserite.");
            } else {
                DB::commit();
                $this->info("Backfill completato — {$inserite} inserite, {$duplicate} già esistenti, {$saltate} iscrizioni saltate.");
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Errore: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
