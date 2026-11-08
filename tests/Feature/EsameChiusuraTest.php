<?php

namespace Tests\Feature;

use App\Esami\AmmissioneDiscente;
use App\Esami\ChiusuraEsame;
use App\Esami\RisultatiEsame;
use App\Models\Esame;
use App\Models\Esito;
use App\Models\TcriItinereDecisione;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chiusura dell'esame: esempi numerici, completezza, chiusura, snapshot, riapertura (finestra di 15 giorni).
 * DB di test: lurch_test.
 */
class EsameChiusuraTest extends EsameChiusuraBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->domande(25);
    }

    private function chiudiConTutti(): void
    {
        // x completo (Caso 1), y completo; nonAmmesso e ritirato fuori dal conto.
        $this->discenteCompleto('x');
        $this->discenteCompleto('y', [3, 3, 3, 3], '3', 16, 0, 15, 10);
        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);
        $this->esame->refresh();
    }

    // ---- esempi numerici del piano -------------------------------------------------------------

    public function test_caso_1_74_1_idoneo(): void
    {
        $this->discenteCompleto('x');
        $r = $this->riga(RisultatiEsame::per($this->esame->fresh()), 'x');

        $this->assertSame('calcolato', $r['stato']);
        $this->assertSame('74.1', $r['finale']);
        $this->assertTrue($r['idoneo']);
        $this->assertSame('21.75', $r['prove']['itinere']['contributo']);
        $this->assertSame('8.00', $r['prove']['colloquio']['contributo']);
        $this->assertSame('30.00', $r['prove']['prova_pratica']['contributo']);
        $this->assertSame('14.40', $r['prove']['test_scritto']['contributo']);
    }

    public function test_caso_2_58_8_non_idoneo(): void
    {
        $this->discenteCompleto('x', [3, 3, 3, 3], '3', 14, 8, 15, 10); // I=12, C=3, P=14+8·0,25=16, T=15/25
        $r = $this->riga(RisultatiEsame::per($this->esame->fresh()), 'x');

        $this->assertSame('58.8', $r['finale']);
        $this->assertFalse($r['idoneo']);
        $this->assertSame('22.85', $r['prove']['prova_pratica']['contributo']);
    }

    public function test_caso_3_colloquio_decimale_72_9(): void
    {
        $this->discenteCompleto('x', [3.5, 3.5, 3.5, 3.5], '3.7', 20, 0, 20, 5); // I=14, P=20, T=20/25
        $r = $this->riga(RisultatiEsame::per($this->esame->fresh()), 'x');

        $this->assertSame('72.9', $r['finale']);
        $this->assertSame('7.40', $r['prove']['colloquio']['contributo']);
        $this->assertSame('28.57', $r['prove']['prova_pratica']['contributo']);
        $this->assertTrue($r['idoneo']);
    }

    public function test_limite_60_0_idoneo_e_59_9_con_penalita_non_idoneo(): void
    {
        // 20 domande: 4a = 19 giuste + 1 sbagliata (T=19); 4b = penalità (1 / 0,5 / 0,1): 19 giuste + 1 vuota (T=18,9).
        TestRispostaDelete::tutte($this->esame);
        $this->domande(20);
        $this->finali('x', [2.5, 2.5, 2.5, 2.5], '3', 14, 0); // I=10, C=3, P=14
        $this->testInviato('x', 19, 1);
        $this->assertSame('60.0', $this->riga(RisultatiEsame::per($this->esame->fresh()), 'x')['finale']);
        $this->assertTrue($this->riga(RisultatiEsame::per($this->esame->fresh()), 'x')['idoneo']);

        // stesso candidato con penalità: 19 giuste + 1 vuota
        TestRispostaDelete::consegne($this->esame);
        $this->esame->update(['test_penalita_attive' => true, 'test_punti_corretta' => '1.0', 'test_penalita_errata' => '0.5', 'test_penalita_vuota' => '0.1']);
        $this->testInviato('x', 19, 0);
        $r = $this->riga(RisultatiEsame::per($this->esame->fresh()), 'x');
        $this->assertSame('59.9', $r['finale']);
        $this->assertFalse($r['idoneo']);
    }

    public function test_caso_5_penalita_piena_66_6(): void
    {
        TestRispostaDelete::tutte($this->esame);
        $this->domande(20);
        $this->esame->update(['test_penalita_attive' => true, 'test_punti_corretta' => '1.0', 'test_penalita_errata' => '0.5', 'test_penalita_vuota' => '0.2']);
        $this->finali('x', [3, 3, 3, 3], '3.5', 20, 0); // I=12, C=3.5, P=20
        $this->testInviato('x', 15, 3);                 // 2 vuote → T = 15 − 1,5 − 0,4 = 13,1
        $r = $this->riga(RisultatiEsame::per($this->esame->fresh()), 'x');

        $this->assertSame('66.6', $r['finale']);
        $this->assertSame('13.10', $r['prove']['test_scritto']['contributo']);
    }

    // ---- completezza -----------------------------------------------------------------------------

    public function test_incompleto_se_manca_il_test_o_una_finale(): void
    {
        $this->finali('x'); // senza test
        $r = $this->riga(RisultatiEsame::per($this->esame->fresh()), 'x');
        $this->assertSame('incompleto', $r['stato']);
        $this->assertNull($r['finale']);
        $this->assertSame(['test scritto'], $r['mancanti']);

        $this->discenteCompleto('y');
        TcriItinereDecisione::where('discente_socio_id', $this->s['y']->id)->limit(1)->delete();
        $this->assertContains("punteggi finali d'area", $this->riga(RisultatiEsame::per($this->esame->fresh()), 'y')['mancanti']);
    }

    public function test_la_chiusura_e_bloccata_con_un_ammesso_incompleto(): void
    {
        $this->discenteCompleto('x');
        $this->finali('y'); // y senza test
        $motivo = ChiusuraEsame::motivoNonChiudibile($this->esame->fresh(), $this->u['direttore']);
        $this->assertStringContainsString('Mancano dati per 1 discente ammesso', $motivo);

        $this->expectException(\RuntimeException::class);
        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);
    }

    public function test_si_esce_dal_blocco_segnando_non_ammesso_il_discente_incompleto(): void
    {
        $this->discenteCompleto('x');
        $this->finali('y');
        AmmissioneDiscente::segnaNonAmmesso($this->esame->fresh(), $this->u['direttore'], $this->s['y']->id, 'assente al test');

        $this->assertNull(ChiusuraEsame::motivoNonChiudibile($this->esame->fresh(), $this->u['direttore']));
    }

    public function test_senza_discenti_ammessi_non_si_chiude(): void
    {
        DB::table('iscrizioni')->where('corso_id', $this->corso->id)->update(['non_ammesso' => true, 'non_ammesso_motivo' => 'x']);
        $this->assertSame("L'esame non ha discenti ammessi.", ChiusuraEsame::motivoNonChiudibile($this->esame->fresh(), $this->u['direttore']));
    }

    public function test_chiudono_solo_il_direttore_e_a_esame_in_corso(): void
    {
        $this->discenteCompleto('x');
        $this->discenteCompleto('y');
        foreach (['super', 'adminReg', 'presidente', 'docComm', 'docente'] as $chi) {
            $this->assertNotNull(ChiusuraEsame::motivoNonChiudibile($this->esame->fresh(), $this->u[$chi]), $chi);
        }
        $this->assertStringContainsString('direttore del corso', ChiusuraEsame::motivoNonChiudibile($this->esame->fresh(), $this->u['super']));
        $this->assertNull(ChiusuraEsame::motivoNonChiudibile($this->esame->fresh(), $this->u['direttore']));

        $this->esame->update(['stato' => Esame::STATO_BOZZA]);
        $this->assertSame("L'esame non è ancora attivo.", ChiusuraEsame::motivoNonChiudibile($this->esame->fresh(), $this->u['direttore']));
    }

    public function test_direttore_senza_utente_del_pannello_ha_il_tooltip_dedicato(): void
    {
        $this->discenteCompleto('x');
        $this->u['direttore']->update(['socio_id' => null]);
        $m = ChiusuraEsame::motivoNonChiudibile($this->esame->fresh(), $this->u['super']);
        $this->assertStringContainsString('non ha un utente del pannello collegato', $m);
    }

    // ---- chiusura e snapshot ---------------------------------------------------------------------

    public function test_chiusura_scrive_lo_snapshot_con_non_ammessi_e_senza_ritirati(): void
    {
        $this->chiudiConTutti();

        $this->assertTrue($this->esame->isChiuso());
        $this->assertNotNull($this->esame->chiuso_il);
        $this->assertSame($this->s['direttore']->id, $this->esame->chiuso_da);
        $this->assertSame(3, Esito::count());           // x, y, nonAmmesso: nessun ritirato
        $na = Esito::where('discente_socio_id', $this->s['nonAmmesso']->id)->first();
        $this->assertSame('non_ammesso', $na->stato);
        $this->assertSame('assenze', $na->motivo_non_ammesso);
        $this->assertNull($na->punteggio_finale);
        $x = Esito::where('discente_socio_id', $this->s['x']->id)->first();
        $this->assertSame('calcolato', $x->stato);
        $this->assertSame('74.1', (string) $x->punteggio_finale);
        $this->assertSame('60.00', (string) $x->lap);
        $this->assertSame('TCRI', $x->tipo_codice);
    }

    public function test_snapshot_invariato_anche_se_cambiano_lap_modello_o_schede(): void
    {
        $this->chiudiConTutti();
        $prima = RisultatiEsame::per($this->esame->fresh());

        DB::table('esami')->where('id', $this->esame->id)->update(['lap' => 99]);
        DB::table('esame_modelli')->where('tipo_codice', 'TCRI')->update(['struttura' => json_encode(['x' => 1])]);
        TcriItinereDecisione::where('discente_socio_id', $this->s['x']->id)->update(['punteggio' => '1.0']);
        DB::table('tcri_colloqui')->update(['punteggio' => '1.0']);

        $dopo = RisultatiEsame::per($this->esame->fresh());
        $this->assertEquals(
            array_map(fn ($r) => [$r['socio']->id, $r['stato'], $r['finale'], $r['idoneo'], $r['lap'], $r['prove']], $prima),
            array_map(fn ($r) => [$r['socio']->id, $r['stato'], $r['finale'], $r['idoneo'], $r['lap'], $r['prove']], $dopo),
        );
        $this->assertSame('60.00', $dopo[0]['lap']);
    }

    public function test_un_esito_non_si_modifica(): void
    {
        $this->chiudiConTutti();
        $this->expectException(\LogicException::class);
        Esito::first()->update(['punteggio_finale' => '10.0']);
    }

    public function test_secondo_chiudi_e_rifiutato(): void
    {
        $this->chiudiConTutti();
        $this->expectException(\RuntimeException::class);
        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);
    }

    public function test_il_check_del_db_impone_chiuso_il_con_lo_stato(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('esami')->where('id', $this->esame->id)->update(['stato' => 'chiuso']); // senza chiuso_il
    }

    // ---- riapertura ------------------------------------------------------------------------------

    public function test_riapertura_solo_superadmin_con_motivo_e_cancella_gli_esiti(): void
    {
        $this->chiudiConTutti();

        foreach (['direttore', 'adminReg', 'presidente'] as $chi) {
            $this->assertNotNull(ChiusuraEsame::motivoNonRiapribile($this->esame->fresh(), $this->u[$chi]), $chi);
        }
        try {
            ChiusuraEsame::riapri($this->esame->fresh(), $this->u['super'], 'corto');
            $this->fail('motivo troppo corto');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('obbligatorio', $e->getMessage());
        }

        ChiusuraEsame::riapri($this->esame->fresh(), $this->u['super'], 'Errore nella finale di un discente');
        $this->esame->refresh();
        $this->assertSame(Esame::STATO_IN_CORSO, $this->esame->stato);
        $this->assertNull($this->esame->chiuso_il);
        $this->assertNull($this->esame->chiuso_da);
        $this->assertSame($this->u['super']->id, $this->esame->riaperto_da);
        $this->assertSame('Errore nella finale di un discente', $this->esame->riaperto_motivo);
        $this->assertSame(0, Esito::count());

        // seconda chiusura + seconda riapertura: sovrascrive i tre campi, nessuno storico
        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);
        $this->assertSame(3, Esito::count());
        ChiusuraEsame::riapri($this->esame->fresh(), $this->u['super'], 'Altro motivo valido');
        $this->assertSame('Altro motivo valido', $this->esame->fresh()->riaperto_motivo);
    }

    public function test_riapertura_non_tocca_interruttore_e_avvii(): void
    {
        $this->esame->apriProvaFinale();
        $this->chiudiConTutti();
        $avvii = $this->esame->testAvvii()->count();
        ChiusuraEsame::riapri($this->esame->fresh(), $this->u['super'], 'Correzione necessaria');
        $this->assertTrue($this->esame->fresh()->provaFinaleAperta());
        $this->assertSame($avvii, $this->esame->testAvvii()->count());
    }

    public function test_finestra_di_15_giorni_di_calendario(): void
    {
        Carbon::setTestNow('2026-11-02 10:30:00');
        $this->chiudiConTutti();
        $this->assertSame('2026-11-17 23:59:59', $this->esame->riapribileFinoAl()->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-11-17 23:59:59');
        $this->assertNull(ChiusuraEsame::motivoNonRiapribile($this->esame->fresh(), $this->u['super']));

        Carbon::setTestNow('2026-11-18 00:00:00');
        $this->assertStringContainsString('più di 15 giorni', ChiusuraEsame::motivoNonRiapribile($this->esame->fresh(), $this->u['super']));
        try {
            ChiusuraEsame::riapri($this->esame->fresh(), $this->u['super'], 'Troppo tardi per correggere');
            $this->fail('deve rifiutare');
        } catch (\RuntimeException $e) {
            $this->assertTrue($this->esame->fresh()->isChiuso());
        }
        Carbon::setTestNow();
    }

    public function test_dopo_una_riapertura_la_finestra_riparte_dalla_nuova_chiusura(): void
    {
        Carbon::setTestNow('2026-11-02 10:00:00');
        $this->chiudiConTutti();
        Carbon::setTestNow('2026-11-10 10:00:00');
        ChiusuraEsame::riapri($this->esame->fresh(), $this->u['super'], 'Correzione di una finale');
        Carbon::setTestNow('2026-11-12 10:00:00');
        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);

        Carbon::setTestNow('2026-11-26 12:00:00'); // oltre 15 giorni dalla prima chiusura, dentro per la seconda
        $this->assertNull(ChiusuraEsame::motivoNonRiapribile($this->esame->fresh(), $this->u['super']));
        Carbon::setTestNow();
    }
}

/** Piccolo aiuto dei test: svuota domande/consegne per rifare il test con un'altra configurazione. */
final class TestRispostaDelete
{
    public static function consegne(Esame $e): void
    {
        DB::table('test_risposte')->delete();
        DB::table('test_consegne')->where('esame_id', $e->id)->delete();
    }

    public static function tutte(Esame $e): void
    {
        self::consegne($e);
        DB::table('test_avvii')->where('esame_id', $e->id)->delete();
        DB::table('test_domande')->where('esame_id', $e->id)->delete();
    }
}
