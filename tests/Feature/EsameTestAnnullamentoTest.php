<?php

namespace Tests\Feature;

use App\Esami\AnnullamentoDomandaTest;
use App\Esami\PubblicazioneTest;
use App\Esami\TestException;
use App\Esami\TestScritto;
use App\Filament\Resources\EsameResource;
use Livewire\Livewire;

/**
 * Test scritto: statistica delle risposte (solo test inviati, ordine del test) e annullamento di una domanda (solo il
 * direttore, solo quando tutti gli ammessi hanno inviato; la domanda resta con le risposte ma esce dal calcolo).
 */
class EsameTestAnnullamentoTest extends EsameTestScrittoBase
{
    private function ids(): array
    {
        return $this->esame->testDomande()->orderBy('ordine')->pluck('id')->all();
    }

    /** Corrette B,A,D,C,B = 1,0,3,2,1. */
    private function compilaEInvia(string $k, array $opzioni): void
    {
        foreach ($this->ids() as $i => $id) {
            $this->rispondi($k, $id, $opzioni[$i])->assertOk();
        }
        $this->invia($k)->assertRedirect();
    }

    private function punteggio(string $k): ?string
    {
        $e = $this->esame->fresh();

        return TestScritto::mostraPunteggio(TestScritto::punteggio($e, TestScritto::consegna($e, $this->s[$k]->id)));
    }

    private function rifiuta(\Closure $f, string $messaggio): void
    {
        try {
            $f();
            $this->fail('atteso un rifiuto: '.$messaggio);
        } catch (TestException $e) {
            $this->assertStringContainsString($messaggio, $e->getMessage());
        }
    }

    public function test_statistica_conta_solo_i_test_inviati_nell_ordine_del_test(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compilaEInvia('x', [1, 0, 0, 0, 1]);              // giuste 1, 2, 5
        $this->rispondi('y', $this->ids()[0], 3)->assertOk();    // y in compilazione: non conta

        $st = TestScritto::statistica($this->esame->fresh());
        $this->assertSame(1, $st['inviati']);
        $this->assertSame($this->ids(), array_column($st['domande'], 'id'));
        $this->assertSame([0, 1, 0, 0], $st['domande'][0]['scelte']);
        $this->assertSame(['giuste' => 1, 'sbagliate' => 0, 'vuote' => 0, 'errore' => 0], array_intersect_key($st['domande'][0], array_flip(['giuste', 'sbagliate', 'vuote', 'errore'])));
        $this->assertSame([1, 0, 0, 0], $st['domande'][2]['scelte']);
        $this->assertSame(100, $st['domande'][2]['errore']);

        $this->compilaEInvia('y', [3, 1, 3, 2, 0]);              // y: domanda 1 cambiata in D prima dell'invio
        $st = TestScritto::statistica($this->esame->fresh());
        $this->assertSame(2, $st['inviati']);
        $this->assertSame([0, 1, 0, 1], $st['domande'][0]['scelte']);
        $this->assertSame(50, $st['domande'][0]['errore']);
    }

    /** Tre inviati (x, y e il terzo iscritto reso ammesso): errori per domanda 0, 1, 2, 3, 0. */
    private function treInviati(): void
    {
        \App\Models\Iscrizione::where('partecipante_id', $this->s['nonAmmesso']->id)->update(['non_ammesso' => false, 'non_ammesso_motivo' => null]);
        $this->domande();
        $this->avvia('x', 'y', 'nonAmmesso');
        $this->compilaEInvia('x', [1, 0, 0, 0, 1]);              // sbaglia 3 e 4
        $this->compilaEInvia('y', [1, 1, 3, 0, 1]);              // sbaglia 2 e 4
        $this->compilaEInvia('nonAmmesso', [1, 0, 0, 0, 1]);     // sbaglia 3 e 4
    }

    public function test_evidenze_della_statistica_sui_conteggi_esatti(): void
    {
        $this->treInviati();
        $ev = fn () => array_column(TestScritto::statistica($this->esame->fresh())['domande'], 'evidenza');

        // 4: 3 errori su 3 → la più sbagliata; 3: 2 su 3 → oltre la metà; 2: 1 su 3 → niente; 1 e 5: giuste per tutti.
        $this->assertSame([TestScritto::EVIDENZA_PERFETTA, null, TestScritto::EVIDENZA_ALTA, TestScritto::EVIDENZA_PEGGIORE, TestScritto::EVIDENZA_PERFETTA], $ev());

        // Annullata la 4: nessuna evidenza per lei, e la più sbagliata diventa la 3.
        AnnullamentoDomandaTest::annulla($this->esame->fresh(), $this->u['direttore'], $this->ids()[3], 'Fuori programma');
        $this->assertSame([TestScritto::EVIDENZA_PERFETTA, null, TestScritto::EVIDENZA_PEGGIORE, null, TestScritto::EVIDENZA_PERFETTA], $ev());

        // Pannello e app della commissione mostrano le stesse etichette.
        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\TestScrittoEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('La più sbagliata')->assertSee('Giusta per tutti')->assertSee('Scarica la statistica (PDF)');
        $this->come('docComm')->get(route('app.esame.test.anteprima', $this->esame))->assertOk()
            ->assertSee('La più sbagliata')->assertSee('Giusta per tutti');
    }

