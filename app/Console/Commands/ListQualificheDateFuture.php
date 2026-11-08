<?php

namespace App\Console\Commands;

use App\Models\QualificaSocio;
use Illuminate\Console\Command;

class ListQualificheDateFuture extends Command
{
    protected $signature   = 'qualifiche-soci:date-future';
    protected $description = 'Elenca le qualifiche soci con data di acquisizione o ultimo rinnovo nel futuro (probabile errore di battitura in un import GAIA)';

    public function handle(): int
    {
        $righe = QualificaSocio::with(['socio', 'catalogoCorso'])
            ->where(function ($q) {
                $q->whereDate('data_acquisizione', '>', now())
                    ->orWhereDate('data_ultimo_rinnovo', '>', now());
            })
            ->get();

        if ($righe->isEmpty()) {
            $this->info('Nessuna data nel futuro trovata.');
            return self::SUCCESS;
        }

        $this->table(
            ['Socio', 'CF', 'Corso', 'Data acquisizione', 'Data ultimo rinnovo', 'Scadenza calcolata'],
            $righe->map(fn (QualificaSocio $qs) => [
                $qs->socio->cognome . ' ' . $qs->socio->nome,
                $qs->socio->codice_fiscale,
                $qs->catalogoCorso->codice,
                $qs->data_acquisizione?->toDateString(),
                $qs->data_ultimo_rinnovo?->toDateString(),
                $qs->scadenza_calcolata?->toDateString(),
            ])
        );

        $this->newLine();
        $this->warn("{$righe->count()} qualifiche con data nel futuro — da correggere manualmente dal pannello Qualifiche.");

        return self::SUCCESS;
    }
}
