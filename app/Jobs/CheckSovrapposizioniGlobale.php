<?php

namespace App\Jobs;

use App\Models\ConflittoIscrizione;
use App\Models\Iscrizione;
use App\Services\SovrapposizioniCorsi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Ricalcola TUTTI i conflitti di date.
 * Usato:
 *  - dal task notturno (scheduler, ore 02:00)
 *  - dal comando Artisan `conflitti:ricalcola` per il backfill iniziale
 *
 * Strategia: svuota la tabella e rifà il calcolo (SovrapposizioniCorsi, la stessa regola del job
 * incrementale) solo per i soci con almeno 2 iscrizioni. Non più una INSERT...SELECT sui range di
 * date: la sovrapposizione dipende dai giorni in presenza, che una query sui range non vede.
 */
class CheckSovrapposizioniGlobale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minuti — sufficiente per dataset grandi
    public int $tries   = 1;    // Non ha senso riprovare un batch completo

    public function handle(): void
    {
        // Svuota e riscrive in una transazione per coerenza
        DB::transaction(function () {
            ConflittoIscrizione::truncate();

            Iscrizione::query()
                ->whereNotNull('partecipante_id')
                ->groupBy('partecipante_id')
                ->havingRaw('count(*) > 1')
                ->pluck('partecipante_id')
                ->each(fn ($socioId) => SovrapposizioniCorsi::ricalcola($socioId));
        });
    }
}
