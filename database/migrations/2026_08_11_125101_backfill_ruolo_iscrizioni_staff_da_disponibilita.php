<?php

use App\Models\IscrizioneStaff;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Il campo iscrizioni_staff.ruolo non è mai stato sincronizzato con le disponibilità
     * docente/logistica dichiarate in risposte_preregistrazione.risposte['disponibilita']
     * (per-slot, può contenere sia 'formazione' che 'logistica' per la stessa persona).
     * La migration 2026_08_04_231312 aveva poi impostato in blocco ruolo=NULL -> 'logistica',
     * mascherando chi in realtà aveva disponibilità come docente. Da qui in avanti la modale
     * "Modifica dati" di GestioneStaff sincronizza ruolo ad ogni salvataggio (vedi
     * GestioneStaff::modificaDatiStaffAction()) — questa migration ricalcola una tantum
     * il dato storico con la stessa regola: 'docenza' se esiste almeno uno slot 'formazione',
     * altrimenti 'logistica' se esiste almeno uno slot 'logistica', altrimenti il valore resta invariato.
     */
    public function up(): void
    {
        $righe = DB::table('risposte_preregistrazione')
            ->where('iscrizioneable_type', IscrizioneStaff::class)
            ->select('iscrizioneable_id', 'risposte')
            ->get();

        $aggiornati = 0;

        foreach ($righe as $riga) {
            $risposte      = json_decode($riga->risposte, true) ?? [];
            $disponibilita = $risposte['disponibilita'] ?? [];

            if (empty($disponibilita)) {
                continue;
            }

            $haFormazione = collect($disponibilita)->contains(fn ($d) => ($d['tipo'] ?? null) === 'formazione');
            $haLogistica  = collect($disponibilita)->contains(fn ($d) => ($d['tipo'] ?? null) === 'logistica');

            $nuovoRuolo = match (true) {
                $haFormazione => 'docenza',
                $haLogistica  => 'logistica',
                default       => null,
            };

            if ($nuovoRuolo === null) {
                continue;
            }

            $aggiornati += DB::table('iscrizioni_staff')
                ->where('id', $riga->iscrizioneable_id)
                ->where('ruolo', '!=', $nuovoRuolo)
                ->update(['ruolo' => $nuovoRuolo]);
        }

        \Log::info("backfill_ruolo_iscrizioni_staff_da_disponibilita: {$aggiornati} righe aggiornate.");
    }

    /**
     * Irreversibile: non si conserva il valore precedente di ruolo.
     */
    public function down(): void
    {
        //
    }
};
