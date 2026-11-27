<?php

namespace Tests\Feature;

use App\Filament\Resources\CorsoResource;
use App\Mail\QuestionarioGradimentoInvito;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\Iscrizione;
use App\Models\QuestionarioGradimento;
use App\Models\QuestionarioRisposta;
use App\Models\Socio;
use App\Models\TestPubblicazione;
use App\Models\User;
use App\Questionari\CompilazioneQuestionario;
use App\Questionari\InvioQuestionario;
use App\Questionari\PermessiQuestionario;
use App\Questionari\StatisticheQuestionario;
use Database\Seeders\EsameModelliSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Questionario di gradimento: anonimato, invio, compilazione dall'app, soglia, permessi. DB di test: lurch_test. */
class QuestionarioGradimentoTest extends TestCase
{
    use RefreshDatabase;

    private Corso $corso;
    private int $cr;
    /** @var array<string, Socio> */
    private array $s = [];
    /** @var array<string, Iscrizione> */
    private array $i = [];

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        $this->cr = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR', 'created_at' => $now, 'updated_at' => $now]);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $this->cr, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'candidature_staff_uuid' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach (['direttore', 'docente', 'a', 'b', 'c', 'd', 'e', 'f', 'ritirato', 'nonAmmesso', 'senzaEmail'] as $k) {
            $this->s[$k] = Socio::create([
                'nome' => ucfirst($k), 'cognome' => 'Test', 'codice_fiscale' => strtoupper(substr(md5($k), 0, 16)),
                'email' => $k === 'senzaEmail' ? null : "{$k}@example.invalid",
            ]);
        }
        $this->corso = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso', 'numero_protocollo' => 'X/2026/Q/1',
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
            'direttore_socio_id' => $this->s['direttore']->id,
        ]);
        // Il direttore è anche tra i docenti: in S9 deve comparire una volta sola.
        $this->corso->docenti()->attach([$this->s['docente']->id, $this->s['direttore']->id]);
        foreach (['a', 'b', 'c', 'd', 'e', 'f', 'ritirato', 'nonAmmesso', 'senzaEmail'] as $k) {
            $this->i[$k] = Iscrizione::create([
                'corso_id' => $this->corso->id, 'partecipante_id' => $this->s[$k]->id, 'token' => (string) Str::uuid(),
                'stato' => $k === 'ritirato' ? 'ritirato' : 'pending', 'non_ammesso' => $k === 'nonAmmesso',
            ]);
        }
        $this->travelTo('2026-11-10 10:00:00');
    }

    private function utente(string $ruolo, array $extra = []): User
    {
        return User::create($extra + ['name' => $ruolo, 'email' => $ruolo . uniqid() . '@t.it', 'password' => 'x', 'ruolo' => $ruolo, 'attivo' => true]);
    }

    private function questionario(array $sezioni = []): QuestionarioGradimento
    {
        return QuestionarioGradimento::create(['corso_id' => $this->corso->id, 'versione' => 1, 'giorni_apertura' => 7, 'sezioni' => $sezioni]);
    }

    private function invia(QuestionarioGradimento $q, array $chi): array
    {
        Mail::fake();

        return InvioQuestionario::invia(
            $this->corso->fresh(), $q, $this->utente('superadmin'),
            collect($chi)->map(fn ($k) => $this->i[$k]->fresh()), Mail::getFacadeRoot(),
        );
    }

    /** Risposte valide complete per le sezioni sempre presenti e i valutati. */
    private function risposteComplete(QuestionarioGradimento $q): array
    {
        $r = [];
        foreach (\App\Questionari\Domande::sezioniAttive(1, $q->sezioni) as $sez) {
            foreach ($sez['domande'] as $d) {
                $v = match ($d['tipo']) {
                    'scale5' => '4', 'yes_no' => 'No', 'single_choice', 'single_choice_other' => $d['opzioni'][0],
                    default => 'testo di prova',
                };
                if ($d['show_if']) {
                    continue;
                }
                $r[$d['key']] = $sez['per_valutato'] ? array_fill(0, count($q->valutati), $v) : $v;
            }
        }

        return $r;
    }

    private function comeSocio(string $k): self
    {
        return $this->withSession(['app_socio_id' => $this->s[$k]->id, 'app_login_at' => now()->getTimestamp()]);
    }

    public function test_risposta_non_collegabile_a_invito_o_discente(): void
    {
        $this->assertSame(['id', 'questionario_id', 'risposte'], Schema::getColumnListing('questionario_risposte'));
        $this->assertSame(['id', 'questionario_id', 'iscrizione_id', 'inviato_il', 'compilato', 'aperto', 'bozza'], Schema::getColumnListing('questionario_inviti'));
        $this->assertSame([], array_filter(Schema::getForeignKeys('questionario_risposte'), fn ($fk) => $fk['foreign_table'] !== 'questionari_gradimento'));

        $q = $this->questionario();
        $this->invia($q, ['a']);
        $this->assertTrue(CompilazioneQuestionario::salva($q->fresh(), $this->s['a']->id, ['x' => 1]));
        $id = QuestionarioRisposta::first()->id;
        $this->assertTrue(Str::isUuid($id));
        $this->assertSame('4', $id[14], 'UUID v4 casuale, non v7 ordinato nel tempo');
    }

    public function test_compila_una_volta_sola_e_non_dopo_la_chiusura(): void
    {
        $q = $this->questionario();
        $this->invia($q, ['a', 'b']);
        $q->refresh();
        $this->assertSame('2026-11-17 23:59:59', $q->chiude_il->format('Y-m-d H:i:s'));

        $this->assertTrue(CompilazioneQuestionario::salva($q, $this->s['a']->id, ['x' => 1]));
        $this->assertFalse(CompilazioneQuestionario::salva($q, $this->s['a']->id, ['x' => 2]), 'seconda compilazione');
        $this->assertFalse(CompilazioneQuestionario::salva($q, $this->s['c']->id, ['x' => 1]), 'non invitato');

        $this->travelTo('2026-11-18 00:00:01');
        $this->assertFalse(CompilazioneQuestionario::salva($q, $this->s['b']->id, ['x' => 1]), 'dopo la chiusura');
        $this->assertSame(1, $q->risposte()->count());
    }

    public function test_blocchi_esame_mandati_e_commento_dentro_il_monitoraggio(): void
    {
        $blocchi = \App\Questionari\Domande::blocchi(1, ['esame_finale', 'tempo_mandati']);
        $mon = collect($blocchi)->firstWhere('key', 'monitoraggio');
        $this->assertSame([null, 'Esame finale', 'Tempo per la preparazione dei mandati', null], array_column($mon['parti'], 'sottotitolo'));
        $this->assertSame('mon_commento', $mon['parti'][3]['domande'][0]['key'], 'il commento chiude il blocco');
        $this->assertSame('bilancio', end($blocchi)['key']);

        $senza = collect(\App\Questionari\Domande::blocchi(1, []))->firstWhere('key', 'monitoraggio');
        $this->assertCount(2, $senza['parti'], 'senza sezioni facoltative: domande + commento');
    }

    public function test_sezioni_facoltative_e_dettaglio_disagio(): void
    {
        $q = $this->questionario();
        $this->invia($q, ['a']);
        $q->refresh();
        [$pulite, $errori] = CompilazioneQuestionario::valida($q, $this->risposteComplete($q) + ['pasti_cibo' => '5']);
        $this->assertSame([], $errori);
        $this->assertArrayNotHasKey('pasti_cibo', $pulite, 'sezione non spuntata: valore scartato');

        // Stesso corso, sezione «Esame finale» spuntata (modello non salvato: un questionario per corso).
        $q2 = new QuestionarioGradimento(['versione' => 1, 'sezioni' => ['esame_finale'], 'valutati' => $q->valutati]);
        $base = $this->risposteComplete($q2);
        $this->assertArrayHasKey('esame_disagio', $base);

        [, $errori] = CompilazioneQuestionario::valida($q2, ['esame_disagio' => 'No'] + $base);
        $this->assertArrayNotHasKey('esame_disagio_dettaglio', $errori);

        [, $errori] = CompilazioneQuestionario::valida($q2, ['esame_disagio' => 'Sì'] + $base);
        $this->assertArrayHasKey('esame_disagio_dettaglio', $errori);

        [$pulite, $errori] = CompilazioneQuestionario::valida($q2, ['esame_disagio' => 'Sì', 'esame_disagio_dettaglio' => 'ansia'] + $base);
        $this->assertSame([], $errori);
        $this->assertSame('ansia', $pulite['esame_disagio_dettaglio']);

        [, $errori] = CompilazioneQuestionario::valida($q2, ['org_ore' => ''] + $base);
        $this->assertArrayHasKey('org_ore', $errori, 'tutte le domande sono obbligatorie');
    }

    public function test_s9_ripete_direttore_e_docenti_della_copia_salvata(): void
    {
        $q = $this->questionario();
        $this->invia($q, ['a']);
        $q->refresh();
        $this->assertSame(
            [[$this->s['direttore']->id, 'direttore'], [$this->s['docente']->id, 'docente']],
            array_map(fn ($v) => [$v['socio_id'], $v['ruolo']], $q->valutati),
        );

        // Un docente aggiunto dopo l'invio non entra nel questionario già inviato.
        $nuovo = Socio::create(['nome' => 'Nuovo', 'cognome' => 'Doc', 'codice_fiscale' => 'NVDCXX80A01H501Z']);
        $this->corso->docenti()->attach($nuovo->id);
        [, $errori] = CompilazioneQuestionario::valida($q->fresh(), []);
        $this->assertArrayHasKey('doc_competenza.0', $errori);
        $this->assertArrayHasKey('doc_competenza.1', $errori);
        $this->assertArrayNotHasKey('doc_competenza.2', $errori);
    }

    public function test_invio_unico_senza_doppioni_e_dopo_la_fine_del_corso(): void
    {
        $q = $this->questionario();
        $this->travelTo('2026-11-02 12:00:00');
        $this->assertNotNull(InvioQuestionario::motivoBlocco($this->corso, $q), 'corso non ancora finito');
        $this->travelTo('2026-11-10 10:00:00');

        $this->assertSame(['inviati' => 2, 'saltati' => 1], $this->invia($q, ['a', 'b', 'senzaEmail']));
        Mail::assertSent(QuestionarioGradimentoInvito::class, 2);

        $esito = $this->invia($q->fresh(), ['a', 'c']);
        $this->assertSame(['inviati' => 1, 'saltati' => 1], $esito);
        Mail::assertSent(QuestionarioGradimentoInvito::class, fn ($m) => $m->hasTo('c@example.invalid'));
        Mail::assertNotSent(QuestionarioGradimentoInvito::class, fn ($m) => $m->hasTo('a@example.invalid'));
        $this->assertSame(3, $q->inviti()->count());
    }

    public function test_frase_esame_solo_con_risultati_pubblicati_e_mai_a_ritirati_o_non_ammessi(): void
    {
        $this->seed(EsameModelliSeeder::class);
        $catalogo = CatalogoCorso::create(['codice' => 'TCRI', 'nome' => 'TCRI', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'TCRI', 'attivo' => true]);
        $this->corso->update(['catalogo_corso_id' => $catalogo->id]);
        $esame = Esame::creaPerCorso($this->corso->fresh(), 'Esame', 60);

        foreach (['a', 'd', 'ritirato', 'nonAmmesso'] as $k) {
            TestPubblicazione::create(['esame_id' => $esame->id, 'discente_socio_id' => $this->s[$k]->id, 'pubblicato_il' => now()]);
        }
        foreach (['d', 'e'] as $k) {
            \App\Models\EsitoPubblicazione::create(['esame_id' => $esame->id, 'discente_socio_id' => $this->s[$k]->id, 'pubblicato_il' => now()]);
        }
        TestPubblicazione::create(['esame_id' => $esame->id, 'discente_socio_id' => $this->s['c']->id, 'pubblicato_il' => null]);

        $this->assertSame('test', InvioQuestionario::fraseEsame($this->i['a']->fresh()));
        $this->assertSame('entrambi', InvioQuestionario::fraseEsame($this->i['d']->fresh()));
        $this->assertSame('esito', InvioQuestionario::fraseEsame($this->i['e']->fresh()));
        $this->assertNull(InvioQuestionario::fraseEsame($this->i['b']->fresh()), 'nessuna pubblicazione');
        $this->assertNull(InvioQuestionario::fraseEsame($this->i['c']->fresh()), 'pubblicazione ritirata');
        $this->assertNull(InvioQuestionario::fraseEsame($this->i['ritirato']->fresh()));
        $this->assertNull(InvioQuestionario::fraseEsame($this->i['nonAmmesso']->fresh()));

        // Avviso prima dell'invio: b e c (ammessi, senza risultati); ritirato e non ammesso non contano.
        $sel = collect(['a', 'b', 'c', 'ritirato', 'nonAmmesso'])->map(fn ($k) => $this->i[$k]->fresh());
        $this->assertSame(2, InvioQuestionario::senzaRisultatiPubblicati($this->corso->fresh(), $sel));

        $q = $this->questionario();
        $this->invia($q, ['a', 'b', 'd', 'e']);
        $testo = fn (string $k) => Mail::sent(QuestionarioGradimentoInvito::class, fn ($m) => $m->hasTo("{$k}@example.invalid"))->first()->render();
        $this->assertStringContainsString('risultati del test scritto</strong> del tuo esame', $testo('a'));
        $this->assertStringContainsString('risultati del test scritto</strong> e l\'<strong>esito finale', $testo('d'));
        $this->assertStringContainsString('anche l\'<strong>esito finale</strong> del tuo esame', $testo('e'));
        $this->assertStringNotContainsString('del tuo esame', $testo('b'));
        // Impaginazione comune delle email di corsi ed eventi, mai quella standard di Laravel.
        $this->assertStringContainsString('Croce Rossa Italiana', $testo('b'));
        $this->assertStringNotContainsString('All rights reserved', $testo('b'));
        $this->assertStringNotContainsString('Laravel', $testo('b'));
    }

    public function test_anteprima_email_dal_pannello_senza_invio_ne_salvataggi(): void
    {
        Mail::fake();
        $q = $this->questionario();
        $this->actingAs($this->utente('superadmin'));
        $this->get(CorsoResource::getUrl('questionario', ['record' => $this->corso]))->assertOk();

        \Livewire\Livewire::test(CorsoResource\Pages\QuestionarioCorso::class, ['record' => $this->corso])
            ->set('selezionati', [$this->i['b']->id])
            ->mountAction('anteprima')
            ->assertMountedActionModalSee([
                'Questionario di gradimento – X/2026/Q/1', 'b@example.invalid', 'Ciao B Test', '17/11/2026',
                'posta in uscita della regione non configurata', 'primo dei selezionati',
            ]);

        Mail::assertNothingSent();
        $this->assertSame(0, $q->inviti()->count());
        $this->assertNull($q->fresh()->chiude_il, 'la chiusura calcolata per l\'anteprima non si salva');
    }

    public function test_compilazione_dall_app_e_soglia_dei_risultati_per_il_docente(): void
    {
        $q = $this->questionario();
        $this->invia($q, ['a', 'b', 'c', 'd', 'e', 'f']);
        $q->refresh();

        $this->comeSocio('c')->get(route('app.questionario', $q))->assertOk()->assertSee('Docente – Docente Test')->assertSee('Direttore del corso – Direttore Test');
        $this->comeSocio('ritirato')->get(route('app.questionario', $q))->assertNotFound();
        $this->comeSocio('a')->post(route('app.questionario.invia', $q), ['r' => ['org_ore' => '3']])->assertStatus(422);
        $this->assertSame(0, $q->risposte()->count());

        foreach (['a', 'b', 'c', 'd'] as $k) {
            $this->comeSocio($k)->post(route('app.questionario.invia', $q), ['r' => $this->risposteComplete($q)])->assertRedirect(route('app.home'));
        }
        $this->comeSocio('a')->get(route('app.questionario', $q))->assertSee('Hai già compilato');
        $this->assertFalse(StatisticheQuestionario::visibili($q));
        $this->comeSocio('docente')->get(route('app.questionario.risultati', $q))->assertOk()->assertSee('almeno 5');

        $this->comeSocio('e')->post(route('app.questionario.invia', $q), ['r' => $this->risposteComplete($q)]);
        $this->assertTrue(StatisticheQuestionario::visibili($q));

        // Il docente vede solo la propria sezione; chi non è valutato non vede nulla.
        $sezioni = StatisticheQuestionario::per($q, PermessiQuestionario::indiceValutato($q, $this->s['docente']->id));
        $this->assertSame(['Docente – Docente Test'], array_column($sezioni, 'titolo'));
        $this->comeSocio('docente')->get(route('app.questionario.risultati', $q))->assertOk()
            ->assertSee('Docente – Docente Test')->assertDontSee('Organizzazione generale')->assertDontSee('Direttore del corso');
        $this->comeSocio('a')->get(route('app.questionario.risultati', $q))->assertNotFound();
        $stat = StatisticheQuestionario::per($q);
        $this->assertSame(5, $stat[0]['parti'][0]['domande'][0]['n']);
        $this->assertSame(30, $stat[0]['riepilogo']['n'], 'riepilogo del blocco: 6 domande a scala × 5 risposte');
        // Esame finale e commento finale stanno dentro il blocco Monitoraggio (non sono sezioni a sé).
        $this->assertNotContains('Monitoraggio, valutazione e feedback – commento', array_column($stat, 'titolo'));

        // Pannello ed export sopra la soglia; mai i nomi di chi ha compilato.
        $this->actingAs($this->utente('superadmin'));
        $this->get(CorsoResource::getUrl('questionario', ['record' => $this->corso]))->assertOk()
            ->assertSeeInOrder(['Invitati', 'Mai aperto', 'Iniziati', 'Completati'])->assertSee('Media')->assertDontSee('Compilato');
        $this->assertSame(5, \App\Questionari\CompilazioneQuestionario::conteggi($q)['completati']);
        $this->get(route('corsi.questionario-excel', $this->corso))->assertOk();
        $this->get(route('corsi.questionario-pdf', $this->corso))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('corsi.questionario-pdf-docente', ['corso' => $this->corso, 'indice' => 1]))->assertOk();
        $this->get(route('corsi.questionario-pdf-docente', ['corso' => $this->corso, 'indice' => 9]))->assertNotFound();
    }

    public function test_bozza_cifrata_ripresa_cancellata_all_invio_e_alla_chiusura_con_i_conteggi(): void
    {
        $q = $this->questionario();
        $this->invia($q, ['a', 'b', 'c']);
        $q->refresh();
        $conteggi = fn () => \App\Questionari\CompilazioneQuestionario::conteggi($q);
        $this->assertSame(['invitati' => 3, 'mai_aperto' => 3, 'iniziati' => 0, 'completati' => 0], $conteggi());

        // Aprire non è iniziare; una risposta salvata sì. La bozza è cifrata nel database e torna nel modulo.
        $this->comeSocio('a')->get(route('app.questionario', $q))->assertOk();
        $this->comeSocio('a')->post(route('app.questionario.bozza', $q), ['r' => ['org_ore' => '2', 'contenuti_commento' => 'testo riservato della bozza', 'chiave_ignota' => 'x']])
            ->assertOk()->assertJson(['ok' => true]);
        $grezza = DB::table('questionario_inviti')->where('iscrizione_id', $this->i['a']->id)->value('bozza');
        $this->assertStringNotContainsString('riservato', $grezza);
        $invitoA = \App\Models\QuestionarioInvito::where('iscrizione_id', $this->i['a']->id)->first();
        $this->assertSame(['contenuti_commento' => 'testo riservato della bozza', 'org_ore' => 2], $invitoA->bozza, 'solo risposte valide e note');
        $this->comeSocio('a')->get(route('app.questionario', $q))->assertSee('testo riservato della bozza')->assertSee('salvata temporaneamente e cifrata');
        $this->comeSocio('b')->get(route('app.questionario', $q));
        $this->assertSame(['invitati' => 3, 'mai_aperto' => 1, 'iniziati' => 1, 'completati' => 0], $conteggi());

        // All'invio la bozza sparisce; dopo, nessuna bozza si salva più.
        $this->comeSocio('a')->post(route('app.questionario.invia', $q), ['r' => $this->risposteComplete($q)])->assertRedirect(route('app.home'));
        $this->assertNull(DB::table('questionario_inviti')->where('iscrizione_id', $this->i['a']->id)->value('bozza'));
        $this->comeSocio('a')->post(route('app.questionario.bozza', $q), ['r' => ['org_ore' => '1']])->assertStatus(409);
        $this->assertNull(DB::table('questionario_inviti')->where('iscrizione_id', $this->i['a']->id)->value('bozza'));
        $this->assertSame(['invitati' => 3, 'mai_aperto' => 1, 'iniziati' => 0, 'completati' => 1], $conteggi());
        $this->comeSocio('ritirato')->post(route('app.questionario.bozza', $q), ['r' => ['org_ore' => '1']])->assertNotFound();

        // Alla chiusura le bozze mai inviate si cancellano.
        $this->comeSocio('b')->post(route('app.questionario.bozza', $q), ['r' => ['org_ore' => '4']])->assertOk();
        $this->travelTo('2026-11-18 00:00:01');
        $this->comeSocio('b')->post(route('app.questionario.bozza', $q), ['r' => ['org_ore' => '5']])->assertStatus(409);
        $this->artisan('questionari:cancella-bozze-scadute')->assertSuccessful();
        $this->assertSame(0, DB::table('questionario_inviti')->whereNotNull('bozza')->count());
        $this->assertSame(1, $q->risposte()->count());
    }

    public function test_chi_gestisce_il_questionario(): void
    {
        $evento = $this->corso->evento;
        $this->assertTrue(PermessiQuestionario::puoGestire($this->utente('admin', ['comitato_regionale_id' => $this->cr]), $this->corso));
        $this->assertTrue(PermessiQuestionario::puoGestire($this->utente('operatore', ['socio_id' => $this->s['direttore']->id]), $this->corso), 'direttore con ruolo operatore');

        $altra = DB::table('comitati_regionali')->insertGetId(['nome' => 'Altra', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertFalse(PermessiQuestionario::puoGestire($this->utente('admin', ['comitato_regionale_id' => $altra]), $this->corso));

        $operatore = $this->utente('operatore', ['comitato_regionale_id' => $this->cr]);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'corsi.view', 'guard_name' => 'web']);
        $operatore->givePermissionTo('corsi.view');
        DB::table('evento_operatore')->insert(['evento_id' => $evento->id, 'user_id' => $operatore->id]);
        $this->assertFalse(PermessiQuestionario::puoGestire($operatore, $this->corso), 'operatore assegnato all\'evento');

        $url = CorsoResource::getUrl('questionario', ['record' => $this->corso]);
        $this->actingAs($operatore)->get($url)->assertForbidden();
        // Sessione nuova: AuthenticateSession di Filament scollega se cambia l'utente nella stessa sessione.
        $this->flushSession();
        $this->actingAs($this->utente('operatore', ['socio_id' => $this->s['direttore']->id]))->get($url)->assertOk();
        $this->get(route('corsi.questionario-excel', $this->corso))->assertNotFound();
    }
}
