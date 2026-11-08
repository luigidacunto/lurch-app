<?php

namespace App\Console\Commands;

use App\Jobs\CheckSovrapposizioniGlobale;
use Illuminate\Console\Command;

class RicalcolaConflitti extends Command
{
    protected $signature   = 'conflitti:ricalcola {--queue : Esegui in coda invece che in modo sincrono}';
    protected $description = 'Ricalcola tutti i conflitti di date tra iscrizioni (backfill iniziale o verifica manuale)';

    public function handle(): int
    {
        if ($this->option('queue')) {
            CheckSovrapposizioniGlobale::dispatch();
            $this->info('Job CheckSovrapposizioniGlobale accodato.');
        } else {
            $this->info('Avvio ricalcolo conflitti (sincrono)...');
            (new CheckSovrapposizioniGlobale())->handle();
            $this->info('Ricalcolo completato.');
        }

        return self::SUCCESS;
    }
}
