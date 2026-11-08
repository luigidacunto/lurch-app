<?php

namespace Tests\Feature;

use App\Esami\AmmissioneDiscente;
use App\Esami\AvvioTest;
use App\Esami\ChiusuraEsame;
use App\Esami\DecisioneItinere;
use App\Esami\FinaleProvaFinale;
use App\Esami\PreparaTest;
use App\Esami\SvolgimentoTest;
use App\Filament\Resources\EsameResource;
use App\Models\TestDomanda;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * TRIPWIRE della chiusura dell'esame: con l'esame CHIUSO nessuna scrittura deve passare, da nessun punto d'ingresso. Ogni test prende
 * l'«impronta» del DB (righe di ~20 tabelle) prima e dopo e deve trovarla identica.
 *  1. rotte PWA non-GET (itera Route::getRoutes(): una rotta nuova senza parametri mappati fa FALLIRE il test);
 *  2. servizi che scrivono;
 *  3. azioni del pannello (elenco dichiarato + test di copertura che scansiona le pagine: una Action nuova obbliga a classificarla);
 *  4. la lettura resta possibile.
 */
class EsameChiusoSolaLetturaTest extends EsameChiusuraBase
{
    private const TABELLE = [
        'esami', 'esame_docenti_commissione', 'esame_laboratori', 'esame_gruppi', 'esame_gruppo_discente', 'esame_gruppo_docente',
        'esame_tutoraggi', 'esame_tutoraggio_discente', 'esame_tutoraggio_docente',
        'tcri_schede_semaforo', 'tcri_schede_semaforo_voci', 'tcri_schede_itinere', 'tcri_schede_itinere_voci', 'tcri_itinere_decisioni',
        'tcri_colloqui', 'tcri_prove_pratiche', 'tcri_prove_pratiche_voci', 'test_domande', 'test_avvii', 'test_consegne',
        'test_risposte', 'esiti', 'iscrizioni',
    ];

