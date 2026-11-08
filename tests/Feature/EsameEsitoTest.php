<?php

namespace Tests\Feature;

use App\Esami\ChiusuraEsame;
use App\Esami\RisultatiEsame;
use App\Filament\Resources\EsameResource;
use App\Models\Esame;
use App\Models\Iscrizione;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Esito dell'esame, parte pannello: tab «Esito», chiusura/riapertura dal pannello, commissione, blocco della configurazione a
 * esame chiuso, azione «non ammesso», PDF e CSV. DB di test: lurch_test.
 */
class EsameEsitoTest extends EsameChiusuraBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->domande(25);
    }

    private function pagina(string $classe, string $chi)
    {
        $this->pannello($chi);

        return Livewire::test($classe, ['record' => $this->esame->getRouteKey()]);
    }

    private function chiudi(): void
    {
        $this->discenteCompleto('x');
        $this->discenteCompleto('y', [3, 3, 3, 3], '3', 14, 8, 15, 10);
        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);
        $this->esame->refresh();
    }

    // ---- accesso e permessi della tab -----------------------------------------------------------

    public function test_chi_legge_vede_la_tab_esito_gli_altri_no(): void
    {
        foreach (['direttore', 'presidente', 'docComm', 'adminReg', 'adminLoc', 'super'] as $chi) {
            $this->pagina(EsameResource\Pages\EsitoEsame::class, $chi)->assertSuccessful();
        }
        foreach (['docente', 'x'] as $chi) {
            $this->actingAs($this->u[$chi]);
            $this->assertFalse(EsameResource\Pages\EsitoEsame::canAccess(['record' => $this->esame]), $chi);
        }
    }

    public function test_provvisorio_poi_chiuso_con_la_data_limite_di_riapertura(): void
    {
        $this->discenteCompleto('x');
        $this->pagina(EsameResource\Pages\EsitoEsame::class, 'direttore')->assertSee('PROVVISORIO')->assertSee('Testx');

        $this->discenteCompleto('y');
        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);
        $this->pagina(EsameResource\Pages\EsitoEsame::class, 'presidente')
            ->assertSee('Esame chiuso')
            ->assertSee($this->esame->fresh()->riapribileFinoAl()->format('d/m/Y'))
            ->assertDontSee('PROVVISORIO');
    }

    // ---- chiusura e riapertura dal pannello ------------------------------------------------------

    public function test_il_direttore_chiude_dal_pannello_ma_il_superadmin_no(): void
    {
        $this->discenteCompleto('x');
        $this->discenteCompleto('y');

        $this->pagina(EsameResource\Pages\EsitoEsame::class, 'super')->assertActionDisabled('chiudi');
        $this->assertFalse($this->esame->fresh()->isChiuso());

        $this->pagina(EsameResource\Pages\EsitoEsame::class, 'direttore')->callAction('chiudi');
        $this->assertTrue($this->esame->fresh()->isChiuso());
    }

    public function test_la_chiusura_dal_pannello_e_rifiutata_con_un_ammesso_incompleto(): void
    {
        $this->discenteCompleto('x');
        $this->finali('y');
        $this->pagina(EsameResource\Pages\EsitoEsame::class, 'direttore')->callAction('chiudi');

        $this->assertFalse($this->esame->fresh()->isChiuso());
        $this->assertSame(0, $this->esame->esiti()->count());
    }

    public function test_riapre_solo_il_superadmin_con_motivo(): void
    {
        $this->chiudi();

        $this->pagina(EsameResource\Pages\EsitoEsame::class, 'direttore')->assertActionHidden('riapri');
        $this->assertTrue($this->esame->fresh()->isChiuso());

        $this->pagina(EsameResource\Pages\EsitoEsame::class, 'super')->callAction('riapri', ['motivo' => 'Correzione di una finale']);
        $e = $this->esame->fresh();
        $this->assertSame(Esame::STATO_IN_CORSO, $e->stato);
        $this->assertSame('Correzione di una finale', $e->riaperto_motivo);
    }

    // ---- commissione e configurazione ------------------------------------------------------------

    public function test_la_commissione_la_impostano_admin_e_superadmin_non_il_direttore(): void
    {
        $this->assertFalse($this->esame->puoImpostareCommissione($this->u['direttore']));
        $this->assertTrue($this->esame->puoImpostareCommissione($this->u['super']));
        $this->assertTrue($this->esame->puoImpostareCommissione($this->u['adminReg']));
        $this->assertTrue($this->esame->puoImpostareCommissione($this->u['adminLoc']));
        $this->assertFalse($this->esame->puoImpostareCommissione($this->u['docComm']));

        $this->pagina(EsameResource\Pages\GestioneEsame::class, 'direttore')->assertActionHidden('commissione');
        $this->assertSame([$this->s['docComm']->id], $this->esame->docentiCommissione()->pluck('socio_id')->all());

        $this->pagina(EsameResource\Pages\GestioneEsame::class, 'adminReg')
            ->callAction('commissione', ['docenti' => [$this->s['docente']->id]]);
        $this->assertSame([$this->s['docente']->id], $this->esame->docentiCommissione()->pluck('socio_id')->all());
    }

    public function test_a_esame_chiuso_la_configurazione_non_si_tocca(): void
    {
        $this->chiudi();
        $commissione = $this->esame->docentiCommissione()->pluck('socio_id')->all();

        foreach (['direttore', 'super', 'adminReg'] as $chi) {
            $this->assertFalse($this->esame->fresh()->puoModificareConfigurazione($this->u[$chi]), $chi);
        }

        // Commissione
        $this->pagina(EsameResource\Pages\GestioneEsame::class, 'super')->assertActionHidden('commissione');
        $this->assertSame($commissione, $this->esame->docentiCommissione()->pluck('socio_id')->all());

        // Modifica base: la pagina non si apre
        $this->actingAs($this->u['direttore']);
        $this->assertFalse($this->esame->fresh()->puoModificareConfigurazione($this->u['direttore']));
        $this->esame->update(['stato' => Esame::STATO_IN_CORSO, 'chiuso_il' => null, 'chiuso_da' => null]); // per montare la pagina
        $edit = $this->pagina(EsameResource\Pages\EditEsame::class, 'direttore');
        DB::table('esami')->where('id', $this->esame->id)->update(['stato' => 'chiuso', 'chiuso_il' => now(), 'chiuso_da' => $this->s['direttore']->id]);
        $edit->fillForm(['titolo' => 'Nuovo', 'lap' => 10])->call('save'); // chiuso nel frattempo: il salvataggio è rifiutato lato server
        $this->assertSame('60.00', (string) $this->esame->fresh()->lap);

        // Laboratori
        $n = $this->esame->laboratori()->count();
        $this->pagina(EsameResource\Pages\GestioneLaboratori::class, 'super')->assertActionHidden('creaLaboratorio');
        $this->assertSame($n, $this->esame->laboratori()->count());

        // Gruppi
        $this->pagina(EsameResource\Pages\GestioneLaboratori::class, 'super')->assertActionHidden('nuovoGruppo', ['laboratorio_id' => $this->esame->laboratori()->first()->id]);
        $this->assertSame(0, $this->esame->gruppi()->count());
    }

    public function test_riaperto_l_esame_la_configurazione_torna_modificabile(): void
    {
        $this->chiudi();
        ChiusuraEsame::riapri($this->esame->fresh(), $this->u['super'], 'Correzione necessaria qui');
        $this->assertTrue($this->esame->fresh()->puoModificareConfigurazione($this->u['direttore']));
    }

    // ---- non ammesso -----------------------------------------------------------------------------

    public function test_il_direttore_segna_e_toglie_non_ammesso_dalla_pagina_discenti(): void
    {
        $isc = Iscrizione::where('corso_id', $this->corso->id)->where('partecipante_id', $this->s['y']->id)->first();

        $this->pagina(EsameResource\Pages\DiscentiEsame::class, 'super')->assertTableActionHidden('segnaNonAmmesso', $isc);
        $this->assertFalse((bool) $isc->fresh()->non_ammesso, 'il superadmin non può');

        $p = $this->pagina(EsameResource\Pages\DiscentiEsame::class, 'direttore');
        $p->callTableAction('segnaNonAmmesso', $isc, ['motivo' => ''])->assertHasTableActionErrors(['motivo' => 'required']);
        $this->assertFalse((bool) $isc->fresh()->non_ammesso, 'motivo obbligatorio');

        $p = $this->pagina(EsameResource\Pages\DiscentiEsame::class, 'direttore'); // pagina nuova: la modale con errori resta montata
        $p->callTableAction('segnaNonAmmesso', $isc, ['motivo' => 'Assenze oltre soglia']);
        $this->assertTrue((bool) $isc->fresh()->non_ammesso);
        $this->assertSame('Assenze oltre soglia', $isc->fresh()->non_ammesso_motivo);

        $p->callTableAction('togliNonAmmesso', $isc->fresh());
        $this->assertFalse((bool) $isc->fresh()->non_ammesso);
        $this->assertNull($isc->fresh()->non_ammesso_motivo);
    }

    public function test_non_ammesso_non_cancella_i_dati_e_riappaiono_togliendolo(): void
    {
        $this->discenteCompleto('y');
        \App\Esami\AmmissioneDiscente::segnaNonAmmesso($this->esame->fresh(), $this->u['direttore'], $this->s['y']->id, 'Motivo');
        $this->assertSame('non_ammesso', $this->riga(RisultatiEsame::per($this->esame->fresh()), 'y')['stato']);
        $this->assertSame(4, $this->esame->decisioniItinere()->where('discente_socio_id', $this->s['y']->id)->count());

        \App\Esami\AmmissioneDiscente::togliNonAmmesso($this->esame->fresh(), $this->u['direttore'], $this->s['y']->id);
        $this->assertSame('calcolato', $this->riga(RisultatiEsame::per($this->esame->fresh()), 'y')['stato']);
    }

    public function test_non_ammesso_rifiutato_a_esame_chiuso_e_ai_ritirati(): void
    {
        try {
            \App\Esami\AmmissioneDiscente::segnaNonAmmesso($this->esame->fresh(), $this->u['direttore'], $this->s['ritirato']->id, 'x');
            $this->fail('ritirato');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ritirato', $e->getMessage());
        }

        $this->chiudi();
        $this->expectException(\RuntimeException::class);
        \App\Esami\AmmissioneDiscente::segnaNonAmmesso($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id, 'x');
    }

    // ---- PDF e CSV -------------------------------------------------------------------------------

    public function test_pdf_e_csv_chi_puo_scaricare(): void
    {
        $this->chiudi();
        $pdf = route('esami.esito-pdf', [$this->esame, $this->s['x']]);
        $csv = route('esami.esiti-csv', $this->esame);

        foreach (['direttore', 'presidente', 'docComm', 'adminReg', 'super'] as $chi) {
            $this->actingAs($this->u[$chi])->get($pdf)->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->actingAs($this->u[$chi])->get($csv)->assertOk();
        }
        foreach (['docente', 'x'] as $chi) {
            $this->actingAs($this->u[$chi])->get($pdf)->assertForbidden();
            $this->actingAs($this->u[$chi])->get($csv)->assertForbidden();
        }
        // un estraneo (né ammesso né non ammesso) non ha un foglio
        $this->actingAs($this->u['direttore'])->get(route('esami.esito-pdf', [$this->esame, $this->s['estraneo']]))->assertNotFound();
        $this->actingAs($this->u['direttore'])->get(route('esami.esito-pdf', [$this->esame, $this->s['ritirato']]))->assertNotFound();
    }

    public function test_export_non_riportano_il_motivo_di_non_ammissione(): void
    {
        $this->discenteCompleto('x');
        $this->discenteCompleto('y');
        \App\Esami\AmmissioneDiscente::segnaNonAmmesso($this->esame->fresh(), $this->u['direttore'], $this->s['y']->id, 'Motivo riservato XYZ');

        $csv = $this->actingAs($this->u['direttore'])->get(route('esami.esiti-csv', $this->esame))->baseResponse->getFile()->getContent();
        $this->assertStringContainsString('non_ammesso', $csv);
        $this->assertStringNotContainsString('Motivo riservato XYZ', $csv);

        $html = view('pdf.esito-esame', [
            'esame' => $this->esame->fresh()->load('corso.direttore', 'chiusoDa'),
            'riga' => $this->riga(RisultatiEsame::per($this->esame->fresh()), 'y'),
            'provvisorio' => true,
            'pesi' => $this->esame->fresh()->modello_snapshot['pesi'],
        ])->render();
        $this->assertStringContainsString('NON AMMESSO', $html);
        $this->assertStringNotContainsString('Motivo riservato XYZ', $html);
    }

    public function test_csv_contenuto_provvisorio_e_dati_vietati(): void
    {
        $this->discenteCompleto('x');
        $this->discenteCompleto('y');
        $this->s['x']->update(['cognome' => '=HYPERLINK("http://x")', 'email' => 'segreta@example.it']);

        $r = $this->actingAs($this->u['direttore'])->get(route('esami.esiti-csv', $this->esame));
        $r->assertOk();
        $this->assertStringContainsString('_provvisorio.csv', (string) $r->headers->get('content-disposition'));
        $testo = $r->baseResponse->getFile()->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $testo, 'BOM');
        $this->assertStringContainsString('"Cognome";"Nome";"Codice fiscale";"Stato"', $testo);
        $this->assertStringContainsString(';"provvisorio";', $testo);
        $this->assertStringContainsString($this->s['y']->codice_fiscale, $testo, 'il codice fiscale è previsto');
        $this->assertStringContainsString('74,1', $testo, 'virgola decimale');
        $this->assertStringContainsString('"\'=HYPERLINK', $testo, 'formula neutralizzata');
        $this->assertStringNotContainsString('segreta@example.it', $testo);
        $this->assertStringNotContainsString('"=HYPERLINK', $testo);
    }

    public function test_csv_dopo_la_chiusura_non_cambia_se_cambiano_le_schede(): void
    {
        $this->chiudi();
        $prima = $this->actingAs($this->u['direttore'])->get(route('esami.esiti-csv', $this->esame))->baseResponse->getFile()->getContent();
        \Illuminate\Support\Facades\DB::table('esami')->where('id', $this->esame->id)->update(['lap' => 5]);
        \Illuminate\Support\Facades\DB::table('tcri_colloqui')->update(['punteggio' => '1.0']);
        $dopo = $this->actingAs($this->u['direttore'])->get(route('esami.esiti-csv', $this->esame))->baseResponse->getFile()->getContent();

        $this->assertSame($prima, $dopo);
        $this->assertStringNotContainsString('provvisorio', $dopo);
    }

    public function test_foglio_pdf_ha_lap_in_testa_codice_fiscale_e_niente_dati_vietati(): void
    {
        $this->chiudi();
        $this->s['x']->update(['email' => 'segreta@example.it']);
        $html = view('pdf.esito-esame', [
            'esame' => $this->esame->fresh()->load('corso.direttore', 'chiusoDa'),
            'riga' => $this->riga(RisultatiEsame::per($this->esame->fresh()), 'x'),
            'provvisorio' => false,
            'pesi' => $this->esame->modello_snapshot['pesi'],
        ])->render();

        $this->assertStringContainsString('LAP: 60%', $html);
        $this->assertStringContainsString($this->s['x']->codice_fiscale, $html);
        $this->assertStringContainsString('IDONEO', $html);
        $this->assertStringContainsString('74,1', $html);
        $this->assertStringContainsString('21,75', $html);
        $this->assertStringNotContainsString('segreta@example.it', $html);
        $this->assertStringNotContainsString('PROVVISORIO', $html);
    }
}
