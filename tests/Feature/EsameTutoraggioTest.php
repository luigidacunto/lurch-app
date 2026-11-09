<?php

namespace Tests\Feature;

use App\Esami\DecisioneItinere;
use App\Esami\PermessiEsame;
use App\Filament\Resources\EsameResource\Pages\GestioneTutoraggio;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\EsameTutoraggio;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\TcriSchedaItinere;
use App\Models\TcriSchedaSemaforo;
use App\Models\User;
use Database\Seeders\EsameModelliSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gruppi di tutoraggio: il tutor legge (mai scrive) l'andamento dei SUOI discenti con le schede di tutti i docenti;
 * un discente sta in un solo gruppo per esame, un docente può essere tutor in più gruppi.
 */
class EsameTutoraggioTest extends TestCase
{
    use RefreshDatabase;

    private Esame $esame;
    private Corso $corso;
    /** @var array<string, Socio> */
    private array $s = [];
    private User $direttore;

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

        foreach (['direttore', 'presidente', 'tutor', 'altro', 'x', 'y', 'ritirato'] as $k) {
            $this->s[$k] = Socio::create(['nome' => $k . 'Nome', 'cognome' => 'Test' . $k, 'codice_fiscale' => strtoupper(substr(md5($k), 0, 16))]);
        }
        $this->corso = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso TCRI', 'numero_protocollo' => 'X/' . uniqid(),
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
            'direttore_socio_id' => $this->s['direttore']->id, 'presidente_socio_id' => $this->s['presidente']->id,
            'catalogo_corso_id' => $catalogo->id,
        ]);
        $this->corso->docenti()->attach([$this->s['tutor']->id, $this->s['altro']->id]);
        foreach (['x', 'y', 'ritirato'] as $k) {
            Iscrizione::create([
                'corso_id' => $this->corso->id, 'partecipante_id' => $this->s[$k]->id, 'token' => (string) Str::uuid(),
                'stato' => $k === 'ritirato' ? 'ritirato' : 'pending',
            ]);
        }

        $this->esame = Esame::creaPerCorso($this->corso, 'Esame', 60);
        $this->esame->attiva();
        $this->esame->refresh();

        $this->direttore = User::create(['name' => 'dir', 'email' => 'dir@t.it', 'password' => 'x', 'ruolo' => 'operatore', 'attivo' => true, 'socio_id' => $this->s['direttore']->id]);
    }

    /** Gruppo «T»: tutor + discenti x e ritirato (y senza tutor). */
    private function gruppo(): EsameTutoraggio
    {
        $g = EsameTutoraggio::create(['esame_id' => $this->esame->id, 'nome' => 'T']);
        $g->discenti()->attach([$this->s['x']->id, $this->s['ritirato']->id]);
        $g->docenti()->attach($this->s['tutor']->id);

        return $g;
    }

    private function schedaItinere(string $docente, string $discente): TcriSchedaItinere
    {
        $scheda = TcriSchedaItinere::create([
            'esame_id' => $this->esame->id, 'discente_socio_id' => $this->s[$discente]->id, 'docente_socio_id' => $this->s[$docente]->id,
        ]);
        $scheda->voci()->create(['voce_codice' => $this->esame->modello_snapshot['itinere']['aree'][0]['voci'][0]['codice'], 'voto' => 4]);

        return $scheda;
    }

    private function schedaLab(string $docente, string $discente, bool $inviata): TcriSchedaSemaforo
    {
        return TcriSchedaSemaforo::create([
            'laboratorio_id' => $this->esame->laboratori()->first()->id, 'discente_socio_id' => $this->s[$discente]->id,
            'docente_socio_id' => $this->s[$docente]->id, 'inviata_il' => $inviata ? now() : null,
        ]);
    }

    private function permessi(string $k): PermessiEsame
    {
        return PermessiEsame::per($this->esame->fresh(), $this->s[$k]->id);
    }

    private function pwa(string $k, string $rotta = 'app.esame.tutorati')
    {
        return $this->withSession(['app_socio_id' => $this->s[$k]->id])->get(route($rotta, $this->esame));
    }

    public function test_il_tutor_legge_le_schede_altrui_solo_dei_suoi_discenti(): void
    {
        $this->gruppo();
        $p = $this->permessi('tutor');

        $this->assertSame([$this->s['x']->id], $p->discentiTutorati()->all(), 'il ritirato è escluso, y non è nel gruppo');
        $this->assertTrue($p->vedeScheda($this->schedaItinere('altro', 'x')));
        $this->assertFalse($p->vedeScheda($this->schedaItinere('altro', 'y')));
        $this->assertTrue($p->vedeScheda($this->schedaLab('altro', 'x', true)));
        $this->assertFalse($p->vedeScheda($this->schedaLab('direttore', 'x', false)), 'bozze di laboratorio: mai');
        // Lettura sì, scrittura no.
        $this->assertFalse($p->modificaScheda($this->esame->schedeItinere()->where('docente_socio_id', $this->s['altro']->id)->first()));
        // Chi non è tutor resta alla regola di prima.
        $this->assertFalse($this->permessi('altro')->vedeScheda($this->schedaItinere('tutor', 'x')));
    }

    public function test_pwa_pagina_tutorati_solo_al_tutor_e_solo_i_suoi_discenti(): void
    {
        $this->gruppo();
        $this->schedaItinere('altro', 'x');
        DecisioneItinere::salva($this->esame->fresh(), $this->direttore, $this->s['x']->id, $this->esame->modello_snapshot['itinere']['aree'][0]['codice'], '3.5', 'ok');

        $this->pwa('tutor')->assertOk()->assertSee('I miei tutorati')->assertSee('Testx')->assertDontSee('Testy')
            ->assertDontSee('Testritirato')->assertSee('Testaltro')->assertSee('3,5');
        $this->pwa('tutor', 'app.esame')->assertOk()->assertSee('I miei tutorati');
        // Legenda del LAP (60%) in cima: valore in centesimi e in punti per prova + fasce dei colori.
        $this->pwa('tutor')->assertSee('LAP 60% — idoneo da 60 su 100')
            ->assertSee('Itinere: <strong>18</strong> su 30', false)->assertSee('(12 su 20 punti)')
            ->assertSee('Prova pratica: <strong>24</strong> su 40', false)->assertSee('(16,8 su 28 punti)')
            ->assertSee('Colloquio: <strong>6</strong> su 10', false)->assertSee('Test scritto: <strong>12</strong> su 20', false)
            ->assertSee('rosso [0%, 20%[')->assertSee('arancione [20%, 40%[')->assertSee('verde [60%, 80%[')->assertSee('azzurro [80%, 100%]')
            ->assertDontSee('title="da 20% (incluso)', false);   // nell'app niente suggerimenti
        // Non c'è sulla pagina della commissione.
        $this->pwa('presidente', 'app.esame.itinere.riepilogo')->assertDontSee('LAP 60%');

        foreach (['altro', 'presidente', 'x'] as $k) {
            $this->pwa($k)->assertNotFound();
        }

        // Anteprima: media d'area dove ci sono voti, "–" dove non ce ne sono; laboratorio con media e matrice apribile.
        $lab = $this->schedaLab('altro', 'x', true);
        foreach ($this->esame->modello_snapshot['semaforo']['competenze'] as $c) {
            foreach ($c['voci'] as $v) {
                $lab->voci()->create(['voce_codice' => $v['codice'], 'valore' => '1']);
            }
        }
        $aree = $this->esame->modello_snapshot['itinere']['aree'];
        $this->pwa('tutor')->assertOk()
            ->assertSee($aree[0]['nome'].' 4,0')->assertSee($aree[1]['nome'].' –')
            ->assertSee('Lab 1 28,0')->assertSee('details class="lab-dett"', false)->assertSee('Totale (su 28,0)')
            ->assertDontSee('Δ');
        // La pagina della commissione resta com'era (niente anteprima del tutor).
        $this->pwa('presidente', 'app.esame.itinere.riepilogo')->assertOk()->assertDontSee('lab-dett"', false)->assertDontSee('etichetta-riga">Itinere', false);
        $this->pwa('altro', 'app.esame')->assertOk()->assertDontSee('I miei tutorati');
    }

    public function test_anteprima_itinere_in_centesimi_medio_e_poi_finale(): void
    {
        $this->gruppo();
        $aree = $this->esame->modello_snapshot['itinere']['aree'];
        // «altro» vota 4 su tutte le voci di tutte le aree: media 4 per area → 30 × 16/20 = 24 centesimi, suggerito.
        $scheda = TcriSchedaItinere::create(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['x']->id, 'docente_socio_id' => $this->s['altro']->id]);
        foreach ($aree as $a) {
            foreach ($a['voci'] as $v) {
                $scheda->voci()->create(['voce_codice' => $v['codice'], 'voto' => 4]);
            }
        }
        $this->pwa('tutor')->assertOk()->assertSee('24,0 su 30 · medio');

        // Il direttore fissa le 4 aree a 5 → 30 centesimi, definitivo.
        foreach ($aree as $a) {
            DecisioneItinere::salva($this->esame->fresh(), $this->direttore, $this->s['x']->id, $a['codice'], '5.0', 'ok');
        }
        $this->pwa('tutor')->assertSee('30,0 su 30 · finale')->assertDontSee('· medio');
    }

    public function test_anteprima_itinere_senza_voti_resta_vuota(): void
    {
        $this->gruppo();   // nessuna scheda: nessuna media da proporre
        $this->pwa('tutor')->assertOk()->assertSee('– su 30 · medio');
    }

    public function test_il_tutor_legge_le_note_degli_altri_docenti_sui_suoi_discenti(): void
    {
        $this->gruppo();
        $this->schedaItinere('altro', 'x')->update(['annotazione' => 'Nota itinere su x']);
        $this->schedaItinere('altro', 'y')->update(['annotazione' => 'Nota itinere su y']);
        $lab = $this->schedaLab('altro', 'x', true);
        foreach ($this->esame->modello_snapshot['semaforo']['competenze'] as $c) {
            foreach ($c['voci'] as $v) {
                $lab->voci()->create(['voce_codice' => $v['codice'], 'valore' => '1']);
            }
        }
        $lab->update(['annotazione' => 'Nota lab su x']);
        $this->schedaLab('direttore', 'x', false)->update(['annotazione' => 'Nota bozza lab']);

        $this->pwa('tutor')->assertOk()->assertSee('Nota itinere su x')->assertSee('Nota lab su x')
            ->assertDontSee('Nota itinere su y')->assertDontSee('Nota bozza lab');
        // La commissione (non direttore) continua a non leggerle.
        $this->pwa('presidente', 'app.esame.itinere.riepilogo')->assertOk()->assertDontSee('Nota itinere su x')->assertDontSee('Nota lab su x');
    }

    public function test_itinere_una_colonna_solo_per_i_docenti_che_hanno_votato_quell_area(): void
    {
        $this->gruppo();
        $aree = $this->esame->modello_snapshot['itinere']['aree'];
        // «altro» vota solo la prima area; «direttore» solo la seconda; «tutor» ha una scheda vuota (nessun voto).
        $this->schedaItinere('altro', 'x');
        $d = TcriSchedaItinere::create(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['x']->id, 'docente_socio_id' => $this->s['direttore']->id]);
        $d->voci()->create(['voce_codice' => $aree[1]['voci'][0]['codice'], 'voto' => 3]);
        TcriSchedaItinere::create(['esame_id' => $this->esame->id, 'discente_socio_id' => $this->s['x']->id, 'docente_socio_id' => $this->s['tutor']->id]);

        $html = $this->pwa('tutor')->assertOk()->getContent();

        // Ognuno compare nell'intestazione di UNA sola tabella (prima: una colonna per area, anche senza voti).
        $this->assertSame(1, substr_count($html, '<th>Testaltro altroNome</th>'));
        $this->assertSame(1, substr_count($html, '<th>Testdirettore direttoreNome</th>'));
        $this->assertSame(0, substr_count($html, '<th>Testtutor tutorNome</th>'));
        $this->assertStringContainsString("Nessun docente ha ancora votato quest'area.", $html);
    }

    public function test_tutor_tolto_dai_docenti_del_corso_perde_l_accesso(): void
    {
        $this->gruppo();
        $this->corso->docenti()->detach($this->s['tutor']->id);

        $this->assertTrue($this->permessi('tutor')->discentiTutorati()->isEmpty());
        $this->pwa('tutor')->assertNotFound();
    }

    public function test_pannello_un_discente_in_un_solo_gruppo_un_docente_in_piu_gruppi(): void
    {
        $this->actingAs($this->direttore);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $pagina = fn () => Livewire::test(GestioneTutoraggio::class, ['record' => $this->esame->getRouteKey()]);

        $pagina()->callAction('nuovoTutoraggio', ['nome' => 'A', 'discenti_ids' => [$this->s['x']->id], 'docenti_ids' => [$this->s['tutor']->id]])
            ->assertHasNoActionErrors();
        $pagina()->callAction('nuovoTutoraggio', ['nome' => 'B', 'discenti_ids' => [$this->s['x']->id], 'docenti_ids' => [$this->s['tutor']->id]])
            ->assertHasActionErrors(['discenti_ids']);
        $pagina()->callAction('nuovoTutoraggio', ['nome' => 'B', 'discenti_ids' => [$this->s['y']->id], 'docenti_ids' => [$this->s['tutor']->id]])
            ->assertHasNoActionErrors();

        $this->assertSame(2, $this->esame->tutoraggi()->count());
        $this->assertSame([$this->s['x']->id, $this->s['y']->id], $this->permessi('tutor')->discentiTutorati()->sort()->values()->all());
    }

    public function test_eliminare_un_gruppo_non_tocca_le_schede(): void
    {
        $g = $this->gruppo();
        $this->schedaItinere('altro', 'x');
        $g->delete();

        $this->assertSame(1, TcriSchedaItinere::count());
        $this->assertSame(0, DB::table('esame_tutoraggio_discente')->count());
    }
}
