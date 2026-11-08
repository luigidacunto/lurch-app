<?php

namespace Tests\Feature;

use App\Esami\AvvioTest;
use App\Esami\PermessiEsame;
use App\Esami\PreparaTest;
use App\Esami\SvolgimentoTest;
use App\Esami\TestException;
use App\Esami\TestScritto;
use App\Models\Esame;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use App\Models\TestRisposta;

/**
 * Esami: test scritto (preparazione, avvio per discente, svolgimento online, cartaceo, punteggio, permessi).
 * La fuga di dati verso il discente è in EsameTestScrittoLeakTest.
 */
class EsameTestScrittoTest extends EsameTestScrittoBase
{
    private function ids(): array
    {
        return $this->esame->testDomande()->orderBy('ordine')->pluck('id')->all();
    }

    /** Il discente $k risponde a tutte le domande con le opzioni date e invia. */
    private function compila(string $k, array $opzioni, bool $invia = true): void
    {
        foreach ($this->ids() as $i => $id) {
            if (array_key_exists($i, $opzioni)) {
                $this->rispondi($k, $id, $opzioni[$i])->assertOk();
            }
        }
        if ($invia) {
            $this->invia($k)->assertRedirect(route('app.esame.test', $this->esame));
        }
    }

    private function punteggio(string $k): ?string
    {
        $c = TestScritto::consegna($this->esame->fresh(), $this->s[$k]->id);

        return TestScritto::mostraPunteggio(TestScritto::punteggio($this->esame->fresh(), $c));
    }

    // ---- Permessi ----

    public function test_matrice_permessi_del_pannello(): void
    {
        $this->domande();
        $e = $this->esame->fresh();

        $preparano = ['direttore', 'super', 'adminReg', 'adminLoc'];
        foreach (array_keys($this->u) as $k) {
            $this->assertSame(in_array($k, $preparano, true), $e->puoPreparareTest($this->u[$k]), "prepara: $k");
            $this->assertSame($k === 'direttore', $e->puoAvviareTest($this->u[$k]), "avvia: $k");
            $this->assertSame(in_array($k, $preparano, true), $e->puoScaricareTestCartaceo($this->u[$k]), "pdf: $k");
        }
        foreach (['direttore', 'presidente', 'docComm', 'super', 'adminReg', 'adminLoc'] as $k) {
            $this->assertTrue($e->puoVedereTestScritto($this->u[$k]), "vede: $k");
        }
        foreach (['docente', 'x'] as $k) {
            $this->assertFalse($e->puoVedereTestScritto($this->u[$k]), "non vede: $k");
        }
    }

    public function test_domande_preparabili_in_bozza_e_sopravvivono_all_attivazione(): void
    {
        $this->esame->riportaInBozza();
        $e = $this->esame->fresh();
        $this->assertNull($e->modello_snapshot);

        foreach (['direttore', 'super', 'adminReg', 'adminLoc'] as $k) {
            $this->assertTrue($e->puoVedereDomandeTest($this->u[$k]), "domande in bozza: $k");
            $this->assertTrue($e->puoPreparareTest($this->u[$k]), "prepara in bozza: $k");
        }
        foreach (['presidente', 'docComm', 'docente', 'x'] as $k) {
            $this->assertFalse($e->puoVedereDomandeTest($this->u[$k]), "non vede in bozza: $k");
        }
        // Il test scritto (avvio e risultati) resta chiuso finché l'esame non è attivo.
        $this->assertFalse($e->puoVedereTestScritto($this->u['direttore']));

        PreparaTest::creaDomanda($e, $this->u['direttore'], 'Domanda in bozza', ['a', 'b', 'c', 'd'], 1);
        $e->attiva();

        $e = $e->fresh();
        $this->assertSame(1, $e->testDomande()->count());
        $this->assertTrue($e->puoVedereTestScritto($this->u['direttore']));
    }

    public function test_docente_semplice_e_discente_non_vedono_il_test_in_pwa(): void
    {
        $this->domande();
        foreach (['docente', 'x', 'estraneo'] as $k) {
            $this->come($k)->get(route('app.esame.test.anteprima', $this->esame))->assertNotFound();
        }
        foreach (['direttore', 'presidente', 'docComm'] as $k) {
            $this->come($k)->get(route('app.esame.test.anteprima', $this->esame))->assertOk()->assertSee('Domanda numero 1 zz');
        }
    }

