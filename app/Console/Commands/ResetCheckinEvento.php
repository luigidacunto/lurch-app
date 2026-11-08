<?php

namespace App\Console\Commands;

use App\Models\AccessoEvento;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\OspiteEvento;
use App\Models\RispostaCheckin;
use App\Models\SocioEvento;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ResetCheckinEvento extends Command
{
    protected $signature = 'checkin:reset
                            {evento : ID dell\'evento}
                            {--data= : Cancella solo i record di una data specifica (Y-m-d)}
                            {--reset-stato : Resetta anche lo stato iscrizioni da checkin_fatto a form_completato/pending (solo senza --data)}
                            {--force : Salta la conferma interattiva}';

    protected $description = 'Cancella i record di check-in/out di un evento, con opzione per filtrare per data';

    public function handle(): int
    {
        $evento = Evento::find($this->argument('evento'));
        if (! $evento) {
            $this->error("Evento con ID {$this->argument('evento')} non trovato.");
            return self::FAILURE;
        }

        $dataFiltro = null;
        if ($this->option('data')) {
            try {
                $dataFiltro = Carbon::createFromFormat('Y-m-d', $this->option('data'))->startOfDay();
            } catch (\Exception) {
                $this->error('Formato data non valido. Usare Y-m-d (es. 2026-05-30).');
                return self::FAILURE;
            }
        }

        $resetStato = $this->option('reset-stato') && ! $dataFiltro;

        // SocioEvento primary per l'evento (dove vivono AccessoEvento e RispostaCheckin)
        $socioEventoIds = SocioEvento::where('evento_id', $evento->id)
            ->whereIn('id', function ($sub) use ($evento) {
                $sub->selectRaw('MIN(id)')
                    ->from('socio_evento')
                    ->where('evento_id', $evento->id)
                    ->groupBy('partecipante_id');
            })
            ->pluck('id');

        $ospitiIds = OspiteEvento::where('evento_id', $evento->id)->pluck('id');

        $query = AccessoEvento::where(function ($q) use ($socioEventoIds, $ospitiIds) {
            $q->where(function ($q2) use ($socioEventoIds) {
                $q2->where('iscrizioneable_type', SocioEvento::class)
                   ->whereIn('iscrizioneable_id', $socioEventoIds);
            })->orWhere(function ($q2) use ($ospitiIds) {
                $q2->where('iscrizioneable_type', OspiteEvento::class)
                   ->whereIn('iscrizioneable_id', $ospitiIds);
            });
        });

        if ($dataFiltro) {
            $query->whereDate('timestamp_accesso', $dataFiltro);
        }

        $totale = $query->count();

        // risposte_checkin morfate su SocioEvento — cancellate solo senza filtro data
        $risposteCheckinIds = ! $dataFiltro
            ? RispostaCheckin::where('iscrizioneable_type', SocioEvento::class)
                ->whereIn('iscrizioneable_id', $socioEventoIds)
                ->pluck('id')
            : collect();

        $this->newLine();
        $this->line("  Evento:       <fg=cyan>{$evento->nome}</> (ID {$evento->id})");
        $this->line("  Filtro:       " . ($dataFiltro ? "<fg=yellow>{$dataFiltro->format('d/m/Y')}</>" : '<fg=yellow>tutti i giorni</>'));
        $this->line("  Accessi:      <fg=yellow>{$totale}</> record da cancellare");
        if (! $dataFiltro) {
            $this->line("  Dati checkin: <fg=yellow>{$risposteCheckinIds->count()}</> risposte da cancellare");
        }

        if ($resetStato) {
            $iscrizioniIds = Iscrizione::whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))->pluck('id');
            $staffIds      = IscrizioneStaff::where('evento_id', $evento->id)->pluck('id');
            $conCheckin    = Iscrizione::whereIn('id', $iscrizioniIds)->where('stato', 'checkin_fatto')->count()
                           + IscrizioneStaff::whereIn('id', $staffIds)->where('stato', 'checkin_fatto')->count();
            $this->line("  Stato:        <fg=yellow>{$conCheckin}</> iscrizioni verranno riportate a form_completato/pending");
        }

        $this->newLine();

        if ($totale === 0 && $risposteCheckinIds->isEmpty() && ! $resetStato) {
            $this->info('Nessun record da cancellare.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Procedere con la cancellazione?')) {
            $this->line('Operazione annullata.');
            return self::SUCCESS;
        }

        $cancellati = $query->delete();
        $this->info("  {$cancellati} record di accesso eliminati.");

        if ($risposteCheckinIds->isNotEmpty()) {
            $rcCancellate = RispostaCheckin::whereIn('id', $risposteCheckinIds)->delete();
            $this->info("  {$rcCancellate} risposte checkin eliminate.");
        }

        if ($resetStato) {
            $iscrizioniIds = $iscrizioniIds ?? Iscrizione::whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))->pluck('id');
            $staffIds      = $staffIds ?? IscrizioneStaff::where('evento_id', $evento->id)->pluck('id');

            Iscrizione::whereIn('id', $iscrizioniIds)
                ->where('stato', 'checkin_fatto')
                ->each(function (Iscrizione $i) {
                    $i->update(['stato' => $i->rispostaPreregistrazione ? 'form_completato' : 'pending']);
                });

            IscrizioneStaff::whereIn('id', $staffIds)
                ->where('stato', 'checkin_fatto')
                ->each(function (IscrizioneStaff $i) {
                    $i->update(['stato' => $i->rispostaPreregistrazione ? 'form_completato' : 'pending']);
                });

            $this->info('  Stato iscrizioni aggiornato.');
        }

        $this->newLine();
        return self::SUCCESS;
    }
}
