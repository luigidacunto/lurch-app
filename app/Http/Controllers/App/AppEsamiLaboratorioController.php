<?php

namespace App\Http\Controllers\App;

use App\Esami\PermessiEsame;
use App\Esami\RiepilogoItinere;
use App\Esami\StrutturaSemaforo;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Models\EsameLaboratorio;
use App\Models\Socio;
use App\Models\TcriSchedaSemaforo;
use App\Services\SocioCorrente;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Schede dei laboratori in PWA: valutazione PERSONALE del docente (appunti), mai una scheda finale.
 * Ogni regola di chi-vede-cosa sta in PermessiEsame; qui solo orchestrazione. Il totale non si salva: lo calcola
 * il motore (CalcoloTcri::totaleSemaforo).
 */
class AppEsamiLaboratorioController extends Controller
{
    use Concerns\SalvaAnnotazione;

    /** Elenco dei discenti del mio gruppo nel laboratorio, con lo stato delle mie schede. */
    public function laboratorio(Esame $esame, EsameLaboratorio $laboratorio)
    {
        $permessi = $this->permessi($esame, $laboratorio);
        $struttura = static::struttura($esame);

        $discenti = $permessi->discentiProponibili($laboratorio);
        $schede = $laboratorio->schedeSemaforo()->where('docente_socio_id', SocioCorrente::id())->with('voci')->get()->keyBy('discente_socio_id');

        $righe = $discenti->map(function (Socio $s) use ($schede, $esame) {
            $scheda = $schede->get($s->id);

            return [
                'socio'      => $s,
                'compilate'  => $scheda ? $scheda->voci->whereNotNull('valore')->count() : 0,
                'inviata'    => $scheda?->eInviata() ?? false,
                'totale'     => $scheda ? static::totaleSeCompleta($scheda, $esame) : null,
            ];
        });

        return view('app.esame-laboratorio', [
            'esame'      => $esame,
            'laboratorio' => $laboratorio,
            'righe'      => $righe,
            'nVoci'      => count($struttura['codici']),
            'eDocente'   => $permessi->eDocente(),
        ]);
    }

    /** La scheda a voci: in sola lettura se l'esame non è in corso. */
    public function scheda(Esame $esame, EsameLaboratorio $laboratorio, Socio $socio)
    {
        $permessi = $this->permessi($esame, $laboratorio);
        abort_unless($permessi->vedeSchedaLab($laboratorio, $socio->id), 404);

        $struttura = static::struttura($esame);
        $scheda = $laboratorio->schedeSemaforo()->where('docente_socio_id', SocioCorrente::id())
            ->where('discente_socio_id', $socio->id)->with('voci')->first();
        $valori = [];
        foreach ($scheda?->voci ?? [] as $voce) {
            if ($voce->valore !== null) {
                $valori[$voce->voce_codice] = StrutturaSemaforo::livello($struttura['scala'], (string) $voce->valore);
            }
        }

        return view('app.esame-scheda-lab', [
            'esame'       => $esame,
            'laboratorio' => $laboratorio,
            'socio'       => $socio,
            'struttura'   => $struttura,
            'valori'      => $valori,
            'modificabile' => $permessi->puoCompilareLab($laboratorio, $socio->id),
            'inviata'     => $scheda?->eInviata() ?? false,
            'annotazione' => $scheda?->annotazione,
            'annotazioneMax' => self::ANNOTAZIONE_MAX,
            'totale'      => $scheda ? static::totaleSeCompleta($scheda, $esame) : null,
            // 5.6: solo le MIE schede di questo discente negli altri laboratori (mai quelle di altri docenti).
            'andamento'   => $this->andamento($esame, $socio),
            // Dopo il mio invio in QUESTO laboratorio: schede inviate di tutti i docenti, questo laboratorio e gli altri, con le annotazioni.
            'confronto'   => $permessi->vedeConfrontoLab($laboratorio, $socio->id) ? $this->confronto($esame, $laboratorio, $socio) : [],
        ]);
    }

    /** Salva UNA voce a ogni tocco (bozza che non si perde). Crea la scheda al primo tocco. */
    public function salvaVoce(Request $request, Esame $esame, EsameLaboratorio $laboratorio, Socio $socio): JsonResponse
    {
        $permessi = $this->permessi($esame, $laboratorio);
        abort_unless($permessi->puoCompilareLab($laboratorio, $socio->id), 403);

        $struttura = static::struttura($esame);
        $dati = $request->validate([
            'voce'   => ['required', 'string', 'in:'.implode(',', $struttura['codici'])],
            'valore' => ['required', 'string', 'in:'.implode(',', array_keys($struttura['scala']))],
        ]);
        $valore = $struttura['scala'][$dati['valore']]['valore'];

        $scheda = DB::transaction(function () use ($laboratorio, $socio, $dati, $valore) {
            $attributi = [
                'laboratorio_id'    => $laboratorio->id,
                'discente_socio_id' => $socio->id,
                'docente_socio_id'  => SocioCorrente::id(),
            ];
            try {
                $scheda = TcriSchedaSemaforo::firstOrCreate($attributi);
            } catch (UniqueConstraintViolationException) {
                // Due tocchi ravvicinati hanno creato la scheda insieme: il vincolo univoco ha tenuto, la rileggo.
                $scheda = TcriSchedaSemaforo::where($attributi)->firstOrFail();
            }
            $scheda->voci()->updateOrCreate(['voce_codice' => $dati['voce']], ['valore' => $valore]);

            return $scheda;
        });

        $scheda->load('voci');

        return response()->json([
            'compilate' => $scheda->voci->whereNotNull('valore')->count(),
            'totale'    => static::totaleSeCompleta($scheda, $esame),
        ]);
    }