    public function test_admin_prepara_ma_non_avvia(): void
    {
        $this->domande();
        $this->expectException(TestException::class);
        AvvioTest::avvia($this->esame->fresh(), $this->u['super']);
    }

    // ---- Preparazione ----

    public function test_preparazione_in_bozza_e_validazioni(): void
    {
        $e = $this->esame->fresh();
        $d = PreparaTest::creaDomanda($e, $this->u['adminReg'], 'Testo?', ['a', 'b', 'c', 'd'], 2);
        $this->assertSame(1, $d->ordine);
        $this->assertSame(2, PreparaTest::creaDomanda($e, $this->u['direttore'], 'Altra?', ['a', 'b', 'c', 'd'], 0)->ordine);

        foreach ([['  ', ['a', 'b', 'c', 'd'], 0], ['T', ['a', 'b', 'c'], 0], ['T', ['a', 'b', 'c', ' '], 0], ['T', ['a', 'b', 'c', 'd'], 4]] as [$t, $o, $c]) {
            try {
                PreparaTest::creaDomanda($e, $this->u['direttore'], $t, $o, $c);
                $this->fail('Domanda non valida accettata');
            } catch (TestException $ex) {
                $this->assertSame(422, $ex->stato);
            }
        }
        $this->assertSame(2, $e->testDomande()->count());

        // Eliminando la prima, la numerazione si compatta
        PreparaTest::eliminaDomanda($e, $this->u['direttore'], $d);
        $this->assertSame([1], $e->testDomande()->pluck('ordine')->all());

        // chi non gestisce l'esame non prepara
        $this->expectException(TestException::class);
        PreparaTest::creaDomanda($e, $this->u['docComm'], 'Testo?', ['a', 'b', 'c', 'd'], 1);
    }

    public function test_configurazione_del_punteggio(): void
    {
        $e = $this->esame->fresh();
        $this->assertFalse(TestScritto::configurazione($e)['penalita']);
        $this->assertTrue(TestScritto::configurazione($e)['obbligatorie']);

        PreparaTest::salvaConfigurazione($e, $this->u['direttore'], ['penalita' => true, 'corretta' => '2', 'errata' => '1,5', 'vuota' => '0.5']);
        $c = TestScritto::configurazione($e->fresh());
        $this->assertSame(['2.0', '1.5', '0.5'], [$c['corretta'], $c['errata'], $c['vuota']]);
        $this->assertFalse($c['obbligatorie']);

        foreach ([['penalita' => true, 'corretta' => '1.25', 'errata' => '1', 'vuota' => '0'],   // 2 decimali
                  ['penalita' => true, 'corretta' => '0', 'errata' => '1', 'vuota' => '0'],       // corretta zero
                  ['penalita' => true, 'corretta' => '1', 'errata' => '0', 'vuota' => '0'],       // nessuna penalità reale
                  ['penalita' => true, 'corretta' => 'a', 'errata' => '1', 'vuota' => '0']] as $dati) {
            try {
                PreparaTest::salvaConfigurazione($e, $this->u['direttore'], $dati);
                $this->fail('Configurazione non valida accettata: '.json_encode($dati));
            } catch (TestException $ex) {
                $this->assertSame(422, $ex->stato);
            }
        }

        // penalità spente: i valori tornano 1/0/0 e le risposte obbligatorie
        PreparaTest::salvaConfigurazione($e, $this->u['direttore'], ['penalita' => false]);
        $this->assertTrue(TestScritto::configurazione($e->fresh())['obbligatorie']);
    }