    public function test_evidenze_con_la_meta_esatta_e_con_pari_merito(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compilaEInvia('x', [1, 0, 0, 0, 1]);              // sbaglia 3 e 4
        $this->compilaEInvia('y', [1, 1, 3, 0, 1]);              // sbaglia 2 e 4
        $ev = fn () => array_column(TestScritto::statistica($this->esame->fresh())['domande'], 'evidenza');

        // 2 e 3 sbagliate dalla metà esatta: nessuna evidenza.
        $this->assertSame([TestScritto::EVIDENZA_PERFETTA, null, null, TestScritto::EVIDENZA_PEGGIORE, TestScritto::EVIDENZA_PERFETTA], $ev());
        // Annullata la 4, la 2 e la 3 sono pari merito: entrambe le più sbagliate.
        AnnullamentoDomandaTest::annulla($this->esame->fresh(), $this->u['direttore'], $this->ids()[3], 'x');
        $this->assertSame([TestScritto::EVIDENZA_PERFETTA, TestScritto::EVIDENZA_PEGGIORE, TestScritto::EVIDENZA_PEGGIORE, null, TestScritto::EVIDENZA_PERFETTA], $ev());
    }

    public function test_pdf_statistica_per_chi_vede_la_pagina_con_tutto_quello_che_vede_il_direttore(): void
    {
        $this->treInviati();
        AnnullamentoDomandaTest::annulla($this->esame->fresh(), $this->u['direttore'], $this->ids()[3], 'Fuori programma');
        $url = route('esami.test-statistica-pdf', $this->esame);

        foreach (['direttore', 'super', 'adminReg', 'adminLoc', 'presidente', 'docComm'] as $chi) {
            $r = $this->actingAs($this->u[$chi])->get($url);
            $r->assertOk();
            $this->assertStringContainsString('application/pdf', $r->headers->get('Content-Type'), $chi);
        }
        foreach (['docente', 'x'] as $chi) {
            $this->actingAs($this->u[$chi])->get($url)->assertForbidden();
        }

        $e = $this->esame->fresh()->load('corso');
        $stat = TestScritto::statistica($e);
        $html = view('pdf.test-statistica', [
            'esame' => $e, 'stat' => $stat, 'nAnnullate' => 1, 'evidenze' => TestScritto::EVIDENZE,
            'regola' => '', 'massimo' => '4,0', 'generata' => now(),
        ])->render();
        foreach (['3 test inviati', '(1 annullata)', 'La più sbagliata', 'Giusta per tutti', 'Domanda annullata', 'Motivo: Fuori programma', '✓', '2 · 67%'] as $atteso) {
            $this->assertStringContainsString($atteso, $html);
        }
        $this->assertStringNotContainsString($this->s['x']->cognome, $html, 'nessun nome di discente');

        // senza domande niente PDF (nessun controllo sullo stato dell'esame: è una lettura)
        \App\Models\TestRisposta::query()->delete();
        \App\Models\TestDomanda::query()->delete();
        $this->actingAs($this->u['direttore'])->get($url)->assertNotFound();
    }

