<?php

namespace Tests\Feature;

use App\Esami\AvvioTest;
use App\Esami\TestScritto;
use App\Models\TestDomanda;
use Illuminate\Support\Facades\Route;

/**
 * Test scritto: al client del discente non devono mai arrivare le risposte corrette né il punteggio (prima, durante
 * e dopo l'invio), e prima dell'avvio non deve arrivare nemmeno il testo delle domande. Il punteggio è un valore
 * distintivo (5,3) cercato in ogni risposta; l'output è anche confrontato con sé stesso cambiando le corrette e le
 * risposte date: se dipendesse da loro, sarebbe una fuga.
 */
class EsameTestScrittoLeakTest extends EsameTestScrittoBase
{
    /** Corretta 3,5 / sbagliata 0,7 / non data 0,3: q1 giusta, q2 e q3 sbagliate, q4 vuota, q5 giusta = 7 − 1,4 − 0,3 = 5,3 su 17,5. */
    private function penalitaDistintive(): void
    {
        $this->esame->update(['test_penalita_attive' => true, 'test_punti_corretta' => '3.5', 'test_penalita_errata' => '0.7', 'test_penalita_vuota' => '0.3']);
        $this->esame->refresh();
    }

    /** Toglie CSRF e token dell'avvio: l'unica parte della pagina che cambia da una richiesta all'altra. */
    private function normalizza(string $html, string $token): string
    {
        $html = str_replace($token, 'TOKEN', $html);

        return preg_replace('/"[A-Za-z0-9]{40}"|value="[A-Za-z0-9]{40}"/', 'CSRF', $html);
    }

    private function assertNiente(string $corpo, string $contesto): void
    {
        // (niente forme con il punto: nel layout ci sono path SVG con numeri come 17.5; il punteggio si mostra sempre con la virgola)
        foreach (['5,3', '17,5', '"punti"', '"massimo"', 'data-corretta', '"corretta"', 'corretta":', 'punteggio'] as $vietato) {
            $this->assertStringNotContainsString($vietato, $corpo, "$contesto: compare «{$vietato}»");
        }
    }

    public function test_nessuna_risposta_al_discente_contiene_corrette_o_punteggio(): void
    {
        $this->domande();
        $this->penalitaDistintive();
        $this->avvia('x');
        $ids = $this->ids();
        $risposte = [1, 1, 1, null, 1]; // q1 giusta (1), q2 sbagliata (giusta=0), q3 sbagliata (giusta=3), q4 vuota, q5 giusta (1)

        $corpi = ['home' => $this->come('x')->get(route('app.home'))->getContent()];
        $corpi['elenco'] = $this->come('x')->get(route('app.esami'))->getContent();
        $corpi['dettaglio'] = $this->come('x')->followingRedirects()->get(route('app.esame', $this->esame))->getContent();
        $pagina = $this->come('x')->get(route('app.esame.test', $this->esame));
        $pagina->assertOk();
        $corpi['modulo'] = $pagina->getContent();
        foreach ($risposte as $i => $opz) {
            if ($opz === null) {
                continue;
            }
            $put = $this->rispondi('x', $ids[$i], $opz)->assertOk();
            $this->assertSame(['ok', 'date', 'totale'], array_keys($put->json()), 'il PUT deve rispondere solo ok/date/totale');
            $corpi["put$i"] = $put->getContent();
        }
        $dopoPut = $this->come('x')->get(route('app.esame.test', $this->esame));
        $corpi['modulo_con_scelte'] = $dopoPut->getContent();
        $invio = $this->invia('x');
        $invio->assertRedirect();
        $corpi['invio'] = $invio->getContent();
        $inviato = $this->come('x')->get(route('app.esame.test', $this->esame));
        $corpi['inviato'] = $inviato->getContent();

        // il punteggio c'è davvero ed è quello atteso: se la ricerca non lo trovasse in nessun posto lecito, il test non proverebbe nulla
        $this->assertSame('5,3 su 17,5', TestScritto::mostraPunteggio(TestScritto::punteggio($this->esame->fresh(), TestScritto::consegna($this->esame->fresh(), $this->s['x']->id))));
        foreach ($corpi as $contesto => $corpo) {
            $this->assertNiente($corpo, $contesto);
        }

        // la pagina del test non porta alla vista nessuna chiave di punteggio o corretta
        $dati = $pagina->viewData('domande');
        foreach ($dati as $d) {
            $this->assertSame(['id', 'testo', 'opzioni'], array_keys($d));
        }
    }

