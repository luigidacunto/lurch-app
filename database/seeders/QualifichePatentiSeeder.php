<?php

namespace Database\Seeders;

use App\Models\PatenteCri;
use Illuminate\Database\Seeder;

class QualifichePatentiSeeder extends Seeder
{
    public function run(): void
    {
        // Modello ufficiale 2024 (vedi migrazione 2026_09_04_100100_align_patenti_cri_to_2024_model).
        $patenti = [
            ['tipologia' => '1',  'descrizione' => 'Ciclomotori di servizio, ovvero veicoli ad essi assimilabili.',                                                                                       'ordine' =>  1],
            ['tipologia' => '2',  'descrizione' => 'Motoveicoli da trasporto e veicoli speciali da trasporto assimilabili.',                                                                              'ordine' =>  2],
            ['tipologia' => '2b', 'descrizione' => 'Motoveicoli di soccorso in emergenza (motoveicoli da strada a 2, 3 o 4 ruote per interventi di emergenza sanitaria).',                                 'ordine' =>  3],
            ['tipologia' => '3',  'descrizione' => 'Autovetture ed autocarri leggeri da trasporto ed operativo, massa complessiva fino a 4,5 t e posti ≤ 9 compreso il conducente.',                       'ordine' =>  4],
            ['tipologia' => '4',  'descrizione' => 'Ambulanze impiegate in attività di trasporto, massa autorizzata fino a 4,5 t.',                                                                        'ordine' =>  5],
            ['tipologia' => '5',  'descrizione' => 'Veicoli impiegati in attività di soccorso in emergenza urgenza (ambulanze di soccorso, automediche e assimilati), massa complessiva fino a 4,5 t.',    'ordine' =>  6],
            ['tipologia' => '6',  'descrizione' => 'Autocarri operativi medi e pesanti, trattori stradali, carri attrezzi e veicoli speciali operativi, massa autorizzata superiore a 4,5 t.',             'ordine' =>  7],
            ['tipologia' => '7',  'descrizione' => 'Veicoli da trasporto ed operativi con massa autorizzata oltre 4,5 t e posti superiori a 20 (autobus).',                                                'ordine' =>  8],
            ['tipologia' => '7b', 'descrizione' => 'Minibus da trasporto ed operativi, massimo 20 posti compreso il conducente e massa autorizzata fino a 4,5 t.',                                          'ordine' =>  9],
            ['tipologia' => '8',  'descrizione' => 'Complessi veicolari con rimorchio di massa autorizzata superiore a 2 t e complesso superiore a 6,5 t.',                                                 'ordine' => 10],
        ];

        foreach ($patenti as $p) {
            PatenteCri::updateOrCreate(['tipologia' => $p['tipologia']], array_merge($p, ['attivo' => true]));
        }
    }
}
