<?php

namespace App\Http\Controllers\App;

use App\Esami\PermessiEsame;
use App\Esami\Formato;
use App\Esami\PubblicazioneEsito;
use App\Esami\RisultatiEsame;
use App\Models\Esito;
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
 * Test scritto in PWA, lato DISCENTE. Si compila solo col test avviato online per quel discente
 * (PermessiEsame::vedeTestComeDiscente); dopo l'invio (anche cartaceo inserito dal direttore) lo si rivede in sola
 * lettura (vedeTestInviatoComeDiscente). In tutti gli altri casi 404, non 403.
 *
 * ⚠️ Al client arrivano SOLO id, testo e opzioni delle domande (TestScritto::domandePerDiscente), le risposte che il
 * discente ha scelto e un contatore «date su totale». Mai la risposta corretta né il punteggio, finché il direttore
 * non gli pubblica i risultati (vedeRisultatiTest → TestScritto::correzionePerDiscente, solo nella sola lettura).
 * Salvataggio a ogni tocco, invio finale; token dell'attivazione su ogni richiesta (409 se nel frattempo il
 * direttore ha disattivato/riattivato). Pagine con Cache-Control: no-store.
 */
class AppEsamiTestController extends Controller
{
    /** Il modulo, oppure il test inviato in sola lettura (con le correzioni solo se pubblicate). */
    public function mostra(Esame $esame)
    {
        $socioId = SocioCorrente::id();
        $permessi = PermessiEsame::per($esame, $socioId);
        $consegna = $socioId ? TestScritto::consegna($esame, $socioId) : null;

        if ($consegna?->eInviata()) {
            abort_unless($permessi->vedeTestInviatoComeDiscente(), 404);

            return $this->senzaCache(response()->view('app.esame-test-inviato', [
                'esame'      => $esame,
                'domande'    => TestScritto::domandePerDiscente($esame),
                'scelte'     => TestScritto::risposteDi($esame, $consegna),
                'cartaceo'   => $consegna->origine === TestConsegna::ORIGINE_CARTACEO,
                'correzione' => $permessi->vedeRisultatiTest() ? TestScritto::correzionePerDiscente($esame, $consegna) : null,
            ]));
        }

        $permessi = $this->permessi($esame);

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

    /** Esito finale del discente: 404 finché chi gestisce l'esame non glielo pubblica (o se non è più calcolato). */
    public function esito(Esame $esame)
    {
        $socioId = SocioCorrente::id();
        $permessi = PermessiEsame::per($esame, $socioId);
        abort_unless($permessi->vedeEsitoComeDiscente(), 404);
        $esito = $this->esitoPerDiscente($esame, $socioId);
        abort_unless($esito !== null, 404);

        return $this->senzaCache(response()->view('app.esame-esito', ['esame' => $esame, 'esito' => $esito]));
    }

    /**
     * Esito finale per il discente (solo se pubblicato): totale, idoneità e contributo di ogni prova con il suo peso, dai
     * valori attuali di RisultatiEsame (a esame chiuso: quelli salvati). Null se l'esito non è (più) calcolato.
     *
     * Per ogni prova: punti ottenuti sul massimo della prova e conversione in centesimi sul suo peso; poi LAP, totale, esito.
     *
     * @return array{finale: string, idoneo: bool, lap: string, prove: list<array{nome: string, punti: ?string, massimo: ?string, contributo: ?string, peso: string}>}|null
     */
    private function esitoPerDiscente(Esame $esame, int $socioId): ?array
    {
        $riga = PubblicazioneEsito::riga($esame, $socioId);
        if (! $riga || $riga['stato'] !== Esito::STATO_CALCOLATO || $riga['finale'] === null) {
            return null;
        }
        $pesi = $esame->modello_snapshot['pesi'] ?? [];
        $nomi = ['itinere' => 'Itinere', 'colloquio' => 'Colloquio', 'prova_pratica' => 'Prova pratica', 'test_scritto' => 'Test scritto'];
        $virgola = fn (?string $v) => $v === null ? null : str_replace('.', ',', $v);
        $numero = fn (?string $nd) => Formato::decimale(RisultatiEsame::daFrazione($nd));

        return [
            'finale' => $virgola((string) $riga['finale']),
            'idoneo' => (bool) $riga['idoneo'],
            'lap'    => $virgola(rtrim(rtrim((string) ($riga['lap'] ?? $esame->lap), '0'), '.')),
            'prove'  => collect($nomi)->map(fn (string $nome, string $prova) => [
                'nome'       => $nome,
                'punti'      => $numero($riga['prove'][$prova]['punti'] ?? null),
                'massimo'    => $numero($riga['prove'][$prova]['massimo'] ?? null),
                'contributo' => $virgola($riga['prove'][$prova]['contributo'] ?? null),
                'peso'       => (string) ($pesi[$prova] ?? ''),
            ])->values()->all(),
        ];
    }

    private function senzaCache($response)
    {
        return $response->header('Cache-Control', 'no-store, private');
    }
}
