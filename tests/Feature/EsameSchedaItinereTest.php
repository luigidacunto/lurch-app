<?php

namespace Tests\Feature;

use App\Esami\PermessiEsame;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\TcriSchedaItinere;
use Database\Seeders\EsameModelliSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Esami: scheda itinere in PWA (valutazione personale). DB di test: lurch_test. */
class EsameSchedaItinereTest extends TestCase
{
    use RefreshDatabase;

    private Esame $esame;
    private Corso $corso;
    /** @var array<string, Socio> */
    private array $s = [];
    /** @var list<string> codici delle voci, in ordine di area */
    private array $codici = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EsameModelliSeeder::class);

        $now    = now();
        $cr     = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR', 'created_at' => $now, 'updated_at' => $now]);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $catalogo = CatalogoCorso::create(['codice' => 'TCRI', 'nome' => 'TCRI', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'TCRI', 'attivo' => true]);

        foreach (['direttore', 'presidente', 'docA', 'docB', 'x', 'y', 'nonAmmesso', 'ritirato'] as $k) {
            $this->s[$k] = Socio::create(['nome' => $k . 'Nome', 'cognome' => 'Test', 'codice_fiscale' => strtoupper(substr(md5($k), 0, 16))]);
        }
        $this->corso = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso TCRI', 'numero_protocollo' => 'X/' . uniqid(),
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
            'direttore_socio_id' => $this->s['direttore']->id, 'presidente_socio_id' => $this->s['presidente']->id,
            'catalogo_corso_id' => $catalogo->id,
        ]);
        $this->corso->docenti()->attach([$this->s['docA']->id, $this->s['docB']->id]);
        foreach (['x', 'y', 'nonAmmesso', 'ritirato'] as $k) {
            Iscrizione::create([
                'corso_id' => $this->corso->id, 'partecipante_id' => $this->s[$k]->id, 'token' => (string) Str::uuid(),
                'stato' => $k === 'ritirato' ? 'ritirato' : 'pending',
                'non_ammesso' => $k === 'nonAmmesso', 'non_ammesso_motivo' => $k === 'nonAmmesso' ? 'assenze' : null,
            ]);
        }

        $this->esame = Esame::creaPerCorso($this->corso, 'Esame', 60);
        $this->esame->attiva();
        $this->esame->refresh();

        foreach ($this->esame->modello_snapshot['itinere']['aree'] as $a) {
            array_push($this->codici, ...array_column($a['voci'], 'codice'));
        }
    }

    private function come(string $k)
    {
        return $this->withSession(['app_socio_id' => $this->s[$k]->id]);
    }

    private function url(string $discente): string
    {
        return route('app.esame.itinere.voto', [$this->esame, $this->s[$discente]]);
    }

    private function vota(string $docente, string $discente, string $voce, ?int $voto)
    {
        return $this->come($docente)->putJson($this->url($discente), ['voce' => $voce, 'voto' => $voto]);
    }

    private function permessi(string $k): PermessiEsame
    {
        return PermessiEsame::per($this->esame->fresh(), $this->s[$k]->id);
    }

    private function idsItinere(string $k): array
    {
        return $this->permessi($k)->discentiItinere()->pluck('id')->sort()->values()->all();
    }

    public function test_il_modello_ha_13_voci_in_4_aree(): void
    {
        $this->assertCount(13, $this->codici);
        $this->assertCount(4, $this->esame->modello_snapshot['itinere']['aree']);
    }

    public function test_ogni_docente_vede_tutti_i_discenti_non_ritirati_e_non_ammesso_e_ininfluente(): void
    {
        // x, y e il non ammesso compaiono identici; il ritirato no.
        $atteso = collect([$this->s['x']->id, $this->s['y']->id, $this->s['nonAmmesso']->id])->sort()->values()->all();
        $this->assertSame($atteso, $this->idsItinere('docA'));
        $this->assertSame($atteso, $this->idsItinere('docB'));
        $this->assertSame($atteso, $this->idsItinere('direttore'), 'il direttore compila come docente anche senza commissione');
    }

    public function test_presidente_e_discente_non_compilano_nulla(): void
    {
        $this->assertSame([], $this->idsItinere('presidente'));
        $this->assertSame([], $this->idsItinere('x'));
        $this->come('presidente')->get(route('app.esame.itinere', $this->esame))->assertOk()->assertSee('Non hai schede da compilare');
        $this->vota('presidente', 'x', $this->codici[0], 3)->assertForbidden();
        $this->come('x')->get(route('app.esame.itinere', $this->esame))->assertNotFound();
    }

    public function test_presidente_che_e_anche_docente_compila_come_docente(): void
    {
        $this->corso->docenti()->attach($this->s['presidente']->id);
        $this->assertCount(3, $this->idsItinere('presidente'));
    }

    public function test_bozza_non_visibile(): void
    {
        $this->esame->riportaInBozza();
        $this->come('docA')->get(route('app.esame.itinere', $this->esame))->assertNotFound();
        $this->vota('docA', 'x', $this->codici[0], 3)->assertNotFound();
    }

    public function test_autosave_ripresa_e_una_sola_scheda(): void
    {
        $this->vota('docA', 'x', $this->codici[0], 4)->assertOk()->assertJson(['voti' => 1]);
        $this->vota('docA', 'x', $this->codici[1], 3)->assertOk()->assertJson(['voti' => 2]);
        $this->vota('docA', 'x', $this->codici[0], 5)->assertOk()->assertJson(['voti' => 2]); // sovrascrive

        $this->assertSame(1, TcriSchedaItinere::count());
        $this->assertSame(2, TcriSchedaItinere::first()->voci()->count());

        $html = $this->come('docA')->get(route('app.esame.itinere.scheda', [$this->esame, $this->s['x']]))->assertOk()->getContent();
        $this->assertStringContainsString('2/13 voti', $html);
        $this->come('docA')->get(route('app.esame.itinere', $this->esame))->assertOk()->assertSee('2 voti su 13')->assertSee('Da fare');
    }

    public function test_ritoccare_il_voto_lo_toglie_e_la_riga_resta_a_null(): void
    {
        $this->vota('docA', 'x', $this->codici[0], 4)->assertOk();
        $this->vota('docA', 'x', $this->codici[0], null)->assertOk()->assertJson(['voti' => 0, 'totale' => null]);
        $this->assertNull(TcriSchedaItinere::first()->voci()->first()->voto);
    }

    public function test_media_4_e_3_vale_3_virgola_5_e_aree_vuote_restano_fuori_dal_totale(): void
    {
        $area1 = $this->esame->modello_snapshot['itinere']['aree'][0]['voci'];
        $this->vota('docA', 'x', $area1[0]['codice'], 4)->assertOk();
        $r = $this->vota('docA', 'x', $area1[1]['codice'], 3)->assertOk()->json();

        $this->assertSame('3,5', $r['aree'][0]['media']);
        $this->assertSame([null, null, null], [$r['aree'][1]['media'], $r['aree'][2]['media'], $r['aree'][3]['media']], 'area senza voti: niente valore forzato');
        $this->assertSame('3,5', $r['totale']);
        $this->assertSame('20', $r['massimo']);
        $this->assertSame(1, $r['aree_valutate']);
        $this->assertSame(4, $r['n_aree']);
    }

    public function test_media_non_esatta_e_troncata_mai_arrotondata(): void
    {
        $area1 = $this->esame->modello_snapshot['itinere']['aree'][0]['voci'];
        $this->vota('docA', 'x', $area1[0]['codice'], 4)->assertOk();
        $this->vota('docA', 'x', $area1[1]['codice'], 3)->assertOk();
        $r = $this->vota('docA', 'x', $area1[2]['codice'], 3)->assertOk()->json();
        $this->assertSame('3,33', $r['aree'][0]['media']); // 10/3
    }

    public function test_totale_e_somma_delle_medie_delle_aree_valutate(): void
    {
        $aree = $this->esame->modello_snapshot['itinere']['aree'];
        $this->vota('docA', 'x', $aree[0]['voci'][0]['codice'], 4);
        $r = $this->vota('docA', 'x', $aree[1]['voci'][0]['codice'], 2)->json();
        $this->assertSame('6,0', $r['totale']);
        $this->assertSame(2, $r['aree_valutate']);
    }

    public function test_voce_o_voto_fuori_scala_422(): void
    {
        $this->vota('docA', 'x', 'itinere.inesistente.9', 3)->assertStatus(422);
        $this->vota('docA', 'x', $this->codici[0], 6)->assertStatus(422);
        $this->vota('docA', 'x', $this->codici[0], 0)->assertStatus(422);
        $this->assertSame(0, TcriSchedaItinere::count());
    }

    public function test_un_altro_docente_non_vede_ne_tocca_le_schede_altrui(): void
    {
        $this->vota('docA', 'x', $this->codici[0], 5)->assertOk();

        // B parte da zero: la sua scheda su X è un'altra riga.
        $html = $this->come('docB')->get(route('app.esame.itinere.scheda', [$this->esame, $this->s['x']]))->assertOk()->getContent();
        $this->assertStringContainsString('0/13 voti', $html);
        $this->vota('docB', 'x', $this->codici[0], 2)->assertOk()->assertJson(['voti' => 1]);

        $this->assertSame(2, TcriSchedaItinere::count());
        $this->assertSame(5, TcriSchedaItinere::where('docente_socio_id', $this->s['docA']->id)->first()->voci()->first()->voto);
    }

    public function test_ritirato_sparisce_ma_la_scheda_resta_e_riappare_se_riammesso(): void
    {
        $this->vota('docA', 'ritirato', $this->codici[0], 3)->assertForbidden();
        // scheda già compilata prima del ritiro
        Iscrizione::where('partecipante_id', $this->s['ritirato']->id)->update(['stato' => 'pending']);
        $this->vota('docA', 'ritirato', $this->codici[0], 3)->assertOk();
        Iscrizione::where('partecipante_id', $this->s['ritirato']->id)->update(['stato' => 'ritirato']);

        $this->assertNotContains($this->s['ritirato']->id, $this->idsItinere('docA'));
        $this->come('docA')->get(route('app.esame.itinere.scheda', [$this->esame, $this->s['ritirato']]))->assertNotFound();
        $this->vota('docA', 'ritirato', $this->codici[0], 4)->assertForbidden();
        $this->assertSame(1, TcriSchedaItinere::count(), 'i dati restano nel database');

        Iscrizione::where('partecipante_id', $this->s['ritirato']->id)->update(['stato' => 'pending']);
        $this->assertContains($this->s['ritirato']->id, $this->idsItinere('docA'));
        $this->come('docA')->get(route('app.esame.itinere.scheda', [$this->esame, $this->s['ritirato']]))->assertOk()->assertSee('1/13 voti');
    }

    public function test_esame_chiuso_sola_lettura(): void
    {
        $this->vota('docA', 'x', $this->codici[0], 4)->assertOk();
        $this->esame->update(['stato' => Esame::STATO_CHIUSO, 'chiuso_il' => now()]);

        $this->come('docA')->get(route('app.esame.itinere.scheda', [$this->esame, $this->s['x']]))
            ->assertOk()->assertSee('si può solo consultare');
        $this->vota('docA', 'x', $this->codici[0], 1)->assertForbidden();
        $this->assertSame(4, TcriSchedaItinere::first()->voci()->first()->voto, 'nessuna scheda toccata');

        // Riapertura: di nuovo modificabile.
        $this->esame->update(['stato' => Esame::STATO_IN_CORSO, 'chiuso_il' => null]);
        $this->vota('docA', 'x', $this->codici[0], 1)->assertOk();
    }

    public function test_annotazione_salvata_letta_dal_direttore_e_mai_da_altri(): void
    {
        $url = route('app.esame.itinere.annotazione', [$this->esame, $this->s['x']]);
        // Senza voti la scheda nasce dalla nota; testo vuoto torna NULL.
        $this->come('docA')->putJson($url, ['annotazione' => '  Molto attento in aula  '])->assertOk();
        $this->assertSame('Molto attento in aula', TcriSchedaItinere::first()->annotazione);
        $this->come('docA')->putJson($url, ['annotazione' => str_repeat('a', 3001)])->assertStatus(422);
        $this->come('presidente')->putJson($url, ['annotazione' => 'x'])->assertForbidden();
        // Le note di laboratorio (accanto al docente, schede inviate) sono provate in EsameRiepilogoItinereTest.
        $riepilogo = route('app.esame.itinere.riepilogo', $this->esame);
        $this->come('direttore')->get($riepilogo)->assertOk()->assertSee('Molto attento in aula')->assertSee('ha inserito una nota sulla scheda itinere');
        $this->come('presidente')->get($riepilogo)->assertOk()->assertDontSee('Molto attento in aula')->assertDontSee('ha inserito una nota');
        $this->come('docA')->get(route('app.esame.itinere.scheda', [$this->esame, $this->s['x']]))->assertSee('Molto attento in aula');
        $this->come('docB')->get(route('app.esame.itinere.scheda', [$this->esame, $this->s['x']]))->assertDontSee('Molto attento in aula');
        // Il limite delle note itinere è 3000 (1000 in laboratorio).
        $this->come('docA')->putJson($url, ['annotazione' => str_repeat('a', 3000)])->assertOk();

        $this->come('docA')->putJson($url, ['annotazione' => '   '])->assertOk();
        $this->assertNull(TcriSchedaItinere::first()->annotazione);
    }

    public function test_elenco_con_ricerca_e_riepilogo_per_discente(): void
    {
        $aree = $this->esame->modello_snapshot['itinere']['aree'];
        $this->vota('docA', 'x', $aree[0]['voci'][0]['codice'], 4);
        $this->vota('docA', 'x', $aree[1]['voci'][0]['codice'], 3);

        $this->come('docA')->get(route('app.esame.itinere', $this->esame))->assertOk()
            ->assertSee('id="cerca"', false)
            ->assertSee('2 voti su 13 · Aree 2 su 4 · Totale 7,0 su 20');
    }

    public function test_card_itinere_nel_dettaglio_esame(): void
    {
        $this->vota('docA', 'x', $this->codici[0], 4)->assertOk();
        $this->come('docA')->get(route('app.esame', $this->esame))->assertOk()
            ->assertSee('Schede iniziate: 1 su 3')->assertSee(route('app.esame.itinere', $this->esame), false);
        $this->come('presidente')->get(route('app.esame', $this->esame))->assertOk()->assertSee('Nessuna scheda da compilare');
    }

    public function test_testi_della_scheda_vengono_dallo_snapshot(): void
    {
        $area = $this->esame->modello_snapshot['itinere']['aree'][0];
        $html = $this->come('docA')->get(route('app.esame.itinere.scheda', [$this->esame, $this->s['x']]))->assertOk()->getContent();
        $this->assertStringContainsString(e($area['voci'][0]['testo']), $html);
        $this->assertStringContainsString(e($area['nome']), $html);
        $this->assertStringContainsString('Valutazione personale', $html);
    }
}