    /** Annotazione libera del docente (Concerns\SalvaAnnotazione): stessi permessi delle voci. */
    public function salvaAnnotazione(Request $request, Esame $esame, EsameLaboratorio $laboratorio, Socio $socio): JsonResponse
    {
        $permessi = $this->permessi($esame, $laboratorio);
        abort_unless($permessi->puoCompilareLab($laboratorio, $socio->id), 403);

        return $this->salvaAnnotazioneSu($request, TcriSchedaSemaforo::class, [
            'laboratorio_id'    => $laboratorio->id,
            'discente_socio_id' => $socio->id,
            'docente_socio_id'  => SocioCorrente::id(),
        ]);
    }

    /** Invio: richiede tutte le voci; dopo l'invio l'autore può ancora correggere finché l'esame è in corso. */
    public function invia(Esame $esame, EsameLaboratorio $laboratorio, Socio $socio)
    {
        $permessi = $this->permessi($esame, $laboratorio);
        abort_unless($permessi->puoCompilareLab($laboratorio, $socio->id), 403);

        $scheda = $laboratorio->schedeSemaforo()->where('docente_socio_id', SocioCorrente::id())
            ->where('discente_socio_id', $socio->id)->with('voci')->first();

        if (! $scheda || $scheda->totale($esame->modello_snapshot)['mancanti'] !== []) {
            return back()->with('errore', 'Per inviare la scheda devi compilare tutte le voci.');
        }
        $scheda->inviata_il ??= now();
        $scheda->save();

        return redirect()->route('app.esame.laboratorio', [$esame, $laboratorio])
            ->with('ok', 'Scheda di '.$socio->cognome.' '.$socio->nome.' inviata.');
    }

    private function permessi(Esame $esame, EsameLaboratorio $laboratorio): PermessiEsame
    {
        abort_unless($laboratorio->esame_id === $esame->id, 404);
        $permessi = PermessiEsame::per($esame, SocioCorrente::id());
        abort_unless($permessi->vedeEsame() && $esame->modello_snapshot, 404);

        return $permessi;
    }

    /** Totale formattato ("28,00") solo a scheda completa; mai salvato. */
    public static function totaleSeCompleta(TcriSchedaSemaforo $scheda, Esame $esame): ?array
    {
        return $scheda->totaleFormattato($esame->modello_snapshot);
    }

    /**
     * Matrici di confronto del discente: prima questo laboratorio (sempre, anche se solo la mia è inviata), poi gli
     * altri in ordine, solo se hanno almeno una scheda inviata.
     *
     * @return list<array{laboratorio: EsameLaboratorio, corrente: bool, dettaglio: array}>
     */
    private function confronto(Esame $esame, EsameLaboratorio $corrente, Socio $socio): array
    {
        $righe = [];
        foreach ($esame->laboratori()->orderBy('numero')->get()->sortBy(fn ($l) => $l->id === $corrente->id ? 0 : 1) as $lab) {
            $dettaglio = RiepilogoItinere::dettaglioLaboratorio($esame, $socio->id, $lab->id, conAnnotazioni: true);
            if ($lab->id === $corrente->id || $dettaglio['docenti'] !== []) {
                $righe[] = ['laboratorio' => $lab, 'corrente' => $lab->id === $corrente->id, 'dettaglio' => $dettaglio];
            }
        }

        return $righe;
    }

    /** @return list<array{laboratorio: EsameLaboratorio, totale: ?array}> */
    private function andamento(Esame $esame, Socio $socio): array
    {
        return TcriSchedaSemaforo::query()
            ->whereIn('laboratorio_id', $esame->laboratori()->select('id'))
            ->where('docente_socio_id', SocioCorrente::id())
            ->where('discente_socio_id', $socio->id)
            ->with(['laboratorio', 'voci'])->get()
            ->sortBy(fn ($s) => $s->laboratorio->numero)
            ->map(fn ($s) => ['laboratorio' => $s->laboratorio, 'inviata' => $s->eInviata(), 'totale' => static::totaleSeCompleta($s, $esame)])
            ->values()->all();
    }

    /** @return array{competenze: array, scala: array<string, array>, codici: list<string>} */
    public static function struttura(Esame $esame): array
    {
        return StrutturaSemaforo::per($esame);
    }
}
