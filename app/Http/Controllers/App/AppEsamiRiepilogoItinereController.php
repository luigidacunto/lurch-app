<?php

namespace App\Http\Controllers\App;

use App\Esami\PermessiEsame;
use App\Esami\RiepilogoItinere;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Services\SocioCorrente;

/**
 * Riepilogo itinere in PWA: SOLA LETTURA per la commissione (direttore, presidente o delegato, docenti di
 * commissione). La finale d'area si inserisce solo dal pannello, dal direttore. Dati da App\Esami\RiepilogoItinere,
 * gli stessi del pannello. Docenti non in commissione e discenti: 404.
 */
class AppEsamiRiepilogoItinereController extends Controller
{
    public function riepilogo(Esame $esame)
    {
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($permessi->vedeRiepilogoItinere() && $esame->modello_snapshot, 404);

        return view('app.esame-riepilogo-itinere', [
            'esame' => $esame,
            'r'     => RiepilogoItinere::per($esame, $permessi->ha(PermessiEsame::DIRETTORE)),
        ]);
    }
}
