<?php

namespace Tests\Feature;

use App\Esami\CompletezzaEsame;
use App\Esami\FinaleProvaFinale;
use App\Esami\PermessiEsame;
use App\Esami\RiepilogoProvaFinale;
use App\Esami\StrutturaSemaforo;
use App\Filament\Resources\EsameResource;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\TcriColloquio;
use App\Models\TcriProvaPratica;
use App\Models\User;
use Database\Seeders\EsameModelliSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Esami: colloquio e prova pratica. Schede PERSONALI (PWA, direttore + docenti di commissione, visibili solo a
 * «Apri la prova finale» aperta) e schede FINALI (pannello, solo il direttore). DB di test: lurch_test.
 */
class EsameProvaFinaleTest extends TestCase
{
    use RefreshDatabase;

    private Esame $esame;
    private Corso $corso;
    /** @var array<string, Socio> */
    private array $s = [];
    /** @var array<string, User> */
    private array $u = [];
    /** @var list<string> */
    private array $voci;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EsameModelliSeeder::class);

        $now    = now();
        $cr     = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR', 'created_at' => $now, 'updated_at' => $now]);
        $cl     = DB::table('comitati_locali')->insertGetId(['comitato_regionale_id' => $cr, 'nome' => 'CL', 'created_at' => $now, 'updated_at' => $now]);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr, 'comitato_locale_id' => $cl, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $catalogo = CatalogoCorso::create(['codice' => 'TCRI', 'nome' => 'TCRI', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'TCRI', 'attivo' => true]);

        foreach (['direttore', 'presidente', 'docA', 'docB', 'x', 'y', 'nonAmmesso', 'ritirato'] as $k) {
            $this->s[$k] = Socio::create(['nome' => $k . 'Nome', 'cognome' => 'Test' . $k, 'codice_fiscale' => strtoupper(substr(md5($k), 0, 16))]);
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
        $this->esame->docentiCommissione()->create(['socio_id' => $this->s['docA']->id]); // docB NON in commissione
        $this->esame->attiva();
        $this->esame->refresh();
        $this->voci = StrutturaSemaforo::per($this->esame)['codici'];

        foreach (['direttore', 'presidente', 'docA', 'docB', 'x'] as $k) {
            $this->u[$k] = User::create(['name' => $k, 'email' => $k . '@t.it', 'password' => 'x', 'ruolo' => 'operatore', 'attivo' => true, 'socio_id' => $this->s[$k]->id]);
        }
        $this->u['super'] = User::create(['name' => 'super', 'email' => 'super@t.it', 'password' => 'x', 'ruolo' => 'superadmin', 'attivo' => true]);
        $this->u['adminReg'] = User::create(['name' => 'ar', 'email' => 'ar@t.it', 'password' => 'x', 'ruolo' => 'admin', 'attivo' => true, 'comitato_regionale_id' => $cr]);
        $this->u['adminLoc'] = User::create(['name' => 'al', 'email' => 'al@t.it', 'password' => 'x', 'ruolo' => 'admin_locale', 'attivo' => true, 'comitato_locale_id' => $cl]);
    }

    private function apri(): void
    {
        $this->esame->apriProvaFinale();
        $this->esame->refresh();
    }

    private function come(string $k)
    {
        return $this->withSession(['app_socio_id' => $this->s[$k]->id]);
    }

    private function pannello(string $chi): void
    {
        $this->actingAs($this->u[$chi]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function colloquioPersonale(string $membro, string $discente, int $voto, ?string $imp = null): TcriColloquio
    {
        return TcriColloquio::create([
            'esame_id' => $this->esame->id, 'discente_socio_id' => $this->s[$discente]->id, 'tipo' => 'personale',
            'membro_socio_id' => $this->s[$membro]->id, 'punteggio' => $voto, 'impressione' => $imp,
        ]);
    }

    /** Scheda personale di prova pratica con tutte le voci a $valore (o la prima voce a $primo). */
    private function provaPersonale(string $membro, string $discente, string $valore, bool $inviata = true, ?string $primo = null): TcriProvaPratica
    {
        $p = TcriProvaPratica::create([
            'esame_id' => $this->esame->id, 'discente_socio_id' => $this->s[$discente]->id, 'tipo' => 'personale',
            'membro_socio_id' => $this->s[$membro]->id, 'inviata_il' => $inviata ? now() : null,
        ]);
        foreach ($this->voci as $i => $codice) {
            $p->voci()->create(['voce_codice' => $codice, 'valore' => $i === 0 && $primo !== null ? $primo : $valore]);
        }

        return $p;
    }

    private function tutteLeVoci(string $chiave): array
    {
        return array_fill_keys($this->voci, $chiave);
    }

    // ---- Interruttore «Apri la prova finale» ----

    public function test_chi_aziona_l_interruttore_e_solo_a_esame_in_corso(): void
    {
        $e = $this->esame->fresh();
        foreach (['direttore', 'super', 'adminReg', 'adminLoc'] as $k) {
            $this->assertTrue($e->puoGestireProvaFinale($this->u[$k]), $k);
        }
        foreach (['presidente', 'docA', 'docB', 'x'] as $k) {
            $this->assertFalse($e->puoGestireProvaFinale($this->u[$k]), $k);
        }

        $this->assertFalse($e->provaFinaleAperta());
        $e->apriProvaFinale();
        $this->assertTrue($e->fresh()->provaFinaleAperta());
        $e->chiudiProvaFinale();
        $this->assertFalse($e->fresh()->provaFinaleAperta());

        $e->riportaInBozza();
        $this->assertFalse($e->fresh()->puoGestireProvaFinale($this->u['direttore']));
        $this->expectException(\RuntimeException::class);
        $e->fresh()->apriProvaFinale();
    }

    public function test_azione_in_gestione_apre_e_richiude(): void
    {
        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\GestioneEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Apri la prova finale')
            ->callAction('provaFinale');
        $this->assertTrue($this->esame->fresh()->provaFinaleAperta());

        Livewire::test(EsameResource\Pages\GestioneEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Richiudi la prova finale')
            ->callAction('provaFinale');
        $this->assertFalse($this->esame->fresh()->provaFinaleAperta());
    }

    // ---- Chi compila le schede personali ----

    public function test_compilano_solo_direttore_e_docenti_di_commissione_e_solo_a_prova_aperta(): void
    {
        $x = $this->s['x']->id;
        foreach (['direttore', 'docA', 'presidente', 'docB', 'x'] as $k) {
            $this->assertFalse(PermessiEsame::per($this->esame->fresh(), $this->s[$k]->id)->puoCompilareProvaPersonale($x), "$k prima dell'apertura");
        }
        $this->apri();
        foreach (['direttore' => true, 'docA' => true, 'presidente' => false, 'docB' => false, 'x' => false] as $k => $atteso) {
            $this->assertSame($atteso, PermessiEsame::per($this->esame->fresh(), $this->s[$k]->id)->puoCompilareProvaPersonale($x), $k);
        }
        // Mai per non ammessi e ritirati.
        foreach (['nonAmmesso', 'ritirato'] as $k) {
            $this->assertFalse(PermessiEsame::per($this->esame->fresh(), $this->s['docA']->id)->puoCompilareProvaPersonale($this->s[$k]->id), $k);
        }
    }

    public function test_schede_personali_non_si_compilano_ad_esame_chiuso(): void
    {
        $this->apri();
        $this->esame->update(['stato' => Esame::STATO_CHIUSO, 'chiuso_il' => now()]);
        $this->assertFalse(PermessiEsame::per($this->esame->fresh(), $this->s['docA']->id)->puoCompilareProvaPersonale($this->s['x']->id));
        $this->come('docA')->put(route('app.esame.colloquio.voto', [$this->esame, $this->s['x']]), ['voto' => 3])->assertForbidden();
    }

    // ---- PWA: colloquio ----

    public function test_pwa_colloquio_prima_dell_apertura_non_esiste(): void
    {
        $this->come('docA')->get(route('app.esame.colloquio', $this->esame))->assertNotFound();
        $this->come('docA')->get(route('app.esame.provapratica', $this->esame))->assertNotFound();
        $this->come('docA')->get(route('app.esame', $this->esame))->assertOk()->assertDontSee('Il tuo voto e la tua impressione')->assertDontSee('La tua scheda a semaforo');
    }

    public function test_pwa_colloquio_elenco_voto_intero_obbligatorio_e_impressione(): void
    {
        $this->apri();
        $x = $this->s['x'];
        $this->come('docA')->get(route('app.esame', $this->esame))->assertOk()->assertSee('Colloquio')->assertSee('Prova pratica');

        $this->come('docA')->get(route('app.esame.colloquio', $this->esame))->assertOk()
            ->assertSee('Testx')->assertSee('Testy')
            ->assertDontSee('TestnonAmmesso')->assertDontSee('Testritirato');
        $this->come('docA')->get(route('app.esame.colloquio.scheda', [$this->esame, $x]))->assertOk()
            ->assertSee('Autovalutazione assente'); // descrittore dal modello
        $this->come('docA')->get(route('app.esame.colloquio.scheda', [$this->esame, $this->s['nonAmmesso']]))->assertNotFound();
        $this->come('docA')->get(route('app.esame.colloquio.scheda', [$this->esame, $this->s['ritirato']]))->assertNotFound();

        $voto = fn ($v) => $this->come('docA')->putJson(route('app.esame.colloquio.voto', [$this->esame, $x]), ['voto' => $v]);
        $voto(3.5)->assertStatus(422);
        $voto(0)->assertStatus(422);
        $voto(6)->assertStatus(422);
        $voto(null)->assertStatus(422);
        $this->assertSame(0, $this->esame->colloqui()->count());

        // Impressione senza voto: nessuna scheda creata.
        $this->come('docA')->putJson(route('app.esame.colloquio.impressione', [$this->esame, $x]), ['impressione' => 'ok'])->assertStatus(422);
        $this->assertSame(0, $this->esame->colloqui()->count());

        $voto(4)->assertOk()->assertJson(['voto' => 4]);
        $voto(5)->assertOk();
        $this->assertSame(1, $this->esame->colloqui()->count()); // si aggiorna, non se ne crea una seconda
        $scheda = $this->esame->colloqui()->first();
        $this->assertSame('personale', $scheda->tipo);
        $this->assertSame($this->s['docA']->id, $scheda->membro_socio_id);
        $this->assertSame(5, (int) $scheda->punteggio);

        $imp = fn ($t) => $this->come('docA')->putJson(route('app.esame.colloquio.impressione', [$this->esame, $x]), ['impressione' => $t]);
        $imp(str_repeat('a', 1001))->assertStatus(422);
        $imp(str_repeat('a', 1000))->assertOk();
        $imp('Ha dato esempi concreti.')->assertOk();
        $this->assertSame('Ha dato esempi concreti.', $scheda->fresh()->impressione);
        $imp('')->assertOk();
        $this->assertNull($scheda->fresh()->impressione);
        $this->assertSame(5, (int) $scheda->fresh()->punteggio); // il voto non si toglie
    }

    public function test_pwa_colloquio_ognuno_la_propria_e_chi_non_compila_non_entra(): void
    {
        $this->apri();
        $x = $this->s['x'];
        $this->come('docA')->putJson(route('app.esame.colloquio.voto', [$this->esame, $x]), ['voto' => 4])->assertOk();
        $this->come('direttore')->putJson(route('app.esame.colloquio.voto', [$this->esame, $x]), ['voto' => 2])->assertOk();
        $this->assertSame(2, $this->esame->colloqui()->count());
        $this->come('docA')->get(route('app.esame.colloquio.scheda', [$this->esame, $x]))->assertOk(); // la sua
        $this->assertSame(4, (int) $this->esame->colloqui()->where('membro_socio_id', $this->s['docA']->id)->value('punteggio'));

        // Presidente, docente non in commissione, discente: 404 su elenco e scheda, 404 sulla scrittura.
        foreach (['presidente', 'docB', 'x'] as $k) {
            $this->come($k)->get(route('app.esame.colloquio', $this->esame))->assertNotFound();
            $this->come($k)->putJson(route('app.esame.colloquio.voto', [$this->esame, $x]), ['voto' => 3])->assertNotFound();
        }
        $this->assertSame(2, $this->esame->colloqui()->count());
    }

    // ---- PWA: prova pratica personale ----

    public function test_pwa_prova_pratica_bozza_invio_solo_con_tutte_le_voci(): void
    {
        $this->apri();
        $x = $this->s['x'];
        $this->come('docA')->get(route('app.esame.provapratica', $this->esame))->assertOk()
            ->assertSee('Testx')->assertDontSee('TestnonAmmesso')->assertDontSee('Testritirato');
        $this->come('docA')->get(route('app.esame.provapratica.scheda', [$this->esame, $x]))->assertOk()->assertSee('0/28');

        $voce = fn ($codice, $v) => $this->come('docA')->putJson(route('app.esame.provapratica.voce', [$this->esame, $x]), ['voce' => $codice, 'valore' => $v]);
        $voce($this->voci[0], 'verde')->assertOk()->assertJson(['compilate' => 1, 'totale' => null]);
        $voce($this->voci[0], 'blu')->assertStatus(422);
        $this->assertSame(1, $this->esame->provePratiche()->count());
        $prova = $this->esame->provePratiche()->first();
        $this->assertSame('personale', $prova->tipo);
        $this->assertNull($prova->inviata_il);

        // Invio rifiutato finché mancano voci.
        $this->come('docA')->post(route('app.esame.provapratica.invia', [$this->esame, $x]))->assertSessionHas('errore');
        $this->assertNull($prova->fresh()->inviata_il);

        foreach (array_slice($this->voci, 1) as $codice) {
            $risposta = $voce($codice, 'verde');
        }
        $risposta->assertOk()->assertJson(['totale' => ['punti' => '28,00', 'massimo' => '28,00']]);
        $this->come('docA')->post(route('app.esame.provapratica.invia', [$this->esame, $x]))->assertRedirect(route('app.esame.provapratica', $this->esame));
        $this->assertNotNull($prova->fresh()->inviata_il);

        // Correggibile dopo l'invio, finché in corso; il presidente non scrive.
        $voce($this->voci[0], 'giallo')->assertOk();
        $this->come('presidente')->putJson(route('app.esame.provapratica.voce', [$this->esame, $x]), ['voce' => $this->voci[0], 'valore' => 'rosso'])->assertNotFound();
    }

    // ---- Prova finale richiusa: le personali restano visibili, in sola lettura ----

    public function test_a_prova_chiusa_personali_e_finali_restano_visibili_ma_non_si_modifica(): void
    {
        $this->apri();
        $this->colloquioPersonale('docA', 'x', 4, 'Brava');
        $this->provaPersonale('docA', 'x', '1');
        TcriColloquio::create(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['x']->id, 'tipo' => 'finale', 'punteggio' => '4.0', 'inserito_da' => $this->s['direttore']->id]);

        $r = RiepilogoProvaFinale::per($this->esame->fresh());
        $d = collect($r['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['x']->id);
        $this->assertCount(1, $d['colloquio']['personali']);
        $this->assertSame('Brava', $d['colloquio']['personali'][0]['impressione']);
        $this->assertSame('4,0', $d['colloquio']['finale']['punteggio']);

        $this->esame->fresh()->chiudiProvaFinale();
        $r = RiepilogoProvaFinale::per($this->esame->fresh());
        $d = collect($r['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['x']->id);
        $this->assertCount(1, $d['colloquio']['personali']);
        $this->assertSame('4,0', $d['colloquio']['proposta']); // la proposta resta disponibile al direttore
        $this->assertCount(1, $d['prova_pratica']['personali']);
        $this->assertSame('4,0', $d['colloquio']['finale']['punteggio']); // la finale non dipende dall'interruttore
        $permessi = PermessiEsame::per($this->esame->fresh(), $this->s['docA']->id);
        $this->assertTrue($permessi->vedeSchedeProvaPersonali());
        $this->assertTrue($permessi->vedeRiepilogoProvaFinale());

        // PWA: chi ha già schede le vede (sola lettura), ma non può più scrivere; riaprendo torna tutto modificabile.
        $x = $this->s['x'];
        $this->assertTrue($permessi->inElencoProvaFinale($x->id));
        $this->assertFalse($permessi->puoCompilareProvaPersonale($x->id));
        $this->come('docA')->get(route('app.esame.colloquio.scheda', [$this->esame, $x]))->assertOk()->assertSee('solo consultare');
        $this->come('docA')->get(route('app.esame.provapratica.scheda', [$this->esame, $x]))->assertOk()->assertSee('solo consultare');
        $this->come('docA')->get(route('app.esame', $this->esame))->assertOk()->assertSee('Il tuo voto e la tua impressione');
        $this->come('docA')->putJson(route('app.esame.colloquio.voto', [$this->esame, $x]), ['voto' => 2])->assertForbidden();
        $this->come('docA')->putJson(route('app.esame.provapratica.voce', [$this->esame, $x]), ['voce' => $this->voci[0], 'valore' => 'rosso'])->assertForbidden();
        $this->assertSame(4, (int) $this->esame->colloqui()->where('tipo', 'personale')->value('punteggio'));
        // Chi non ha mai scritto nulla non entra a prova chiusa (nemmeno in lettura).
        $this->come('direttore')->get(route('app.esame.colloquio', $this->esame))->assertNotFound();

        $this->esame->fresh()->apriProvaFinale();
        $this->come('docA')->putJson(route('app.esame.colloquio.voto', [$this->esame, $x]), ['voto' => 2])->assertOk();
        $this->assertSame(2, (int) $this->esame->colloqui()->where('tipo', 'personale')->value('punteggio'));
    }

    public function test_riepilogo_mostra_il_nome_di_chi_ha_compilato_la_prova_pratica(): void
    {
        $this->apri();
        $this->provaPersonale('docA', 'x', '1'); // solo prova pratica, nessun colloquio dello stesso docente
        $d = collect(RiepilogoProvaFinale::per($this->esame->fresh())['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['x']->id);
        $this->assertSame('TestdocA docANome', $d['prova_pratica']['personali'][0]['membro']);
    }

    public function test_riepilogo_solo_ammessi(): void
    {
        $r = RiepilogoProvaFinale::per($this->esame->fresh());
        $this->assertEqualsCanonicalizing(
            [$this->s['x']->id, $this->s['y']->id],
            collect($r['discenti'])->map(fn ($d) => $d['socio']->id)->all()
        );
        $this->assertEqualsCanonicalizing([$this->s['x']->id, $this->s['y']->id], array_keys($r['completezza']));
    }

    public function test_pwa_riepilogo_sola_lettura_per_la_commissione(): void
    {
        $this->apri();
        $this->colloquioPersonale('docA', 'x', 4, 'Esempi concreti');
        foreach (['direttore', 'presidente', 'docA'] as $k) {
            $this->come($k)->get(route('app.esame.provafinale.riepilogo', $this->esame))->assertOk()
                ->assertSee('Schede personali dei commissari')->assertSee('Schede finali del direttore')->assertSee('Esempi concreti');
            $this->come($k)->get(route('app.esame', $this->esame))->assertOk()->assertSee('Riepilogo prova finale');
        }
        foreach (['docB', 'x'] as $k) {
            $this->come($k)->get(route('app.esame.provafinale.riepilogo', $this->esame))->assertNotFound();
        }
        // Il presidente vede il riepilogo ma non la compilazione.
        $this->come('presidente')->get(route('app.esame', $this->esame))->assertOk()->assertDontSee('La tua scheda a semaforo');
        // Chiusa: le personali restano nel riepilogo PWA, con l'avviso di sola lettura.
        $this->esame->fresh()->chiudiProvaFinale();
        $this->come('presidente')->get(route('app.esame.provafinale.riepilogo', $this->esame))->assertOk()->assertSee('Esempi concreti')->assertSee('sola lettura');
    }

    // ---- Schede finali: solo il direttore ----

    public function test_finale_solo_il_direttore_nemmeno_il_superadmin(): void
    {
        $e = $this->esame->fresh();
        $this->assertTrue($e->puoInserireFinali($this->u['direttore']));
        foreach (['presidente', 'docA', 'docB', 'x', 'super', 'adminReg', 'adminLoc'] as $k) {
            $this->assertFalse($e->puoInserireFinali($this->u[$k]), $k);
        }
        // puoDecidereItinere è la stessa regola (nessuna duplicazione).
        $this->assertSame($e->puoInserireFinali($this->u['super']), $e->puoDecidereItinere($this->u['super']));

        $this->expectException(\RuntimeException::class);
        FinaleProvaFinale::salvaColloquio($e, $this->u['super'], $this->s['x']->id, '3', null);
    }

    public function test_finale_colloquio_proposta_troncata_motivazione_e_range(): void
    {
        $this->apri();
        $this->colloquioPersonale('docA', 'x', 4);
        $this->colloquioPersonale('direttore', 'x', 3);
        $e = $this->esame->fresh();
        $x = $this->s['x']->id;

        // Proposta = media 3,5 troncata a 1 decimale.
        $this->assertSame('3.5', RiepilogoProvaFinale::propostaColloquio($e, $x)->tronca(1));

        $salva = fn (string $p, ?string $m = null) => FinaleProvaFinale::salvaColloquio($e, $this->u['direttore'], $x, $p, $m);
        foreach (['0.9', '5.1', '3.55', 'abc', ''] as $non_valido) {
            try {
                $salva($non_valido, 'm');
                $this->fail("Atteso il rifiuto di '$non_valido'");
            } catch (ValidationException) {
            }
        }
        $this->assertSame(0, $e->colloqui()->where('tipo', 'finale')->count());

        // Diverso dalla proposta senza motivazione → rifiutato; con motivazione → salvato.
        try {
            $salva('4');
            $this->fail('Motivazione obbligatoria');
        } catch (ValidationException $ex) {
            $this->assertArrayHasKey('motivazione', $ex->errors());
        }
        $f = $salva('3,5'); // uguale alla proposta: nessuna motivazione
        $this->assertSame('3.5', (string) $f->punteggio);
        $f = $salva('4', 'Ha recuperato nel dialogo');
        $this->assertSame(1, $e->colloqui()->where('tipo', 'finale')->count()); // sovrascrive
        $this->assertSame('4.0', (string) $f->fresh()->punteggio);
        $this->assertSame($this->s['direttore']->id, $f->fresh()->inserito_da);
        // Non tocca le personali.
        $this->assertSame(2, $e->colloqui()->where('tipo', 'personale')->count());
    }

    public function test_finale_colloquio_senza_schede_personali_non_serve_motivazione(): void
    {
        $f = FinaleProvaFinale::salvaColloquio($this->esame->fresh(), $this->u['direttore'], $this->s['x']->id, '2.5', null);
        $this->assertSame('2.5', (string) $f->punteggio);
    }

    public function test_finali_rifiutate_per_non_ammessi_ritirati_chiuso_e_bozza(): void
    {
        $e = $this->esame->fresh();
        foreach (['nonAmmesso', 'ritirato'] as $k) {
            try {
                FinaleProvaFinale::salvaColloquio($e, $this->u['direttore'], $this->s[$k]->id, '3', null);
                $this->fail("$k deve essere rifiutato");
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('discente', $ex->errors());
            }
            try {
                FinaleProvaFinale::salvaProvaPratica($e, $this->u['direttore'], $this->s[$k]->id, $this->tutteLeVoci('verde'));
                $this->fail("$k deve essere rifiutato");
            } catch (ValidationException) {
            }
        }
        $this->assertSame(0, $e->colloqui()->count() + $e->provePratiche()->count());

        $e->update(['stato' => Esame::STATO_CHIUSO, 'chiuso_il' => now()]);
        $this->expectException(\RuntimeException::class);
        FinaleProvaFinale::salvaColloquio($e->fresh(), $this->u['direttore'], $this->s['x']->id, '3', null);
    }

    public function test_finale_prova_pratica_tutte_le_voci_e_sovrascrive(): void
    {
        $e = $this->esame->fresh();
        $x = $this->s['x']->id;
        $parziale = $this->tutteLeVoci('verde');
        unset($parziale[$this->voci[0]]);
        try {
            FinaleProvaFinale::salvaProvaPratica($e, $this->u['direttore'], $x, $parziale);
            $this->fail('27 voci non bastano');
        } catch (ValidationException $ex) {
            $this->assertArrayHasKey('voci', $ex->errors());
        }
        try {
            FinaleProvaFinale::salvaProvaPratica($e, $this->u['direttore'], $x, $this->tutteLeVoci('blu'));
            $this->fail('valore sconosciuto');
        } catch (ValidationException) {
        }
        $this->assertSame(0, $e->provePratiche()->count());

        $f = FinaleProvaFinale::salvaProvaPratica($e, $this->u['direttore'], $x, $this->tutteLeVoci('verde'));
        $this->assertSame('finale', $f->tipo);
        $this->assertNull($f->membro_socio_id);
        $this->assertSame('28,00', $f->fresh('voci')->totaleFormattato($e->modello_snapshot)['punti']);

        FinaleProvaFinale::salvaProvaPratica($e, $this->u['direttore'], $x, $this->tutteLeVoci('giallo'));
        $this->assertSame(1, $e->provePratiche()->count());
        $this->assertSame(28, $f->voci()->count());
        $this->assertSame('7,00', $f->fresh('voci')->totaleFormattato($e->modello_snapshot)['punti']);
    }

    public function test_proposta_prova_pratica_dalle_sole_schede_inviate_con_discordanze(): void
    {
        $this->apri();
        $this->provaPersonale('docA', 'x', '1', true);                // tutto verde
        $this->provaPersonale('direttore', 'x', '1', true, '0.25');   // verde ma la prima voce gialla
        $r = RiepilogoProvaFinale::provaPraticaDiscente($this->esame->fresh(), $this->s['x']->id);
        $this->assertCount(2, $r['commissari']);
        $this->assertSame('0.25', $r['proposta'][$this->voci[0]]);    // pareggio 1 a 1 → il più basso
        $this->assertTrue($r['discordanti'][$this->voci[0]]);
        $this->assertSame('1.00', $r['proposta'][$this->voci[1]]);
        $this->assertFalse($r['discordanti'][$this->voci[1]]);

        // Una bozza non conta nella proposta.
        $this->provaPersonale('docB', 'x', '0', false);
        $this->assertCount(2, RiepilogoProvaFinale::provaPraticaDiscente($this->esame->fresh(), $this->s['x']->id)['commissari']);

        // A prova chiusa proposta e confronto restano disponibili al direttore.
        $this->esame->fresh()->chiudiProvaFinale();
        $r = RiepilogoProvaFinale::provaPraticaDiscente($this->esame->fresh(), $this->s['x']->id);
        $this->assertCount(2, $r['commissari']);
        $this->assertSame('0.25', $r['proposta'][$this->voci[0]]);
    }

    // ---- Pannello ----

    public function test_pannello_lettura_commissione_e_inserimento_solo_direttore(): void
    {
        $this->apri();
        $this->colloquioPersonale('docA', 'x', 4, 'Chiaro');
        foreach (['direttore', 'presidente', 'docA', 'super', 'adminReg', 'adminLoc'] as $k) {
            $this->pannello($k);
            $this->assertTrue(EsameResource\Pages\ColloquioEsame::canAccess(['record' => $this->esame->fresh()]), $k);
            Livewire::test(EsameResource\Pages\ColloquioEsame::class, ['record' => $this->esame->getRouteKey()])
                ->assertSee('Schede personali dei commissari')->assertSee('Schede finali')->assertSee('Chiaro');
            Livewire::test(EsameResource\Pages\ProvaPraticaEsame::class, ['record' => $this->esame->getRouteKey()])
                ->assertSee('Schede personali dei commissari')->assertSee('Schede finali');
        }
        foreach (['docB', 'x'] as $k) {
            $this->pannello($k);
            $this->assertFalse(EsameResource\Pages\ColloquioEsame::canAccess(['record' => $this->esame->fresh()]), $k);
        }
        // Pagina di inserimento della prova pratica finale: solo il direttore.
        foreach (['presidente', 'docA', 'super', 'adminReg'] as $k) {
            $this->pannello($k);
            $this->assertFalse(EsameResource\Pages\InserisciProvaPraticaFinale::canAccess(['record' => $this->esame->fresh()]), $k);
        }
        $this->pannello('direttore');
        $this->assertTrue(EsameResource\Pages\InserisciProvaPraticaFinale::canAccess(['record' => $this->esame->fresh()]));
        Livewire::test(EsameResource\Pages\ColloquioEsame::class, ['record' => $this->esame->getRouteKey()])->assertSee('Inserisci colloquio finale');
        Livewire::test(EsameResource\Pages\ProvaPraticaEsame::class, ['record' => $this->esame->getRouteKey()])->assertSee('Inserisci prova pratica finale');
        $this->pannello('presidente');
        Livewire::test(EsameResource\Pages\ColloquioEsame::class, ['record' => $this->esame->getRouteKey()])->assertDontSee('Inserisci colloquio finale');
        Livewire::test(EsameResource\Pages\ProvaPraticaEsame::class, ['record' => $this->esame->getRouteKey()])->assertDontSee('Inserisci prova pratica finale');
    }

    public function test_pannello_azione_colloquio_finale_e_pagina_prova_pratica(): void
    {
        $this->apri();
        $this->colloquioPersonale('docA', 'x', 4);
        $this->colloquioPersonale('direttore', 'x', 3);
        $this->pannello('direttore');

        Livewire::test(EsameResource\Pages\ColloquioEsame::class, ['record' => $this->esame->getRouteKey()])
            ->callAction('colloquioFinale', ['punteggio' => '4.0', 'proposta' => '3.5', 'motivazione' => 'Più sicuro del previsto'], [
                'discente_id' => $this->s['x']->id, 'discente_nome' => 'X', 'proposta' => '3.5',
            ]);
        $this->assertSame('4.0', (string) $this->esame->colloqui()->where('tipo', 'finale')->first()->punteggio);

        $this->provaPersonale('docA', 'x', '1');
        Livewire::test(EsameResource\Pages\InserisciProvaPraticaFinale::class, ['record' => $this->esame->getRouteKey(), 'discente' => $this->s['x']->id])
            ->assertSee('precompilate')
            ->call('salva', $this->tutteLeVoci('verde'));
        $this->assertNotNull($this->esame->provePratiche()->where('tipo', 'finale')->first());

        // Non ammessi e ritirati: la pagina non esiste.
        foreach (['nonAmmesso', 'ritirato'] as $k) {
            Livewire::test(EsameResource\Pages\InserisciProvaPraticaFinale::class, ['record' => $this->esame->getRouteKey(), 'discente' => $this->s[$k]->id])->assertNotFound();
        }
    }

    // ---- Completezza e protezione docenti ----

    public function test_completezza_solo_finali_e_personali_mancanti_come_segnalazione(): void
    {
        $this->apri();
        $e = $this->esame->fresh();
        $x = $this->s['x']->id;

        $c = CompletezzaEsame::per($e);
        $this->assertFalse($c[$x]['completa']);
        $this->assertSame(0, $c[$x]['aree_decise']);
        $this->assertSame(['colloquio' => 2, 'prova_pratica' => 2, 'attese' => 2], $c[$x]['personali_mancanti']);

        TcriColloquio::create(['esame_id' => $e->id, 'discente_socio_id' => $x, 'tipo' => 'finale', 'punteggio' => '3.5']);
        FinaleProvaFinale::salvaProvaPratica($e, $this->u['direttore'], $x, $this->tutteLeVoci('verde'));
        foreach ($e->modello_snapshot['itinere']['aree'] as $area) {
            $e->decisioniItinere()->create(['discente_socio_id' => $x, 'area_codice' => $area['codice'], 'punteggio' => '3.0']);
        }
        $c = CompletezzaEsame::per($e->fresh());
        $this->assertTrue($c[$x]['completa'], 'le personali mancanti non rendono incompleto');
        $this->assertFalse($c[$this->s['y']->id]['completa']);
        $this->assertArrayNotHasKey($this->s['nonAmmesso']->id, $c);
    }

    public function test_docente_con_scheda_personale_di_prova_finale_non_si_toglie_dal_corso(): void
    {
        $this->assertNotContains($this->s['docB']->id, $this->corso->docentiNonRimovibili()->all());
        $this->colloquioPersonale('docB', 'x', 3);
        $this->assertContains($this->s['docB']->id, $this->corso->fresh()->docentiNonRimovibili()->all());

        $this->provaPersonale('docB', 'y', '1');
        $this->assertSame(1, $this->corso->fresh()->docentiNonRimovibili()->filter(fn ($id) => $id === $this->s['docB']->id)->count());
    }

    public function test_riportare_in_bozza_azzera_l_interruttore(): void
    {
        $this->apri();
        $this->esame->fresh()->riportaInBozza();
        $this->assertFalse($this->esame->fresh()->provaFinaleAperta());
    }
}
