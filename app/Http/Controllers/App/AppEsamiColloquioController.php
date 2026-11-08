<?php

namespace App\Http\Controllers\App;

use App\Esami\PermessiEsame;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Models\Socio;
use App\Models\TcriColloquio;
use App\Services\SocioCorrente;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Colloquio in PWA: scheda PERSONALE di direttore e docenti di commissione (appunti: voto intero + impressione
 * facoltativa), mai la scheda finale (quella la inserisce il direttore dal pannello). Una scheda per (esame, discente,
 * membro), salvata a ogni tocco, nessun invio. Si compila solo a «Apri la prova finale» aperta (richiusa: sola lettura); non ammessi e ritirati mai.
 * Chi-vede-cosa sta in PermessiEsame.
 */
class AppEsamiColloquioController extends Controller
{
    public const IMPRESSIONE_MAX = 1000;

    /** I miei discenti con il voto che ho già dato. */
    public function elenco(Esame $esame)
    {
        $permessi = $this->permessi($esame);

        $miei = $esame->colloqui()->where('tipo', TcriColloquio::TIPO_PERSONALE)
            ->where('membro_socio_id', SocioCorrente::id())->get()->keyBy('discente_socio_id');
        $righe = $permessi->discentiProvaFinale()->map(fn (Socio $s) => [
            'socio' => $s,
            'voto'  => $miei->has($s->id) ? (int) $miei->get($s->id)->punteggio : null,
        ]);

        return view('app.esame-colloquio', ['esame' => $esame, 'righe' => $righe]);
    }

    /** La scheda: in sola lettura se l'esame non è in corso. */
    public function scheda(Esame $esame, Socio $socio)
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->inElencoProvaFinale($socio->id), 404);

        $scheda = $this->miaScheda($esame, $socio);

        return view('app.esame-scheda-colloquio', [
            'esame'        => $esame,
            'socio'        => $socio,
            'struttura'    => static::struttura($esame),
            'voto'         => $scheda ? (int) $scheda->punteggio : null,
            'impressione'  => $scheda?->impressione,
            'modificabile' => $permessi->puoCompilareProvaPersonale($socio->id),
            'impressioneMax' => self::IMPRESSIONE_MAX,
        ]);
    }

    /** Voto intero obbligatorio: si può cambiare ma non togliere. Crea la scheda al primo voto. */
    public function salvaVoto(Request $request, Esame $esame, Socio $socio): JsonResponse
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->puoCompilareProvaPersonale($socio->id), 403);

        $struttura = static::struttura($esame);
        $dati = $request->validate([
            'voto' => ['required', 'integer', 'between:'.$struttura['min'].','.$struttura['max']],
        ]);

        $attributi = [
            'esame_id'          => $esame->id,
            'discente_socio_id' => $socio->id,
            'tipo'              => TcriColloquio::TIPO_PERSONALE,
            'membro_socio_id'   => SocioCorrente::id(),
        ];
        try {
            $scheda = TcriColloquio::updateOrCreate($attributi, ['punteggio' => $dati['voto'], 'inserito_da' => SocioCorrente::id()]);
        } catch (UniqueConstraintViolationException) {
            // Due tocchi ravvicinati hanno creato la scheda insieme: gli indici parziali hanno tenuto, aggiorno la riga.
            $scheda = TcriColloquio::where($attributi)->firstOrFail();
            $scheda->update(['punteggio' => $dati['voto']]);
        }

        return response()->json(['voto' => (int) $scheda->punteggio]);
    }

    /** Impressione facoltativa (max 1000 caratteri). Serve la scheda: il voto è obbligatorio e la crea. */
    public function salvaImpressione(Request $request, Esame $esame, Socio $socio): JsonResponse
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->puoCompilareProvaPersonale($socio->id), 403);

        $dati = $request->validate([
            'impressione' => ['present', 'nullable', 'string', 'max:'.self::IMPRESSIONE_MAX],
        ]);
        $scheda = $this->miaScheda($esame, $socio);
        if (! $scheda) {
            return response()->json(['errore' => 'Scegli prima il voto.'], 422);
        }
        $testo = trim((string) $dati['impressione']);
        $scheda->update(['impressione' => $testo === '' ? null : $testo]);

        return response()->json(['ok' => true]);
    }

    private function permessi(Esame $esame): PermessiEsame
    {
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($esame->modello_snapshot && $permessi->accedeProvaPersonale(), 404);

        return $permessi;
    }

    private function miaScheda(Esame $esame, Socio $socio): ?TcriColloquio
    {
        return $esame->colloqui()->where('tipo', TcriColloquio::TIPO_PERSONALE)
            ->where('membro_socio_id', SocioCorrente::id())->where('discente_socio_id', $socio->id)->first();
    }

    /**
     * Scala e descrittori dallo snapshot (nulla nel codice).
     *
     * @return array{min: int, max: int, livelli: array, domande: array}
     */
    public static function struttura(Esame $esame): array
    {
        $colloquio = $esame->modello_snapshot['colloquio'];

        return [
            'min'     => (int) $colloquio['scala']['min'],
            'max'     => (int) $colloquio['scala']['max'],
            'livelli' => $colloquio['scala']['livelli'],
            'domande' => $colloquio['domande_guida'] ?? [],
        ];
    }
}
