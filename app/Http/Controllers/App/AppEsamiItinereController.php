<?php

namespace App\Http\Controllers\App;

use App\Esami\Calcolo\Frazione;
use App\Esami\Formato;
use App\Esami\PermessiEsame;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Models\Socio;
use App\Models\TcriSchedaItinere;
use App\Services\SocioCorrente;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Schede itinere in PWA: valutazione PERSONALE del docente (appunti), mai una scheda finale.
 * Una scheda per (esame, discente, docente), salvata a ogni tocco, nessun invio. Chi-vede-cosa sta in PermessiEsame;
 * medie e totale li calcola il motore (TcriSchedaItinere::riepilogo → CalcoloTcri::mediaArea), mai salvati.
 */
class AppEsamiItinereController extends Controller
{
    use Concerns\SalvaAnnotazione;

    /** I miei discenti con lo stato delle mie schede. */
    public function elenco(Esame $esame)
    {
        $permessi = $this->permessi($esame);
        $nVoci = count(static::struttura($esame)['codici']);

        $discenti = $permessi->discentiItinere();
        $schede = $esame->schedeItinere()->where('docente_socio_id', SocioCorrente::id())->with('voci')->get()->keyBy('discente_socio_id');

        $righe = $discenti->map(fn (Socio $s) => [
            'socio' => $s,
            'voti'  => static::contaVoti($schede->get($s->id)),
            'riep'  => $schede->has($s->id) ? static::riepilogoFormattato($schede->get($s->id), $esame) : null,
        ]);

        return view('app.esame-itinere', [
            'esame'    => $esame,
            'righe'    => $righe,
            'nVoci'    => $nVoci,
            'eDocente' => $permessi->eDocente(),
        ]);
    }

    /** La scheda: in sola lettura se l'esame non è in corso. */
    public function scheda(Esame $esame, Socio $socio)
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->inElencoItinere($socio->id), 404);

        $struttura = static::struttura($esame);
        $scheda = $this->miaScheda($esame, $socio);
        $voti = [];
        foreach ($scheda?->voci ?? [] as $voce) {
            if ($voce->voto !== null) {
                $voti[$voce->voce_codice] = $voce->voto;
            }
        }

        return view('app.esame-scheda-itinere', [
            'esame'        => $esame,
            'socio'        => $socio,
            'struttura'    => $struttura,
            'voti'         => $voti,
            'modificabile' => $permessi->puoCompilareItinere($socio->id),
            'annotazione'  => $scheda?->annotazione,
            'annotazioneMax' => self::ANNOTAZIONE_MAX,
            'riepilogo'    => static::riepilogoFormattato($scheda ?? new TcriSchedaItinere(), $esame),
        ]);
    }

    /** Salva UN voto a ogni tocco (`voto` null = toglie il voto). Crea la scheda al primo tocco. */
    public function salvaVoto(Request $request, Esame $esame, Socio $socio): JsonResponse
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->puoCompilareItinere($socio->id), 403);

        $struttura = static::struttura($esame);
        $dati = $request->validate([
            'voce' => ['required', 'string', 'in:'.implode(',', $struttura['codici'])],
            'voto' => ['present', 'nullable', 'integer', 'between:'.$struttura['min'].','.$struttura['max']],
        ]);

        $scheda = DB::transaction(function () use ($esame, $socio, $dati) {
            $attributi = [
                'esame_id'          => $esame->id,
                'discente_socio_id' => $socio->id,
                'docente_socio_id'  => SocioCorrente::id(),
            ];
            try {
                $scheda = TcriSchedaItinere::firstOrCreate($attributi);
            } catch (UniqueConstraintViolationException) {
                // Due tocchi ravvicinati hanno creato la scheda insieme: il vincolo univoco ha tenuto, la rileggo.
                $scheda = TcriSchedaItinere::where($attributi)->firstOrFail();
            }
            $scheda->voci()->updateOrCreate(['voce_codice' => $dati['voce']], ['voto' => $dati['voto']]);

            return $scheda;
        });

        $scheda->load('voci');

        return response()->json(static::riepilogoFormattato($scheda, $esame));
    }

    /** Annotazione libera del docente (Concerns\SalvaAnnotazione): stessi permessi dei voti. */
    public function salvaAnnotazione(Request $request, Esame $esame, Socio $socio): JsonResponse
    {
        $permessi = $this->permessi($esame);
        abort_unless($permessi->puoCompilareItinere($socio->id), 403);

        return $this->salvaAnnotazioneSu($request, TcriSchedaItinere::class, [
            'esame_id'          => $esame->id,
            'discente_socio_id' => $socio->id,
            'docente_socio_id'  => SocioCorrente::id(),
        ]);
    }

    private function permessi(Esame $esame): PermessiEsame
    {
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($permessi->vedeEsame() && $esame->modello_snapshot, 404);

        return $permessi;
    }

    private function miaScheda(Esame $esame, Socio $socio): ?TcriSchedaItinere
    {
        return $esame->schedeItinere()->where('docente_socio_id', SocioCorrente::id())
            ->where('discente_socio_id', $socio->id)->with('voci')->first();
    }

    /** Voti dati (voce non vuota) in una scheda. */
    public static function contaVoti(?TcriSchedaItinere $scheda): int
    {
        return $scheda ? $scheda->voci->whereNotNull('voto')->count() : 0;
    }

    /**
     * Medie d'area e totale già formattati ("3,5") per la pagina e per la risposta del salvataggio.
     *
     * @return array{voti: int, aree: list<array{codice: string, media: ?string, contati: int}>, totale: ?string, massimo: string, aree_valutate: int, n_aree: int}
     */
    public static function riepilogoFormattato(TcriSchedaItinere $scheda, Esame $esame): array
    {
        $r = $scheda->riepilogo($esame->modello_snapshot, $esame->lap);

        return [
            'voti'          => $r['voti'],
            'aree'          => array_map(fn ($a) => ['codice' => $a['codice'], 'media' => static::formatta($a['media']), 'contati' => $a['voti_contati']], $r['aree']),
            'totale'        => static::formatta($r['totale']),
            // Massimo = aree × massimo della scala, dallo snapshot (4 × 5 = 20 nel TCRI v1).
            'massimo'       => (string) ($r['n_aree'] * (int) $esame->modello_snapshot['itinere']['scala']['max']),
            'aree_valutate' => $r['aree_valutate'],
            'n_aree'        => $r['n_aree'],
        ];
    }

    private static function formatta(?Frazione $f): ?string
    {
        return Formato::decimale($f);
    }

    /**
     * Struttura della scheda dallo snapshot (testi e scala non stanno nel codice).
     *
     * @return array{aree: array, livelli: array, min: int, max: int, codici: list<string>}
     */
    public static function struttura(Esame $esame): array
    {
        $itinere = $esame->modello_snapshot['itinere'];
        $codici = [];
        foreach ($itinere['aree'] as $a) {
            array_push($codici, ...array_column($a['voci'], 'codice'));
        }

        return [
            'aree'    => $itinere['aree'],
            'livelli' => $itinere['scala']['livelli'],
            'min'     => (int) $itinere['scala']['min'],
            'max'     => (int) $itinere['scala']['max'],
            'codici'  => $codici,
        ];
    }
}
