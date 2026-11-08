<?php

namespace App\Http\Controllers\App;

use App\Esami\PermessiEsame;
use App\Esami\StrutturaSemaforo;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Models\Socio;
use App\Models\TcriProvaPratica;
use App\Services\SocioCorrente;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Prova pratica in PWA: scheda PERSONALE a semaforo (28 voci) di direttore e docenti di commissione, appunti
 * che non entrano nel calcolo; la scheda FINALE la inserisce solo il direttore dal pannello. Stessa scheda e stesso
 * flusso dei laboratori (bozza a ogni tocco, «Invia» solo con tutte le voci, correggibile finché l'esame è in corso).
 * Si compila solo a «Apri la prova finale» aperta (richiusa: sola lettura); non ammessi e ritirati mai. Il totale lo calcola il motore.
 */
class AppEsamiProvaPraticaController extends Controller
{
    public function elenco(Esame $esame)
    {
        $permessi = $this->permessi($esame);
        $nVoci = count(StrutturaSemaforo::per($esame)['codici']);

        $mie = $esame->provePratiche()->where('tipo', TcriProvaPratica::TIPO_PERSONALE)
            ->where('membro_socio_id', SocioCorrente::id())->with('voci')->get()->keyBy('discente_socio_id');
        $righe = $permessi->discentiProvaFinale()->map(function (Socio $s) use ($mie, $esame) {
            $scheda = $mie->get($s->id);

            return [
                'socio'     => $s,
                'compilate' => $scheda ? $scheda->voci->whereNotNull('valore')->count() : 0,
                'inviata'   => $scheda?->eInviata() ?? false,
                'totale'    => $scheda?->totaleFormattato($esame->modello_snapshot),
            ];
        });

        return view('app.esame-prova-pratica', ['esame' => $esame, 'righe' => $righe, 'nVoci' => $nVoci]);
    }

    public function scheda(Esame $esame, Socio $socio)
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->inElencoProvaFinale($socio->id), 404);

        $struttura = StrutturaSemaforo::per($esame);
        $scheda = $this->miaScheda($esame, $socio);
        $valori = [];
        foreach ($scheda?->voci ?? [] as $voce) {
            if ($voce->valore !== null) {
                $valori[$voce->voce_codice] = StrutturaSemaforo::livello($struttura['scala'], (string) $voce->valore);
            }
        }

        return view('app.esame-scheda-prova-pratica', [
            'esame'        => $esame,
            'socio'        => $socio,
            'struttura'    => $struttura,
            'valori'       => $valori,
            'modificabile' => $permessi->puoCompilareProvaPersonale($socio->id),
            'inviata'      => $scheda?->eInviata() ?? false,
            'totale'       => $scheda?->totaleFormattato($esame->modello_snapshot),
        ]);
    }

    /** Salva UNA voce a ogni tocco (bozza che non si perde). Crea la scheda al primo tocco. */
    public function salvaVoce(Request $request, Esame $esame, Socio $socio): JsonResponse
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->puoCompilareProvaPersonale($socio->id), 403);

        $struttura = StrutturaSemaforo::per($esame);
        $dati = $request->validate([
            'voce'   => ['required', 'string', 'in:'.implode(',', $struttura['codici'])],
            'valore' => ['required', 'string', 'in:'.implode(',', array_keys($struttura['scala']))],
        ]);
        $valore = $struttura['scala'][$dati['valore']]['valore'];

        $scheda = DB::transaction(function () use ($esame, $socio, $dati, $valore) {
            $attributi = $this->attributi($esame, $socio);
            try {
                $scheda = TcriProvaPratica::firstOrCreate($attributi, ['inserito_da' => SocioCorrente::id()]);
            } catch (UniqueConstraintViolationException) {
                // Due tocchi ravvicinati hanno creato la scheda insieme: gli indici parziali hanno tenuto, la rileggo.
                $scheda = TcriProvaPratica::where($attributi)->firstOrFail();
            }
            $scheda->voci()->updateOrCreate(['voce_codice' => $dati['voce']], ['valore' => $valore]);

            return $scheda;
        });
        $scheda->load('voci');

        return response()->json([
            'compilate' => $scheda->voci->whereNotNull('valore')->count(),
            'totale'    => $scheda->totaleFormattato($esame->modello_snapshot),
        ]);
    }

    /** Invio: richiede tutte le voci; dopo l'invio l'autore può ancora correggere finché l'esame è in corso. */
    public function invia(Esame $esame, Socio $socio)
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->puoCompilareProvaPersonale($socio->id), 403);

        $scheda = $this->miaScheda($esame, $socio);
        if (! $scheda || $scheda->totale($esame->modello_snapshot)['mancanti'] !== []) {
            return back()->with('errore', 'Per inviare la scheda devi compilare tutte le voci.');
        }
        $scheda->inviata_il ??= now();
        $scheda->save();

        return redirect()->route('app.esame.provapratica', $esame)
            ->with('ok', 'Scheda di '.$socio->cognome.' '.$socio->nome.' inviata.');
    }

    private function permessi(Esame $esame): PermessiEsame
    {
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($esame->modello_snapshot && $permessi->accedeProvaPersonale(), 404);

        return $permessi;
    }

    private function attributi(Esame $esame, Socio $socio): array
    {
        return [
            'esame_id'          => $esame->id,
            'discente_socio_id' => $socio->id,
            'tipo'              => TcriProvaPratica::TIPO_PERSONALE,
            'membro_socio_id'   => SocioCorrente::id(),
        ];
    }

    private function miaScheda(Esame $esame, Socio $socio): ?TcriProvaPratica
    {
        return TcriProvaPratica::where($this->attributi($esame, $socio))->with('voci')->first();
    }
}
