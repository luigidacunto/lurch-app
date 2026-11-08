<?php

namespace App\Http\Controllers\App;

use App\Esami\PermessiEsame;
use App\Esami\RiepilogoProvaFinale;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Services\SocioCorrente;

/**
 * Riepilogo della prova finale in PWA: SOLA LETTURA per la commissione (direttore, presidente o delegato,
 * docenti di commissione). Le schede finali si inseriscono solo dal pannello, dal direttore; le personali si vedono
 * solo a prova finale aperta. Dati da App\Esami\RiepilogoProvaFinale, gli stessi del pannello. Altri: 404.
 */
class AppEsamiRiepilogoProvaFinaleController extends Controller
{
    public function riepilogo(Esame $esame)
    {
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($permessi->vedeRiepilogoProvaFinale() && $esame->modello_snapshot, 404);

        return view('app.esame-riepilogo-prova-finale', [
            'esame' => $esame,
            'r'     => RiepilogoProvaFinale::per($esame),
        ]);
    }
}
