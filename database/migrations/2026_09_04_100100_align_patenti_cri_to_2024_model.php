<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allinea il catalogo `patenti_cri` al modello ufficiale 2024.
 *
 * Modello 2024: 1, 2, 2b, 3, 4, 5, 6, 7, 7b, 8.
 * Spariscono 4m (mai stato ufficiale — "motoclub" interno), 5b e 9.
 *
 * ⚠️ Dati in produzione: le righe `socio_patenti` sui tipi che spariscono vengono convertite
 * quando esiste un corrispettivo 2024 (5b → 2b; 4b → 7b, se mai presente), altrimenti eliminate
 * (4m, 9). La migrazione logga i conteggi.
 */
return new class extends Migration
{
    /** vecchia tipologia => nuova tipologia 2024 (null = nessun corrispettivo, pivot eliminati) */
    private array $conversioniPivot = [
        '5b' => '2b',
        '4b' => '7b',
        '4m' => null,
        '9'  => null,
    ];

    private array $catalogo2024 = [
        ['tipologia' => '1',  'ordine' => 1,  'descrizione' => 'Ciclomotori di servizio, ovvero veicoli ad essi assimilabili.'],
        ['tipologia' => '2',  'ordine' => 2,  'descrizione' => 'Motoveicoli da trasporto e veicoli speciali da trasporto assimilabili.'],
        ['tipologia' => '2b', 'ordine' => 3,  'descrizione' => 'Motoveicoli di soccorso in emergenza (motoveicoli da strada a 2, 3 o 4 ruote per interventi di emergenza sanitaria).'],
        ['tipologia' => '3',  'ordine' => 4,  'descrizione' => 'Autovetture ed autocarri leggeri da trasporto ed operativo, massa complessiva fino a 4,5 t e posti ≤ 9 compreso il conducente.'],
        ['tipologia' => '4',  'ordine' => 5,  'descrizione' => 'Ambulanze impiegate in attività di trasporto, massa autorizzata fino a 4,5 t.'],
        ['tipologia' => '5',  'ordine' => 6,  'descrizione' => 'Veicoli impiegati in attività di soccorso in emergenza urgenza (ambulanze di soccorso, automediche e assimilati), massa complessiva fino a 4,5 t.'],
        ['tipologia' => '6',  'ordine' => 7,  'descrizione' => 'Autocarri operativi medi e pesanti, trattori stradali, carri attrezzi e veicoli speciali operativi, massa autorizzata superiore a 4,5 t.'],
        ['tipologia' => '7',  'ordine' => 8,  'descrizione' => 'Veicoli da trasporto ed operativi con massa autorizzata oltre 4,5 t e posti superiori a 20 (autobus).'],
        ['tipologia' => '7b', 'ordine' => 9,  'descrizione' => 'Minibus da trasporto ed operativi, massimo 20 posti compreso il conducente e massa autorizzata fino a 4,5 t.'],
        ['tipologia' => '8',  'ordine' => 10, 'descrizione' => 'Complessi veicolari con rimorchio di massa autorizzata superiore a 2 t e complesso superiore a 6,5 t.'],
    ];

    public function up(): void
    {
        // 1. crea/aggiorna le tipologie 2024
        foreach ($this->catalogo2024 as $p) {
            DB::table('patenti_cri')->updateOrInsert(
                ['tipologia' => $p['tipologia']],
                ['descrizione' => $p['descrizione'], 'ordine' => $p['ordine'], 'attivo' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        // 2. converti / elimina i pivot sui tipi che spariscono
        foreach ($this->conversioniPivot as $vecchia => $nuova) {
            $vecchioId = DB::table('patenti_cri')->where('tipologia', $vecchia)->value('id');
            if (! $vecchioId) {
                continue;
            }

            if ($nuova !== null) {
                $nuovoId = DB::table('patenti_cri')->where('tipologia', $nuova)->value('id');

                // sposta i pivot al nuovo id, senza violare la PK composta (socio_id, patente_cri_id):
                // se il socio ha già la nuova tipologia si tiene quella e si scarta la vecchia.
                $spostati = 0;
                foreach (DB::table('socio_patenti')->where('patente_cri_id', $vecchioId)->get() as $riga) {
                    $giaPresente = DB::table('socio_patenti')
                        ->where('socio_id', $riga->socio_id)
                        ->where('patente_cri_id', $nuovoId)
                        ->exists();

                    if (! $giaPresente) {
                        DB::table('socio_patenti')
                            ->where('socio_id', $riga->socio_id)
                            ->where('patente_cri_id', $vecchioId)
                            ->update(['patente_cri_id' => $nuovoId]);
                        $spostati++;
                    }
                }
                echo "patenti_cri: tipo $vecchia → $nuova, $spostati pivot convertiti.\n";
            } else {
                $eliminati = DB::table('socio_patenti')->where('patente_cri_id', $vecchioId)->count();
                echo "patenti_cri: tipo $vecchia eliminato, $eliminati pivot socio rimossi (nessun corrispettivo 2024).\n";
            }

            // FK cascadeOnDelete: elimina la riga catalogo e i pivot residui
            DB::table('patenti_cri')->where('id', $vecchioId)->delete();
        }
    }

    public function down(): void
    {
        // Ripristino best-effort delle sole righe catalogo storiche (i pivot convertiti restano
        // convertiti — non c'è modo affidabile di sapere quali fossero originariamente 5b/9/4m).
        foreach ([
            ['tipologia' => '4m', 'ordine' => 5,  'descrizione' => 'Autovetture operative, ambulanze da trasporto, minibus operativi con non più di 9 posti, autocarri operativi leggeri con massa ≤ 3,5 t (motoclub).'],
            ['tipologia' => '5b', 'ordine' => 7,  'descrizione' => 'Motomediche.'],
            ['tipologia' => '9',  'ordine' => 11, 'descrizione' => 'Macchine operatrici, carrelli elevatori, motocarri.'],
        ] as $p) {
            DB::table('patenti_cri')->updateOrInsert(
                ['tipologia' => $p['tipologia']],
                ['descrizione' => $p['descrizione'], 'ordine' => $p['ordine'], 'attivo' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
};