    public function test_dopo_l_avvio_solo_refusi_e_si_torna_in_bozza_se_nessuno_ha_inviato(): void
    {
        $d = $this->domande()[0];
        $e = $this->esame->fresh();
        $this->avvia('x');
        $e->refresh();
        $this->assertSame(Esame::TEST_AVVIATO, $e->statoTest());

        $this->assertFalse($e->puoPreparareTest($this->u['direttore']));
        $this->assertTrue($e->puoCorreggereRefusiTest($this->u['super']));
        try {
            PreparaTest::creaDomanda($e, $this->u['direttore'], 'T?', ['a', 'b', 'c', 'd'], 0);
            $this->fail('Domanda aggiunta a test avviato');
        } catch (TestException $ex) {
            $this->assertSame(403, $ex->stato);
        }
        try {
            PreparaTest::aggiornaDomanda($e, $this->u['direttore'], $d, 'Nuovo testo', ['a', 'b', 'c', 'd'], 3); // corretta cambiata
            $this->fail('Corretta cambiata a test avviato');
        } catch (TestException $ex) {
            $this->assertSame(422, $ex->stato);
        }
        $ok = PreparaTest::aggiornaDomanda($e, $this->u['direttore'], $d, 'Refuso corretto', ['uno', 'due', 'tre', 'quattro'], (int) $d->corretta);
        $this->assertSame('Refuso corretto', $ok->testo);
        $this->expectException(TestException::class);
        PreparaTest::salvaConfigurazione($e, $this->u['direttore'], ['penalita' => false]);
    }

    public function test_riporta_in_bozza_riapre_le_modifiche_e_non_serve_con_consegne(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $e = $this->esame->fresh();

        AvvioTest::riportaInBozza($e, $this->u['direttore']);
        $this->assertSame(Esame::TEST_BOZZA, $e->fresh()->statoTest());
        $this->assertTrue($e->fresh()->puoPreparareTest($this->u['adminLoc']));

        // dopo un invio non si torna più in bozza
        $this->avvia('x');
        $this->compila('x', self::CORRETTE);
        $this->assertSame(Esame::TEST_CON_CONSEGNE, $this->esame->fresh()->statoTest());
        $this->assertNotNull(AvvioTest::motivoNonRiportabileInBozza($this->esame->fresh(), $this->u['direttore']));
        $this->expectException(TestException::class);
        AvvioTest::riportaInBozza($this->esame->fresh(), $this->u['direttore']);
    }

    public function test_modificare_in_bozza_scarta_le_compilazioni_non_inviate(): void
    {
        $this->domande();
        $this->avvia('x');
        $this->rispondi('x', $this->ids()[0], 1)->assertOk();
        $this->assertSame(1, TestRisposta::count());

        // disattivare conserva le risposte salvate
        AvvioTest::disattiva($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id);
        $this->assertSame(1, TestRisposta::count());
        // riattivando senza toccare le domande, le ritrova
        $this->avvia('x');
        $this->assertSame(1, TestRisposta::count());

        // riportando in bozza e modificando le domande, la compilazione non inviata non ha più senso
        AvvioTest::riportaInBozza($this->esame->fresh(), $this->u['direttore']);
        PreparaTest::creaDomanda($this->esame->fresh(), $this->u['direttore'], 'Nuova?', ['a', 'b', 'c', 'd'], 0);
        $this->assertSame(0, TestRisposta::count());
        $this->assertSame(0, TestConsegna::count());
    }

    // ---- Avvio ----

    public function test_avvio_per_tutti_e_per_selezionati_solo_ammessi_ed_e_idempotente(): void
    {
        $this->domande();

        // selezionati: x, un non ammesso, un ritirato, un estraneo → solo x
        $n = AvvioTest::avvia($this->esame->fresh(), $this->u['direttore'], array_map(fn ($k) => $this->s[$k]->id, ['x', 'nonAmmesso', 'ritirato', 'estraneo']));
        $this->assertSame(1, $n);
        $this->assertSame([$this->s['x']->id], $this->esame->testAvvii()->pluck('discente_socio_id')->all());

        $data = TestAvvio::first()->avviato_il;
        // tutti: ora anche y, x non cambia (né data né token)
        $token = TestAvvio::first()->token;
        $this->assertSame(1, AvvioTest::avvia($this->esame->fresh(), $this->u['direttore']));
        $this->assertSame(0, AvvioTest::avvia($this->esame->fresh(), $this->u['direttore']));
        $x = TestAvvio::where('discente_socio_id', $this->s['x']->id)->first();
        $this->assertEquals($data, $x->avviato_il);
        $this->assertSame($token, $x->token);
        $this->assertSame(2, TestAvvio::count());
    }