    public function test_annullamento_solo_del_direttore_e_solo_a_test_inviato_da_tutti(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compilaEInvia('x', [1, 0, 0, 0, 1]);
        $q3 = $this->ids()[2];
        $e = fn () => $this->esame->fresh();

        $this->rifiuta(fn () => AnnullamentoDomandaTest::annulla($e(), $this->u['direttore'], $q3, 'Poco chiara'), 'manca 1');
        $this->compilaEInvia('y', [1, 1, 3, 2, 0]);

        foreach (['super', 'adminReg', 'presidente', 'docComm'] as $chi) {
            $this->rifiuta(fn () => AnnullamentoDomandaTest::annulla($e(), $this->u[$chi], $q3, 'x'), 'Solo il direttore');
        }
        $this->rifiuta(fn () => AnnullamentoDomandaTest::annulla($e(), $this->u['direttore'], $q3, "  \u{00A0} "), 'motivo');
        $this->assertNull($e()->testDomande()->whereNotNull('annullata_il')->first());
        $this->assertSame('3,0 su 5,0', $this->punteggio('x'));
        $this->assertSame('3,0 su 5,0', $this->punteggio('y'));   // giuste 1, 3, 4

        AnnullamentoDomandaTest::annulla($e(), $this->u['direttore'], $q3, '  Formulata male  ');
        $d = $e()->testDomande()->find($q3);
        $this->assertTrue($d->eAnnullata());
        $this->assertSame('Formulata male', $d->motivo_annullamento);
        $this->assertSame($this->s['direttore']->id, $d->annullata_da);
        $this->assertSame(2, $d->risposte()->count(), 'le risposte restano');
        $this->assertSame('3,0 su 4,0', $this->punteggio('x'));   // x l'aveva sbagliata: resta 3, il massimo scende
        $this->assertSame('2,0 su 4,0', $this->punteggio('y'));   // y l'aveva giusta: perde il punto
        $this->assertSame(4, TestScritto::numeroDomandeValide($e()));
        $this->assertSame(5, TestScritto::numeroDomande($e()));
        $this->assertNotNull(TestScritto::statistica($e())['domande'][2]['annullata']);

        $this->rifiuta(fn () => AnnullamentoDomandaTest::annulla($e(), $this->u['direttore'], $q3, 'x'), 'già annullata');
        // mai l'ultima valida
        foreach ([0, 1, 3] as $i) {
            AnnullamentoDomandaTest::annulla($e(), $this->u['direttore'], $this->ids()[$i], 'x');
        }
        $this->rifiuta(fn () => AnnullamentoDomandaTest::annulla($e(), $this->u['direttore'], $this->ids()[4], 'x'), "l'ultima domanda valida");
        foreach ([0, 1, 3] as $i) {
            AnnullamentoDomandaTest::ripristina($e(), $this->u['direttore'], $this->ids()[$i]);
        }

        AnnullamentoDomandaTest::ripristina($e(), $this->u['direttore'], $q3);
        $this->assertNull($e()->testDomande()->find($q3)->motivo_annullamento);
        $this->assertSame('3,0 su 5,0', $this->punteggio('x'));
        $this->rifiuta(fn () => AnnullamentoDomandaTest::ripristina($e(), $this->u['direttore'], $q3), 'non è annullata');
    }

    public function test_il_discente_vede_l_annullamento_solo_dopo_la_pubblicazione(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compilaEInvia('x', [1, 0, 0, 0, 1]);
        $this->compilaEInvia('y', [1, 0, 3, 2, 1]);
        AnnullamentoDomandaTest::annulla($this->esame->fresh(), $this->u['direttore'], $this->ids()[2], 'Due risposte giuste');

        $prima = $this->come('x')->get(route('app.esame.test', $this->esame))->assertOk()->getContent();
        $this->assertStringNotContainsString('Domanda annullata', $prima);
        $this->assertStringNotContainsString('Due risposte giuste', $prima);

        PubblicazioneTest::pubblica($this->esame->fresh(), $this->u['direttore'], [$this->s['x']->id]);
        $this->come('x')->get(route('app.esame.test', $this->esame))->assertOk()
            ->assertSee('3,0 su 4,0')
            ->assertSee('Risposte giuste: 3 su 4')
            ->assertSee('Domanda annullata: non conta nel punteggio. Motivo: Due risposte giuste');
    }

    public function test_pannello_e_app_mostrano_statistica_e_il_direttore_annulla_dal_pannello(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compilaEInvia('x', [1, 0, 0, 0, 1]);

        // App della commissione: domande e statistica in riquadri chiusi.
        $html = $this->come('docComm')->get(route('app.esame.test.anteprima', $this->esame))->assertOk()
            ->assertSee('Statistica delle risposte · 1 test inviato')->getContent();
        $this->assertSame(2, substr_count($html, '<details class="box">'));
        $this->assertStringNotContainsString('<details class="box" open', $html);

        // Pannello: il pulsante è spento finché manca un test.
        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\TestScrittoEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Statistica delle risposte')->assertSee('ne manca 1')->assertDontSee('Ripristina la domanda');

        $this->compilaEInvia('y', [1, 0, 3, 2, 1]);
        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\TestScrittoEsame::class, ['record' => $this->esame->getRouteKey()])
            ->callAction('annullaDomanda', ['motivo' => 'Ambigua'], ['domanda_id' => $this->ids()[1], 'numero' => 2])
            ->assertHasNoActionErrors()
            ->assertSee('Domanda annullata')->assertSee('Ambigua')->assertSee('Ripristina la domanda');
        $this->assertSame('Ambigua', $this->esame->testDomande()->find($this->ids()[1])->motivo_annullamento);

        // Il presidente legge la statistica ma non ha il pulsante.
        $this->pannello('presidente');
        Livewire::test(EsameResource\Pages\TestScrittoEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Statistica delle risposte')->assertDontSee('Ripristina la domanda');
    }
}
