<?php

namespace Tests\Feature;

use App\Esami\AvvioTest;
use App\Esami\PubblicazioneTest;
use App\Esami\SvolgimentoTest;
use App\Esami\TestException;
use App\Models\TestConsegna;
use App\Models\TestDomanda;

/**
 * Test scritto dopo l'invio: il discente rivede le sue risposte in sola lettura, SENZA corrette né punteggio, finché il
 * direttore non gli pubblica i risultati; da lì vede punteggio e correzioni, solo lui. Cartaceo come online.
 */
class EsameTestScrittoPubblicazioneTest extends EsameTestScrittoBase
{
    private function ids(): array
    {
        return $this->esame->testDomande()->orderBy('ordine')->pluck('id')->all();
    }

    /** Corrette B,A,D,C,B (1,0,3,2,1): con [1,0,0,0,1] tre giuste su cinque. */
    private function compilaEInvia(string $k, array $opzioni = [1, 0, 0, 0, 1]): void
    {
        foreach ($this->ids() as $i => $id) {
            $this->rispondi($k, $id, $opzioni[$i])->assertOk();
        }
        $this->invia($k)->assertRedirect();
    }

    private function pagina(string $k): string
    {
        return $this->come($k)->get(route('app.esame.test', $this->esame))->assertOk()->getContent();
    }

    private function senzaCsrf(string $html): string
    {
        return preg_replace('/"[A-Za-z0-9]{40}"|value="[A-Za-z0-9]{40}"/', 'CSRF', $html);
    }

    public function test_dopo_l_invio_rivede_le_sue_risposte_senza_corrette_ne_punteggio_e_senza_moduli(): void
    {
        $this->domande();
        $this->avvia('x');
        $this->compilaEInvia('x');

        $html = $this->pagina('x');
        $this->assertStringContainsString('Opzione 1 della domanda 1', $html);
        $this->assertStringContainsString('sola lettura', $html);
        $this->assertStringContainsString('La tua', $html);
        $salvataggio = [route('app.esame.test.invia', $this->esame), route('app.esame.test.risposta', $this->esame)];
        foreach (['Risultato del test scritto', 'opzione giusta', 'opzione sbagliata', '3,0 su 5,0', 'punteggio', 'btn-invia', 'data-opzione', ...$salvataggio] as $vietato) {
            $this->assertStringNotContainsString($vietato, $html, "prima della pubblicazione compare «{$vietato}»");
        }

        // la pagina non dipende dalle corrette: cambiandole tutte resta identica
        TestDomanda::query()->update(['corretta' => 2]);
        $this->assertSame($this->senzaCsrf($html), $this->senzaCsrf($this->pagina('x')));
    }

    public function test_dopo_l_invio_le_scritture_restano_rifiutate(): void
    {
        $this->domande();
        $this->avvia('x');
        $this->compilaEInvia('x');

        $this->rispondi('x', $this->ids()[0], 3)->assertStatus(409);
        $this->invia('x')->assertStatus(409);
        $this->assertSame(1, (int) TestConsegna::where('discente_socio_id', $this->s['x']->id)->first()->risposte()->where('domanda_id', $this->ids()[0])->value('opzione'));
    }

    public function test_pubblicazione_per_discente_mostra_punteggio_e_correzioni_solo_a_lui_ed_e_ritirabile(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compilaEInvia('x');
        $this->compilaEInvia('y', [0, 1, 2, 3, 0]);

        $this->assertSame(1, PubblicazioneTest::pubblica($this->esame->fresh(), $this->u['direttore'], [$this->s['x']->id]));

        $x = $this->pagina('x');
        $this->assertStringContainsString('Risultato del test scritto', $x);
        $this->assertStringContainsString('3,0 su 5,0', $x);
        $this->assertStringContainsString('Risposte giuste: 3 su 5', $x);
        $this->assertStringContainsString('opzione giusta', $x);
        $this->assertStringContainsString('opzione sbagliata', $x);

        // y non pubblicato: niente risultati, e la sua pagina non dipende dalle corrette
        $y = $this->pagina('y');
        $this->assertStringNotContainsString('Risultato del test scritto', $y);
        $this->assertStringNotContainsString('opzione giusta', $y);

        // ritiro: x torna alla sola lettura
        $this->assertSame(1, PubblicazioneTest::ritira($this->esame->fresh(), $this->u['direttore'], [$this->s['x']->id]));
        $this->assertStringNotContainsString('Risultato del test scritto', $this->pagina('x'));

        // «per tutti» pubblica a entrambi; idempotente
        $this->assertSame(2, PubblicazioneTest::pubblica($this->esame->fresh(), $this->u['direttore']));
        $this->assertSame(0, PubblicazioneTest::pubblica($this->esame->fresh(), $this->u['direttore']));
        $this->assertStringContainsString('0,0 su 5,0', $this->pagina('y'));
    }

    public function test_si_pubblica_solo_a_chi_ha_inviato_e_solo_il_direttore(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compilaEInvia('x');

        // y avviato ma non inviato, non ammesso, estraneo: ignorati
        $ids = [$this->s['y']->id, $this->s['nonAmmesso']->id, $this->s['estraneo']->id];
        $this->assertSame(0, PubblicazioneTest::pubblica($this->esame->fresh(), $this->u['direttore'], $ids));

        foreach (['presidente', 'docComm', 'super', 'adminReg'] as $chi) {
            try {
                PubblicazioneTest::pubblica($this->esame->fresh(), $this->u[$chi]);
                $this->fail("{$chi} non deve poter pubblicare");
            } catch (TestException $e) {
                $this->assertSame(403, $e->stato);
            }
        }
        $this->assertSame(0, $this->esame->testPubblicazioni()->count());
    }

    public function test_il_cartaceo_rivede_le_risposte_inserite_e_poi_le_correzioni(): void
    {
        $this->domande();
        AvvioTest::impostaCartaceo($this->esame->fresh(), $this->u['direttore'], $this->s['y']->id, 'DSA');
        $token = $this->token('y');

        // prima dell'inserimento: nulla nella app
        $this->come('y')->get(route('app.esame.test', $this->esame))->assertNotFound();

        foreach ($this->ids() as $i => $id) {
            SvolgimentoTest::salvaRisposta($this->esame->fresh(), $this->s['y']->id, $token, TestConsegna::ORIGINE_CARTACEO, $this->s['direttore']->id, $id, self::CORRETTE[$i]);
        }
        SvolgimentoTest::invia($this->esame->fresh(), $this->s['y']->id, $token, TestConsegna::ORIGINE_CARTACEO, $this->s['direttore']->id);

        $html = $this->pagina('y');
        $this->assertStringContainsString('Test svolto su carta', $html);
        $this->assertStringNotContainsString('Risultato del test scritto', $html);
        $this->assertStringContainsString($this->esame->titolo, $this->come('y')->get(route('app.esami'))->getContent());
        $this->come('y')->get(route('app.esame', $this->esame))->assertRedirect(route('app.esame.test', $this->esame));

        PubblicazioneTest::pubblica($this->esame->fresh(), $this->u['direttore'], [$this->s['y']->id]);
        $this->assertStringContainsString('5,0 su 5,0', $this->pagina('y'));
    }

    public function test_il_discente_che_ha_inviato_vede_l_esame_nell_elenco(): void
    {
        $this->domande();
        $this->avvia('x');
        $this->compilaEInvia('x');

        $this->assertStringContainsString($this->esame->titolo, $this->come('x')->get(route('app.esami'))->getContent());
        // gli altri discenti, mai avviati, no
        $this->come('y')->get(route('app.esame.test', $this->esame))->assertNotFound();
    }
}
