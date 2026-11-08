<?php

namespace Tests\Feature;

use App\Esami\PermessiEsame;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\EsameGruppo;
use App\Models\EsameLaboratorio;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\TcriSchedaSemaforo;
use Database\Seeders\EsameModelliSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Esami: scheda di laboratorio (semaforo) in PWA. DB di test: lurch_test. */
class EsameSchedaLaboratorioTest extends TestCase
{
    use RefreshDatabase;

    private Esame $esame;
    private EsameLaboratorio $lab;
    private EsameGruppo $g1;
    /** @var array<string, Socio> */
    private array $s = [];
    /** @var list<string> */
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

        foreach (['direttore', 'presidente', 'docA', 'docB', 'docC', 'docD', 'x', 'y', 'nonAmmesso', 'ritirato'] as $k) {
            $this->s[$k] = Socio::create(['nome' => $k . 'Nome', 'cognome' => 'Test', 'codice_fiscale' => strtoupper(substr(md5($k), 0, 16))]);
        }
        $corso = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso TCRI', 'numero_protocollo' => 'X/' . uniqid(),
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
            'direttore_socio_id' => $this->s['direttore']->id, 'presidente_socio_id' => $this->s['presidente']->id,
            'catalogo_corso_id' => $catalogo->id,
        ]);
        $corso->docenti()->attach(array_map(fn ($k) => $this->s[$k]->id, ['docA', 'docB', 'docC', 'docD']));
        foreach (['x', 'y', 'nonAmmesso', 'ritirato'] as $k) {
            Iscrizione::create([
                'corso_id' => $corso->id, 'partecipante_id' => $this->s[$k]->id, 'token' => (string) Str::uuid(),
                'stato' => $k === 'ritirato' ? 'ritirato' : 'pending',
                'non_ammesso' => $k === 'nonAmmesso', 'non_ammesso_motivo' => $k === 'nonAmmesso' ? 'assenze' : null,
            ]);
        }

        $this->esame = Esame::creaPerCorso($corso, 'Esame', 60);
        $this->esame->attiva();
        $this->esame->refresh();
        $this->lab = $this->esame->laboratori()->first();
        // G1: docenti A e B; discenti X, non ammesso e ritirato. G2: docente C, discente Y. D e il presidente in nessun gruppo.
        $this->g1 = $this->gruppo('G1', ['docA', 'docB'], ['x', 'nonAmmesso', 'ritirato']);
        $this->gruppo('G2', ['docC'], ['y']);

        foreach ($this->esame->modello_snapshot['semaforo']['competenze'] as $c) {
            array_push($this->codici, ...array_column($c['voci'], 'codice'));
        }
    }

    private function gruppo(string $nome, array $docenti, array $discenti): EsameGruppo
    {
        $g = EsameGruppo::create(['esame_id' => $this->esame->id, 'laboratorio_id' => $this->lab->id, 'nome' => $nome]);
        $g->docenti()->attach(array_map(fn ($k) => $this->s[$k]->id, $docenti));
        $g->discenti()->attach(array_map(fn ($k) => $this->s[$k]->id, $discenti));

        return $g;
    }

    private function come(string $k)
    {
        return $this->withSession(['app_socio_id' => $this->s[$k]->id]);
    }

    private function urlVoce(string $discente): string
    {
        return route('app.esame.scheda.voce', [$this->esame, $this->lab, $this->s[$discente]]);
    }

    private function tocca(string $docente, string $discente, string $voce, string $valore)
    {
        return $this->come($docente)->putJson($this->urlVoce($discente), ['voce' => $voce, 'valore' => $valore]);
    }

    /** Compila tutte le voci con lo stesso livello; restituisce l'ultima risposta. */
    private function compilaTutto(string $docente, string $discente, string $livello)
    {
        $r = null;
        foreach ($this->codici as $codice) {
            $r = $this->tocca($docente, $discente, $codice, $livello);
            $r->assertOk();
        }

        return $r;
    }

    private function permessi(string $k): PermessiEsame
    {
        return PermessiEsame::per($this->esame->fresh(), $this->s[$k]->id);
    }

    private function idsProponibili(string $k): array
    {
        return $this->permessi($k)->discentiProponibili($this->lab)->pluck('id')->sort()->values()->all();
    }

    public function test_il_modello_ha_28_voci(): void
    {
        $this->assertCount(28, $this->codici);
    }

    public function test_bottoni_in_ordine_rosso_giallo_verde_anche_con_jsonb(): void
    {
        $this->assertSame(['rosso', 'giallo', 'verde'], array_keys(\App\Http\Controllers\App\AppEsamiLaboratorioController::struttura($this->esame->fresh())['scala']));
        $html = $this->come('docA')->get(route('app.esame.scheda', [$this->esame, $this->lab, $this->s['x']]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-livello="rosso".*data-livello="giallo".*data-livello="verde"/s', $html);
    }

    public function test_docente_vede_solo_i_discenti_del_proprio_gruppo_senza_ritirati(): void
    {
        $atteso = collect([$this->s['x']->id, $this->s['nonAmmesso']->id])->sort()->values()->all(); // non ammesso incluso, ritirato escluso
        $this->assertSame($atteso, $this->idsProponibili('docA'));
        $this->assertSame($atteso, $this->idsProponibili('docB'));
        $this->assertSame([$this->s['y']->id], $this->idsProponibili('docC'));
        $this->assertSame([], $this->idsProponibili('docD'), 'docente senza gruppo');
        $this->assertSame([], $this->idsProponibili('presidente'), 'il presidente non compila');
        $this->assertSame([], $this->idsProponibili('x'), 'il discente non ha nulla da compilare');
    }

    public function test_direttore_in_un_gruppo_compila_come_docente_altrimenti_no(): void
    {
        $this->assertSame([], $this->idsProponibili('direttore'));
        $this->g1->docenti()->attach($this->s['direttore']->id);
        $this->assertContains($this->s['x']->id, $this->idsProponibili('direttore'));
    }

    public function test_docente_in_due_gruppi_dello_stesso_laboratorio_vede_entrambi(): void
    {
        $this->gruppo('G3', ['docA'], ['y']);
        $this->assertContains($this->s['y']->id, $this->idsProponibili('docA'));
        $this->assertContains($this->s['x']->id, $this->idsProponibili('docA'));
    }

    public function test_pagine_per_ruolo(): void
    {
        $url = route('app.esame.laboratorio', [$this->esame, $this->lab]);

        $this->come('docA')->get($url)->assertOk()->assertSee('Valutazione personale')->assertSee($this->s['x']->nome)->assertDontSee($this->s['y']->nome);
        $this->come('docD')->get($url)->assertOk()->assertSee('Non hai discenti assegnati in questo laboratorio');
        $this->come('presidente')->get($url)->assertOk()->assertSee('Non hai schede da compilare')->assertDontSee('Chiedi al direttore');
        $this->come('presidente')->get(route('app.esame', $this->esame))->assertOk();
        // Il discente e l'estraneo non vedono nulla.
        $this->come('x')->get($url)->assertNotFound();
        $this->come('x')->get(route('app.esame', $this->esame))->assertNotFound();
        $this->come('ritirato')->get(route('app.esame', $this->esame))->assertNotFound();
        $this->come('docA')->get(route('app.esame', $this->esame))->assertOk()->assertSee('Laboratorio 1');
    }

    public function test_scheda_non_apribile_per_un_discente_di_un_altro_gruppo(): void
    {
        $this->come('docA')->get(route('app.esame.scheda', [$this->esame, $this->lab, $this->s['y']]))->assertNotFound();
        $this->tocca('docA', 'y', $this->codici[0], 'verde')->assertForbidden();
        $this->assertSame(0, TcriSchedaSemaforo::count());
    }

    public function test_ogni_tocco_salva_e_la_scheda_si_riprende(): void
    {
        $this->tocca('docA', 'x', $this->codici[0], 'verde')->assertOk()->assertJson(['compilate' => 1, 'totale' => null]);
        $this->tocca('docA', 'x', $this->codici[1], 'giallo')->assertOk()->assertJson(['compilate' => 2]);
        $this->tocca('docA', 'x', $this->codici[0], 'rosso')->assertOk()->assertJson(['compilate' => 2]); // correzione, non una seconda voce

        $scheda = TcriSchedaSemaforo::firstOrFail();
        $this->assertNull($scheda->inviata_il);
        $this->assertSame(1, TcriSchedaSemaforo::count());

        $pagina = $this->come('docA')->get(route('app.esame.scheda', [$this->esame, $this->lab, $this->s['x']]))->assertOk();
        $pagina->assertSee('2/28 voci');
        $this->come('docA')->get(route('app.esame.laboratorio', [$this->esame, $this->lab]))->assertSee('Bozza · 2 voci su 28');
    }

    public function test_totali_dal_motore_28_7_0(): void
    {
        $this->assertSame('28,00', $this->compilaTutto('docA', 'x', 'verde')->json('totale.punti'));
        $this->assertSame('28,00', $this->compilaTutto('docA', 'x', 'verde')->json('totale.massimo'));
        $this->assertSame('7,00', $this->compilaTutto('docA', 'x', 'giallo')->json('totale.punti'));
        $this->assertSame('0,00', $this->compilaTutto('docA', 'x', 'rosso')->json('totale.punti'));
        // Nessun totale è salvato: la tabella non ha colonne di punteggio.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('tcri_schede_semaforo', 'totale'));
    }

    public function test_invio_richiede_tutte_le_voci(): void
    {
        $url = route('app.esame.scheda.invia', [$this->esame, $this->lab, $this->s['x']]);

        $this->tocca('docA', 'x', $this->codici[0], 'verde');
        $this->come('docA')->post($url)->assertSessionHas('errore');
        $this->assertNull(TcriSchedaSemaforo::first()->inviata_il);

        $this->compilaTutto('docA', 'x', 'verde');
        $this->come('docA')->post($url)->assertRedirect()->assertSessionHas('ok');
        $this->assertNotNull(TcriSchedaSemaforo::first()->inviata_il);
        $this->come('docA')->get(route('app.esame.laboratorio', [$this->esame, $this->lab]))->assertSee('Inviata · 28,00 su 28,00');

        // Dopo l'invio l'autore può ancora correggere.
        $this->tocca('docA', 'x', $this->codici[0], 'giallo')->assertOk();
    }

    public function test_invio_senza_scheda_e_rifiutato(): void
    {
        $this->come('docA')->post(route('app.esame.scheda.invia', [$this->esame, $this->lab, $this->s['x']]))->assertSessionHas('errore');
        $this->assertSame(0, TcriSchedaSemaforo::count());
    }

    public function test_voce_o_valore_non_validi_sono_rifiutati(): void
    {
        $this->tocca('docA', 'x', 'semaforo.inventata.1', 'verde')->assertStatus(422);
        $this->tocca('docA', 'x', $this->codici[0], 'blu')->assertStatus(422);
        $this->tocca('docA', 'x', $this->codici[0], '1')->assertStatus(422); // si accettano le chiavi della scala, non numeri
        $this->assertSame(0, TcriSchedaSemaforo::count());
    }

    public function test_un_docente_non_vede_ne_tocca_le_schede_degli_altri(): void
    {
        $this->tocca('docA', 'x', $this->codici[0], 'verde');
        $this->tocca('docB', 'x', $this->codici[1], 'rosso');

        $this->assertSame(2, TcriSchedaSemaforo::count(), 'una scheda per coppia docente+discente');
        $pagB = $this->come('docB')->get(route('app.esame.scheda', [$this->esame, $this->lab, $this->s['x']]))->assertOk();
        $pagB->assertSee('1/28 voci');
        // L'URL non contiene id di scheda: B può solo lavorare sulla propria scheda, mai su quella di A.
        $this->assertSame(1, TcriSchedaSemaforo::where('docente_socio_id', $this->s['docB']->id)->first()->voci()->count());
        $this->assertSame('verde', $this->livelloVoce('docA', 'x', $this->codici[0]));
        $this->assertSame('rosso', $this->livelloVoce('docB', 'x', $this->codici[1]));
    }

    public function test_confronto_con_gli_altri_docenti_solo_dopo_il_proprio_invio_con_annotazioni(): void
    {
        $url = route('app.esame.scheda', [$this->esame, $this->lab, $this->s['x']]);
        $this->compilaTutto('docA', 'x', 'verde');
        $this->come('docA')->post(route('app.esame.scheda.invia', [$this->esame, $this->lab, $this->s['x']]));
        TcriSchedaSemaforo::where('docente_socio_id', $this->s['docA']->id)->update(['annotazione' => 'Segreto di A']);
        $this->compilaTutto('docB', 'x', 'rosso');

        // Un altro laboratorio con una scheda inviata da A (B non insegna lì) e uno senza schede.
        $lab2 = \App\Models\EsameLaboratorio::create(['esame_id' => $this->esame->id, 'numero' => 2]);
        \App\Models\EsameLaboratorio::create(['esame_id' => $this->esame->id, 'numero' => 3]);
        $altra = TcriSchedaSemaforo::create(['laboratorio_id' => $lab2->id, 'discente_socio_id' => $this->s['x']->id,
            'docente_socio_id' => $this->s['docA']->id, 'inviata_il' => now(), 'annotazione' => 'Segreto lab 2']);
        foreach ($this->codici as $codice) {
            $altra->voci()->create(['voce_codice' => $codice, 'valore' => '0.25']);
        }

        // B non ha ancora inviato: niente confronto.
        $this->come('docB')->get($url)->assertOk()->assertDontSee('Schede inviate dei docenti')->assertDontSee('Segreto di A');
        $this->come('docB')->post(route('app.esame.scheda.invia', [$this->esame, $this->lab, $this->s['x']]));
        // Dopo l'invio: valori e annotazioni di tutti i laboratori con schede inviate.
        $this->come('docB')->get($url)->assertOk()->assertSee('Schede inviate dei docenti')
            ->assertSee('(questo) · 2 schede', false)->assertSee('Laboratorio 2')->assertDontSee('Laboratorio 3')
            ->assertSee('Segreto di A')->assertSee('Segreto lab 2')
            ->assertSee('Totale (su 28,0)', false)->assertSee('>28,0<', false)->assertSee('>0,0<', false);
        // La propria resta modificabile dopo l'invio.
        $this->tocca('docB', 'x', $this->codici[0], 'giallo')->assertOk();
    }

    private function livelloVoce(string $docente, string $discente, string $codice): ?string
    {
        $v = TcriSchedaSemaforo::where('docente_socio_id', $this->s[$docente]->id)->where('discente_socio_id', $this->s[$discente]->id)->first()
            ?->voci()->where('voce_codice', $codice)->first();

        return match ($v?->valore === null ? null : (string) $v->valore) {
            '1.00' => 'verde', '0.25' => 'giallo', '0.00' => 'rosso', default => null,
        };
    }

    public function test_cambio_gruppo_la_scheda_gia_fatta_resta_modificabile_dall_autore(): void
    {
        $this->tocca('docA', 'x', $this->codici[0], 'verde');

        // Il direttore sposta A su un altro gruppo: nessun blocco, i gruppi non si congelano.
        $this->g1->docenti()->detach($this->s['docA']->id);
        $this->gruppo('G4', ['docA'], []);

        $this->assertContains($this->s['x']->id, $this->idsProponibili('docA'));
        $this->tocca('docA', 'x', $this->codici[1], 'giallo')->assertOk()->assertJson(['compilate' => 2]);
        // Nessuna NUOVA scheda per un discente fuori dal proprio gruppo.
        $this->tocca('docA', 'nonAmmesso', $this->codici[0], 'verde')->assertForbidden();
        // B resta nel gruppo e vede X.
        $this->assertContains($this->s['x']->id, $this->idsProponibili('docB'));
    }

    public function test_ritirato_dopo_la_scheda_sparisce_dall_elenco_ma_i_dati_restano(): void
    {
        $this->tocca('docA', 'x', $this->codici[0], 'verde');
        Iscrizione::where('partecipante_id', $this->s['x']->id)->update(['stato' => 'ritirato']);

        $this->assertNotContains($this->s['x']->id, $this->idsProponibili('docA'));
        $this->tocca('docA', 'x', $this->codici[1], 'verde')->assertForbidden();
        $this->assertSame(1, TcriSchedaSemaforo::first()->voci()->count());
    }

    public function test_esame_in_bozza_o_chiuso_non_si_compila(): void
    {
        $this->esame->update(['stato' => Esame::STATO_CHIUSO, 'chiuso_il' => now()]);
        $this->tocca('docA', 'x', $this->codici[0], 'verde')->assertForbidden();
        $this->come('docA')->get(route('app.esame.scheda', [$this->esame, $this->lab, $this->s['x']]))->assertOk()->assertSee('non è in corso');

        $this->esame->update(['stato' => Esame::STATO_BOZZA, 'chiuso_il' => null]);
        $this->tocca('docA', 'x', $this->codici[0], 'verde')->assertNotFound();
        $this->come('docA')->get(route('app.esame', $this->esame))->assertNotFound();
    }

    public function test_esame_con_schede_non_si_riporta_in_bozza_e_il_laboratorio_non_si_elimina(): void
    {
        $this->assertNull($this->esame->motivoNonRiportabileInBozza(), 'senza schede si può');
        $this->tocca('docA', 'x', $this->codici[0], 'verde');
        $this->assertNotNull($this->esame->fresh()->motivoNonRiportabileInBozza());
        $this->assertTrue($this->esame->fresh()->hasDatiCorrelati());
    }

    public function test_andamento_mostra_solo_le_schede_dell_utente(): void
    {
        $lab2 = EsameLaboratorio::create(['esame_id' => $this->esame->id, 'numero' => 2]);
        $g = EsameGruppo::create(['esame_id' => $this->esame->id, 'laboratorio_id' => $lab2->id, 'nome' => 'L2']);
        $g->docenti()->attach([$this->s['docA']->id, $this->s['docB']->id]);
        $g->discenti()->attach($this->s['x']->id);

        // B compila tutto verde in lab 1; A tutto giallo in lab 2.
        $this->compilaTutto('docB', 'x', 'verde');
        foreach ($this->codici as $codice) {
            $this->come('docA')->putJson(route('app.esame.scheda.voce', [$this->esame, $lab2, $this->s['x']]), ['voce' => $codice, 'valore' => 'giallo'])->assertOk();
        }

        $html = $this->come('docA')->get(route('app.esame.scheda', [$this->esame, $this->lab, $this->s['x']]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Laboratorio 2:\s+7,00 su 28,00/', $html);
        $this->assertStringNotContainsString('28,00 su 28,00', $html, 'la scheda di B non deve comparire ad A');
    }
}
