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
