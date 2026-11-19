<?php

namespace Tests\Feature;

use App\Esami\ChiusuraEsame;
use App\Esami\PubblicazioneEsito;
use App\Esami\TestException;
use App\Filament\Resources\EsameResource;
use Livewire\Livewire;

/**
 * Esito finale pubblicato al discente: solo con tutte le prove fissate, solo chi gestisce l'esame; nella app la pagina
 * dell'esito esiste (e il pulsante compare) solo dopo la pubblicazione e solo per il discente pubblicato.
 */
class EsamePubblicazioneEsitoTest extends EsameChiusuraBase
{
    private function esito(string $k)
    {
        return $this->come($k)->get(route('app.esame.esito', $this->esame));
    }

    private function hub(string $k): string
    {
        return $this->come($k)->get(route('app.esame', $this->esame))->assertOk()->getContent();
    }

    public function test_si_pubblica_solo_con_tutte_le_prove_e_lo_vede_solo_il_discente_pubblicato(): void
    {
        $this->domande(25);
        $this->discenteCompleto('x');
        $this->testInviato('y', 18, 7); // y: solo il test, mancano le altre prove

        $this->esito('x')->assertNotFound();
        $this->assertStringNotContainsString(route('app.esame.esito', $this->esame), $this->hub('x'));
        $this->assertStringContainsString('Test scritto', $this->hub('x'));

        try {
            PubblicazioneEsito::pubblica($this->esame->fresh(), $this->u['direttore'], $this->s['y']->id);
            $this->fail('y incompleto non deve essere pubblicabile');
        } catch (TestException $e) {
            $this->assertSame(409, $e->stato);
        }

        PubblicazioneEsito::pubblica($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id);
        $x = $this->esito('x')->assertOk()->getContent();
        $this->assertStringContainsString('su 100', $x);
        $this->assertStringContainsString('In centesimi', $x);
        $this->assertStringContainsString('LAP (soglia di idoneità)', $x);
        $this->assertMatchesRegularExpression('/Idoneo|Non idoneo/', $x);
        foreach (['Itinere', 'Colloquio', 'Prova pratica', 'Test scritto'] as $prova) {
            $this->assertStringContainsString($prova, $x);
        }
        $this->assertStringContainsString(route('app.esame.esito', $this->esame), $this->hub('x'));
        $this->esito('y')->assertNotFound();

        PubblicazioneEsito::ritira($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id);
        $this->esito('x')->assertNotFound();
    }

    public function test_pubblica_a_tutti_salta_gli_incompleti_e_poi_si_esaurisce(): void
    {
        $this->domande(25);
        $this->discenteCompleto('x');
        $this->testInviato('y', 18, 7);

        $this->assertSame(1, PubblicazioneEsito::pubblicaTutti($this->esame->fresh(), $this->u['direttore']));
        $this->assertSame(0, PubblicazioneEsito::pubblicaTutti($this->esame->fresh(), $this->u['direttore']));
        $this->esito('y')->assertNotFound();

        // y si completa: il secondo giro pubblica solo lui
        $this->finali('y');
        $this->assertSame([$this->s['y']->id], PubblicazioneEsito::daPubblicare($this->esame->fresh()));
        $this->assertSame(1, PubblicazioneEsito::pubblicaTutti($this->esame->fresh(), $this->u['direttore']));
        $this->esito('y')->assertOk();
        $this->assertSame([], PubblicazioneEsito::daPubblicare($this->esame->fresh()));
    }

    public function test_pubblicano_direttore_admin_e_superadmin_non_presidente_ne_docenti(): void
    {
        $this->domande(25);
        $this->discenteCompleto('x');
        $x = $this->s['x']->id;

        foreach (['presidente', 'docComm', 'docente'] as $chi) {
            try {
                PubblicazioneEsito::pubblica($this->esame->fresh(), $this->u[$chi], $x);
                $this->fail("{$chi} non deve poter pubblicare l'esito");
            } catch (TestException $e) {
                $this->assertSame(403, $e->stato);
            }
        }
        foreach (['direttore', 'super', 'adminReg', 'adminLoc'] as $chi) {
            PubblicazioneEsito::ritira($this->esame->fresh(), $this->u[$chi], $x);
            PubblicazioneEsito::pubblica($this->esame->fresh(), $this->u[$chi], $x);
            $this->assertTrue($this->esame->esitoPubblicazioni()->where('discente_socio_id', $x)->whereNotNull('pubblicato_il')->exists(), $chi);
        }
    }

    public function test_a_esame_chiuso_si_pubblica_e_si_legge_l_esito_salvato(): void
    {
        $this->domande(25);
        $this->discenteCompleto('x');
        $this->discenteCompleto('y');
        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);

        PubblicazioneEsito::pubblica($this->esame->fresh(), $this->u['adminReg'], $this->s['x']->id);
        $this->esito('x')->assertOk()->assertSee('su 100');
    }

    public function test_azioni_del_pannello_nella_pagina_discenti(): void
    {
        $this->domande(25);
        $this->discenteCompleto('x');
        $this->discenteCompleto('y');
        $iscr = \App\Models\Iscrizione::where('corso_id', $this->corso->id)->where('partecipante_id', $this->s['x']->id)->first();

        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\RiepilogoDiscentiEsame::class, ['record' => $this->esame->getRouteKey()])
            ->callTableAction('pubblicaEsito', $iscr)
            ->assertHasNoTableActionErrors()
            ->callAction('pubblicaEsitoTutti')
            ->assertActionDisabled('pubblicaEsitoTutti');
        $this->assertSame(2, $this->esame->esitoPubblicazioni()->whereNotNull('pubblicato_il')->count());
    }
}
