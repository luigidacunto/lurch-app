<?php

namespace Tests\Feature;

use App\Esami\AvvioTest;
use App\Esami\CompletezzaEsame;
use App\Esami\TestScritto;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use App\Models\TestDomanda;
use Livewire\Livewire;

/**
 * Test scritto: pannello (tab «Test scritto», pagina del test cartaceo, PDF da stampare). DB di test: lurch_test.
 */
class EsameTestScrittoPannelloTest extends EsameTestScrittoBase
{
    private function pagina(string $classe, array $extra = [])
    {
        return Livewire::test($classe, ['record' => $this->esame->getRouteKey()] + $extra);
    }

    public function test_la_tab_la_legge_chi_legge_l_esame_e_non_il_docente_semplice_ne_il_discente(): void
    {
        $this->domande();
        foreach (['direttore', 'presidente', 'docComm', 'super', 'adminReg', 'adminLoc'] as $k) {
            $this->pannello($k);
            $this->assertTrue(EsameResource\Pages\TestScrittoEsame::canAccess(['record' => $this->esame->fresh()]), $k);
            $this->assertTrue(EsameResource\Pages\DomandeTestEsame::canAccess(['record' => $this->esame->fresh()]), $k);
            $this->pagina(EsameResource\Pages\DomandeTestEsame::class)->assertSee('Domanda numero 1 zz')->assertSee('Punteggio');
        }
        foreach (['docente', 'x'] as $k) {
            $this->pannello($k);
            $this->assertFalse(EsameResource\Pages\TestScrittoEsame::canAccess(['record' => $this->esame->fresh()]), $k);
        }
    }

    public function test_pulsanti_attivi_solo_per_il_direttore_gli_altri_vedono_il_motivo(): void
    {
        $this->domande();
        $this->pannello('direttore');
        $this->pagina(EsameResource\Pages\TestScrittoEsame::class)->assertSee('Avvia per tutti');
        $this->pagina(EsameResource\Pages\DomandeTestEsame::class)->assertSee('Imposta il punteggio')->assertSee('Aggiungi domanda');

        $this->pannello('presidente');
        $this->pagina(EsameResource\Pages\TestScrittoEsame::class)->assertDontSee('Avvia per tutti')->assertSee('Solo il direttore del corso avvia il test');
        $this->pagina(EsameResource\Pages\DomandeTestEsame::class)->assertDontSee('Imposta il punteggio')->assertDontSee('Aggiungi domanda');

        // admin e superadmin preparano (domande/punteggio) ma non avviano
        foreach (['super', 'adminReg'] as $k) {
            $this->pannello($k);
            $this->pagina(EsameResource\Pages\DomandeTestEsame::class)->assertSee('Aggiungi domanda')->assertSee('Imposta il punteggio');
            $this->pagina(EsameResource\Pages\TestScrittoEsame::class)->assertDontSee('Avvia per tutti');
        }
    }

    public function test_azioni_domanda_e_punteggio_dal_pannello(): void
    {
        $this->pannello('adminLoc');
        $p = $this->pagina(EsameResource\Pages\DomandeTestEsame::class);
        $p->callAction('domanda', ['testo' => 'Quanto fa 2+2?', 'o0' => '3', 'o1' => '4', 'o2' => '5', 'o3' => '6', 'corretta' => '1']);
        $d = TestDomanda::first();
        $this->assertSame('Quanto fa 2+2?', $d->testo);
        $this->assertSame(1, (int) $d->makeVisible('corretta')->corretta);

        $p->callAction('domanda', ['testo' => 'Quanto fa 2+3?', 'o0' => '3', 'o1' => '4', 'o2' => '5', 'o3' => '6', 'corretta' => '2'], ['domanda_id' => $d->id]);
        $this->assertSame('Quanto fa 2+3?', $d->fresh()->testo);

        $p->callAction('configurazione', ['penalita' => true, 'corretta' => '2', 'errata' => '1', 'vuota' => '0,5']);
        $this->assertTrue($this->esame->fresh()->test_penalita_attive);
        $this->assertSame('0.5', (string) $this->esame->fresh()->test_penalita_vuota);

        $p->callAction('eliminaDomanda', [], ['domanda_id' => $d->id]);
        $this->assertSame(0, TestDomanda::count());
    }

