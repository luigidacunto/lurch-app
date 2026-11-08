<?php

namespace App\Jobs;

use App\Services\SovrapposizioniCorsi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Ricalcola i conflitti di date per un singolo socio.
 * Lanciato in coda dall'observer di Iscrizione e Corso al verificarsi
 * di eventi rilevanti (nuova iscrizione, cancellazione, modifica date/giorni in presenza del corso).
 * La regola di sovrapposizione è in SovrapposizioniCorsi.
 */
class CheckSovrapposizioniSocio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $socioId) {}

    public function handle(): void
    {
        SovrapposizioniCorsi::ricalcola($this->socioId);
    }
}
