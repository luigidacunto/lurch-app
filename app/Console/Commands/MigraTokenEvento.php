<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\SocioEvento;
use Illuminate\Console\Command;

class MigraTokenEvento extends Command
{
    protected $signature = 'checkin:migra-token-evento
                            {evento : ID dell\'evento}
                            {--force : Salta la conferma interattiva}';

    protected $description = 'Popola badge_tokens_legacy su SocioEvento con tutti i token legacy (Iscrizione/IscrizioneStaff/SocioEvento secondari)';

    public function handle(): int
    {
        $evento = Evento::find($this->argument('evento'));
        if (! $evento) {
            $this->error("Evento con ID {$this->argument('evento')} non trovato.");
            return self::FAILURE;
        }

        // Carica tutti i SocioEvento dell'evento
        $socioEventi = SocioEvento::where('evento_id', $evento->id)
            ->orderBy('partecipante_id')
            ->orderBy('id')
            ->get();

        // Raggruppa per partecipante_id per trovare primary e secondari
        $grouped = $socioEventi->groupBy('partecipante_id');

        // Carica tutti i token da Iscrizione e IscrizioneStaff per questo evento
        $tokenIscrizioni = Iscrizione::whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
            ->get(['partecipante_id', 'badge_token']);

        $tokenStaff = IscrizioneStaff::where('evento_id', $evento->id)
            ->get(['partecipante_id', 'badge_token']);

        // Raggruppa per partecipante_id
        $tokenIscByPart  = $tokenIscrizioni->groupBy('partecipante_id');
        $tokenStaffByPart = $tokenStaff->groupBy('partecipante_id');

        $totale      = $grouped->count();
        $daAggiornare = 0;
        $saltati     = 0;

        $this->newLine();
        $this->line("  Evento:        <fg=cyan>{$evento->nome}</> (ID {$evento->id})");
        $this->line("  Partecipanti:  <fg=yellow>{$totale}</> da elaborare");
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Procedere con il backfill di badge_tokens_legacy?')) {
            $this->line('Operazione annullata.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totale);
        $bar->start();

        foreach ($grouped as $partecipanteId => $seRecords) {
            // Primary = record con id minore
            $primary    = $seRecords->sortBy('id')->first();
            $secondari  = $seRecords->sortBy('id')->slice(1);

            // Raccoglie tutti i token legacy:
            // 1. badge_token dei SocioEvento secondari
            // 2. badge_token di tutte le Iscrizioni
            // 3. badge_token di IscrizioneStaff
            $legacy = [];

            foreach ($secondari as $se) {
                if ($se->badge_token && $se->badge_token !== $primary->badge_token) {
                    $legacy[] = $se->badge_token;
                }
            }

            foreach ($tokenIscByPart->get($partecipanteId, collect()) as $isc) {
                if ($isc->badge_token && $isc->badge_token !== $primary->badge_token) {
                    $legacy[] = $isc->badge_token;
                }
            }

            foreach ($tokenStaffByPart->get($partecipanteId, collect()) as $staff) {
                if ($staff->badge_token && $staff->badge_token !== $primary->badge_token) {
                    $legacy[] = $staff->badge_token;
                }
            }

            $legacy = array_values(array_unique($legacy));

            if (empty($legacy)) {
                $saltati++;
            } else {
                $primary->update(['badge_tokens_legacy' => $legacy]);
                $daAggiornare++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("  Aggiornati: {$daAggiornare} record con token legacy");
        $this->line("  Saltati:    {$saltati} (solo token canonico, nessun legacy)");
        $this->newLine();

        return self::SUCCESS;
    }
}