    public function test_avvio_dal_pannello_tutti_selezionati_e_solo_direttore(): void
    {
        $this->domande();

        // l'admin non avvia: nessuna riga creata
        $this->pannello('super');
        $this->pagina(EsameResource\Pages\TestScrittoEsame::class)->callAction('avviaTutti');
        $this->assertSame(0, TestAvvio::count());

        $this->pannello('direttore');
        $p = $this->pagina(EsameResource\Pages\TestScrittoEsame::class)
            ->set('selezionati', [$this->s['x']->id, $this->s['nonAmmesso']->id, $this->s['ritirato']->id])
            ->callAction('avviaSelezionati');
        $this->assertSame([$this->s['x']->id], TestAvvio::pluck('discente_socio_id')->all());

        $p->callAction('avviaUno', [], ['discente_id' => $this->s['y']->id, 'discente_nome' => 'Y']);
        $this->assertSame(2, TestAvvio::count());
        $p->callAction('disattiva', [], ['discente_id' => $this->s['y']->id, 'discente_nome' => 'Y']);
        $this->assertNull(TestAvvio::where('discente_socio_id', $this->s['y']->id)->first()->avviato_il);

        $p->callAction('riportaInBozza');
        $this->assertSame(Esame::TEST_BOZZA, $this->esame->fresh()->statoTest());
        $p->callAction('avviaTutti');
        $this->assertSame(2, TestAvvio::whereNotNull('avviato_il')->count());
    }

    public function test_cartaceo_dal_pannello_e_pagina_di_inserimento(): void
    {
        $this->domande();
        $this->pannello('direttore');
        $p = $this->pagina(EsameResource\Pages\TestScrittoEsame::class);
        $p->callAction('cartaceo', ['motivo' => 'DSA certificato'], ['discente_id' => $this->s['x']->id, 'discente_nome' => 'X']);
        $this->assertSame('cartaceo', TestAvvio::first()->modalita);

        $pagina = $this->pagina(EsameResource\Pages\InserisciTestCartaceo::class, ['discente' => $this->s['x']->id]);
        $pagina->assertSee('DSA certificato')->assertSee('Domanda numero 1 zz');
        // niente corrette nella pagina di inserimento
        $this->assertStringNotContainsString('✓', $pagina->html());

        $ids = $this->esame->testDomande()->orderBy('ordine')->pluck('id');
        foreach ($ids as $i => $id) {
            $pagina->call('scegli', $id, self::CORRETTE[$i]);
        }
        $pagina->assertSee('Risposte inserite 5 su 5')->callAction('invia');

        $c = TestConsegna::first();
        $this->assertTrue($c->eInviata());
        $this->assertSame('cartaceo', $c->origine);
        $this->assertSame($this->s['direttore']->id, $c->inserito_da);
        $this->assertSame('5,0 su 5,0', TestScritto::mostraPunteggio(TestScritto::punteggio($this->esame->fresh(), $c)));

        // dopo l'invio: sola lettura (nessuna modifica)
        $pagina2 = $this->pagina(EsameResource\Pages\InserisciTestCartaceo::class, ['discente' => $this->s['x']->id]);
        $pagina2->call('scegli', $ids[0], 0);
        $this->assertSame(1, (int) $c->risposte()->where('domanda_id', $ids[0])->value('opzione'));
    }

