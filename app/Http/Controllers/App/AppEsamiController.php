<?php

namespace App\Http\Controllers\App;

use App\Esami\PermessiEsame;
use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Services\SocioCorrente;

/**
 * Sezione "Esami" della PWA: elenco con il ruolo del socio e dettaglio per il personale. Al discente con il test avviato
 * l'elenco mostra l'esame e il dettaglio lo porta alla sola pagina del test.
 */
class AppEsamiController extends Controller
{
    public function index()
    {
        $socioId = SocioCorrente::id();

        $esami = Esame::query()->visibiliAlSocio($socioId)
            ->with('corso')
            ->get()
            ->sortByDesc(fn (Esame $e) => $e->corso?->data_inizio)
            ->map(fn (Esame $e) => [
                'esame'  => $e,
                'ruoli'  => static::etichetteRuoli(PermessiEsame::per($e, $socioId)),
            ])
            ->values();

        return view('app.esami', compact('esami'));
    }

    /** Dettaglio minimo dell'esame: intestazione e laboratori con lo stato delle schede dell'utente. */
    public function show(Esame $esame)
    {
        $socioId = SocioCorrente::id();
        $permessi = PermessiEsame::per($esame, $socioId);
        if (! $permessi->vedeEsame()) {
            // Il discente con il test avviato ha solo la pagina del test: mai le schede.
            abort_unless($permessi->vedeTestComeDiscente(), 404);

            return redirect()->route('app.esame.test', $esame);
        }

        $esame->load('corso');
        $laboratori = $esame->laboratori()->orderBy('numero')->get()->map(function ($lab) use ($permessi, $socioId) {
            $miei = $permessi->discentiProponibili($lab);
            $schede = $lab->schedeSemaforo()->where('docente_socio_id', $socioId)->whereIn('discente_socio_id', $miei->pluck('id'))->get();

            return ['lab' => $lab, 'discenti' => $miei->count(), 'inviate' => $schede->filter->eInviata()->count()];
        });

        // Itinere: X = discenti per cui ho almeno un voto, Y = i miei discenti.
        $discentiItinere = $permessi->discentiItinere();
        $conVoti = $esame->schedeItinere()->where('docente_socio_id', $socioId)
            ->whereIn('discente_socio_id', $discentiItinere->pluck('id'))
            ->whereHas('voci', fn ($q) => $q->whereNotNull('voto'))->count();

        return view('app.esame', [
            'esame'       => $esame,
            'ruoli'       => static::etichetteRuoli($permessi),
            'laboratori'  => $laboratori,
            'eDocente'    => $permessi->eDocente(),
            'itinere'     => ['discenti' => $discentiItinere->count(), 'iniziate' => $conVoti],
            'riepilogoItinere' => $permessi->vedeRiepilogoItinere(),
            // Schede personali di colloquio e prova pratica (compilatori: compilabili a prova aperta, in sola lettura se richiusa) + riepilogo di sola lettura
            'provaFinale'      => $permessi->accedeProvaPersonale(),
            'riepilogoProvaFinale' => $permessi->vedeRiepilogoProvaFinale(),
            // Anteprima/risultati del test scritto, solo commissione
            'testScritto'      => $permessi->vedeTestScritto(),
        ]);
    }

    /** @return list<string> */
    public static function etichetteRuoli(PermessiEsame $permessi): array
    {
        if ($permessi->eCommissario()) {
            return ['Commissione'];
        }
        if ($permessi->eDocente()) {
            return ['Docente'];
        }

        // Il discente arriva qui solo se il test è stato avviato per lui.
        return $permessi->eDiscente() ? ['Discente'] : [];
    }
}
