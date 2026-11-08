<?php

namespace App\Console\Commands;

use App\Models\QualificaSocio;
use Illuminate\Console\Command;

class ResetQualificheSoci extends Command
{
    protected $signature   = 'qualifiche-soci:reset';
    protected $description = 'Elimina TUTTE le qualifiche soci dal DB per permettere un reimport pulito';

    public function handle(): int
    {
        $totale = QualificaSocio::count();

        if ($totale === 0) {
            $this->info('La tabella qualifiche_soci è già vuota. Nessuna operazione eseguita.');
            return self::SUCCESS;
        }

        $this->warn("⚠  Stai per eliminare {$totale} qualifiche soci dal database.");
        $this->warn('   Questa operazione è IRREVERSIBILE senza un backup.');
        $this->newLine();

        if (! $this->confirm('Sei sicuro di voler procedere?')) {
            $this->info('Operazione annullata.');
            return self::SUCCESS;
        }

        $conferma = $this->ask('Digita RESET per confermare');
        if ($conferma !== 'RESET') {
            $this->error('Conferma non valida. Operazione annullata.');
            return self::FAILURE;
        }

        QualificaSocio::truncate();

        $this->info("✓ Eliminate {$totale} qualifiche soci. La tabella è ora vuota.");
        $this->info('  Puoi ora reimportare le qualifiche da zero tramite il pannello admin.');

        return self::SUCCESS;
    }
}
