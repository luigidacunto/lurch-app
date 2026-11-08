<?php

namespace Tests\Feature;

use App\Esami\PreparaTest;
use App\Esami\SvolgimentoTest;
use App\Esami\TestScritto;
use App\Filament\Resources\EsameResource;
use App\Models\TestConsegna;
use Livewire\Livewire;

/**
 * Test scritto, interruttore «mescola domande e risposte»: ordine casuale a ogni visualizzazione, ma la risposta salvata è
 * sempre l'indice ORIGINALE dell'opzione, quindi la correzione non dipende dalla posizione mostrata. Spento = ordine di
 * origine. DB di test: lurch_test.
 */
class EsameTestMescolaTest extends EsameTestScrittoBase
{
    private function mescola(bool $si): void
    {
        $this->esame->update(['test_mescola' => $si]);
        $this->esame->refresh();
    }

    public function test_spento_la_pagina_del_discente_mostra_le_lettere_in_ordine(): void
    {
        $this->domande(2);
        $this->avvia('x');
        $html = $this->come('x')->get(route('app.esame.test', $this->esame))->assertOk()->getContent();
        preg_match_all('/<span class="lettera">([A-D])<\/span>/', $html, $l);
        $this->assertSame(['A', 'B', 'C', 'D', 'A', 'B', 'C', 'D'], $l[1]);
    }

    public function test_spento_l_ordine_e_quello_di_origine(): void
    {
        $this->domande(5);
        $per = TestScritto::domandePerDiscente($this->esame, false);
        $this->assertSame(array_column(TestScritto::domandeComplete($this->esame), 'id'), array_column($per, 'id'));
        foreach ($per as $d) {
            $this->assertSame([0, 1, 2, 3], array_keys($d['opzioni']));
        }
    }

    public function test_acceso_cambia_ordine_ma_ogni_indice_conserva_il_suo_testo(): void
    {
        $this->domande(5);
        $originali = collect(TestScritto::domandeComplete($this->esame))->keyBy('id');

        $ordineDomande = [];
        $ordineOpzioni = [];
        for ($giro = 0; $giro < 60; $giro++) {
            $per = TestScritto::domandePerDiscente($this->esame, true);
            $this->assertSame($originali->keys()->sort()->values()->all(), collect($per)->pluck('id')->sort()->values()->all(), 'stesse domande');
            $ordineDomande[implode(',', array_column($per, 'id'))] = true;
            foreach ($per as $d) {
                $o = $originali[$d['id']];
                $this->assertEqualsCanonicalizing([0, 1, 2, 3], array_keys($d['opzioni']));
                foreach ($d['opzioni'] as $indice => $testo) {
                    $this->assertSame($o['opzioni'][$indice], $testo, 'l\'indice originale punta sempre allo stesso testo');
                }
                $ordineOpzioni[implode('', array_keys($d['opzioni']))] = true;
                $this->assertArrayNotHasKey('corretta', $d);
            }
        }
        $this->assertGreaterThan(1, count($ordineDomande), 'l\'ordine delle domande cambia');
        $this->assertGreaterThan(1, count($ordineOpzioni), 'l\'ordine delle opzioni cambia');
    }

    public function test_pagina_del_discente_senza_lettere_e_valore_per_indice_originale(): void
    {
        $this->domande(3);
        $this->mescola(true);
        $this->avvia('x');

        for ($giro = 0; $giro < 10; $giro++) {
            $html = $this->come('x')->get(route('app.esame.test', $this->esame))->assertOk()->getContent();
            // mescolato: nessuna lettera (solo il tondino); il data-opzione è l'indice originale
            $this->assertSame(0, preg_match('/<span class="lettera">\s*[A-D1-4]\s*<\/span>/', $html));
            $this->assertSame(12, preg_match_all('/<span class="lettera"><\/span>/', $html));
            // ogni testo «Opzione k della domanda n» sta sotto data-opzione="k"
            preg_match_all('/data-opzione="(\d)".*?<span class="testo">Opzione (\d) della domanda \d<\/span>/s', $html, $m, PREG_SET_ORDER);
            $this->assertCount(12, $m);
            foreach ($m as $riga) {
                $this->assertSame($riga[1], $riga[2]);
            }
            $this->assertStringNotContainsString('data-corretta', $html);
        }
    }

    public function test_la_correzione_non_dipende_dalla_posizione_mostrata(): void
    {
        $this->domande(5);
        $this->mescola(true);
        $this->avvia('x');
        $token = $this->token('x');

        // il discente risponde con l'indice originale giusto per ogni domanda, qualunque ordine abbia visto
        $ids = $this->esame->testDomande()->orderBy('ordine')->pluck('id')->all();
        foreach ($ids as $i => $id) {
            SvolgimentoTest::salvaRisposta($this->esame->fresh(), $this->s['x']->id, $token, TestConsegna::ORIGINE_ONLINE, null, $id, self::CORRETTE[$i]);
        }
        $this->invia('x', $token);

        $c = TestScritto::consegna($this->esame->fresh(), $this->s['x']->id);
        $this->assertSame('5,0 su 5,0', TestScritto::mostraPunteggio(TestScritto::punteggio($this->esame->fresh(), $c)));
    }

    public function test_si_imposta_dal_pannello_solo_in_bozza_e_si_vede_nel_riepilogo(): void
    {
        $this->domande(5);
        $this->pannello('direttore');
        $p = Livewire::test(EsameResource\Pages\DomandeTestEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Ordine al discente')->assertSee('come impostato');
        $p->callAction('configurazione', ['mescola' => true, 'penalita' => false])->assertHasNoActionErrors()->assertNotified('Punteggio salvato.');
        $this->assertTrue($this->esame->fresh()->test_mescola);
        $p->assertSee('mescolato a ogni visualizzazione');

        $p->callAction('configurazione', ['mescola' => false, 'penalita' => false]);
        $this->assertFalse($this->esame->fresh()->test_mescola);

        // dopo l'avvio è congelato come il punteggio
        $this->avvia('x');
        $this->expectException(\App\Esami\TestException::class);
        PreparaTest::salvaConfigurazione($this->esame->fresh(), $this->u['direttore'], ['penalita' => false, 'mescola' => true]);
    }
}
