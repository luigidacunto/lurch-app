<?php

namespace App\Http\Controllers\App;

use App\Esami\Formato;
use App\Esami\PermessiEsame;
use App\Esami\TestScritto;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Services\SocioCorrente;

/**
 * Test scritto in PWA, lato COMMISSIONE: domande con le risposte corrette (anche in bozza, per verificare il
 * test insieme al direttore) e, per i discenti ammessi, stato e punteggio. Sola lettura: nessuna rotta di scrittura.
 * Direttore, presidente/delegato e docenti di commissione; i docenti non in commissione e i discenti: 404.
 */
class AppEsamiRiepilogoTestController extends Controller
{
    public function anteprima(Esame $esame)
    {
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($esame->modello_snapshot && $permessi->vedeTestScritto(), 404);

        $righe = collect(TestScritto::riepilogo($esame))->map(function (array $r) use ($esame) {
            $r['risposte'] = $r['stato'] === 'inviato' ? TestScritto::risposteDi($esame, $r['consegna']) : null;

            return $r;
        });

        return view('app.esame-test-anteprima', [
            'esame'    => $esame,
            'domande'  => TestScritto::domandeComplete($esame),
            'config'   => TestScritto::configurazione($esame),
            'stato'    => $esame->statoTest(),
            'massimo'  => Formato::unDecimale(TestScritto::massimo($esame)),
            'righe'    => $righe,
        ]);
    }
}
