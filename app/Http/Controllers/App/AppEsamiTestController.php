<?php

namespace App\Http\Controllers\App;

use App\Esami\PermessiEsame;
use App\Esami\SvolgimentoTest;
use App\Esami\TestException;
use App\Esami\TestScritto;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use App\Services\SocioCorrente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Test scritto in PWA, lato DISCENTE. Si entra solo col test avviato online per quel discente
 * (PermessiEsame::vedeTestComeDiscente): in tutti gli altri casi 404, non 403.
 *
 * ⚠️ Al client arrivano SOLO id, testo e opzioni delle domande (TestScritto::domandePerDiscente), le risposte che il
 * discente ha già scelto e un contatore «date su totale». Mai la risposta corretta, mai il punteggio, nemmeno dopo l'invio.
 * Salvataggio a ogni tocco, invio finale; token dell'attivazione su ogni richiesta (409 se nel frattempo il
 * direttore ha disattivato/riattivato). Pagine con Cache-Control: no-store.
 */
class AppEsamiTestController extends Controller
{
    /** Il modulo, oppure «Test inviato» se ha già inviato. */
    public function mostra(Esame $esame)
    {
        $permessi = $this->permessi($esame);
        $socioId = SocioCorrente::id();
        $consegna = TestScritto::consegna($esame, $socioId);

        if ($consegna?->eInviata()) {
            return $this->senzaCache(response()->view('app.esame-test-inviato', ['esame' => $esame]));
        }

        $config = TestScritto::configurazione($esame);
        $domande = TestScritto::domandePerDiscente($esame, $config['mescola']);
        $date = $consegna ? $consegna->risposte()->whereNotNull('opzione')->pluck('opzione', 'domanda_id')->all() : [];

        return $this->senzaCache(response()->view('app.esame-test', [
            'esame'    => $esame,
            'domande'  => $domande,
            'scelte'   => array_map('intval', $date),
            'token'    => $permessi->avvioAttivo()->token,
            'regola'   => TestScritto::regola($esame),
            'obbligatorie' => $config['obbligatorie'],
            'mescola'  => $config['mescola'],
            'urlRisposta' => route('app.esame.test.risposta', $esame),
        ]));
    }

    /** Una risposta a ogni tocco. Risponde solo `{ok, date, totale}`. */
    public function salvaRisposta(Request $request, Esame $esame): JsonResponse
    {
        $dati = $request->validate([
            'token'   => ['required', 'string'],
            'domanda' => ['required', 'integer'],
            'opzione' => ['present', 'nullable', 'integer'],
        ]);

        try {
            $this->permessiScrittura($esame);
            $r = SvolgimentoTest::salvaRisposta(
                $esame, SocioCorrente::id(), $dati['token'], TestConsegna::ORIGINE_ONLINE, null,
                (int) $dati['domanda'], $dati['opzione'] === null ? null : (int) $dati['opzione'],
            );
        } catch (TestException $e) {
            return response()->json(['errore' => $e->getMessage()], $e->stato);
        }

        return response()->json(['ok' => true, 'date' => $r['date'], 'totale' => $r['totale']]);
    }

    /** Invio finale (form classico, nessun payload di ritorno con dati del test). */
    public function invia(Request $request, Esame $esame)
    {
        $dati = $request->validate(['token' => ['required', 'string']]);

        try {
            $this->permessiScrittura($esame);
            SvolgimentoTest::invia($esame, SocioCorrente::id(), $dati['token'], TestConsegna::ORIGINE_ONLINE, null);
        } catch (TestException $e) {
            return $this->senzaCache(response()->view('app.esame-test-errore', ['esame' => $esame, 'messaggio' => $e->getMessage()], $e->stato));
        }

        return redirect()->route('app.esame.test', $esame);
    }

    private function permessi(Esame $esame): PermessiEsame
    {
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($permessi->vedeTestComeDiscente(), 404);

        return $permessi;
    }

    /**
     * Per le scritture (salvataggio e invio): chi non è mai stato avviato riceve 404 come per qualsiasi altra pagina; chi
     * lo era e ora non lo è più (il direttore ha disattivato o riportato in bozza il test) riceve 409 «ricarica»:
     * il modulo che aveva aperto è di una finestra annullata e non scrive nulla.
     *
     * @throws TestException
     */
    private function permessiScrittura(Esame $esame): PermessiEsame
    {
        $socioId = SocioCorrente::id();
        $permessi = PermessiEsame::per($esame, $socioId);
        if ($permessi->vedeTestComeDiscente()) {
            return $permessi;
        }
        $eraAvviato = $permessi->eDiscente() && $socioId && TestScritto::avvio($esame, $socioId)?->modalita === TestAvvio::ONLINE;
        abort_unless($eraAvviato, 404);

        throw new TestException('Il test è stato aggiornato o non è più disponibile: ricarica la pagina.', 409);
    }

    private function senzaCache($response)
    {
        return $response->header('Cache-Control', 'no-store, private');
    }
}
