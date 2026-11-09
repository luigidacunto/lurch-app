<?php

namespace Tests\Feature;

use App\Esami\DecisioneItinere;
use App\Esami\PermessiEsame;
use App\Esami\RiepilogoItinere;
use App\Filament\Resources\EsameResource;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Esami\Calcolo\Frazione;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\TcriItinereDecisione;
use App\Models\TcriSchedaItinere;
use App\Models\TcriSchedaSemaforo;
use App\Models\User;
use Database\Seeders\EsameModelliSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Esami: riepilogo itinere (lettura commissione) e scheda finale d'area (solo direttore). DB di test: lurch_test. */
class EsameRiepilogoItinereTest extends TestCase
{
    use RefreshDatabase;

    private Esame $esame;
    private Corso $corso;
    /** @var array<string, Socio> */
    private array $s = [];
    /** @var array<string, User> */
    private array $u = [];
    private string $area;
    /** @var list<string> */
    private array $vociArea;

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

        foreach (['direttore', 'presidente', 'docA', 'docB', 'docC', 'x', 'y', 'nonAmmesso', 'ritirato'] as $k) {
            $this->s[$k] = Socio::create(['nome' => $k . 'Nome', 'cognome' => 'Test' . $k, 'codice_fiscale' => strtoupper(substr(md5($k), 0, 16))]);
        }
        $this->corso = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso TCRI', 'numero_protocollo' => 'X/' . uniqid(),
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
            'direttore_socio_id' => $this->s['direttore']->id, 'presidente_socio_id' => $this->s['presidente']->id,
            'catalogo_corso_id' => $catalogo->id,
        ]);
        $this->corso->docenti()->attach([$this->s['docA']->id, $this->s['docB']->id, $this->s['docC']->id]);
        foreach (['x', 'y', 'nonAmmesso', 'ritirato'] as $k) {
            Iscrizione::create([
                'corso_id' => $this->corso->id, 'partecipante_id' => $this->s[$k]->id, 'token' => (string) Str::uuid(),
                'stato' => $k === 'ritirato' ? 'ritirato' : 'pending',
                'non_ammesso' => $k === 'nonAmmesso', 'non_ammesso_motivo' => $k === 'nonAmmesso' ? 'assenze' : null,
            ]);
        }

        $this->esame = Esame::creaPerCorso($this->corso, 'Esame', 60);
        $this->esame->docentiCommissione()->create(['socio_id' => $this->s['docA']->id]);
        $this->esame->attiva();
        $this->esame->refresh();

        $a = $this->esame->modello_snapshot['itinere']['aree'][0];
        $this->area = $a['codice'];
        $this->vociArea = array_column($a['voci'], 'codice');

        foreach (['direttore', 'presidente', 'docA', 'docB', 'x'] as $k) {
            $this->u[$k] = User::create(['name' => $k, 'email' => $k . '@t.it', 'password' => 'x', 'ruolo' => 'operatore', 'attivo' => true, 'socio_id' => $this->s[$k]->id]);
        }
        $this->u['super'] = User::create(['name' => 'super', 'email' => 'super@t.it', 'password' => 'x', 'ruolo' => 'superadmin', 'attivo' => true]);
        $this->u['adminReg'] = User::create(['name' => 'ar', 'email' => 'ar@t.it', 'password' => 'x', 'ruolo' => 'admin', 'attivo' => true, 'comitato_regionale_id' => $cr]);
        $this->u['adminLoc'] = User::create(['name' => 'al', 'email' => 'al@t.it', 'password' => 'x', 'ruolo' => 'admin_locale', 'attivo' => true, 'comitato_locale_id' => $cl]);
    }

    /** Scheda itinere con i voti dati sulle prime voci dell'area. */
    private function scheda(string $docente, string $discente, array $voti): TcriSchedaItinere
    {
        $scheda = TcriSchedaItinere::create([
            'esame_id' => $this->esame->id, 'discente_socio_id' => $this->s[$discente]->id, 'docente_socio_id' => $this->s[$docente]->id,
        ]);
        foreach ($voti as $i => $voto) {
            $scheda->voci()->create(['voce_codice' => $this->vociArea[$i], 'voto' => $voto]);
        }

        return $scheda;
    }

    private function pannello(?string $chi): void
    {
        $this->actingAs($this->u[$chi]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function salva(string $chi, string $discente, string $punteggio, ?string $motivazione = null, ?string $area = null): TcriItinereDecisione
    {
        return DecisioneItinere::salva($this->esame->fresh(), $this->u[$chi], $this->s[$discente]->id, $area ?? $this->area, $punteggio, $motivazione);
    }

    // ---- Permessi ----

    public function test_chi_legge_e_chi_decide(): void
    {
        $e = $this->esame->fresh();
        foreach (['direttore', 'presidente', 'docA', 'super', 'adminReg', 'adminLoc'] as $k) {
            $this->assertTrue($e->puoVedereRiepilogoItinere($this->u[$k]), "$k legge");
        }
        foreach (['docB', 'x'] as $k) {
            $this->assertFalse($e->puoVedereRiepilogoItinere($this->u[$k]), "$k non legge");
        }
        // Decide SOLO il direttore: nemmeno il superadmin, né gli admin, né la commissione.
        $this->assertTrue($e->puoDecidereItinere($this->u['direttore']));
        foreach (['presidente', 'docA', 'docB', 'super', 'adminReg', 'adminLoc', 'x'] as $k) {
            $this->assertFalse($e->puoDecidereItinere($this->u[$k]), "$k non decide");
        }
    }

    public function test_lettura_non_e_gestione_per_la_commissione(): void
    {
        $e = $this->esame->fresh();
        foreach (['presidente', 'docA'] as $k) {
            $this->actingAs($this->u[$k]);
            $this->assertTrue(EsameResource::canViewAny(), "$k vede l'elenco esami");
            $this->assertFalse(EsameResource::canEdit($e), "$k non gestisce");
            $this->assertFalse(EsameResource\Pages\GestioneEsame::canAccess(['record' => $e]));
            $this->assertFalse(EsameResource\Pages\GestioneLaboratori::canAccess(['record' => $e]));
            $this->assertTrue(EsameResource\Pages\RiepilogoItinereEsame::canAccess(['record' => $e]));
            $this->assertSame([$e->id], EsameResource::getEloquentQuery()->pluck('id')->all());
        }
        // Chi gestisce oggi continua a gestire (nessun cambio per admin e direttore).
        foreach (['direttore', 'super', 'adminReg', 'adminLoc'] as $k) {
            $this->actingAs($this->u[$k]);
            $this->assertTrue(EsameResource::canEdit($e), "$k gestisce");
            $this->assertTrue(EsameResource\Pages\GestioneEsame::canAccess(['record' => $e]));
        }
        // Docente semplice e discente non vedono nulla.
        foreach (['docB', 'x'] as $k) {
            $this->actingAs($this->u[$k]);
            $this->assertFalse(EsameResource::canViewAny(), "$k");
            $this->assertFalse(EsameResource\Pages\RiepilogoItinereEsame::canAccess(['record' => $e]));
        }
    }

    public function test_permessi_socio_vede_riepilogo_solo_commissione(): void
    {
        foreach (['direttore' => true, 'presidente' => true, 'docA' => true, 'docB' => false, 'x' => false] as $k => $atteso) {
            $this->assertSame($atteso, PermessiEsame::per($this->esame->fresh(), $this->s[$k]->id)->vedeRiepilogoItinere(), $k);
        }
        $this->assertTrue(PermessiEsame::per($this->esame->fresh(), $this->s['direttore']->id)->puoDecidereItinere());
        $this->assertFalse(PermessiEsame::per($this->esame->fresh(), $this->s['presidente']->id)->puoDecidereItinere());
    }

    public function test_esame_in_bozza_non_e_leggibile_dalla_commissione(): void
    {
        $this->esame->riportaInBozza();
        $this->assertFalse($this->esame->fresh()->puoVedereRiepilogoItinere($this->u['presidente']));
    }

    // ---- Finale d'area ----

    public function test_il_direttore_salva_la_proposta_senza_motivazione_e_sovrascrive(): void
    {
        $this->scheda('docA', 'x', [5, 5]);
        $this->scheda('docB', 'x', [3, 3, 3, 3]);

        $this->salva('direttore', 'x', '4.0'); // proposta = media delle medie = 4,0
        $d = TcriItinereDecisione::first();
        $this->assertSame('4.0', (string) $d->punteggio);
        $this->assertNull($d->motivazione);
        $this->assertSame($this->s['direttore']->id, $d->deciso_da);

        $this->salva('direttore', 'x', '3,5', 'Sentito il docente B');
        $this->assertSame(1, TcriItinereDecisione::count(), 'una riga per (esame, discente, area)');
        $this->assertSame('3.5', (string) TcriItinereDecisione::first()->punteggio);
    }

    public function test_motivazione_obbligatoria_solo_se_diverso_dalla_proposta(): void
    {
        $this->scheda('docA', 'x', [5, 5]);
        $this->scheda('docB', 'x', [3, 3, 3, 3]);

        try {
            $this->salva('direttore', 'x', '3.6');
            $this->fail('serve la motivazione');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('motivazione', $e->errors());
        }
        $this->assertSame(0, TcriItinereDecisione::count());
        $this->salva('direttore', 'x', '3.6', 'motivo');
        $this->assertSame(1, TcriItinereDecisione::count());
    }

    public function test_area_senza_voti_propone_il_valore_forzato_che_entra_nel_calcolo_solo_se_salvato(): void
    {
        $r = RiepilogoItinere::per($this->esame->fresh());
        $y = collect($r['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['y']->id);
        $this->assertTrue($y['aree'][$this->area]['forzato']);
        $this->assertSame('3,0', $y['aree'][$this->area]['proposta']); // LAP 60 × 5
        $this->assertNull($y['aree'][$this->area]['finale'], 'non salvato: non entra nel calcolo');
        $this->assertNull($y['totale']);

        $this->salva('direttore', 'y', '3.0'); // confermato = nessuna motivazione
        $y = collect(RiepilogoItinere::per($this->esame->fresh())['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['y']->id);
        $this->assertSame('3,0', $y['aree'][$this->area]['finale']['punteggio']);
        $this->assertTrue($y['aree'][$this->area]['forzato'], 'resta evidenziato');
    }

    public function test_range_e_formato(): void
    {
        foreach (['0.9', '5.1', 'abc', '3.55'] as $v) {
            try {
                $this->salva('direttore', 'x', $v, 'm');
                $this->fail($v);
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->salva('direttore', 'x', '1', 'm');
        $this->salva('direttore', 'x', '5', 'm');
        $this->assertSame('5.0', (string) TcriItinereDecisione::first()->punteggio);
    }

    public function test_non_ammesso_e_ritirato_non_hanno_finale(): void
    {
        foreach (['nonAmmesso', 'ritirato'] as $k) {
            try {
                $this->salva('direttore', $k, '3.0', 'm');
                $this->fail($k);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('discente', $e->errors());
            }
        }
        $this->assertSame(0, TcriItinereDecisione::count());
    }

    public function test_solo_il_direttore_decide_nemmeno_il_superadmin(): void
    {
        foreach (['super', 'presidente', 'docA', 'docB', 'adminReg', 'adminLoc'] as $k) {
            try {
                $this->salva($k, 'x', '3.0', 'm');
                $this->fail($k);
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, TcriItinereDecisione::count());
    }

    public function test_da_chiuso_sola_lettura_e_se_riaperto_torna_modificabile(): void
    {
        $this->salva('direttore', 'x', '3.0', 'm');
        $this->esame->update(['stato' => Esame::STATO_CHIUSO, 'chiuso_il' => now()]);
        $this->expectException(\RuntimeException::class);
        try {
            $this->salva('direttore', 'x', '4.0', 'm');
        } finally {
            $this->assertSame('3.0', (string) TcriItinereDecisione::first()->punteggio);
        }
    }

    public function test_aree_decise_a_tempi_diversi_e_totale_solo_a_quattro_aree(): void
    {
        $codici = array_column($this->esame->modello_snapshot['itinere']['aree'], 'codice');
        $totale = fn () => collect(RiepilogoItinere::per($this->esame->fresh())['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['x']->id);

        foreach (array_slice($codici, 0, 3) as $c) {
            $this->salva('direttore', 'x', '3.0', null, $c);
        }
        $t = $totale();
        $this->assertSame(3, $t['decise']);
        $this->assertNull($t['totale'], 'incompleto');

        $this->salva('direttore', 'x', '4.0', 'm', $codici[3]);
        $this->assertSame('13,0', $totale()['totale'], '3 + 3 + 3 + 4');

        $this->salva('direttore', 'x', '2.0', 'rivisto', $codici[0]); // area già decisa, ancora modificabile
        $this->assertSame('12,0', $totale()['totale']);
    }

    public function test_la_finale_non_tocca_le_schede_dei_docenti(): void
    {
        $scheda = $this->scheda('docA', 'x', [4, 2]);
        $prima = $scheda->voci()->pluck('voto', 'voce_codice')->all();
        $this->salva('direttore', 'x', '1.5', 'm');
        $this->assertSame($prima, $scheda->fresh()->voci()->pluck('voto', 'voce_codice')->all());
        $this->assertSame(1, TcriSchedaItinere::count());
    }

    // ---- Dati del riepilogo ----

    public function test_riepilogo_medie_e_ritirati_nascosti(): void
    {
        $this->scheda('docA', 'x', [5, 5]);
        $this->scheda('docB', 'x', [3, 3, 3, 3]);
        $this->scheda('docA', 'ritirato', [5]); // resta nel DB, non compare

        $r = RiepilogoItinere::per($this->esame->fresh());
        $ids = collect($r['discenti'])->pluck('socio.id')->all();
        $this->assertNotContains($this->s['ritirato']->id, $ids);
        $this->assertContains($this->s['nonAmmesso']->id, $ids, 'il non ammesso resta in elenco');

        $x = collect($r['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['x']->id);
        $a = $x['aree'][$this->area];
        $this->assertSame('4,0', $a['media_delle_medie']);
        $this->assertSame('3,66', $a['media_tutti_i_voti']); // 22/6 troncato, mai arrotondato
        $this->assertFalse($a['forzato']);
        $this->assertSame(1, TcriSchedaItinere::where('discente_socio_id', $this->s['ritirato']->id)->count());
    }

    public function test_laboratori_media_tra_docenti_solo_schede_inviate_e_dettaglio_lazy(): void
    {
        $lab = $this->esame->laboratori()->first();
        $voci = [];
        foreach ($this->esame->modello_snapshot['semaforo']['competenze'] as $c) {
            array_push($voci, ...array_column($c['voci'], 'codice'));
        }
        $crea = function (string $docente, string $valore, bool $inviata) use ($lab, $voci) {
            $sc = TcriSchedaSemaforo::create([
                'laboratorio_id' => $lab->id, 'discente_socio_id' => $this->s['x']->id, 'docente_socio_id' => $this->s[$docente]->id,
                'inviata_il' => $inviata ? now() : null,
            ]);
            foreach ($voci as $i => $codice) {
                // la prima voce differisce tra A e B: sarà segnalata come discordante
                $sc->voci()->create(['voce_codice' => $codice, 'valore' => $i === 0 && $docente === 'docB' ? '0' : $valore]);
            }
        };
        $crea('docA', '1', true);    // 28
        $crea('docB', '1', true);    // 27
        $crea('docC', '1', false);   // bozza: ignorata

        $x = collect(RiepilogoItinere::per($this->esame->fresh())['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['x']->id);
        $riga = $x['laboratori'][0];
        $this->assertSame('27,5', $riga['media']);
        $this->assertSame('27,0', $riga['minimo']);
        $this->assertCount(2, $riga['docenti']);

        $det = RiepilogoItinere::dettaglioLaboratorio($this->esame->fresh(), $this->s['x']->id, $lab->id);
        $this->assertCount(2, $det['docenti']);
        $this->assertTrue($det['competenze'][0]['voci'][0]['discordante']);
        $this->assertFalse($det['competenze'][0]['voci'][1]['discordante']);
        // verde contro rosso = salto di 1 sulla scala
        $this->assertTrue($det['competenze'][0]['voci'][0]['salto']);
        $this->assertFalse($det['competenze'][0]['voci'][1]['salto']);
        // Δ 1 su 28 (3,5%) sotto la soglia del 15%; note mai senza il flag del direttore
        $this->assertFalse($riga['scarto_alto']);
        $this->assertSame([null, null], $det['note']);
    }

    public function test_scarto_alto_colori_e_note_solo_spazi_ignorate(): void
    {
        $lab = $this->esame->laboratori()->first();
        $voci = [];
        foreach ($this->esame->modello_snapshot['semaforo']['competenze'] as $c) {
            array_push($voci, ...array_column($c['voci'], 'codice'));
        }
        foreach (['docA' => [0, "Nota vera"], 'docB' => [10, " \t\u{00A0}\n "]] as $doc => [$rossi, $nota]) {
            $sc = TcriSchedaSemaforo::create([
                'laboratorio_id' => $lab->id, 'discente_socio_id' => $this->s['x']->id, 'docente_socio_id' => $this->s[$doc]->id,
                'inviata_il' => now(), 'annotazione' => $nota,
            ]);
            foreach ($voci as $i => $codice) {
                $sc->voci()->create(['voce_codice' => $codice, 'valore' => $i < $rossi ? '0' : '1']);
            }
        }
        // Un laboratorio futuro non compare nella sintesi del titolo.
        \App\Models\EsameLaboratorio::create(['esame_id' => $this->esame->id, 'numero' => 2, 'data' => now()->addDays(3)->toDateString()]);
        $x = collect(RiepilogoItinere::per($this->esame->fresh(), true)['discenti'])->first(fn ($d) => $d['socio']->id === $this->s['x']->id);
        $riga = $x['laboratori'][0];
        $this->assertSame([[1, 2, 2, true]], array_map(fn ($l) => [$l['numero'], $l['inviate'], $l['attesi'], $l['scarto_alto']], $x['stato_lab']));
        $this->assertFalse($x['laboratori'][1]['svolto']);
        $this->assertTrue($riga['scarto_alto'], 'Δ 10 su 28 ≥ 15%');
        $this->assertSame(['Nota vera', null], array_column($riga['docenti'], 'nota'));
        $this->assertNotSame($riga['docenti'][0]['colore'], $riga['docenti'][1]['colore'], '28/28 (100%) e 18/28 (64%) in fasce diverse');

        $det = RiepilogoItinere::dettaglioLaboratorio($this->esame->fresh(), $this->s['x']->id, $lab->id, true);
        $this->assertSame(['Nota vera', null], $det['note']);
        $this->assertSame(['28,0', '18,0'], array_column($det['totali'], 'totale'));
        $this->assertSame('28,0', $det['massimo']);

        // Pannello: la modale «i» c'è per il direttore (riga del laboratorio e matrice), mai per il presidente.
        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
            ->call('apriDettaglioLaboratorio', $this->s['x']->id, $lab->id)
            ->assertSuccessful()->assertSee('Nota vera')->assertSee('Annotazione di');
        $this->pannello('presidente');
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
            ->call('apriDettaglioLaboratorio', $this->s['x']->id, $lab->id)
            ->assertSuccessful()->assertDontSee('Nota vera');
    }

    // ---- Pannello (Livewire) ----

    public function test_pagina_pannello_aperta_dalla_commissione_senza_azione_decidi(): void
    {
        $this->scheda('docB', 'x', [4, 3]);
        foreach (['direttore', 'presidente', 'docA', 'super', 'adminReg'] as $k) {
            $this->pannello($k);
            Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
                ->assertSuccessful()
                ->assertSee('Schede personali dei docenti')
                ->assertSee('Scheda finale d');
        }
        $this->pannello('presidente');
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertDontSee('Decidi punteggio');
        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Decidi punteggio');
    }

    public function test_pagina_pannello_negata_a_docente_semplice_e_discente(): void
    {
        // L'esame non rientra nella query filtrata per questi utenti: risposta 404, non 403.
        foreach (['docB', 'x'] as $k) {
            $this->pannello($k);
            Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
                ->assertNotFound();
        }
    }

    public function test_azione_decidi_salva_dal_pannello_del_direttore(): void
    {
        $this->scheda('docB', 'x', [4, 4]);
        $this->pannello('direttore');
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
            ->callAction('decidi', ['punteggio' => '4.0', 'proposta' => '4.0', 'motivazione' => null], [
                'discente_id' => $this->s['x']->id, 'area' => $this->area, 'proposta' => '4.0',
            ])->assertHasNoActionErrors();
        $this->assertSame('4.0', (string) TcriItinereDecisione::first()->punteggio);
    }

    public function test_azione_decidi_non_salva_se_chi_la_lancia_non_e_il_direttore(): void
    {
        $this->pannello('presidente');
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
            ->callAction('decidi', ['punteggio' => '4.0', 'proposta' => '4.0', 'motivazione' => null], [
                'discente_id' => $this->s['x']->id, 'area' => $this->area, 'proposta' => '4.0',
            ]);
        $this->assertSame(0, TcriItinereDecisione::count());
    }

    public function test_pagina_mostra_andamento_laboratori_e_apre_il_dettaglio_al_clic(): void
    {
        $lab = $this->esame->laboratori()->first();
        foreach (['docA' => '1', 'docB' => '0.25'] as $docente => $valore) {
            $sc = TcriSchedaSemaforo::create(['laboratorio_id' => $lab->id, 'discente_socio_id' => $this->s['x']->id, 'docente_socio_id' => $this->s[$docente]->id, 'inviata_il' => now()]);
            foreach ($this->esame->modello_snapshot['semaforo']['competenze'] as $c) {
                foreach ($c['voci'] as $v) {
                    $sc->voci()->create(['voce_codice' => $v['codice'], 'valore' => $valore]);
                }
            }
        }
        $this->pannello('presidente');
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSee('Andamento nei laboratori')
            ->assertSee('Vedi voci')
            ->assertDontSee('Nascondi voci')
            ->call('apriDettaglioLaboratorio', $this->s['x']->id, $lab->id)
            ->assertSee('Nascondi voci')
            ->assertSee('sfondo ambra')
            ->call('apriDettaglioLaboratorio', $this->s['x']->id, $lab->id)
            ->assertDontSee('Nascondi voci');
    }

    public function test_dettaglio_laboratorio_negato_a_chi_non_legge(): void
    {
        $lab = $this->esame->laboratori()->first();
        $this->pannello('docB');
        $this->expectException(\Throwable::class);
        Livewire::test(EsameResource\Pages\RiepilogoItinereEsame::class, ['record' => $this->esame->getRouteKey()])
            ->call('apriDettaglioLaboratorio', $this->s['x']->id, $lab->id);
    }

    // ---- PWA ----

    private function pwa(string $k)
    {
        return $this->withSession(['app_socio_id' => $this->s[$k]->id])->get(route('app.esame.itinere.riepilogo', $this->esame));
    }

    public function test_pwa_riepilogo_solo_per_la_commissione_in_sola_lettura(): void
    {
        $this->scheda('docB', 'x', [4, 3]);
        $this->salva('direttore', 'x', '3.5', 'ok');
        foreach (['direttore', 'presidente', 'docA'] as $k) {
            $this->pwa($k)->assertOk()->assertSee('Riepilogo itinere')->assertSee('Scheda finale d')->assertSee('3,5');
        }
        foreach (['docB', 'docC', 'x', 'ritirato'] as $k) {
            $this->pwa($k)->assertNotFound();
        }
        // Nessuna rotta di scrittura per il riepilogo.
        $this->withSession(['app_socio_id' => $this->s['presidente']->id])
            ->put(route('app.esame.itinere.riepilogo', $this->esame))->assertStatus(405);
    }

    public function test_pwa_card_riepilogo_nel_dettaglio_solo_ai_commissari(): void
    {
        $this->withSession(['app_socio_id' => $this->s['presidente']->id])->get(route('app.esame', $this->esame))->assertOk()->assertSee('Riepilogo itinere');
        $this->withSession(['app_socio_id' => $this->s['docB']->id])->get(route('app.esame', $this->esame))->assertOk()->assertDontSee('Riepilogo itinere');
    }

    public function test_discente_non_vede_nulla(): void
    {
        $this->pwa('x')->assertNotFound();
        $this->assertFalse(PermessiEsame::per($this->esame->fresh(), $this->s['x']->id)->vedeRiepilogoItinere());
    }

    // ---- Proiezione del punteggio (pagina Discenti) ----

    public function test_proiezione_dati_di_partenza(): void
    {
        $this->scheda('docB', 'y', [4, 3]);                    // prima area di y: proposta 3,5
        $this->salva('direttore', 'x', '2.5', 'ok');           // prima area di x: fissata
        $p = \App\Esami\ProiezioneEsame::per($this->esame->fresh());

        $this->assertArrayNotHasKey($this->s['nonAmmesso']->id, $p);
        $this->assertArrayNotHasKey($this->s['ritirato']->id, $p);
        $this->assertSame('2.5', $p[$this->s['x']->id]['aree'][0]['finale']);
        $this->assertSame('3.5', $p[$this->s['y']->id]['aree'][0]['proposta']);
        $this->assertNull($p[$this->s['y']->id]['aree'][1]['proposta'], 'nessun voto: nessuna proposta (mai il valore forzato)');
        $this->assertSame('3,5', $p[$this->s['y']->id]['aree'][0]['docenti'][0]['media']);
        $this->assertNull($p[$this->s['x']->id]['colloquio']);
    }

    public function test_proiezione_totale_stessa_formula_del_motore(): void
    {
        $snap = $this->esame->modello_snapshot;
        $aree = array_fill_keys(array_column($snap['itinere']['aree'], 'codice'), '4');
        $massimi = ['colloquio' => Frazione::di(5), 'prova_pratica' => Frazione::di(28), 'test_scritto' => Frazione::di(20)];

        // 30×16/20 + 10×4/5 + 40×21/28 + 20×15/20 = 24 + 8 + 30 + 15 = 77
        $t = \App\Esami\ProiezioneEsame::totale($snap, '60', ['itinere' => $aree, 'colloquio' => '4', 'prova_pratica' => '21', 'test_scritto' => '15'], $massimi);
        $this->assertSame('77.0', $t['finale']);
        $this->assertTrue($t['idoneo']);
        $this->assertSame('24.00', $t['contributi']['itinere']->tronca(2));

        // Valori con virgola, fuori scala, mancanti.
        $t = \App\Esami\ProiezioneEsame::totale($snap, '60', ['itinere' => $aree, 'colloquio' => '6', 'prova_pratica' => '10,5', 'test_scritto' => null], $massimi);
        $this->assertNull($t['finale']);
        $this->assertArrayHasKey('colloquio', $t['errori']);
        $this->assertSame('15.00', $t['contributi']['prova_pratica']->tronca(2));
        $aree[array_key_first($aree)] = '';
        $this->assertNull(\App\Esami\ProiezioneEsame::totale($snap, '60', ['itinere' => $aree, 'colloquio' => null, 'prova_pratica' => null, 'test_scritto' => null], $massimi)['contributi']['itinere']);
    }

    public function test_proiezione_nel_pannello_con_valori_ipotetici(): void
    {
        $this->scheda('docB', 'x', [4, 3]);
        $this->pannello('direttore');
        $iscrizione = Iscrizione::where('partecipante_id', $this->s['x']->id)->first();
        $codici = array_column($this->esame->modello_snapshot['itinere']['aree'], 'codice');

        $pagina = Livewire::test(EsameResource\Pages\RiepilogoDiscentiEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertSuccessful()->assertDontSee('da completare')
            // Legenda del LAP in cima alla tabella (pannello): soglie per prova e fasce.
            ->assertSee('LAP 60% — idoneo da 60 su 100')->assertSee('Itinere: <strong>18</strong> su 30', false)->assertSee('giallo [40%, 60%[')->assertSee('title="da 40% (incluso) a 60% (escluso)"', false)->assertSee('title="da 80% (incluso) a 100% (incluso)"', false)
            // Dati incompleti: totale e stato restano vuoti (nessun idoneo/non idoneo in tabella).
            ->assertTableColumnStateSet('proiezione_totale', '', $iscrizione)
            ->assertTableColumnStateSet('proiezione_stato', '', $iscrizione)
            ->assertTableColumnStateSet('proiezione_colloquio', '–', $iscrizione)
            ->assertTableActionEnabled('proiezione', $iscrizione)
            ->mountTableAction('proiezione', $iscrizione);
        // La finestra è un «partial» di Livewire (non nell'HTML del test): si leggono etichette e stati dei componenti dello
        // schema montato, il che esercita anche le closure del contenuto.
        $finestra = fn () => collect($pagina->instance()->getSchema('mountedActionSchema0')->getFlatComponents())
            ->map(function ($c) {
                $testo = fn ($v) => $v instanceof \Illuminate\Contracts\Support\Htmlable ? $v->toHtml() : (string) $v;
                $riga = method_exists($c, 'getLabel') ? $testo($c->getLabel()) : '';
                $riga .= $c instanceof \Filament\Schemas\Components\Section ? ' '.$testo($c->getHeading()).' '.$testo($c->getDescription()) : '';
                $riga .= $c instanceof \Filament\Schemas\Components\Text ? ' '.$testo($c->getContent()) : '';

                return $riga.($c instanceof \Filament\Infolists\Components\TextEntry ? ' '.$testo($c->getState()) : '');
            })->implode("\n");

        $this->assertSame('3,5', $pagina->get('mountedActions.0.data.itinere_'.$codici[0]), 'campo precompilato con la proposta');
        $this->assertStringContainsString('proposta 3,5', $finestra());
        $this->assertStringContainsString('proposta –', $finestra());

        $dati = ['colloquio' => '4', 'prova_pratica' => '21', 'test_scritto' => '0'];
        foreach ($codici as $c) {
            $dati['itinere_'.$c] = '4';
        }
        $pagina->fillForm($dati);
        $html = $finestra();
        $this->assertStringContainsString('24,0', $html);                          // itinere: 30 × 16/20
        $this->assertStringContainsString('completa i valori mancanti', $html);    // test senza domande: massimo 0

        // Il non ammesso non ha proiezione.
        $na = Iscrizione::where('partecipante_id', $this->s['nonAmmesso']->id)->first();
        Livewire::test(EsameResource\Pages\RiepilogoDiscentiEsame::class, ['record' => $this->esame->getRouteKey()])
            ->assertTableActionDisabled('proiezione', $na);
    }
}