    /** Azioni della cartella Pages che SCRIVONO (provate sotto) e quelle di sola lettura/navigazione. */
    private const AZIONI_SCRIVONO = [
        'segnaNonAmmesso', 'togliNonAmmesso', 'riportaInBozza', 'chiudi', 'riapri', 'attiva', 'provaFinale', 'commissione',
        'copiaGruppi', 'importaTest', 'creaLaboratorio', 'modificaLaboratorio', 'eliminaLaboratorio', 'nuovoGruppo', 'modificaGruppo', 'eliminaGruppo', 'invia', 'colloquioFinale', 'decidi', 'configurazione', 'domanda', 'eliminaDomanda', 'avviaTutti',
        'avviaSelezionati', 'avviaUno', 'disattiva', 'cartaceo', 'nuovoTutoraggio', 'modificaTutoraggio', 'eliminaTutoraggio',
    ];
    private const AZIONI_SOLA_LETTURA = ['csv', 'modifica'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->domande(25);
        $this->discenteCompleto('x');
        $this->discenteCompleto('y');

        // righe in TUTTE le tabelle: schede personali, gruppi, avvio attivo
        $lab = $this->esame->laboratori()->first();
        $gruppo = DB::table('esame_gruppi')->insertGetId(['esame_id' => $this->esame->id, 'laboratorio_id' => $lab->id, 'nome' => 'G1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('esame_gruppo_discente')->insert(['gruppo_id' => $gruppo, 'socio_id' => $this->s['x']->id]);
        DB::table('esame_gruppo_docente')->insert(['gruppo_id' => $gruppo, 'socio_id' => $this->s['docente']->id]);
        $sem = DB::table('tcri_schede_semaforo')->insertGetId(['laboratorio_id' => $lab->id, 'discente_socio_id' => $this->s['x']->id, 'docente_socio_id' => $this->s['docente']->id, 'created_at' => now(), 'updated_at' => now()]);
        $iti = DB::table('tcri_schede_itinere')->insertGetId(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['x']->id, 'docente_socio_id' => $this->s['docente']->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tcri_colloqui')->insert(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['x']->id, 'tipo' => 'personale', 'membro_socio_id' => $this->s['docComm']->id, 'punteggio' => 3, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tcri_prove_pratiche')->insert(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['x']->id, 'tipo' => 'personale', 'membro_socio_id' => $this->s['docComm']->id, 'created_at' => now(), 'updated_at' => now()]);
        unset($sem, $iti);

        ChiusuraEsame::chiudi($this->esame->fresh(), $this->u['direttore']);
        $this->esame->refresh();
        $this->assertTrue($this->esame->isChiuso());
    }

    private function impronta(): array
    {
        $r = [];
        foreach (self::TABELLE as $t) {
            $riga = DB::selectOne("SELECT count(*) AS n, coalesce(md5(string_agg(x::text, '|' ORDER BY x::text)), '') AS h FROM {$t} x");
            $r[$t] = $riga->n.':'.$riga->h;
        }

        return $r;
    }

    private function uguale(array $prima, string $dove): void
    {
        $dopo = $this->impronta();
        foreach ($prima as $tabella => $v) {
            $this->assertSame($v, $dopo[$tabella], "{$dove}: la tabella {$tabella} è cambiata a esame chiuso");
        }
    }

    // ---- 1. rotte PWA ----------------------------------------------------------------------------

    public function test_nessuna_rotta_pwa_scrive_ad_esame_chiuso(): void
    {
        $prima = $this->impronta();
        $lab = $this->esame->laboratori()->first();
        $token = (string) $this->esame->testAvvii()->where('discente_socio_id', $this->s['x']->id)->value('token');
        $domanda = TestDomanda::where('esame_id', $this->esame->id)->value('id');
        $payload = ['voto' => 3, 'voce' => 'itinere.team_work.1', 'valore' => 'verde', 'token' => $token, 'domanda' => $domanda, 'opzione' => 1, 'impressione' => 'x', 'annotazione' => 'x'];

        $provate = 0;
        foreach (Route::getRoutes() as $route) {
            $nome = $route->getName();
            if (! $nome || ! str_starts_with($nome, 'app.esame') || in_array('GET', $route->methods(), true)) {
                continue;
            }
            $parametri = [];
            foreach ($route->parameterNames() as $p) {
                $parametri[$p] = ['esame' => $this->esame, 'laboratorio' => $lab, 'socio' => $this->s['x']][$p]
                    ?? $this->fail("Parametro {$p} della rotta {$nome} non previsto dal tripwire: classificalo");
            }
            $metodo = strtolower($route->methods()[0]);
            foreach (['direttore', 'docComm', 'docente', 'presidente', 'x', 'y'] as $chi) {
                $r = $this->come($chi)->{$metodo.'Json'}(route($nome, $parametri), $payload);
                $this->assertContains($r->getStatusCode(), [403, 404, 409, 422], "{$chi} scrive su {$nome} a esame chiuso: {$r->getStatusCode()}");
                $provate++;
            }
        }
        $this->assertGreaterThan(30, $provate, 'Il tripwire non ha trovato le rotte di scrittura degli esami');
        $this->uguale($prima, 'rotte PWA');
    }

    // ---- 2. servizi ------------------------------------------------------------------------------

    public function test_nessun_servizio_scrive_ad_esame_chiuso(): void
    {
        $prima = $this->impronta();
        $e = $this->esame->fresh();
        $dir = $this->u['direttore'];
        $super = $this->u['super'];
        $xid = $this->s['x']->id;
        $d = TestDomanda::where('esame_id', $e->id)->first();
        $primaChiamata = [
            fn () => AvvioTest::avvia($e, $dir),
            fn () => AvvioTest::disattiva($e, $dir, $xid),
            fn () => AvvioTest::impostaCartaceo($e, $dir, $this->s['y']->id, 'DSA'),
            fn () => AvvioTest::riportaInBozza($e, $dir),
            fn () => PreparaTest::salvaConfigurazione($e, $dir, ['penalita' => false]),
            fn () => PreparaTest::salvaConfigurazione($e, $super, ['penalita' => false]),
            fn () => PreparaTest::creaDomanda($e, $dir, 'Nuova domanda?', ['a', 'b', 'c', 'd'], 1),
            fn () => PreparaTest::aggiornaDomanda($e, $super, $d, 'Refuso corretto', ['a', 'b', 'c', 'd'], 1),
            fn () => PreparaTest::eliminaDomanda($e, $dir, $d),
            fn () => SvolgimentoTest::salvaRisposta($e, $xid, 'token', 'online', null, $d->id, 1),
            fn () => SvolgimentoTest::invia($e, $xid, 'token', 'online', null),
            fn () => DecisioneItinere::salva($e, $dir, $xid, 'team_work', '3.0', 'motivo'),
            fn () => FinaleProvaFinale::salvaColloquio($e, $dir, $xid, '3.0', 'motivo'),
            fn () => FinaleProvaFinale::salvaProvaPratica($e, $dir, $xid, []),
            fn () => $e->attiva(),
            fn () => $e->riportaInBozza(),
            fn () => $e->apriProvaFinale(),
            fn () => $e->chiudiProvaFinale(),
            fn () => AmmissioneDiscente::segnaNonAmmesso($e, $dir, $xid, 'motivo'),
            fn () => AmmissioneDiscente::togliNonAmmesso($e, $dir, $this->s['nonAmmesso']->id),
            fn () => ChiusuraEsame::chiudi($e, $dir),
            fn () => ChiusuraEsame::riapri($e, $dir, 'Non sono il superadmin'),
        ];
        foreach ($primaChiamata as $i => $chiamata) {
            try {
                $chiamata();
                $this->fail("Il servizio n. {$i} non ha rifiutato la scrittura a esame chiuso");
            } catch (\PHPUnit\Framework\AssertionFailedError $f) {
                throw $f;
            } catch (\Throwable) {
                // atteso: rifiuto (RuntimeException, TestException, ValidationException…)
            }
        }
        $this->uguale($prima, 'servizi');
    }

    // ---- 3. pannello -----------------------------------------------------------------------------

    public function test_nessuna_azione_del_pannello_scrive_ad_esame_chiuso(): void
    {
        $tutoraggio = \App\Models\EsameTutoraggio::create(['esame_id' => $this->esame->id, 'nome' => 'T']);
        $tutoraggio->discenti()->attach($this->s['x']->id);
        $prima = $this->impronta();
        $lab = $this->esame->laboratori()->first();
        $iscr = \App\Models\Iscrizione::where('corso_id', $this->corso->id)->where('partecipante_id', $this->s['x']->id)->first();
        $d = TestDomanda::where('esame_id', $this->esame->id)->value('id');
        $x = $this->s['x']->id;

        $prove = [
            [EsameResource\Pages\GestioneEsame::class, 'azione', ['commissione' => [['docenti' => []]], 'attiva' => [[]], 'provaFinale' => [[]]]],
            [EsameResource\Pages\EditEsame::class, 'azione', ['riportaInBozza' => [[]]]],
            [EsameResource\Pages\EsitoEsame::class, 'azione', ['chiudi' => [[]]]],
            [EsameResource\Pages\RiepilogoItinereEsame::class, 'azione', ['decidi' => [['punteggio' => '3.0', 'motivazione' => 'x'], ['discente_id' => $x, 'area' => 'team_work']]]],
            [EsameResource\Pages\ColloquioEsame::class, 'azione', ['colloquioFinale' => [['punteggio' => '3.0', 'motivazione' => 'x'], ['discente_id' => $x]]]],
            [EsameResource\Pages\DomandeTestEsame::class, 'azione', [
                'configurazione' => [['penalita' => false]], 'domanda' => [['testo' => 'zz', 'o0' => 'a', 'o1' => 'b', 'o2' => 'c', 'o3' => 'd', 'corretta' => '1']],
                'eliminaDomanda' => [[], ['domanda_id' => $d]],
                'importaTest' => [['testo' => '{"domande":[{"testo":"q","opzioni":["a","b","c","d"],"corretta":"A"}]}']],
            ]],
            [EsameResource\Pages\GestioneLaboratori::class, 'azione', [
                'creaLaboratorio' => [['data' => null]],
                'modificaLaboratorio' => [['data' => null], ['laboratorio_id' => $lab->id]],
                'eliminaLaboratorio' => [[], ['laboratorio_id' => $lab->id]],
                'nuovoGruppo' => [['laboratorio_id' => $lab->id, 'nome' => 'Nuovo', 'discenti_ids' => [], 'docenti_ids' => []], ['laboratorio_id' => $lab->id]],
                'modificaGruppo' => [['laboratorio_id' => $lab->id, 'nome' => 'Rinominato', 'discenti_ids' => [], 'docenti_ids' => []], ['gruppo_id' => $this->esame->gruppi()->first()->id]],
                'eliminaGruppo' => [[], ['gruppo_id' => $this->esame->gruppi()->first()->id]],
                'copiaGruppi' => [['da' => $lab->id, 'gruppo' => 'tutti'], ['laboratorio_id' => $lab->id]],
            ]],
            [EsameResource\Pages\GestioneTutoraggio::class, 'azione', [
                'nuovoTutoraggio' => [['nome' => 'Nuovo', 'discenti_ids' => [], 'docenti_ids' => []]],
                'modificaTutoraggio' => [['nome' => 'Rinominato', 'discenti_ids' => [], 'docenti_ids' => []], ['gruppo_id' => $tutoraggio->id]],
                'eliminaTutoraggio' => [[], ['gruppo_id' => $tutoraggio->id]],
            ]],
            [EsameResource\Pages\TestScrittoEsame::class, 'azione', [
                'avviaTutti' => [[]], 'avviaSelezionati' => [[]], 'riportaInBozza' => [[]],
                'avviaUno' => [[], ['discente_id' => $x, 'discente_nome' => 'X']], 'disattiva' => [[], ['discente_id' => $x, 'discente_nome' => 'X']],
                'cartaceo' => [['motivo' => 'DSA'], ['discente_id' => $this->s['y']->id, 'discente_nome' => 'Y']],
            ]],
        ];
        foreach (['direttore', 'super', 'adminReg'] as $chi) {
            $this->pannello($chi);
            foreach ($prove as [$classe, , $azioni]) {
                foreach ($azioni as $nome => $args) {
                    try {
                        Livewire::test($classe, ['record' => $this->esame->getRouteKey()])->callAction($nome, $args[0], $args[1] ?? []);
                    } catch (\Throwable) {
                        // nascosta, disabilitata o rifiutata dal servizio: va bene
                    }
                }
            }
            // azioni di tabella
            foreach ([
                [EsameResource\Pages\DiscentiEsame::class, 'segnaNonAmmesso', $iscr, ['motivo' => 'x']],
            ] as [$classe, $azione, $record, $dati]) {
                try {
                    $t = Livewire::test($classe, ['record' => $this->esame->getRouteKey()]);
                    $t->callTableAction($azione, $record, $dati);
                } catch (\Throwable) {
                }
            }
            // pagine con metodi Livewire (non Action)
            foreach ([
                [EsameResource\Pages\InserisciProvaPraticaFinale::class, 'salva', [[]]],
                [EsameResource\Pages\InserisciTestCartaceo::class, 'scegli', [$d, 1]],
            ] as [$classe, $metodo, $args]) {
                try {
                    Livewire::test($classe, ['record' => $this->esame->getRouteKey(), 'discente' => $this->s['y']->id])->call($metodo, ...$args);
                } catch (\Throwable) {
                }
            }
        }
        $this->uguale($prima, 'pannello');
    }

    /** Ogni Action definita nelle pagine dell'esame deve essere classificata: una nuova obbliga a decidere se scrive. */
    public function test_copertura_ogni_azione_delle_pagine_e_classificata(): void
    {
        $noti = array_merge(self::AZIONI_SCRIVONO, self::AZIONI_SOLA_LETTURA);
        $trovate = [];
        foreach (glob(base_path('app/Filament/Resources/EsameResource/Pages/*.php')) as $file) {
            preg_match_all("/Action::make\('([A-Za-z]+)'\)/", file_get_contents($file), $m);
            foreach ($m[1] as $nome) {
                $trovate[$nome][] = basename($file);
            }
        }
        $this->assertNotEmpty($trovate);
        foreach ($trovate as $nome => $file) {
            $this->assertContains($nome, $noti, "Action '{$nome}' (".implode(', ', $file).") non classificata nel tripwire: aggiungila a AZIONI_SCRIVONO (e provala) o AZIONI_SOLA_LETTURA");
        }
    }

    // ---- 4. la lettura resta possibile -----------------------------------------------------------

    public function test_la_lettura_resta_possibile(): void
    {
        $this->come('direttore')->get(route('app.esame', $this->esame))->assertOk();
        $this->come('presidente')->get(route('app.esame.itinere.riepilogo', $this->esame))->assertOk();
        $this->pannello('presidente');
        Livewire::test(EsameResource\Pages\EsitoEsame::class, ['record' => $this->esame->getRouteKey()])->assertSuccessful();
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])->assertSuccessful();
        $this->actingAs($this->u['presidente'])->get(route('esami.esiti-csv', $this->esame))->assertOk();
    }
}