    public function test_il_modulo_non_dipende_dalle_corrette_ne_il_test_inviato_dalle_risposte(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $token = fn (string $k) => $this->token($k);

        $prima = $this->normalizza($this->come('x')->get(route('app.esame.test', $this->esame))->getContent(), $token('x'));
        // cambio TUTTE le corrette: se l'HTML dipendesse da esse, cambierebbe
        TestDomanda::query()->update(['corretta' => 2]);
        $dopo = $this->normalizza($this->come('x')->get(route('app.esame.test', $this->esame))->getContent(), $token('x'));
        $this->assertSame($prima, $dopo);

        // test inviato con risposte diverse → stessa pagina (nessun punteggio, nessun conteggio di giuste)
        $this->compilaTutto('x', [0, 0, 0, 0, 0]);
        $this->compilaTutto('y', [1, 0, 3, 2, 1]);
        $a = $this->come('x')->get(route('app.esame.test', $this->esame))->getContent();
        $b = $this->come('y')->get(route('app.esame.test', $this->esame))->getContent();
        $this->assertSame($this->normalizza($a, 'x'), $this->normalizza($b, 'x'));
    }

    private function compilaTutto(string $k, array $opzioni): void
    {
        foreach ($this->ids() as $i => $id) {
            $this->rispondi($k, $id, $opzioni[$i])->assertOk();
        }
        $this->invia($k)->assertRedirect();
    }

    private function ids(): array
    {
        return $this->esame->testDomande()->orderBy('ordine')->pluck('id')->all();
    }

    public function test_prima_dell_avvio_nessuna_pagina_e_nessun_testo_di_domanda(): void
    {
        $this->domande();
        $this->avvia('x'); // y, non ammesso, ritirato, estraneo, docente: non avviati
        $lab = $this->esame->laboratori()->first();
        $urls = [
            route('app.esame', $this->esame), route('app.esame.test', $this->esame), route('app.esame.test.anteprima', $this->esame),
            route('app.esame.itinere', $this->esame), route('app.esame.laboratorio', [$this->esame, $lab]),
        ];
        foreach (['y', 'nonAmmesso', 'ritirato', 'estraneo', 'docente'] as $k) {
            foreach ($urls as $url) {
                $r = $this->come($k)->get($url);
                // il docente semplice è personale: le pagine delle sue schede le apre, ma il test (domande e risposte) mai
                if ($k !== 'docente') {
                    $this->assertContains($r->getStatusCode(), [403, 404], "$k $url");
                }
                $this->assertStringNotContainsString('Domanda numero', $r->getContent(), "$k $url");
            }
            $this->assertSame(404, $this->come($k)->get(route('app.esame.test', $this->esame))->getStatusCode(), "$k test");
            $this->assertSame(404, $this->come($k)->get(route('app.esame.test.anteprima', $this->esame))->getStatusCode(), "$k anteprima");
            foreach (['elenco' => route('app.esami'), 'home' => route('app.home')] as $nome => $url) {
                $corpo = $this->come($k)->get($url)->getContent();
                $this->assertStringNotContainsString('Domanda numero', $corpo, "$k $nome");
            }
            $this->rispondi($k, $this->ids()[0], 1, 'x')->assertStatus(404);
            $this->invia($k, 'x')->assertStatus(404);
        }
    }

    public function test_tripwire_il_discente_avviato_entra_solo_nelle_rotte_del_test(): void
    {
        $this->domande();
        $this->avvia('x');
        $permesse = ['app.esame', 'app.esame.test', 'app.esame.test.risposta', 'app.esame.test.invia'];
        $lab = $this->esame->laboratori()->first();
        $valori = ['esame' => $this->esame, 'laboratorio' => $lab, 'socio' => $this->s['y']];

        $provate = 0;
        foreach (Route::getRoutes() as $route) {
            $nome = $route->getName();
            if (! $nome || ! str_starts_with($nome, 'app.esame') || in_array($nome, $permesse, true)) {
                continue;
            }
            $parametri = [];
            foreach ($route->parameterNames() as $p) {
                $parametri[$p] = $valori[$p] ?? $this->fail("Parametro {$p} della rotta {$nome} non previsto dal tripwire");
            }
            $metodo = strtolower($route->methods()[0]);
            $r = $this->come('x')->{$metodo === 'get' ? 'get' : $metodo.'Json'}(route($nome, $parametri), $metodo === 'get' ? [] : ['voto' => 3, 'voce' => 'x', 'valore' => 'verde']);
            $this->assertContains($r->getStatusCode(), [403, 404], "Il discente avviato raggiunge {$nome} ({$metodo}): {$r->getStatusCode()}");
            $provate++;
        }
        $this->assertGreaterThan(10, $provate, 'Il tripwire non ha trovato le rotte degli esami');
    }

    public function test_il_pdf_cartaceo_non_contiene_le_corrette(): void
    {
        // il Blade del PDF è verificato nel test del pannello (EsameTestScrittoPannelloTest): qui solo il dato che gli si passa
        $this->domande();
        foreach (TestScritto::domandePerDiscente($this->esame->fresh()) as $d) {
            $this->assertArrayNotHasKey('corretta', $d);
        }
    }
}
