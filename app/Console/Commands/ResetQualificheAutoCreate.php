<?php

namespace App\Console\Commands;

use App\Models\CatalogoCorso;
use App\Models\QualificaSocio;
use Illuminate\Console\Command;

class ResetQualificheAutoCreate extends Command
{
    protected $signature   = 'qualifiche-soci:reset-auto-create {--corso= : Codice catalogo corso, per limitare il reset a una sola qualifica base}';
    protected $description = 'Elimina le qualifiche soci create automaticamente dalla cascata "crea base se assente" durante import';

    public function handle(): int
    {
        $query = QualificaSocio::where('fonte', 'creata_da_aggiornamento');

        $codiceCorso = $this->option('corso');
        if ($codiceCorso) {
            $corso = CatalogoCorso::where('codice', strtoupper(trim($codiceCorso)))->first();
            if (! $corso) {
                $this->error("Nessun corso trovato con codice \"{$codiceCorso}\".");
                return self::FAILURE;
            }
            $query->where('catalogo_corso_id', $corso->id);
            $this->info("Ambito limitato al corso: [{$corso->codice}] {$corso->nome}");
        }

        $totale = $query->count();

        if ($totale === 0) {
            $this->info('Nessuna qualifica creata automaticamente trovata. Nessuna operazione eseguita.');
            return self::SUCCESS;
        }

        $this->warn("⚠  Stai per eliminare {$totale} qualifiche create automaticamente dalla cascata di aggiornamento.");
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

        $eliminate = $query->delete();

        $this->info("✓ Eliminate {$eliminate} qualifiche create automaticamente.");
        $this->info('  Rilanciando l\'import Excel verranno ricreate se la cascata è ancora configurata.');

        return self::SUCCESS;
    }
}