    public function test_avvio_richiede_domande_valide_ed_esame_in_corso(): void
    {
        try {
            AvvioTest::avvia($this->esame->fresh(), $this->u['direttore']);
            $this->fail('Avvio senza domande');
        } catch (TestException $e) {
            $this->assertStringContainsString('domande', $e->getMessage());
        }

        $this->domande();
        $this->conPenalita();
        $this->esame->update(['test_penalita_errata' => '0.0', 'test_penalita_vuota' => '0.0']); // penalità attive ma a zero
        $this->assertNotNull($this->esame->fresh()->motivoTestNonAvviabile());
        $this->esame->update(['test_penalita_errata' => '1.0']);

        $this->assertSame(2, AvvioTest::avvia($this->esame->fresh(), $this->u['direttore']));

        // esame chiuso: nessun avvio nuovo
        $this->esame->update(['stato' => Esame::STATO_CHIUSO, 'chiuso_il' => now()]);
        $this->expectException(TestException::class);
        AvvioTest::avvia($this->esame->fresh(), $this->u['direttore']);
    }

    public function test_avvio_indipendente_da_prova_finale_e_da_ogni_data(): void
    {
        $this->domande();
        $this->assertNull($this->esame->fresh()->prova_finale_aperta_il);
        foreach (['2020-01-01', '2099-12-31'] as $data) {
            $this->corso->update(['data_inizio' => $data, 'data_fine' => $data]);
            $this->assertSame(1, AvvioTest::avvia($this->esame->fresh(), $this->u['direttore'], [$this->s['x']->id]));
            AvvioTest::disattiva($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id);
        }
        $this->esame->apriProvaFinale();
        $this->assertSame(1, AvvioTest::avvia($this->esame->fresh(), $this->u['direttore'], [$this->s['x']->id]));
    }

    public function test_disattivare_e_riattivare_cambia_il_token_solo_fino_all_invio(): void
    {
        $this->domande();
        $this->avvia('x');
        $t1 = $this->token('x');
        AvvioTest::disattiva($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id);
        $this->assertNull(TestAvvio::first()->avviato_il);
        $this->avvia('x');
        $t2 = $this->token('x');
        $this->assertNotSame($t1, $t2);

        $this->compila('x', self::CORRETTE);
        $this->expectException(TestException::class);
        AvvioTest::disattiva($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id);
    }

    public function test_dopo_l_invio_non_si_riattiva_ne_si_cambia_modalita(): void
    {
        $this->domande();
        $this->avvia('x');
        $this->compila('x', self::CORRETTE);

        $this->assertSame(0, $this->avvia('x'));
        $this->expectException(TestException::class);
        AvvioTest::impostaCartaceo($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id, 'DSA');
    }

    // ---- Svolgimento online ----

    public function test_discente_avviato_vede_solo_il_test_in_pwa(): void
    {
        $this->domande();
        $this->avvia('x');

        $this->come('x')->get(route('app.esami'))->assertOk()->assertSee('Discente')->assertSee('Esame');
        $this->come('x')->get(route('app.home'))->assertSee('I tuoi esami');
        $this->come('x')->get(route('app.esame', $this->esame))->assertRedirect(route('app.esame.test', $this->esame));
        $this->come('x')->get(route('app.esame.test', $this->esame))->assertOk()->assertSee('Domanda numero 1 zz')->assertSee('Tutte le risposte sono obbligatorie');

        // nessuna delle pagine delle schede
        $lab = $this->esame->laboratori()->first();
        $this->come('x')->get(route('app.esame.laboratorio', [$this->esame, $lab]))->assertNotFound();
        $this->come('x')->get(route('app.esame.itinere', $this->esame))->assertNotFound();
        $this->come('x')->get(route('app.esame.colloquio', $this->esame))->assertNotFound();
        $this->come('x')->get(route('app.esame.test.anteprima', $this->esame))->assertNotFound();

        // y non avviato: niente
        $this->come('y')->get(route('app.home'))->assertDontSee('I tuoi esami');
        $this->come('y')->get(route('app.esami'))->assertOk()->assertSee('Non hai esami in corso');
        $this->come('y')->get(route('app.esame', $this->esame))->assertNotFound();
        $this->come('y')->get(route('app.esame.test', $this->esame))->assertNotFound();
        $this->rispondi('y', $this->ids()[0], 1, 'qualsiasi')->assertNotFound();
    }

