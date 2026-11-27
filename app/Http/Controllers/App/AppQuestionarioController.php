<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\QuestionarioGradimento;
use App\Questionari\CompilazioneQuestionario;
use App\Questionari\Domande;
use App\Questionari\InvioQuestionario;
use App\Questionari\PermessiQuestionario;
use App\Questionari\StatisticheQuestionario;
use App\Services\SocioCorrente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Questionario di gradimento nell'app. Il discente invitato lo compila una volta; il docente o direttore valutato
 * vede solo la propria sezione, da StatisticheQuestionario::SOGLIA compilazioni. Chi non c'entra riceve 404.
 *
 * ⚠️ Anonimato: nessun dato della richiesta (IP, user agent) viene salvato o scritto nei log; con errori di
 * validazione la pagina si ridisegna direttamente con i valori inviati, senza parcheggiarli in sessione. Le risposte
 * parziali stanno solo nella bozza cifrata dell'invito, cancellata all'invio.
 */
class AppQuestionarioController extends Controller
{
    public function mostra(QuestionarioGradimento $questionario)
    {
        $invito = $this->invito($questionario);
        if (! $invito->compilato && ! $invito->aperto && $questionario->eAperto()) {
            $invito->update(['aperto' => true]);
        }

        // La bozza cifrata ripropone le risposte già date.
        return $this->pagina($questionario, $invito->compilato, $invito->compilato ? [] : ($invito->bozza ?? []), []);
    }

    /** Salvataggio automatico della bozza mentre si compila. Risponde solo `{ok, ora}`. */
    public function salvaBozza(Request $request, QuestionarioGradimento $questionario): JsonResponse
    {
        $this->invito($questionario);
        $ok = CompilazioneQuestionario::salvaBozza($questionario, SocioCorrente::id(), (array) $request->input('r', []));

        return $ok
            ? response()->json(['ok' => true, 'ora' => now()->format('H:i')])
            : response()->json(['ok' => false], 409);
    }

    public function invia(Request $request, QuestionarioGradimento $questionario)
    {
        $invito = $this->invito($questionario);
        if ($invito->compilato || ! $questionario->eAperto()) {
            return $this->pagina($questionario, $invito->compilato, [], []);
        }

        $input = (array) $request->input('r', []);
        [$risposte, $errori] = CompilazioneQuestionario::valida($questionario, $input);
        if ($errori !== []) {
            CompilazioneQuestionario::salvaBozza($questionario, SocioCorrente::id(), $input);

            return $this->pagina($questionario, false, $input, $errori)->setStatusCode(422);
        }

        if (! CompilazioneQuestionario::salva($questionario, SocioCorrente::id(), $risposte)) {
            return $this->pagina($questionario, true, [], []);
        }

        return redirect()->route('app.home')->with('success', 'Grazie: il questionario è stato inviato in forma anonima.');
    }

    public function risultati(QuestionarioGradimento $questionario)
    {
        $indice = PermessiQuestionario::indiceValutato($questionario, SocioCorrente::id());
        abort_if(! $questionario->eInviato() || $indice === null, 404);

        $visibili = StatisticheQuestionario::visibili($questionario);

        return response()->view('app.questionario-risultati', [
            'questionario' => $questionario->load('corso'),
            'sezioni'      => $visibili ? StatisticheQuestionario::per($questionario, $indice) : [],
            'visibili'     => $visibili,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function invito(QuestionarioGradimento $questionario)
    {
        $invito = SocioCorrente::id() ? CompilazioneQuestionario::invito($questionario, SocioCorrente::id()) : null;
        abort_unless($invito, 404);

        return $invito;
    }

    private function pagina(QuestionarioGradimento $questionario, bool $compilato, array $vecchi, array $errori)
    {
        return response()->view('app.questionario', [
            'questionario' => $questionario->load('corso'),
            'compilato'    => $compilato,
            'sezioni'      => Domande::blocchi($questionario->versione, $questionario->sezioni ?? []),
            'valutati'     => $questionario->valutati ?? [],
            'scala'        => Domande::SCALA,
            'introduzione' => Domande::INTRODUZIONE,
            'vecchi'       => $vecchi,
            'errori'       => $errori,
            'titoloValutato' => fn (array $v) => InvioQuestionario::titoloValutato($v),
        ])->header('Cache-Control', 'private, no-store');
    }
}
