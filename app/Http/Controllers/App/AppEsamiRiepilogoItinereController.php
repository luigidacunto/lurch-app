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

    /**
     * «I miei tutorati»: stesso riepilogo, limitato ai discenti dei gruppi di tutoraggio del docente. Sola lettura;
     * niente test scritto, prova pratica, colloquio, esito. Chi non è tutor: 404.
     */
    public function tutorati(Esame $esame)
    {
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($permessi->vedeTutorati() && $esame->modello_snapshot, 404);

        // Il tutor legge anche le note dei docenti sui suoi discenti (itinere e laboratori; di laboratorio solo a scheda inviata).
        $r = RiepilogoItinere::per($esame, true, $permessi->discentiTutorati()->all());

        // Matrice voce × docente di ogni laboratorio con schede inviate, apribile sotto la riga del laboratorio.
        // ponytail: una query per (discente, laboratorio); va bene per i pochi discenti di un tutor.
        $dettagliLab = [];
        foreach ($r['discenti'] as $d) {
            foreach ($d['laboratori'] as $lab) {
                if ($lab['media'] !== null) {
                    $dettagliLab[$d['socio']->id][$lab['id']] = RiepilogoItinere::dettaglioLaboratorio($esame, $d['socio']->id, $lab['id'], true);
                }
            }
        }

        // Itinere in centesimi (definitivo del direttore o suggerito dalle medie), colorato rispetto al LAP.
        // ponytail: ProiezioneEsame::per() rilegge il riepilogo; va bene per i pochi discenti di un tutor.
        $itinereCentesimi = [];
        foreach (\App\Esami\ProiezioneEsame::per($esame, $permessi->discentiTutorati()->all()) as $id => $riga) {
            $i = \App\Esami\ProiezioneEsame::itinereCentesimi($esame, $riga);
            $peso = \App\Esami\Calcolo\Frazione::da($i['peso']);
            $itinereCentesimi[$id] = [
                'testo'      => $i['contributo'] === null ? '–' : \App\Esami\Formato::decimale($i['contributo']),
                'peso'       => $i['peso'],
                'colore'     => $i['contributo'] === null ? null : \App\Esami\ColoreScala::perValore($i['contributo'], $peso, (string) $esame->lap),
                'definitivo' => $i['definitivo'],
            ];
        }

        return view('app.esame-riepilogo-itinere', [
            'esame'       => $esame,
            'r'           => $r,
            'tutorati'    => true,
            'itinereCentesimi' => $itinereCentesimi,
            'dettagliLab' => $dettagliLab,
            'soglie'      => \App\Esami\ProiezioneEsame::soglieLap($esame),
        ]);
    }
}