    public function test_pagina_cartaceo_solo_direttore_solo_se_dichiarato_e_solo_ammessi(): void
    {
        $this->domande();
        $this->pannello('direttore');
        // senza interruttore «cartaceo»: la pagina non esiste
        $this->pagina(EsameResource\Pages\InserisciTestCartaceo::class, ['discente' => $this->s['x']->id])->assertNotFound();

        AvvioTest::impostaCartaceo($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id, 'BES');
        foreach (['super', 'adminReg', 'presidente', 'docComm'] as $k) {
            $this->pannello($k);
            $this->assertFalse(EsameResource\Pages\InserisciTestCartaceo::canAccess(['record' => $this->esame->fresh()]), $k);
        }
        $this->pannello('direttore');
        foreach (['nonAmmesso', 'ritirato', 'estraneo'] as $k) {
            $this->pagina(EsameResource\Pages\InserisciTestCartaceo::class, ['discente' => $this->s[$k]->id])->assertNotFound();
        }
    }

    public function test_pdf_cartaceo_permessi_e_nessuna_risposta_corretta(): void
    {
        $this->domande();
        $url = fn (string $k) => route('esami.test-cartaceo-pdf', [$this->esame, $this->s[$k]]);

        foreach (['direttore', 'super', 'adminReg', 'adminLoc'] as $chi) {
            $r = $this->actingAs($this->u[$chi])->get($url('x'));
            $r->assertOk();
            $this->assertStringContainsString('application/pdf', $r->headers->get('Content-Type'), $chi);
        }
        foreach (['docente', 'x', 'presidente', 'docComm'] as $chi) {
            $this->actingAs($this->u[$chi])->get($url('x'))->assertForbidden();
        }
        $this->actingAs($this->u['direttore']);
        $this->get($url('nonAmmesso'))->assertNotFound();
        $this->get($url('ritirato'))->assertNotFound();

        // il foglio non dipende dalle corrette e dice che è una bozza finché il test non è avviato
        $render = fn () => view('pdf.test-cartaceo', [
            'esame' => $this->esame->fresh()->load('corso'), 'discente' => $this->s['x'], 'domande' => TestScritto::domandePerDiscente($this->esame->fresh()),
            'regola' => TestScritto::regola($this->esame->fresh()), 'bozza' => true,
        ])->render();
        $prima = $render();
        TestDomanda::query()->update(['corretta' => 2]);
        $this->assertSame($prima, $render());
        $this->assertStringContainsString('BOZZA', $prima);
        $this->assertStringContainsString('Testx', $prima); // il cognome del discente compare
        $this->assertStringNotContainsString($this->s['x']->codice_fiscale, $prima, 'il codice fiscale non si mostra mai');

        // test senza domande: niente PDF
        TestDomanda::query()->delete();
        $this->get($url('x'))->assertNotFound();
    }

    public function test_gestione_riepilogo_e_completezza_vedono_il_test(): void
    {
        $this->domande();
        $this->avvia('x');
        foreach ($this->esame->testDomande()->orderBy('ordine')->pluck('id') as $i => $id) {
            $this->rispondi('x', $id, self::CORRETTE[$i])->assertOk();
        }
        $this->invia('x')->assertRedirect();

        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\GestioneEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Test scritto')->assertSee('5 domande')->assertSee('inviati 1 su 2 ammessi');

        $c = CompletezzaEsame::per($this->esame->fresh());
        $this->assertTrue($c[$this->s['x']->id]['test_inviato']);
        $this->assertFalse($c[$this->s['y']->id]['test_inviato']);
        $this->assertFalse($c[$this->s['x']->id]['completa'], 'il test non entra in «completa»');

        // la pagina mostra punteggio e risposte, con le corrette, alla commissione
        $this->pannello('docComm');
        Livewire::test(EsameResource\Pages\TestScrittoEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('5,0 su 5,0')->call('apriDettaglio', $this->s['x']->id)->assertSee('✓');
    }

    public function test_dettaglio_risposte_rifiuta_non_ammessi(): void
    {
        $this->domande();
        $this->pannello('direttore');
        $this->pagina(EsameResource\Pages\TestScrittoEsame::class)->call('apriDettaglio', $this->s['ritirato']->id)->assertNotFound();
    }
}