    public function test_non_ammesso_e_ritirato_non_vedono_nemmeno_con_un_avvio_forzato(): void
    {
        $this->domande();
        foreach (['nonAmmesso', 'ritirato'] as $k) {
            TestAvvio::create(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s[$k]->id, 'modalita' => 'online', 'avviato_il' => now(), 'token' => (string) \Illuminate\Support\Str::uuid()]);
            $this->assertFalse(PermessiEsame::per($this->esame->fresh(), $this->s[$k]->id)->vedeTestComeDiscente(), $k);
            $this->come($k)->get(route('app.esame.test', $this->esame))->assertNotFound();
            $this->assertFalse(Esame::query()->visibiliAlSocio($this->s[$k]->id)->exists(), "sql $k");
        }
    }

    public function test_senza_penalita_tutte_obbligatorie_punteggio_3_su_5(): void
    {
        $this->domande();
        $this->avvia('x');
        $ids = $this->ids();

        // risposte 1,0,2,2,3 (esempio A): corrette 3
        foreach ([1, 0, 2, 2] as $i => $opz) {
            $this->rispondi('x', $ids[$i], $opz)->assertOk()->assertExactJson(['ok' => true, 'date' => $i + 1, 'totale' => 5]);
        }
        // incompleto: non si invia, nessuna consegna inviata
        $this->invia('x')->assertStatus(422);
        $this->assertNull(TestConsegna::first()->consegnato_il);
        // non si può togliere la scelta
        $this->rispondi('x', $ids[0], null)->assertStatus(422);

        $this->rispondi('x', $ids[4], 3)->assertOk();
        $this->invia('x')->assertRedirect(route('app.esame.test', $this->esame));

        $this->assertSame('3,0 su 5,0', $this->punteggio('x'));
        $this->come('x')->get(route('app.esame.test', $this->esame))->assertOk()->assertSee('Test inviato');
    }

    public function test_con_penalita_si_puo_lasciare_vuoto_e_il_punteggio_e_4_5_su_10(): void
    {
        $this->domande();
        $this->conPenalita();
        $this->avvia('x');
        $ids = $this->ids();

        // 3 corrette (q1,q2,q3), 1 sbagliata (q4), q5 mai toccata → vuota
        foreach ([1, 0, 3, 0] as $i => $opz) {
            $this->rispondi('x', $ids[$i], $opz)->assertOk();
        }
        // scelta e poi tolta: torna vuota
        $this->rispondi('x', $ids[4], 2)->assertOk();
        $this->rispondi('x', $ids[4], null)->assertOk()->assertJson(['date' => 4]);

        $this->invia('x')->assertRedirect();
        $this->assertSame('4,5 su 10,0', $this->punteggio('x'));
        $this->assertSame(5, TestRisposta::count()); // le non date sono righe vuote esplicite
        $this->assertSame(1, TestRisposta::whereNull('opzione')->count());
    }

    public function test_tutto_sbagliato_con_penalita_non_scende_sotto_zero(): void
    {
        $this->domande();
        $this->conPenalita();
        $this->avvia('x');
        $ids = $this->ids();
        foreach ([0, 1, 0] as $i => $opz) { // q1..q3 sbagliate; q4, q5 vuote: −3 −1 = −4
            $this->rispondi('x', $ids[$i], $opz)->assertOk();
        }
        $this->invia('x')->assertRedirect();
        $this->assertSame('0,0 su 10,0', $this->punteggio('x'));
    }

    public function test_opzione_fuori_campo_e_domanda_altrui_rifiutate(): void
    {
        $this->domande();
        $this->avvia('x');
        $id = $this->ids()[0];
        $this->rispondi('x', $id, 4)->assertStatus(422);
        $this->rispondi('x', $id, -1)->assertStatus(422);
        $this->rispondi('x', 999999, 1)->assertStatus(422);
        $this->assertSame(0, TestRisposta::count());
    }

    // ---- Consegna unica, token ----

    public function test_secondo_invio_e_scritture_dopo_l_invio_danno_409(): void
    {
        $this->domande();
        $this->avvia('x');
        $token = $this->token('x');
        $this->compila('x', self::CORRETTE);

        $this->invia('x', $token)->assertStatus(409);
        $this->rispondi('x', $this->ids()[0], 0, $token)->assertStatus(409);
        $this->assertSame(1, TestConsegna::count());
        $this->assertSame(5, TestRisposta::count());
        $this->assertSame('5,0 su 5,0', $this->punteggio('x'));
    }

    public function test_servizio_con_consegna_gia_inviata_non_crea_righe_in_piu(): void
    {
        $this->domande();
        $this->avvia('x');
        $this->compila('x', self::CORRETTE);
        $e = $this->esame->fresh();

        try {
            SvolgimentoTest::invia($e, $this->s['x']->id, $this->token('x'), TestConsegna::ORIGINE_ONLINE, null);
            $this->fail('Secondo invio accettato');
        } catch (TestException $ex) {
            $this->assertSame(409, $ex->stato);
        }
        $this->assertSame(1, TestConsegna::count());
        $this->assertSame(5, TestRisposta::count());
    }

    public function test_token_vecchio_dopo_disattiva_riattiva_non_scrive_nulla(): void
    {
        $this->domande();
        $this->avvia('x');
        $vecchio = $this->token('x');
        AvvioTest::disattiva($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id);

        // finestra annullata: il modulo già aperto non scrive e non invia
        $this->rispondi('x', $this->ids()[0], 1, $vecchio)->assertStatus(409);
        $this->invia('x', $vecchio)->assertStatus(409);

        $this->avvia('x');
        $this->rispondi('x', $this->ids()[0], 1, $vecchio)->assertStatus(409); // nuova attivazione = nuovo token
        $this->rispondi('x', $this->ids()[0], 1)->assertOk();
        $this->assertSame(1, TestRisposta::count());
    }

    public function test_riportato_in_bozza_il_modulo_aperto_non_scrive(): void
    {
        $this->domande();
        $this->avvia('x');
        $token = $this->token('x');
        AvvioTest::riportaInBozza($this->esame->fresh(), $this->u['direttore']);

        $this->rispondi('x', $this->ids()[0], 1, $token)->assertStatus(409); // finestra annullata: nessuna scrittura
        $this->invia('x', $token)->assertStatus(409);
        $this->come('x')->get(route('app.esame.test', $this->esame))->assertNotFound(); // e ricaricando non vede più nulla
        $this->assertSame(0, TestRisposta::count());
    }

    public function test_esame_chiuso_chi_non_ha_inviato_non_puo_piu_farlo(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compila('x', self::CORRETTE);
        $tokenY = $this->token('y');
        $this->esame->update(['stato' => Esame::STATO_CHIUSO, 'chiuso_il' => now()]);

        $this->rispondi('y', $this->ids()[0], 1, $tokenY)->assertStatus(409);
        $this->invia('y', $tokenY)->assertStatus(409);
        $this->come('x')->get(route('app.esame.test', $this->esame))->assertOk()->assertSee('Test inviato');
    }

    public function test_due_discenti_indipendenti(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compila('x', self::CORRETTE);
        $this->compila('y', [0, 0, 0, 0, 0]);

        $this->assertSame('5,0 su 5,0', $this->punteggio('x'));
        $this->assertSame('1,0 su 5,0', $this->punteggio('y')); // solo la seconda è A
    }

    // ---- Cartaceo ----

    public function test_cartaceo_motivo_obbligatorio_inserimento_del_direttore_e_online_bloccato(): void
    {
        $this->domande();
        $e = $this->esame->fresh();

        foreach (['', '   '] as $motivo) {
            try {
                AvvioTest::impostaCartaceo($e, $this->u['direttore'], $this->s['x']->id, $motivo);
                $this->fail('Cartaceo senza motivo');
            } catch (TestException $ex) {
                $this->assertSame(422, $ex->stato);
            }
        }
        // non ammesso: no
        try {
            AvvioTest::impostaCartaceo($e, $this->u['direttore'], $this->s['nonAmmesso']->id, 'DSA');
            $this->fail('Cartaceo per non ammesso');
        } catch (TestException $ex) {
            $this->assertSame(404, $ex->stato);
        }

        AvvioTest::impostaCartaceo($e, $this->u['direttore'], $this->s['x']->id, 'DSA certificato');
        $this->assertSame('cartaceo', TestAvvio::first()->modalita);

        // il discente cartaceo non ha niente da fare in app
        $this->come('x')->get(route('app.esame.test', $this->esame))->assertNotFound();
        $this->come('x')->get(route('app.home'))->assertDontSee('I tuoi esami');

        // il direttore inserisce a mano le risposte (stesso servizio) e invia
        $token = $this->token('x');
        $dir = $this->s['direttore']->id;
        foreach ($this->ids() as $i => $id) {
            SvolgimentoTest::salvaRisposta($e, $this->s['x']->id, $token, TestConsegna::ORIGINE_CARTACEO, $dir, $id, self::CORRETTE[$i]);
        }
        $c = SvolgimentoTest::invia($e, $this->s['x']->id, $token, TestConsegna::ORIGINE_CARTACEO, $dir);
        $this->assertSame('cartaceo', $c->origine);
        $this->assertSame($dir, $c->inserito_da);
        $this->assertSame('5,0 su 5,0', $this->punteggio('x'));
        $this->assertSame('DSA certificato', TestAvvio::first()->motivo_cartaceo);

        // cartaceo già inviato: nessuna modifica
        $this->expectException(TestException::class);
        SvolgimentoTest::salvaRisposta($e, $this->s['x']->id, $token, TestConsegna::ORIGINE_CARTACEO, $dir, $this->ids()[0], 0);
    }

    public function test_cartaceo_stesse_regole_di_completezza_dell_online(): void
    {
        $this->domande();
        $e = $this->esame->fresh();
        AvvioTest::impostaCartaceo($e, $this->u['direttore'], $this->s['x']->id, 'BES');
        $token = $this->token('x');
        SvolgimentoTest::salvaRisposta($e, $this->s['x']->id, $token, TestConsegna::ORIGINE_CARTACEO, $this->s['direttore']->id, $this->ids()[0], 1);

        $this->expectException(TestException::class); // tutte obbligatorie (penalità spente)
        SvolgimentoTest::invia($e, $this->s['x']->id, $token, TestConsegna::ORIGINE_CARTACEO, $this->s['direttore']->id);
    }

    public function test_cambiare_modalita_scarta_la_bozza_dell_altra(): void
    {
        $this->domande();
        $this->avvia('x');
        $this->rispondi('x', $this->ids()[0], 1)->assertOk();
        $this->assertSame(1, TestConsegna::count());

        AvvioTest::disattiva($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id);
        AvvioTest::impostaCartaceo($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id, 'DSA');
        $this->assertSame(0, TestConsegna::count());
        $this->assertSame(0, TestRisposta::count());
    }

    // ---- Riepilogo e dati per il calcolo ----

    public function test_riepilogo_stati_e_dati_per_il_calcolo(): void
    {
        $this->domande();
        $this->avvia('x', 'y');
        $this->compila('x', self::CORRETTE);
        $this->rispondi('y', $this->ids()[0], 1)->assertOk();

        $righe = collect(TestScritto::riepilogo($this->esame->fresh()))->keyBy(fn ($r) => $r['socio']->id);
        $this->assertCount(2, $righe); // solo gli ammessi
        $this->assertSame('inviato', $righe[$this->s['x']->id]['stato']);
        $this->assertSame('5,0 su 5,0', $righe[$this->s['x']->id]['punteggio']);
        $this->assertSame('in_compilazione', $righe[$this->s['y']->id]['stato']);
        $this->assertNull($righe[$this->s['y']->id]['punteggio']);

        $e = $this->esame->fresh();
        $this->assertNull(TestScritto::datiPerCalcolo($e, $this->s['y']->id));
        $dati = TestScritto::datiPerCalcolo($e, $this->s['x']->id);
        $this->assertCount(5, $dati['domande']);
        $this->assertSame(4, current($dati['domande'])['n_opzioni']);
        $this->assertSame(array_values(self::CORRETTE), array_values($dati['risposte']));
    }

    public function test_esame_con_test_non_si_cancella_ne_torna_in_bozza(): void
    {
        $this->domande();
        $this->assertTrue($this->esame->fresh()->hasDatiCorrelati());
        $this->assertNotNull($this->esame->fresh()->motivoNonRiportabileInBozza());
    }
}
